package handlers

import (
	"database/sql"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"campus-governance/utils"
)

func (a *App) GetStats(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	var total, pending, verified, inProgress, resolved, spam int64
	err := a.DB.QueryRow(`
		SELECT
			COALESCE(total_reports, 0),
			COALESCE(pending_reports, 0),
			COALESCE(verified_reports, 0),
			COALESCE(in_progress_reports, 0),
			COALESCE(resolved_reports, 0),
			COALESCE(spam_reports, 0)
		FROM stats
		WHERE id = 1
	`).Scan(&total, &pending, &verified, &inProgress, &resolved, &spam)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to load stats")
		return
	}

	utils.OK(w, map[string]any{
		"total_reports":       total,
		"pending_reports":     pending,
		"verified_reports":    verified,
		"in_progress_reports": inProgress,
		"resolved_reports":    resolved,
		"spam_reports":        spam,
	})
}

func (a *App) GetLatestReports(w http.ResponseWriter, r *http.Request) {
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
			r.category,
			r.title,
			COALESCE(r.location, ''),
			r.priority,
			r.status,
			r.created_at,
			COUNT(m.id) AS media_count
		FROM reports r
		LEFT JOIN tokens t ON r.token_id = t.id
		LEFT JOIN report_media m ON m.report_id = r.id
		GROUP BY r.id, t.token, r.category, r.title, r.location, r.priority, r.status, r.created_at
		ORDER BY r.created_at DESC
		LIMIT 10
	`)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch latest reports")
		return
	}
	defer rows.Close()

	type latestReport struct {
		ID         int64  `json:"id"`
		Token      string `json:"token"`
		FullToken  string `json:"fullToken"`
		Category   string `json:"category"`
		Title      string `json:"title"`
		Location   string `json:"location"`
		Priority   string `json:"priority"`
		Status     string `json:"status"`
		CreatedAt  string `json:"created_at"`
		TimeAgo    string `json:"timeAgo"`
		MediaCount int64  `json:"media_count"`
	}

	var out []latestReport
	for rows.Next() {
		var item latestReport
		if err := rows.Scan(
			&item.ID,
			&item.Token,
			&item.Category,
			&item.Title,
			&item.Location,
			&item.Priority,
			&item.Status,
			&item.CreatedAt,
			&item.MediaCount,
		); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan latest report")
			return
		}

		item.FullToken = item.Token
		item.TimeAgo = humanizeTime(item.CreatedAt)
		out = append(out, item)
	}

	utils.OK(w, out)
}

func (a *App) GetAllReports(w http.ResponseWriter, r *http.Request) {
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
			r.category,
			r.title,
			COALESCE(r.description, ''),
			COALESCE(r.location, ''),
			r.priority,
			r.status,
			r.created_at,
			COUNT(m.id) AS media_count
		FROM reports r
		LEFT JOIN tokens t ON r.token_id = t.id
		LEFT JOIN report_media m ON m.report_id = r.id
		GROUP BY r.id, t.token, r.category, r.title, r.description, r.location, r.priority, r.status, r.created_at
		ORDER BY r.created_at DESC
	`)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch reports")
		return
	}
	defer rows.Close()

	type reportItem struct {
		ID          int64  `json:"id"`
		Token       string `json:"token"`
		FullToken   string `json:"fullToken"`
		Category    string `json:"category"`
		Title       string `json:"title"`
		Description string `json:"description"`
		Location    string `json:"location"`
		Priority    string `json:"priority"`
		Status      string `json:"status"`
		CreatedAt   string `json:"created_at"`
		TimeAgo     string `json:"timeAgo"`
		MediaCount  int64  `json:"media_count"`
	}

	out := make([]reportItem, 0)
	for rows.Next() {
		var item reportItem
		if err := rows.Scan(
			&item.ID,
			&item.Token,
			&item.Category,
			&item.Title,
			&item.Description,
			&item.Location,
			&item.Priority,
			&item.Status,
			&item.CreatedAt,
			&item.MediaCount,
		); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan reports")
			return
		}

		item.FullToken = item.Token
		item.TimeAgo = humanizeTime(item.CreatedAt)
		out = append(out, item)
	}

	if err := rows.Err(); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed while reading reports")
		return
	}

	utils.OK(w, out)
}

