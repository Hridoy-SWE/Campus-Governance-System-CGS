package main

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"strings"
)

// ==================== ADMIN MIDDLEWARE ====================

func adminOnly(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		// Get user from session/token (simplified for demo)
		userID := r.Header.Get("X-User-ID")
		if userID == "" {
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		var role string
		err := db.QueryRow("SELECT role FROM users WHERE id = ? AND is_active = 1", userID).Scan(&role)
		if err != nil || role != "admin" {
			http.Error(w, "Forbidden: Admin access required", http.StatusForbidden)
			return
		}
		next(w, r)
	}
}

func departmentOnly(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		userID := r.Header.Get("X-User-ID")
		if userID == "" {
			http.Error(w, "Unauthorized", http.StatusUnauthorized)
			return
		}

		var role string
		err := db.QueryRow("SELECT role FROM users WHERE id = ? AND is_active = 1", userID).Scan(&role)
		if err != nil || (role != "department_head" && role != "admin") {
			http.Error(w, "Forbidden: Department access required", http.StatusForbidden)
			return
		}
		next(w, r)
	}
}

// ==================== ADMIN API ROUTES ====================

// Get all users (admin only)
func handleGetUsers(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	rows, err := db.Query(`
        SELECT u.id, u.username, u.email, u.full_name, u.role, 
               d.name as department, u.is_active, u.created_at
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        ORDER BY u.created_at DESC
    `)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}
	defer rows.Close()

	var users []map[string]interface{}
	for rows.Next() {
		var id int
		var username, email, fullName, role, dept, createdAt string
		var isActive int
		rows.Scan(&id, &username, &email, &fullName, &role, &dept, &isActive, &createdAt)

		users = append(users, map[string]interface{}{
			"id":         id,
			"username":   username,
			"email":      email,
			"full_name":  fullName,
			"role":       role,
			"department": dept,
			"is_active":  isActive == 1,
			"created_at": createdAt,
		})
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "data": users})
}

// Create new user (admin only)
func handleCreateUser(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var user struct {
		Username     string `json:"username"`
		Email        string `json:"email"`
		Password     string `json:"password"`
		FullName     string `json:"full_name"`
		Role         string `json:"role"`
		DepartmentID int    `json:"department_id"`
	}

	if err := json.NewDecoder(r.Body).Decode(&user); err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Invalid request"})
		return
	}

	// In production, hash the password!
	_, err := db.Exec(`
        INSERT INTO users (username, email, password_hash, full_name, role, department_id)
        VALUES (?, ?, ?, ?, ?, ?)
    `, user.Username, user.Email, user.Password, user.FullName, user.Role, user.DepartmentID)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "User created"})
}

// Update user (admin only)
func handleUpdateUser(w http.ResponseWriter, r *http.Request) {
	if r.Method != "PUT" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	pathParts := strings.Split(r.URL.Path, "/")
	userID := pathParts[len(pathParts)-1]

	var updates map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&updates); err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Invalid request"})
		return
	}

	// Build dynamic update query
	query := "UPDATE users SET "
	args := []interface{}{}
	for key, value := range updates {
		query += key + " = ?, "
		args = append(args, value)
	}
	query = query[:len(query)-2] + " WHERE id = ?"
	args = append(args, userID)

	_, err := db.Exec(query, args...)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "User updated"})
}

// Delete user (admin only)
func handleDeleteUser(w http.ResponseWriter, r *http.Request) {
	if r.Method != "DELETE" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	pathParts := strings.Split(r.URL.Path, "/")
	userID := pathParts[len(pathParts)-1]

	_, err := db.Exec("DELETE FROM users WHERE id = ?", userID)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "User deleted"})
}

