package handlers

import (
	"database/sql"
	"errors"
	"net/http"
	"strconv"
	"strings"

	"campus-governance/middleware"
	"campus-governance/utils"
)

func (a *App) GetUsers(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	rows, err := a.DB.Query(`
		SELECT
			u.id,
			u.username,
			u.email,
			u.full_name,
			u.role,
			COALESCE(d.name, ''),
			u.is_active,
			COALESCE(u.phone, ''),
			COALESCE(u.profile_photo, ''),
			u.created_at
		FROM users u
		LEFT JOIN departments d ON d.id = u.department_id
		ORDER BY u.created_at DESC
	`)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch users")
		return
	}
	defer rows.Close()

	type userRow struct {
		ID           int64  `json:"id"`
		Username     string `json:"username"`
		Email        string `json:"email"`
		FullName     string `json:"full_name"`
		Role         string `json:"role"`
		Department   string `json:"department"`
		IsActive     bool   `json:"is_active"`
		Phone        string `json:"phone"`
		ProfilePhoto string `json:"profile_photo"`
		CreatedAt    string `json:"created_at"`
	}

	out := make([]userRow, 0)
	for rows.Next() {
		var item userRow
		if err := rows.Scan(
			&item.ID,
			&item.Username,
			&item.Email,
			&item.FullName,
			&item.Role,
			&item.Department,
			&item.IsActive,
			&item.Phone,
			&item.ProfilePhoto,
			&item.CreatedAt,
		); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan user")
			return
		}
		out = append(out, item)
	}

	utils.OK(w, out)
}

type createUserRequest struct {
	Username     string `json:"username"`
	Email        string `json:"email"`
	Password     string `json:"password"`
	FullName     string `json:"full_name"`
	Role         string `json:"role"`
	DepartmentID *int64 `json:"department_id"`
	Phone        string `json:"phone"`
}

func (a *App) CreateUser(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	var req createUserRequest
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	req.Username = strings.TrimSpace(req.Username)
	req.Email = strings.ToLower(strings.TrimSpace(req.Email))
	req.Password = strings.TrimSpace(req.Password)
	req.FullName = strings.TrimSpace(req.FullName)
	req.Role = strings.ToLower(strings.TrimSpace(req.Role))
	req.Phone = strings.TrimSpace(req.Phone)

	errorsMap := map[string]string{}
	if !utils.ValidUsername(req.Username) {
		errorsMap["username"] = "invalid username"
	}
	if !utils.ValidEmail(req.Email) {
		errorsMap["email"] = "invalid email"
	}
	if !utils.TrimmedLenBetween(req.Password, 8, 128) {
		errorsMap["password"] = "password must be between 8 and 128 characters"
	}
	if !utils.TrimmedLenBetween(req.FullName, 2, 100) {
		errorsMap["full_name"] = "full_name must be between 2 and 100 characters"
	}
	if req.Phone != "" && !utils.TrimmedLenBetween(req.Phone, 6, 30) {
		errorsMap["phone"] = "phone must be between 6 and 30 characters"
	}
	if err := utils.ValidateRole(req.Role); err != nil {
		errorsMap["role"] = err.Error()
	}
	if len(errorsMap) > 0 {
		utils.ValidationFail(w, errorsMap)
		return
	}

	hashed, err := utils.HashPassword(req.Password)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to hash password")
		return
	}

	res, err := a.DB.Exec(`
		INSERT INTO users (
			username,
			email,
			password_hash,
			full_name,
			role,
			department_id,
			phone,
			is_active
		)
		VALUES (?, ?, ?, ?, ?, ?, ?, 1)
	`, req.Username, req.Email, hashed, req.FullName, req.Role, req.DepartmentID, nullable(req.Phone))
	if err != nil {
		if looksLikeUniqueConstraint(err) {
			utils.Fail(w, http.StatusConflict, "username or email already exists")
			return
		}
		utils.Fail(w, http.StatusInternalServerError, "failed to create user")
		return
	}

	id, err := res.LastInsertId()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch user id")
		return
	}

	_ = a.refreshStats()

	utils.Created(w, "user created", map[string]any{
		"user_id": id,
	})
}