type submitReportResponse struct {
	ReportID int64  `json:"report_id"`
	Token    string `json:"token"`
}

func (a *App) SubmitReport(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	if err := r.ParseMultipartForm(50 << 20); err != nil {
		utils.Fail(w, http.StatusBadRequest, "failed to parse multipart form")
		return
	}

	category := strings.TrimSpace(r.FormValue("category"))
	title := strings.TrimSpace(r.FormValue("title"))
	description := strings.TrimSpace(r.FormValue("description"))
	location := strings.TrimSpace(r.FormValue("location"))
	priority := strings.TrimSpace(r.FormValue("priority"))
	if priority == "" {
		priority = "medium"
	}

	isAnonymous := parseBool(r.FormValue("is_anonymous"), true)
	notifyEmail := strings.TrimSpace(r.FormValue("notify_email"))
	notifyPhone := strings.TrimSpace(r.FormValue("notify_phone"))

	errorsMap := map[string]string{}
	if !utils.Required(category) {
		errorsMap["category"] = "category is required"
	}
	if !utils.TrimmedLenBetween(title, 5, 200) {
		errorsMap["title"] = "title must be between 5 and 200 characters"
	}
	if !utils.TrimmedLenBetween(description, 10, 5000) {
		errorsMap["description"] = "description must be between 10 and 5000 characters"
	}
	if err := utils.ValidatePriority(priority); err != nil {
		errorsMap["priority"] = err.Error()
	}
	if notifyEmail != "" && !utils.ValidEmail(notifyEmail) {
		errorsMap["notify_email"] = "invalid email"
	}

	if len(errorsMap) > 0 {
		utils.ValidationFail(w, errorsMap)
		return
	}

	token, err := utils.NewTrackingToken()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to generate tracking token")
		return
	}

	tx, err := a.DB.Begin()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to start transaction")
		return
	}
	defer tx.Rollback()

	res, err := tx.Exec(`
		INSERT INTO tokens (token, email, phone, is_active)
		VALUES (?, ?, ?, 1)
	`, token, nullable(notifyEmail), nullable(notifyPhone))
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to create tracking token")
		return
	}

	tokenID, err := res.LastInsertId()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch token id")
		return
	}

	reportRes, err := tx.Exec(`
		INSERT INTO reports (
			token_id, category, title, description, location,
			priority, status, is_anonymous, notify_email, notify_phone
		)
		VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
	`, tokenID, category, title, description, nullable(location), priority, boolToInt(isAnonymous), nullable(notifyEmail), nullable(notifyPhone))
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to create report")
		return
	}

	reportID, err := reportRes.LastInsertId()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch report id")
		return
	}

	// Accept both "evidence" and "media" for compatibility
	var mediaFiles []*multipart.FileHeader
	if r.MultipartForm != nil {
		if files, ok := r.MultipartForm.File["evidence"]; ok {
			mediaFiles = append(mediaFiles, files...)
		}
		if files, ok := r.MultipartForm.File["media"]; ok {
			mediaFiles = append(mediaFiles, files...)
		}
	}

	for _, header := range mediaFiles {
		if err := a.saveReportMedia(tx, reportID, header); err != nil {
			utils.Fail(w, http.StatusBadRequest, err.Error())
			return
		}
	}

	if _, err := tx.Exec(`
		INSERT INTO report_status_history (report_id, old_status, new_status, note)
		VALUES (?, NULL, 'pending', 'Initial submission')
	`, reportID); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to create status history")
		return
	}

	if _, err := tx.Exec(`
		INSERT INTO activity_logs (action, entity_type, entity_id, new_value, ip_address, user_agent)
		VALUES ('report_submitted', 'report', ?, ?, ?, ?)
	`, reportID, "pending", readIP(r), r.UserAgent()); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to create activity log")
		return
	}

	if err := tx.Commit(); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to commit report")
		return
	}

	_ = a.refreshStats()

	utils.Created(w, "report submitted successfully", submitReportResponse{
		ReportID: reportID,
		Token:    token,
	})
}

