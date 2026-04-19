
package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"campus-governance/middleware"
	"campus-governance/utils"
)

func (a *App) ListMessageThreads(w http.ResponseWriter, r *http.Request) {
	user, ok := middleware.CurrentUser(r)
	if !ok {
		utils.Fail(w, http.StatusUnauthorized, "authentication required")
		return
	}

	rows, err := a.DB.Query(`
		SELECT
			m.id,
			COALESCE(m.subject, ''),
			COALESCE(recipient.full_name, recipient.username, 'Anonymous'),
			MAX(m.created_at) AS updated_at
		FROM messages m
		LEFT JOIN users recipient ON recipient.id = m.recipient_user_id
		WHERE m.sender_user_id = ? OR m.recipient_user_id = ?
		GROUP BY m.id, m.subject, recipient.full_name, recipient.username
		ORDER BY updated_at DESC
	`, user.ID, user.ID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch message threads")
		return
	}
	defer rows.Close()

	out := []map[string]any{}
	for rows.Next() {
		var id int64
		var subject, recipient, updatedAt string
		if err := rows.Scan(&id, &subject, &recipient, &updatedAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan message thread")
			return
		}
		out = append(out, map[string]any{
			"id":         id,
			"subject":    subject,
			"recipient":  recipient,
			"updated_at": updatedAt,
		})
	}
	utils.OK(w, out)
}

func (a *App) GetMessageThread(w http.ResponseWriter, r *http.Request) {
	threadID, err := strconv.ParseInt(r.PathValue("threadID"), 10, 64)
	if err != nil || threadID <= 0 {
		utils.Fail(w, http.StatusBadRequest, "invalid thread id")
		return
	}

	rows, err := a.DB.Query(`
		SELECT id, COALESCE(subject, ''), message, created_at
		FROM messages
		WHERE id = ?
		ORDER BY created_at ASC
	`, threadID)
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch thread")
		return
	}
	defer rows.Close()

	out := []map[string]any{}
	for rows.Next() {
		var id int64
		var subject, message, createdAt string
		if err := rows.Scan(&id, &subject, &message, &createdAt); err != nil {
			utils.Fail(w, http.StatusInternalServerError, "failed to scan thread item")
			return
		}
		out = append(out, map[string]any{
			"id":         id,
			"subject":    subject,
			"message":    message,
			"created_at": createdAt,
		})
	}
	utils.OK(w, out)
}

func (a *App) SendMessage(w http.ResponseWriter, r *http.Request) {
	user, ok := middleware.CurrentUser(r)
	if !ok {
		utils.Fail(w, http.StatusUnauthorized, "authentication required")
		return
	}

	var req struct {
		RecipientUserID *int64 `json:"recipient_user_id"`
		RecipientTokenID *int64 `json:"recipient_token_id"`
		ReportID        *int64 `json:"report_id"`
		Subject         string `json:"subject"`
		Message         string `json:"message"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		utils.Fail(w, http.StatusBadRequest, "invalid JSON body")
		return
	}

	if req.RecipientUserID == nil && req.RecipientTokenID == nil {
		utils.Fail(w, http.StatusBadRequest, "recipient_user_id or recipient_token_id is required")
		return
	}
	if !utils.TrimmedLenBetween(req.Subject, 1, 200) {
		utils.Fail(w, http.StatusBadRequest, "subject must be between 1 and 200 characters")
		return
	}
	if !utils.TrimmedLenBetween(req.Message, 1, 5000) {
		utils.Fail(w, http.StatusBadRequest, "message must be between 1 and 5000 characters")
		return
	}

	res, err := a.DB.Exec(`
		INSERT INTO messages (sender_user_id, recipient_user_id, recipient_token_id, report_id, subject, message, status)
		VALUES (?, ?, ?, ?, ?, ?, 'sent')
	`, user.ID, req.RecipientUserID, req.RecipientTokenID, req.ReportID, strings.TrimSpace(req.Subject), strings.TrimSpace(req.Message))
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to send message")
		return
	}
	id, _ := res.LastInsertId()
	utils.Created(w, "message sent", map[string]any{"message_id": id})
}