func (a *App) UpdateUserStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut && r.Method != http.MethodPatch {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	id, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || id <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid user id")
		return
	}

	var req struct {
		Status string `json:"status"`
	}
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	req.Status = strings.ToLower(strings.TrimSpace(req.Status))
	if req.Status != "active" && req.Status != "blocked" {
		utils.Fail(w, http.StatusBadRequest, "status must be active or blocked")
		return
	}

	isActive := req.Status == "active"

	res, err := a.DB.Exec(`
		UPDATE users
		SET is_active = ?, updated_at = CURRENT_TIMESTAMP
		WHERE id = ?
	`, boolToInt(isActive), id)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to update user status")
		return
	}

	affected, _ := res.RowsAffected()
	if affected == 0 {
		utils.Fail(w, http.StatusNotFound, "user not found")
		return
	}

	_ = a.refreshStats()

	utils.OK(w, map[string]any{
		"user_id": id,
		"status":  req.Status,
	})
}

func (a *App) GetAdminReports(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	user, ok := middleware.CurrentUser(r)
	if !ok {
		utils.Fail(w, http.StatusUnauthorized, "authentication required")
		return
	}

	query := `
		SELECT
			r.id,
			COALESCE(t.token, ''),
			COALESCE(u.username, ''),
			COALESCE(d.name, ''),
			r.category,
			r.title,
			r.description,
			COALESCE(r.location, ''),
			r.priority,
			r.status,
			COALESCE(assignee.full_name, ''),
			r.spam_score,
			COALESCE(r.spam_reason, ''),
			r.views_count,
			r.created_at,
			r.updated_at
		FROM reports r
		LEFT JOIN tokens t ON t.id = r.token_id
		LEFT JOIN users u ON u.id = r.user_id
		LEFT JOIN departments d ON d.id = r.department_id
		LEFT JOIN users assignee ON assignee.id = r.assigned_to
	`
	args := make([]any, 0)

	if strings.ToLower(strings.TrimSpace(user.Role)) != "admin" {
		query += ` WHERE r.department_id = ? OR r.assigned_to = ?`
		args = append(args, nullableInt64(user.DepartmentID), user.ID)
	}

	query += ` ORDER BY r.created_at DESC`

	rows, err := a.DB.Query(query, args...)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch reports")
		return
	}
	defer rows.Close()

	out := make([]map[string]any, 0)
	for rows.Next() {
		var (
			id, spamScore, views                                                           int64
			token, reporter, department, category, title, description, location, priority string
			status, assignedTo, spamReason, createdAt, updatedAt                           string
		)

		if err := rows.Scan(
			&id,
			&token,
			&reporter,
			&department,
			&category,
			&title,
			&description,
			&location,
			&priority,
			&status,
			&assignedTo,
			&spamScore,
			&spamReason,
			&views,
			&createdAt,
			&updatedAt,
		); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan report")
			return
		}

		out = append(out, map[string]any{
			"id":            id,
			"token":         token,
			"reporter_name": reporter,
			"department":    department,
			"category":      category,
			"title":         title,
			"description":   description,
			"location":      location,
			"priority":      priority,
			"status":        status,
			"assigned_to":   assignedTo,
			"spam_score":    spamScore,
			"spam_reason":   spamReason,
			"views":         views,
			"created_at":    createdAt,
			"updated_at":    updatedAt,
		})
	}

	utils.OK(w, out)
}

func (a *App) UpdateReportStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut && r.Method != http.MethodPatch {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	var req struct {
		Status          string `json:"status"`
		AssignedTo      *int64 `json:"assigned_to"`
		ResolutionNotes string `json:"resolution_notes"`
		Note            string `json:"note"`
	}
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	req.Status = strings.ToLower(strings.TrimSpace(req.Status))
	req.ResolutionNotes = strings.TrimSpace(req.ResolutionNotes)
	req.Note = strings.TrimSpace(req.Note)

	if err := utils.ValidateReportStatus(req.Status); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	user, ok := middleware.CurrentUser(r)
	if !ok {
		utils.Fail(w, http.StatusUnauthorized, "authentication required")
		return
	}

	tx, err := a.DB.Begin()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to start transaction")
		return
	}
	defer tx.Rollback()

	var oldStatus string
	if err := tx.QueryRow(`SELECT status FROM reports WHERE id = ?`, reportID).Scan(&oldStatus); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			utils.Fail(w, http.StatusNotFound, "report not found")
			return
		}
		utils.Fail(w, http.StatusInternalServerError, "failed to load report")
		return
	}

	_, err = tx.Exec(`
		UPDATE reports
		SET status = ?,
		    assigned_to = ?,
		    resolution_notes = ?,
		    resolved_at = CASE WHEN ? = 'resolved' THEN CURRENT_TIMESTAMP ELSE resolved_at END,
		    updated_at = CURRENT_TIMESTAMP
		WHERE id = ?
	`, req.Status, req.AssignedTo, nullable(req.ResolutionNotes), req.Status, reportID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to update report status")
		return
	}

	_, err = tx.Exec(`
		INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, note)
		VALUES (?, ?, ?, ?, ?)
	`, reportID, oldStatus, req.Status, user.ID, nullable(req.Note))
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to write status history")
		return
	}

	_, err = tx.Exec(`
		INSERT INTO activity_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent)
		VALUES (?, 'status_update', 'report', ?, ?, ?, ?, ?)
	`, user.ID, reportID, oldStatus, req.Status, readIP(r), r.UserAgent())
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to write activity log")
		return
	}

	if err := tx.Commit(); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to commit report status update")
		return
	}

	_ = a.refreshStats()

	utils.OK(w, map[string]any{
		"report_id": reportID,
		"status":    req.Status,
	})
}