func (a *App) TrackReport(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	token := strings.TrimSpace(r.URL.Query().Get("token"))
	if token == "" {
		utils.Fail(w, http.StatusBadRequest, "token is required")
		return
	}

	row := a.DB.QueryRow(`
		SELECT
			r.id,
			t.token,
			r.category,
			r.title,
			r.description,
			COALESCE(r.location, ''),
			r.priority,
			r.status,
			COALESCE(r.resolution_notes, ''),
			r.created_at,
			r.updated_at
		FROM reports r
		JOIN tokens t ON t.id = r.token_id
		WHERE t.token = ?
		LIMIT 1
	`, token)

	var (
		reportID int64
		result   struct {
			Token           string `json:"token"`
			Category        string `json:"category"`
			Title           string `json:"title"`
			Description     string `json:"description"`
			Location        string `json:"location"`
			Priority        string `json:"priority"`
			Status          string `json:"status"`
			ResolutionNotes string `json:"resolution_notes"`
			CreatedAt       string `json:"created_at"`
			UpdatedAt       string `json:"updated_at"`
		}
	)

	if err := row.Scan(
		&reportID,
		&result.Token,
		&result.Category,
		&result.Title,
		&result.Description,
		&result.Location,
		&result.Priority,
		&result.Status,
		&result.ResolutionNotes,
		&result.CreatedAt,
		&result.UpdatedAt,
	); err != nil {
		if err == sql.ErrNoRows {
			utils.Fail(w, http.StatusNotFound, "report not found")
			return
		}
		utils.Fail(w, http.StatusInternalServerError, "failed to load report")
		return
	}

	timelineRows, err := a.DB.Query(`
		SELECT
			COALESCE(new_status, ''),
			COALESCE(note, ''),
			created_at
		FROM report_status_history
		WHERE report_id = ?
		ORDER BY created_at ASC
	`, reportID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to load report timeline")
		return
	}
	defer timelineRows.Close()

	timeline := make([]map[string]any, 0)
	for timelineRows.Next() {
		var status, note, createdAt string
		if err := timelineRows.Scan(&status, &note, &createdAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan report timeline")
			return
		}

		timeline = append(timeline, map[string]any{
			"title":      prettifyStatusLocal(status),
			"status":     status,
			"note":       note,
			"created_at": createdAt,
		})
	}

	mediaRows, err := a.DB.Query(`
		SELECT id, file_path, file_name, mime_type, media_type, file_size, created_at
		FROM report_media
		WHERE report_id = ?
		ORDER BY created_at ASC
	`, reportID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to load report media")
		return
	}
	defer mediaRows.Close()

	media := []map[string]any{}
	for mediaRows.Next() {
		var (
			id, fileSize                            int64
			filePath, fileName, mimeType, mediaType string
			createdAt                               string
		)

		if err := mediaRows.Scan(&id, &filePath, &fileName, &mimeType, &mediaType, &fileSize, &createdAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan report media")
			return
		}

		media = append(media, map[string]any{
			"id":         id,
			"file_url":   filePath,
			"file_name":  fileName,
			"mime_type":  mimeType,
			"media_type": mediaType,
			"file_size":  fileSize,
			"created_at": createdAt,
		})
	}

	utils.OK(w, map[string]any{
		"token":            result.Token,
		"category":         result.Category,
		"title":            result.Title,
		"description":      result.Description,
		"location":         result.Location,
		"priority":         result.Priority,
		"status":           result.Status,
		"resolution_notes": result.ResolutionNotes,
		"created_at":       result.CreatedAt,
		"updated_at":       result.UpdatedAt,
		"timeline":         timeline,
		"media":            media,
	})
}

