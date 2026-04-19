package main

import (
	"database/sql"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	"campus-governance/config"
	"campus-governance/handlers"
	"campus-governance/middleware"

	_ "github.com/mattn/go-sqlite3"
)

func main() {
	cfg := config.Load()

	if err := os.MkdirAll(cfg.UploadDir, 0755); err != nil {
		log.Fatalf("failed to create upload directory: %v", err)
	}

	db, err := openDatabase(cfg)
	if err != nil {
		log.Fatalf("failed to connect database: %v", err)
	}
	defer db.Close()

	app := handlers.NewApp(db, cfg)

	mux := http.NewServeMux()
	mux.HandleFunc("GET /api/reports/all", app.GetAllReports)

	// Public routes
	mux.HandleFunc("GET /health", app.Health)

	mux.HandleFunc("POST /api/auth/register", app.Register)
	mux.HandleFunc("POST /api/auth/login", app.Login)
	mux.HandleFunc("POST /api/auth/logout", app.Logout)

	mux.Handle(
		"GET /api/auth/me",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				http.HandlerFunc(app.Me),
			),
		),
	)

	mux.HandleFunc("GET /api/stats", app.GetStats)
	mux.HandleFunc("GET /api/reports/latest", app.GetLatestReports)
	mux.HandleFunc("POST /api/report/submit", app.SubmitReport)
	mux.HandleFunc("GET /api/report/track", app.TrackReport)
	mux.HandleFunc("GET /api/report/date-counts", app.GetReportDateCounts)

	// Admin user routes
	mux.Handle(
		"GET /api/admin/users",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.GetUsers)),
			),
		),
	)

	mux.Handle(
		"POST /api/admin/users",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin")(http.HandlerFunc(app.CreateUser)),
			),
		),
	)

	mux.Handle(
		"PUT /api/admin/users/{id}/status",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateUserStatus)),
			),
		),
	)

	mux.Handle(
		"PATCH /api/admin/users/{id}/status",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateUserStatus)),
			),
		),
	)

	// Admin report routes
	mux.Handle(
		"GET /api/admin/reports",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.GetAdminReports)),
			),
		),
	)

	mux.Handle(
		"PUT /api/admin/reports/{id}/status",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateReportStatus)),
			),
		),
	)

	mux.Handle(
		"PATCH /api/admin/reports/{id}/status",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateReportStatus)),
			),
		),
	)

	mux.Handle(
		"PUT /api/admin/reports/{id}/details",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateReportDetails)),
			),
		),
	)

	mux.Handle(
		"PATCH /api/admin/reports/{id}/details",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateReportDetails)),
			),
		),
	)

	mux.Handle(
		"DELETE /api/admin/reports/{id}",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.DeleteReport)),
			),
		),
	)

	mux.Handle(
		"GET /api/admin/reports/{id}/media",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.GetReportMedia)),
			),
		),
	)

	mux.Handle(
		"GET /api/admin/reports/{id}/comments",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.GetComments)),
			),
		),
	)

	mux.Handle(
		"POST /api/admin/reports/{id}/comments",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.AddComment)),
			),
		),
	)

	// Spam routes
	mux.Handle(
		"GET /api/admin/spam-reports",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.GetSpamReports)),
			),
		),
	)

	mux.Handle(
		"PUT /api/admin/reports/{id}/spam",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateSpamState)),
			),
		),
	)

	mux.Handle(
		"PATCH /api/admin/reports/{id}/spam",
		withCORS(cfg.FrontendOrigin)(
			middleware.AuthRequired(db, cfg)(
				requireRoles("admin", "faculty")(http.HandlerFunc(app.UpdateSpamState)),
			),
		),
	)

	// Static uploaded files
	fileServer := http.FileServer(http.Dir(cfg.UploadDir))
	mux.Handle("/uploads/", http.StripPrefix("/uploads/", fileServer))

	// Wrap whole mux with CORS
	handler := withCORS(cfg.FrontendOrigin)(mux)

	log.Printf("backend running on http://127.0.0.1:%s", cfg.Port)
	log.Printf("allowed frontend origin from config: %s", cfg.FrontendOrigin)

	log.Fatal(http.ListenAndServe(":"+cfg.Port, handler))
}

func openDatabase(cfg config.Config) (*sql.DB, error) {
	dsn := strings.TrimSpace(cfg.DatabasePath)
	if dsn == "" {
		dsn = "../database/campus.db"
	}

	db, err := sql.Open("sqlite3", dsn)
	if err != nil {
		return nil, err
	}

	db.SetMaxOpenConns(1)
	db.SetMaxIdleConns(1)
	db.SetConnMaxLifetime(30 * time.Minute)

	if err := db.Ping(); err != nil {
		_ = db.Close()
		return nil, err
	}

	pragmas := []string{
		`PRAGMA foreign_keys = ON;`,
		`PRAGMA journal_mode = WAL;`,
		`PRAGMA synchronous = NORMAL;`,
		`PRAGMA busy_timeout = 5000;`,
	}

	for _, stmt := range pragmas {
		if _, err := db.Exec(stmt); err != nil {
			_ = db.Close()
			return nil, err
		}
	}

	return db, nil
}

func withCORS(frontendOrigin string) func(http.Handler) http.Handler {
	allowedOrigin := strings.TrimSpace(frontendOrigin)

	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			origin := strings.TrimSpace(r.Header.Get("Origin"))

			allow := false

			if origin != "" {
				// exact configured origin from .env
				if allowedOrigin != "" && origin == allowedOrigin {
					allow = true
				}

				// allow common local development origins on any port
				if origin == "http://localhost" ||
					origin == "http://127.0.0.1" ||
					strings.HasPrefix(origin, "http://localhost:") ||
					strings.HasPrefix(origin, "http://127.0.0.1:") {
					allow = true
				}
			}

			if allow {
				w.Header().Set("Access-Control-Allow-Origin", origin)
				w.Header().Set("Vary", "Origin")
				w.Header().Set("Access-Control-Allow-Credentials", "true")
				w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With")
				w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, PATCH, DELETE, OPTIONS")
			}

			if r.Method == http.MethodOptions {
				w.WriteHeader(http.StatusNoContent)
				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

func requireRoles(roles ...string) func(http.Handler) http.Handler {
	allowed := make(map[string]struct{}, len(roles))
	for _, role := range roles {
		role = strings.ToLower(strings.TrimSpace(role))
		if role != "" {
			allowed[role] = struct{}{}
		}
	}

	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			user, ok := middleware.CurrentUser(r)
			if !ok {
				http.Error(w, "authentication required", http.StatusUnauthorized)
				return
			}

			role := strings.ToLower(strings.TrimSpace(user.Role))
			if _, exists := allowed[role]; !exists {
				http.Error(w, "forbidden", http.StatusForbidden)
				return
			}

			next.ServeHTTP(w, r)
		})
	}
}