func (a *App) UpdateReportDetails(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut && r.Method != http.MethodPatch {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	var req struct {
		Title       string `json:"title"`
		Description string `json:"description"`
		Category    string `json:"category"`
		Location    string `json:"location"`
		Priority    string `json:"priority"`
	}
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	req.Title = strings.TrimSpace(req.Title)
	req.Description = strings.TrimSpace(req.Description)
	req.Category = strings.TrimSpace(req.Category)
	req.Location = strings.TrimSpace(req.Location)
	req.Priority = strings.ToLower(strings.TrimSpace(req.Priority))

	errorsMap := map[string]string{}
	if !utils.TrimmedLenBetween(req.Title, 5, 200) {
		errorsMap["title"] = "title must be between 5 and 200 characters"
	}
	if !utils.TrimmedLenBetween(req.Description, 10, 5000) {
		errorsMap["description"] = "description must be between 10 and 5000 characters"
	}
	if !utils.Required(req.Category) {
		errorsMap["category"] = "category is required"
	}
	if err := utils.ValidatePriority(req.Priority); err != nil {
		errorsMap["priority"] = err.Error()
	}
	if len(errorsMap) > 0 {
		utils.ValidationFail(w, errorsMap)
		return
	}

	res, err := a.DB.Exec(`
		UPDATE reports
		SET title = ?,
		    description = ?,
		    category = ?,
		    location = ?,
		    priority = ?,
		    updated_at = CURRENT_TIMESTAMP
		WHERE id = ?
	`, req.Title, req.Description, req.Category, nullable(req.Location), req.Priority, reportID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to update report")
		return
	}

	affected, _ := res.RowsAffected()
	if affected == 0 {
		utils.Fail(w, http.StatusNotFound, "report not found")
		return
	}

	utils.OK(w, map[string]any{
		"report_id": reportID,
	})
}

func (a *App) DeleteReport(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodDelete {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	tx, err := a.DB.Begin()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to start transaction")
		return
	}
	defer tx.Rollback()

	for _, query := range []string{
		`DELETE FROM report_media WHERE report_id = ?`,
		`DELETE FROM comments WHERE report_id = ?`,
		`DELETE FROM messages WHERE report_id = ?`,
		`DELETE FROM notifications WHERE report_id = ?`,
		`DELETE FROM report_status_history WHERE report_id = ?`,
		`DELETE FROM activity_logs WHERE entity_type = 'report' AND entity_id = ?`,
		`DELETE FROM reports WHERE id = ?`,
	} {
		if _, err := tx.Exec(query, reportID); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to delete report")
			return
		}
	}

	if err := tx.Commit(); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to commit delete")
		return
	}

	_ = a.refreshStats()

	utils.OK(w, map[string]any{
		"deleted_report_id": reportID,
	})
}

func (a *App) GetSpamReports(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	rows, err := a.DB.Query(`
		SELECT
			r.id,
			COALESCE(t.token, ''),
			r.title,
			r.category,
			COALESCE(r.location, ''),
			r.spam_score,
			COALESCE(r.spam_reason, ''),
			r.status,
			r.created_at
		FROM reports r
		LEFT JOIN tokens t ON t.id = r.token_id
		WHERE r.status = 'spam' OR r.spam_score > 0 OR COALESCE(r.spam_reason, '') <> ''
		ORDER BY r.updated_at DESC, r.created_at DESC
	`)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch spam reports")
		return
	}
	defer rows.Close()

	out := make([]map[string]any, 0)
	for rows.Next() {
		var id, spamScore int64
		var token, title, category, location, spamReason, status, createdAt string

		if err := rows.Scan(&id, &token, &title, &category, &location, &spamScore, &spamReason, &status, &createdAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan spam report")
			return
		}

		out = append(out, map[string]any{
			"id":          id,
			"token":       token,
			"title":       title,
			"category":    category,
			"location":    location,
			"spam_score":  spamScore,
			"spam_reason": spamReason,
			"status":      status,
			"created_at":  createdAt,
		})
	}

	utils.OK(w, out)
}