func (a *App) GetReportDateCounts(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	year := strings.TrimSpace(r.URL.Query().Get("year"))
	month := strings.TrimSpace(r.URL.Query().Get("month"))
	if year == "" || month == "" {
		utils.Fail(w, http.StatusBadRequest, "year and month are required")
		return
	}

	rows, err := a.DB.Query(`
		SELECT report_date, report_count
		FROM vw_daily_report_counts
		WHERE strftime('%Y', report_date) = ? AND strftime('%m', report_date) = ?
		ORDER BY report_date ASC
	`, year, padMonth(month))
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to load date counts")
		return
	}
	defer rows.Close()

	out := map[string]int64{}
	for rows.Next() {
		var date string
		var count int64
		if err := rows.Scan(&date, &count); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan date counts")
			return
		}
		out[date] = count
	}

	utils.OK(w, out)
}

func (a *App) saveReportMedia(tx *sql.Tx, reportID int64, header *multipart.FileHeader) error {
	const maxFileSize = 25 << 20
	if header.Size > maxFileSize {
		return httpError("one of the uploaded files exceeds 25MB")
	}

	src, err := header.Open()
	if err != nil {
		return httpError("failed to open uploaded file")
	}
	defer src.Close()

	ext := strings.ToLower(filepath.Ext(header.Filename))
	allowed := map[string]string{
		".jpg":  "image",
		".jpeg": "image",
		".png":  "image",
		".webp": "image",
		".mp4":  "video",
		".mov":  "video",
		".webm": "video",
		".pdf":  "document",
		".txt":  "document",
		".doc":  "document",
		".docx": "document",
	}
	mediaType, ok := allowed[ext]
	if !ok {
		return httpError("unsupported file type")
	}

	uploadDir := strings.TrimSpace(a.Cfg.UploadDir)
	if uploadDir == "" {
		uploadDir = "./uploads"
	}

	if err := os.MkdirAll(uploadDir, 0755); err != nil {
		return httpError("failed to prepare upload directory")
	}

	fileName := time.Now().Format("20060102150405") + "_" + sanitizeFileName(header.Filename)
	fullPath := filepath.Join(uploadDir, fileName)

	dst, err := os.Create(fullPath)
	if err != nil {
		return httpError("failed to create upload file")
	}
	defer dst.Close()

	if _, err := io.Copy(dst, src); err != nil {
		return httpError("failed to save uploaded file")
	}

	relativePath := filepath.ToSlash(filepath.Join("uploads", fileName))
	_, err = tx.Exec(`
		INSERT INTO report_media (report_id, file_path, file_name, mime_type, media_type, file_size)
		VALUES (?, ?, ?, ?, ?, ?)
	`, reportID, relativePath, header.Filename, header.Header.Get("Content-Type"), mediaType, header.Size)
	if err != nil {
		return httpError("failed to persist uploaded file")
	}

	return nil
}

