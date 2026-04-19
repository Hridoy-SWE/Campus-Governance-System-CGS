
package middleware

import (
	"context"
	"database/sql"
	"errors"
	"log"
	"net/http"
	"strings"
	"time"

	"campus-governance/config"
	"campus-governance/utils"
)

type contextKey string

const userKey contextKey = "auth_user"

type AuthUser struct {
	ID           int64
	Username     string
	Email        string
	FullName     string
	Role         string
	DepartmentID sql.NullInt64
	IsActive     bool
}

func AuthRequired(db *sql.DB, cfg config.Config) func(http.Handler) http.Handler {
	cookieName := "cgs_session"

	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			token := ""

			if cookie, err := r.Cookie(cookieName); err == nil && strings.TrimSpace(cookie.Value) != "" {
				token = strings.TrimSpace(cookie.Value)
			}

			if token == "" {
				authHeader := strings.TrimSpace(r.Header.Get("Authorization"))
				if strings.HasPrefix(strings.ToLower(authHeader), "bearer ") {
					token = strings.TrimSpace(authHeader[7:])
				}
			}

			if token == "" {
				utils.Fail(w, http.StatusUnauthorized, "authentication required")
				return
			}

			var user AuthUser
			var sessionID int64
			query := `
				SELECT s.id, u.id, u.username, u.email, u.full_name, u.role, u.department_id, u.is_active
				FROM sessions s
				JOIN users u ON u.id = s.user_id
				WHERE s.session_token = ? AND s.expires_at > CURRENT_TIMESTAMP
				LIMIT 1
			`
			err := db.QueryRow(query, token).Scan(
				&sessionID,
				&user.ID,
				&user.Username,
				&user.Email,
				&user.FullName,
				&user.Role,
				&user.DepartmentID,
				&user.IsActive,
			)
			if err != nil {
				if errors.Is(err, sql.ErrNoRows) {
					utils.Fail(w, http.StatusUnauthorized, "invalid or expired session")
					return
				}
				utils.Fail(w, http.StatusInternalServerError, "failed to validate session")
				return
			}

			if !user.IsActive {
				utils.Fail(w, http.StatusForbidden, "account is inactive")
				return
			}

			_, _ = db.Exec(`UPDATE sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?`, sessionID)

			ctx := context.WithValue(r.Context(), userKey, user)
			next.ServeHTTP(w, r.WithContext(ctx))
		})
	}
}

func CurrentUser(r *http.Request) (AuthUser, bool) {
	user, ok := r.Context().Value(userKey).(AuthUser)
	return user, ok
}

func CORS(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		origin := r.Header.Get("Origin")
		if origin == "" {
			origin = "*"
		}
		w.Header().Set("Access-Control-Allow-Origin", origin)
		w.Header().Set("Vary", "Origin")
		w.Header().Set("Access-Control-Allow-Credentials", "true")
		w.Header().Set("Access-Control-Allow-Methods", "GET,POST,PATCH,PUT,DELETE,OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")

		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		next.ServeHTTP(w, r)
	})
}

func Logging(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("%s %s %s", r.Method, r.URL.Path, time.Since(start))
	})
}
