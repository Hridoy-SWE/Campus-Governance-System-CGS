package handlers

import (
	"database/sql"
	"encoding/json"
	"errors"
	"net"
	"net/http"
	"strings"
	"time"

	"campus-governance/config"
	"campus-governance/middleware"
	"campus-governance/utils"
)

type App struct {
	DB  *sql.DB
	Cfg config.Config
}

func NewApp(db *sql.DB, cfg config.Config) *App {
	return &App{
		DB:  db,
		Cfg: cfg,
	}
}

func (a *App) Health(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	utils.OK(w, map[string]any{
		"status": "healthy",
		"time":   time.Now().Format(time.RFC3339),
	})
}

type registerRequest struct {
	Username     string `json:"username"`
	Email        string `json:"email"`
	Password     string `json:"password"`
	FullName     string `json:"full_name"`
	Role         string `json:"role"`
	DepartmentID *int64 `json:"department_id"`
	Phone        string `json:"phone"`
}

func (a *App) Register(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	var req registerRequest
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

	if req.Role == "" {
		req.Role = "student"
	}

	errorsMap := map[string]string{}

	if !utils.ValidUsername(req.Username) {
		errorsMap["username"] = "username must be 3-50 chars and contain only letters, numbers, dot, underscore, dash"
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

	tx, err := a.DB.Begin()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to start transaction")
		return
	}
	defer tx.Rollback()

	res, err := tx.Exec(`
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
	`,
		req.Username,
		req.Email,
		hashed,
		req.FullName,
		req.Role,
		req.DepartmentID,
		req.Phone,
	)
	if err != nil {
		if looksLikeUniqueConstraint(err) {
			utils.Fail(w, http.StatusConflict, "username or email already exists")
			return
		}
		utils.Fail(w, http.StatusInternalServerError, "failed to create user")
		return
	}

	userID, err := res.LastInsertId()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch user id")
		return
	}

	if err := createSession(tx, userID, a.Cfg, w, r); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to create session")
		return
	}

	if err := tx.Commit(); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to commit user creation")
		return
	}

	utils.Created(w, "registration successful", map[string]any{
		"user": map[string]any{
			"id":            userID,
			"username":      req.Username,
			"email":         req.Email,
			"full_name":     req.FullName,
			"role":          req.Role,
			"department_id": req.DepartmentID,
			"phone":         req.Phone,
		},
	})
}

type loginRequest struct {
	Login    string `json:"login"`
	Password string `json:"password"`
}

func (a *App) Login(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	var req loginRequest
	if err := decodeJSONBody(r, &req); err != nil {
		utils.Fail(w, http.StatusBadRequest, err.Error())
		return
	}

	login := strings.TrimSpace(req.Login)
	password := strings.TrimSpace(req.Password)

	if login == "" || password == "" {
		utils.ValidationFail(w, map[string]string{
			"login":    "login is required",
			"password": "password is required",
		})
		return
	}

	loginLower := strings.ToLower(login)

	var (
		userID       int64
		username     string
		email        string
		fullName     string
		role         string
		passwordHash string
		isActive     bool
		departmentID sql.NullInt64
	)

	err := a.DB.QueryRow(`
		SELECT
			id,
			username,
			email,
			full_name,
			role,
			password_hash,
			is_active,
			department_id
		FROM users
		WHERE LOWER(username) = ? OR LOWER(email) = ?
		LIMIT 1
	`, loginLower, loginLower).Scan(
		&userID,
		&username,
		&email,
		&fullName,
		&role,
		&passwordHash,
		&isActive,
		&departmentID,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			utils.Fail(w, http.StatusUnauthorized, "invalid credentials")
			return
		}
		utils.Fail(w, http.StatusInternalServerError, "failed to fetch user")
		return
	}

	if !isActive {
		utils.Fail(w, http.StatusForbidden, "account is inactive")
		return
	}

	if !utils.CheckPassword(passwordHash, password) {
		utils.Fail(w, http.StatusUnauthorized, "invalid credentials")
		return
	}

	tx, err := a.DB.Begin()
	if err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to start transaction")
		return
	}
	defer tx.Rollback()

	if _, err := tx.Exec(`
		UPDATE users
		SET last_login = CURRENT_TIMESTAMP,
		    updated_at = CURRENT_TIMESTAMP
		WHERE id = ?
	`, userID); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to update last login")
		return
	}

	if err := createSession(tx, userID, a.Cfg, w, r); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to create session")
		return
	}

	if err := tx.Commit(); err != nil {
		utils.Fail(w, http.StatusInternalServerError, "failed to commit login")
		return
	}

	var deptID any = nil
	if departmentID.Valid {
		deptID = departmentID.Int64
	}

	utils.OK(w, map[string]any{
		"user": map[string]any{
			"id":            userID,
			"username":      username,
			"email":         email,
			"full_name":     fullName,
			"role":          role,
			"department_id": deptID,
		},
	})
}