func (a *App) refreshStats() error {
	if a.DB == nil {
		return nil
	}

	query := `
		INSERT INTO stats (
			id, total_reports, pending_reports, verified_reports, in_progress_reports,
			resolved_reports, spam_reports, critical_reports, total_users, active_users,
			total_departments, avg_response_time, updated_at
		)
		SELECT
			1,
			(SELECT COUNT(*) FROM reports),
			(SELECT COUNT(*) FROM reports WHERE status = 'pending'),
			(SELECT COUNT(*) FROM reports WHERE status = 'verified'),
			(SELECT COUNT(*) FROM reports WHERE status = 'in_progress'),
			(SELECT COUNT(*) FROM reports WHERE status = 'resolved'),
			(SELECT COUNT(*) FROM reports WHERE status = 'spam'),
			(SELECT COUNT(*) FROM reports WHERE priority = 'critical'),
			(SELECT COUNT(*) FROM users),
			(SELECT COUNT(*) FROM users WHERE is_active = 1),
			(SELECT COUNT(*) FROM departments WHERE is_active = 1),
			(SELECT ROUND(AVG((julianday(resolved_at) - julianday(created_at)) * 24.0), 2) FROM reports WHERE resolved_at IS NOT NULL),
			CURRENT_TIMESTAMP
		ON CONFLICT(id) DO UPDATE SET
			total_reports = excluded.total_reports,
			pending_reports = excluded.pending_reports,
			verified_reports = excluded.verified_reports,
			in_progress_reports = excluded.in_progress_reports,
			resolved_reports = excluded.resolved_reports,
			spam_reports = excluded.spam_reports,
			critical_reports = excluded.critical_reports,
			total_users = excluded.total_users,
			active_users = excluded.active_users,
			total_departments = excluded.total_departments,
			avg_response_time = excluded.avg_response_time,
			updated_at = CURRENT_TIMESTAMP
	`
	_, err := a.DB.Exec(query)
	return err
}

func parseBool(value string, fallback bool) bool {
	value = strings.TrimSpace(strings.ToLower(value))
	if value == "" {
		return fallback
	}
	return value == "1" || value == "true" || value == "yes"
}

func boolToInt(v bool) int {
	if v {
		return 1
	}
	return 0
}

func nullable(v string) any {
	if strings.TrimSpace(v) == "" {
		return nil
	}
	return strings.TrimSpace(v)
}

func padMonth(m string) string {
	m = strings.TrimSpace(m)
	if len(m) == 1 {
		return "0" + m
	}
	return m
}

type httpError string

func (e httpError) Error() string { return string(e) }

func sanitizeFileName(name string) string {
	name = filepath.Base(strings.TrimSpace(name))
	replacer := strings.NewReplacer(" ", "_", "..", "", "/", "", "\\", "", ":", "", "*", "", "?", "", "\"", "", "<", "", ">", "", "|", "")
	return replacer.Replace(name)
}

func humanizeTime(ts string) string {
	if strings.TrimSpace(ts) == "" {
		return "recently"
	}

	layouts := []string{
		"2006-01-02 15:04:05",
		time.RFC3339,
		"2006-01-02T15:04:05Z07:00",
	}

	var parsed time.Time
	var err error
	for _, layout := range layouts {
		parsed, err = time.Parse(layout, ts)
		if err == nil {
			break
		}
	}
	if err != nil {
		return "recently"
	}

	diff := time.Since(parsed)
	switch {
	case diff < time.Minute:
		return "just now"
	case diff < time.Hour:
		return strconv.FormatInt(int64(diff.Minutes()), 10) + " minutes ago"
	case diff < 24*time.Hour:
		return strconv.FormatInt(int64(diff.Hours()), 10) + " hours ago"
	case diff < 48*time.Hour:
		return "1 day ago"
	default:
		return strconv.FormatInt(int64(diff.Hours()/24), 10) + " days ago"
	}
}

func strconvInt64(v int64) string {
	return strings.TrimSpace(strings.ReplaceAll(strings.ReplaceAll(strings.TrimSpace(time.Duration(v).String()), "h0m0s", ""), "m0s", ""))
}

func prettifyStatusLocal(status string) string {
	s := strings.TrimSpace(strings.ReplaceAll(status, "_", " "))
	if s == "" {
		return "Update"
	}

	parts := strings.Fields(strings.ToLower(s))
	for i := range parts {
		if len(parts[i]) > 0 {
			parts[i] = strings.ToUpper(parts[i][:1]) + parts[i][1:]
		}
	}
	return strings.Join(parts, " ")
}