// Get all reports with details (admin/department)
func handleGetAllReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	userID := r.Header.Get("X-User-ID")
	var role string
	var departmentID int
	db.QueryRow("SELECT role, department_id FROM users WHERE id = ?", userID).Scan(&role, &departmentID)

	var rows *sql.Rows
	var err error

	if role == "admin" {
		rows, err = db.Query(`
            SELECT r.id, r.token_id, t.token, r.user_id, u.username, 
                   r.department_id, d.name as department_name,
                   r.category, r.title, r.description, r.location,
                   r.priority, r.status, r.evidence_path,
                   r.assigned_to, assignee.full_name as assigned_to_name,
                   r.views_count, r.created_at, r.updated_at
            FROM reports r
            LEFT JOIN tokens t ON r.token_id = t.id
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN departments d ON r.department_id = d.id
            LEFT JOIN users assignee ON r.assigned_to = assignee.id
            ORDER BY r.created_at DESC
        `)
	} else {
		rows, err = db.Query(`
            SELECT r.id, r.token_id, t.token, r.user_id, u.username,
                   r.department_id, d.name as department_name,
                   r.category, r.title, r.description, r.location,
                   r.priority, r.status, r.evidence_path,
                   r.assigned_to, assignee.full_name as assigned_to_name,
                   r.views_count, r.created_at, r.updated_at
            FROM reports r
            LEFT JOIN tokens t ON r.token_id = t.id
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN departments d ON r.department_id = d.id
            LEFT JOIN users assignee ON r.assigned_to = assignee.id
            WHERE r.department_id = ? OR r.assigned_to = ?
            ORDER BY r.created_at DESC
        `, departmentID, userID)
	}

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}
	defer rows.Close()

	var reports []map[string]interface{}
	for rows.Next() {
		var id, tokenID, userID sql.NullInt64
		var token, username, deptID, deptName, category, title, description, location sql.NullString
		var priority, status, evidencePath, assignedTo, assignedName sql.NullString
		var viewsCount int
		var createdAt, updatedAt string

		rows.Scan(
			&id, &tokenID, &token, &userID, &username,
			&deptID, &deptName, &category, &title, &description, &location,
			&priority, &status, &evidencePath, &assignedTo, &assignedName,
			&viewsCount, &createdAt, &updatedAt,
		)

		reports = append(reports, map[string]interface{}{
			"id":            id.Int64,
			"token":         token.String,
			"reporter_name": username.String,
			"department":    deptName.String,
			"category":      category.String,
			"title":         title.String,
			"description":   description.String,
			"location":      location.String,
			"priority":      priority.String,
			"status":        status.String,
			"assigned_to":   assignedName.String,
			"views":         viewsCount,
			"created_at":    createdAt,
			"updated_at":    updatedAt,
		})
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "data": reports})
}

// Update report status (department/admin)
func handleUpdateReportStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != "PUT" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	pathParts := strings.Split(r.URL.Path, "/")
	reportID := pathParts[len(pathParts)-2] // /api/admin/reports/123/status

	var update struct {
		Status     string `json:"status"`
		AssignedTo int    `json:"assigned_to"`
		Resolution string `json:"resolution_notes"`
	}

	if err := json.NewDecoder(r.Body).Decode(&update); err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Invalid request"})
		return
	}

	tx, err := db.Begin()
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	// Update report
	_, err = tx.Exec(`
        UPDATE reports 
        SET status = ?, assigned_to = ?, resolution_notes = ?,
            resolved_at = CASE WHEN ? IN ('resolved', 'closed') THEN CURRENT_TIMESTAMP ELSE resolved_at END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    `, update.Status, update.AssignedTo, update.Resolution, update.Status, reportID)

	if err != nil {
		tx.Rollback()
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	// Log activity
	userID := r.Header.Get("X-User-ID")
	_, err = tx.Exec(`
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, new_value)
        VALUES (?, 'status_update', 'report', ?, ?)
    `, userID, reportID, update.Status)

	if err != nil {
		tx.Rollback()
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	tx.Commit()
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "Report updated"})
}

// Get comments for a report
func handleGetComments(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	pathParts := strings.Split(r.URL.Path, "/")
	reportID := pathParts[len(pathParts)-2] // /api/reports/123/comments

	rows, err := db.Query(`
        SELECT c.id, c.comment, c.is_staff_response, c.is_private,
               u.full_name as user_name, t.token as anonymous_token,
               c.created_at
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN tokens t ON c.token_id = t.id
        WHERE c.report_id = ?
        ORDER BY c.created_at ASC
    `, reportID)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}
	defer rows.Close()

	var comments []map[string]interface{}
	for rows.Next() {
		var id int
		var comment, userName, anonToken, createdAt string
		var isStaff, isPrivate int
		rows.Scan(&id, &comment, &isStaff, &isPrivate, &userName, &anonToken, &createdAt)

		author := "Anonymous"
		if userName != "" {
			author = userName
		} else if anonToken != "" {
			author = "User " + anonToken[:8] + "..."
		}

		comments = append(comments, map[string]interface{}{
			"id":         id,
			"comment":    comment,
			"author":     author,
			"is_staff":   isStaff == 1,
			"is_private": isPrivate == 1,
			"created_at": createdAt,
		})
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "data": comments})
}

// Add comment to report
func handleAddComment(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	pathParts := strings.Split(r.URL.Path, "/")
	reportID := pathParts[len(pathParts)-2]

	var commentData struct {
		Comment   string `json:"comment"`
		IsStaff   bool   `json:"is_staff"`
		IsPrivate bool   `json:"is_private"`
		UserID    int    `json:"user_id"`
		TokenID   int    `json:"token_id"`
	}

	if err := json.NewDecoder(r.Body).Decode(&commentData); err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Invalid request"})
		return
	}

	_, err := db.Exec(`
        INSERT INTO comments (report_id, user_id, token_id, comment, is_staff_response, is_private)
        VALUES (?, ?, ?, ?, ?, ?)
    `, reportID, commentData.UserID, commentData.TokenID, commentData.Comment,
		commentData.IsStaff, commentData.IsPrivate)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "message": "Comment added"})
}