func (a *App) Logout(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	if a.DB == nil {
		utils.Fail(w, http.StatusInternalServerError, "database is not initialized")
		return
	}

	token := ""
	if cookie, err := r.Cookie("cgs_session"); err == nil {
		token = strings.TrimSpace(cookie.Value)
	}

	if token != "" {
		_, _ = a.DB.Exec(`DELETE FROM sessions WHERE session_token = ?`, token)
	}

	clearSessionCookie(w, a.Cfg)

	utils.OK(w, map[string]string{
		"message": "logged out",
	})
}

func (a *App) Me(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		utils.Fail(w, http.StatusMethodNotAllowed, "method not allowed")
		return
	}

	user, ok := middleware.CurrentUser(r)
	if !ok {
		utils.Fail(w, http.StatusUnauthorized, "authentication required")
		return
	}

	utils.OK(w, map[string]any{
		"id":            user.ID,
		"username":      user.Username,
		"email":         user.Email,
		"full_name":     user.FullName,
		"role":          user.Role,
		"department_id": user.DepartmentID,
	})
}

func createSession(tx *sql.Tx, userID int64, cfg config.Config, w http.ResponseWriter, r *http.Request) error {
	token, err := utils.NewSessionToken()
	if err != nil {
		return err
	}

	ttlHours := cfg.SessionTTLHours
	if ttlHours <= 0 {
		ttlHours = 24
	}

	expiresAt := time.Now().Add(time.Duration(ttlHours) * time.Hour).UTC().Format("2006-01-02 15:04:05")
	userAgent := strings.TrimSpace(r.UserAgent())
	ipAddress := readIP(r)

	_, err = tx.Exec(`
		INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at)
		VALUES (?, ?, ?, ?, ?)
	`, userID, token, ipAddress, userAgent, expiresAt)
	if err != nil {
		return err
	}

	http.SetCookie(w, &http.Cookie{
		Name:     "cgs_session",
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		MaxAge:   ttlHours * 3600,
		Secure:   isSecureCookie(cfg),
	})

	return nil
}

func clearSessionCookie(w http.ResponseWriter, cfg config.Config) {
	http.SetCookie(w, &http.Cookie{
		Name:     "cgs_session",
		Value:    "",
		Path:     "/",
		HttpOnly: true,
		MaxAge:   -1,
		SameSite: http.SameSiteLaxMode,
		Secure:   isSecureCookie(cfg),
	})
}

func isSecureCookie(cfg config.Config) bool {
	return strings.EqualFold(strings.TrimSpace(cfg.AppEnv), "production")
}

func readIP(r *http.Request) string {
	if fwd := strings.TrimSpace(r.Header.Get("X-Forwarded-For")); fwd != "" {
		parts := strings.Split(fwd, ",")
		if len(parts) > 0 {
			return strings.TrimSpace(parts[0])
		}
	}

	if realIP := strings.TrimSpace(r.Header.Get("X-Real-IP")); realIP != "" {
		return realIP
	}

	host, _, err := net.SplitHostPort(strings.TrimSpace(r.RemoteAddr))
	if err == nil && host != "" {
		return host
	}

	return strings.TrimSpace(r.RemoteAddr)
}

func decodeJSONBody(r *http.Request, dst any) error {
	if r.Body == nil {
		return errors.New("request body is required")
	}

	decoder := json.NewDecoder(r.Body)
	decoder.DisallowUnknownFields()

	if err := decoder.Decode(dst); err != nil {
		return errors.New("invalid JSON body")
	}

	if decoder.More() {
		return errors.New("request body must contain only one JSON object")
	}

	return nil
}

func looksLikeUniqueConstraint(err error) bool {
	if err == nil {
		return false
	}

	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "unique") || strings.Contains(msg, "constraint")
}