func (a *App) UpdateSpamState(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut && r.Method != http.MethodPatch {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	var req struct {
		IsSpam     bool   `json:"is_spam"`
		SpamReason string `json:"spam_reason"`
		SpamScore  int64  `json:"spam_score"`
	}
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	req.SpamReason = strings.TrimSpace(req.SpamReason)
	status := "pending"
	if req.IsSpam {
		status = "spam"
	}

	res, err := a.DB.Exec(`
		UPDATE reports
		SET status = ?,
		    spam_reason = ?,
		    spam_score = ?,
		    updated_at = CURRENT_TIMESTAMP
		WHERE id = ?
	`, status, nullable(req.SpamReason), req.SpamScore, reportID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to update spam state")
		return
	}

	affected, _ := res.RowsAffected()
	if affected == 0 {
		utils.Fail(w, http.StatusNotFound, "report not found")
		return
	}

	_ = a.refreshStats()

	utils.OK(w, map[string]any{
		"report_id": reportID,
		"status":    status,
	})
}

func (a *App) GetReportMedia(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	rows, err := a.DB.Query(`
		SELECT id, file_path, file_name, mime_type, media_type, file_size, created_at
		FROM report_media
		WHERE report_id = ?
		ORDER BY created_at ASC
	`)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch report media")
		return
	}
	defer rows.Close()

	out := make([]map[string]any, 0)
	for rows.Next() {
		var id, size int64
		var filePath, fileName, mimeType, mediaType, createdAt string

		if err := rows.Scan(&id, &filePath, &fileName, &mimeType, &mediaType, &size, &createdAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan media")
			return
		}

		out = append(out, map[string]any{
			"id":         id,
			"file_url":   filePath,
			"file_name":  fileName,
			"mime_type":  mimeType,
			"media_type": mediaType,
			"file_size":  size,
			"created_at": createdAt,
		})
	}

	utils.OK(w, out)
}

func (a *App) GetComments(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	rows, err := a.DB.Query(`
		SELECT
			c.id,
			c.comment,
			c.is_staff_response,
			c.is_private,
			COALESCE(u.full_name, ''),
			COALESCE(t.token, ''),
			c.created_at
		FROM comments c
		LEFT JOIN users u ON c.user_id = u.id
		LEFT JOIN tokens t ON c.token_id = t.id
		WHERE c.report_id = ?
		ORDER BY c.created_at ASC
	`)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch comments")
		return
	}
	defer rows.Close()

	out := make([]map[string]any, 0)
	for rows.Next() {
		var id int64
		var comment, fullName, token, createdAt string
		var isStaff, isPrivate bool

		if err := rows.Scan(&id, &comment, &isStaff, &isPrivate, &fullName, &token, &createdAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan comment")
			return
		}

		author := "Anonymous"
		if fullName != "" {
			author = fullName
		} else if token != "" {
			author = token
		}

		out = append(out, map[string]any{
			"id":         id,
			"comment":    comment,
			"author":     author,
			"is_staff":   isStaff,
			"is_private": isPrivate,
			"created_at": createdAt,
		})
	}

	utils.OK(w, out)
}

func (a *App) AddComment(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}
	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	reportID, err := strconv.ParseInt(strings.TrimSpace(r.PathValue("id")), 10, 64)
	if err != nil || reportID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid report id")
		return
	}

	var req struct {
		Comment   string `json:"comment"`
		IsPrivate bool   `json:"is_private"`
		TokenID   *int64 `json:"token_id"`
	}
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	req.Comment = strings.TrimSpace(req.Comment)
	if !utils.TrimmedLenBetween(req.Comment, 1, 5000) {
		utils.Fail(w, http.StatusBadRequest, "comment must be between 1 and 5000 characters")
		return
	}

	user, ok := middleware.CurrentUser(r)
	if !ok {
		utils.Fail(w, http.StatusUnauthorized, "authentication required")
		return
	}

	_, err = a.DB.Exec(`
		INSERT INTO comments (report_id, user_id, token_id, comment, is_staff_response, is_private)
		VALUES (?, ?, ?, ?, 1, ?)
	`, reportID, user.ID, req.TokenID, req.Comment, boolToInt(req.IsPrivate))
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to add comment")
		return
	}

	utils.Created(w, "comment added", map[string]any{
		"report_id": reportID,
	})
}

func nullableInt64(v sql.NullInt64) any {
	if !v.Valid {
		return nil
	}
	return v.Int64
}