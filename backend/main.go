package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	_ "github.com/mattn/go-sqlite3"
)

type Report struct {
	ID          int    `json:"id"`
	Token       string `json:"token"`
	Category    string `json:"category"`
	Title       string `json:"title"`
	Description string `json:"description"`
	Location    string `json:"location"`
	Status      string `json:"status"`
	CreatedAt   string `json:"created_at"`
}

type Stats struct {
	TotalReports    int `json:"total_reports"`
	VerifiedReports int `json:"verified_reports"`
	PendingReports  int `json:"pending_reports"`
	ResolvedReports int `json:"resolved_reports"`
}

type Response struct {
	Success bool        `json:"success"`
	Message string      `json:"message,omitempty"`
	Data    interface{} `json:"data,omitempty"`
}

var db *sql.DB

func main() {
	// Initialize database
	if err := initDB(); err != nil {
		log.Fatal("❌ Database initialization failed:", err)
	}
	defer db.Close()

	// Create uploads directory if not exists
	if err := os.MkdirAll("./uploads", 0755); err != nil {
		log.Printf("⚠️ Warning: Could not create uploads directory: %v", err)
	}

	// Serve static files (frontend)
	http.Handle("/", http.FileServer(http.Dir("./frontend")))

	// API routes with CORS
	http.HandleFunc("/api/stats", enableCORS(handleStats))
	http.HandleFunc("/api/reports/latest", enableCORS(handleLatestReports))
	http.HandleFunc("/api/report/submit", enableCORS(handleSubmitReport))
	http.HandleFunc("/api/report/track", enableCORS(handleTrackReport))
	http.HandleFunc("/api/reports/all", enableCORS(handleAllReports))

	// Health check endpoint
	http.HandleFunc("/health", enableCORS(handleHealth))

	fmt.Println("========================================")
	fmt.Println("🚀 Campus Governance System Server")
	fmt.Println("========================================")
	fmt.Printf("📍 Server URL: http://localhost:8080\n")
	fmt.Printf("📁 Database: ./database/campus.db\n")
	fmt.Printf("📂 Frontend: ./frontend/\n")
	fmt.Printf("🔄 API Endpoints:\n")
	fmt.Printf("   GET  /api/stats\n")
	fmt.Printf("   GET  /api/reports/latest\n")
	fmt.Printf("   GET  /api/reports/all\n")
	fmt.Printf("   POST /api/report/submit\n")
	fmt.Printf("   GET  /api/report/track?token=XXX\n")
	fmt.Println("========================================")
	
	log.Fatal(http.ListenAndServe(":8080", nil))
}

// Health check handler
func handleHealth(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(Response{
		Success: true,
		Message: "Server is running",
		Data: map[string]interface{}{
			"status": "healthy",
			"time":   time.Now().Format(time.RFC3339),
		},
	})
}

// CORS middleware
func enableCORS(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")

		if r.Method == "OPTIONS" {
			w.WriteHeader(http.StatusOK)
			return
		}

		next(w, r)
	}
}

func initDB() error {
	// Ensure database directory exists
	dbPath := "./database/campus.db"
	dbDir := filepath.Dir(dbPath)
	if err := os.MkdirAll(dbDir, 0755); err != nil {
		return fmt.Errorf("failed to create database directory: %v", err)
	}

	// Open database connection
	var err error
	db, err = sql.Open("sqlite3", dbPath+"?_foreign_keys=on")
	if err != nil {
		return fmt.Errorf("failed to open database: %v", err)
	}

	// Configure connection pool
	db.SetMaxOpenConns(25)
	db.SetMaxIdleConns(5)
	db.SetConnMaxLifetime(5 * time.Minute)

	// Test connection
	if err = db.Ping(); err != nil {
		return fmt.Errorf("failed to ping database: %v", err)
	}

	// Check if schema.sql exists
	schemaPath := "./database/schema.sql"
	if _, err := os.Stat(schemaPath); os.IsNotExist(err) {
		return fmt.Errorf("schema file not found at %s", schemaPath)
	}

	// Read schema file
	schema, err := os.ReadFile(schemaPath)
	if err != nil {
		return fmt.Errorf("failed to read schema file: %v", err)
	}

	// Check if tables exist
	var tableCount int
	err = db.QueryRow("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='reports'").Scan(&tableCount)
	if err != nil {
		return fmt.Errorf("failed to check tables: %v", err)
	}

	// Only run schema if tables don't exist
	if tableCount == 0 {
		log.Println("📦 Creating database tables...")
		_, err = db.Exec(string(schema))
		if err != nil {
			return fmt.Errorf("failed to execute schema: %v", err)
		}
		log.Println("✅ Tables created successfully")
	} else {
		log.Println("📦 Database already initialized")
	}

	// Verify stats record exists
	var statsCount int
	err = db.QueryRow("SELECT count(*) FROM stats").Scan(&statsCount)
	if err != nil {
		log.Println("⚠️ Stats table not found, creating...")
		_, err = db.Exec("CREATE TABLE IF NOT EXISTS stats (id INTEGER PRIMARY KEY, total_reports INTEGER DEFAULT 0, verified_reports INTEGER DEFAULT 0, pending_reports INTEGER DEFAULT 0, resolved_reports INTEGER DEFAULT 0)")
		if err != nil {
			return fmt.Errorf("failed to create stats table: %v", err)
		}
		statsCount = 0
	}

	if statsCount == 0 {
		_, err = db.Exec("INSERT OR IGNORE INTO stats (id, total_reports, verified_reports, pending_reports, resolved_reports) VALUES (1, 0, 0, 0, 0)")
		if err != nil {
			return fmt.Errorf("failed to insert stats: %v", err)
		}
	}

	// Update stats with current data
	updateStats()

	log.Println("✅ Database ready")
	return nil
}

// Update stats helper
func updateStats() {
	var total, verified, pending, resolved int
	db.QueryRow("SELECT COUNT(*) FROM reports").Scan(&total)
	db.QueryRow("SELECT COUNT(*) FROM reports WHERE status = 'verified'").Scan(&verified)
	db.QueryRow("SELECT COUNT(*) FROM reports WHERE status = 'pending'").Scan(&pending)
	db.QueryRow("SELECT COUNT(*) FROM reports WHERE status = 'resolved'").Scan(&resolved)

	db.Exec("UPDATE stats SET total_reports=?, verified_reports=?, pending_reports=?, resolved_reports=? WHERE id=1",
		total, verified, pending, resolved)
}

func handleStats(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	var stats Stats
	err := db.QueryRow(`
		SELECT 
			COALESCE(total_reports, 0), 
			COALESCE(verified_reports, 0), 
			COALESCE(pending_reports, 0), 
			COALESCE(resolved_reports, 0) 
		FROM stats WHERE id = 1`).Scan(
		&stats.TotalReports, &stats.VerifiedReports, &stats.PendingReports, &stats.ResolvedReports)

	if err != nil {
		log.Printf("⚠️ Stats query error: %v, calculating on the fly", err)
		// If stats not found, calculate on the fly
		var total, verified, pending, resolved int
		db.QueryRow("SELECT COUNT(*) FROM reports").Scan(&total)
		db.QueryRow("SELECT COUNT(*) FROM reports WHERE status = 'verified'").Scan(&verified)
		db.QueryRow("SELECT COUNT(*) FROM reports WHERE status = 'pending'").Scan(&pending)
		db.QueryRow("SELECT COUNT(*) FROM reports WHERE status = 'resolved'").Scan(&resolved)

		stats = Stats{
			TotalReports:    total,
			VerifiedReports: verified,
			PendingReports:  pending,
			ResolvedReports: resolved,
		}

		// Update stats table
		db.Exec("UPDATE stats SET total_reports=?, verified_reports=?, pending_reports=?, resolved_reports=? WHERE id=1",
			total, verified, pending, resolved)
	}

	json.NewEncoder(w).Encode(Response{
		Success: true,
		Data:    stats,
	})
}

func handleLatestReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	rows, err := db.Query(`
		SELECT token, category, title, location, status, created_at 
		FROM reports 
		ORDER BY created_at DESC 
		LIMIT 10`)
	if err != nil {
		json.NewEncoder(w).Encode(Response{
			Success: false,
			Message: "Failed to fetch reports",
		})
		return
	}
	defer rows.Close()

	var reports []map[string]interface{}
	for rows.Next() {
		var token, category, title, location, status, created_at string
		err := rows.Scan(&token, &category, &title, &location, &status, &created_at)
		if err != nil {
			continue
		}

		// Parse date properly
		var timeAgo string
		parsedTime, err := time.Parse("2006-01-02 15:04:05", created_at)
		if err != nil {
			parsedTime, err = time.Parse(time.RFC3339, created_at)
			if err != nil {
				timeAgo = "recently"
			} else {
				timeAgo = formatTimeAgo(parsedTime)
			}
		} else {
			timeAgo = formatTimeAgo(parsedTime)
		}

		// Mask token for privacy
		parts := strings.Split(token, "-")
		maskedToken := token
		if len(parts) >= 3 {
			maskedToken = parts[0] + "-" + parts[1][:4] + "-****"
		}

		reports = append(reports, map[string]interface{}{
			"token":     maskedToken,
			"fullToken": token,
			"category":  category,
			"title":     title,
			"location":  location,
			"status":    status,
			"timeAgo":   timeAgo,
			"createdAt": created_at,
		})
	}

	json.NewEncoder(w).Encode(Response{
		Success: true,
		Data:    reports,
	})
}

func handleAllReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	rows, err := db.Query(`
		SELECT id, token, category, title, description, location, status, created_at 
		FROM reports 
		ORDER BY created_at DESC`)
	if err != nil {
		json.NewEncoder(w).Encode(Response{
			Success: false,
			Message: "Failed to fetch reports: " + err.Error(),
		})
		return
	}
	defer rows.Close()

	var reports []map[string]interface{}
	for rows.Next() {
		var id int
		var token, category, title, description, location, status, created_at string
		err := rows.Scan(&id, &token, &category, &title, &description, &location, &status, &created_at)
		if err != nil {
			continue
		}

		reports = append(reports, map[string]interface{}{
			"id":          id,
			"token":       token,
			"category":    category,
			"title":       title,
			"description": description,
			"location":    location,
			"status":      status,
			"createdAt":   created_at,
		})
	}

	json.NewEncoder(w).Encode(Response{
		Success: true,
		Data:    reports,
	})
}

func handleSubmitReport(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	if r.Method != "POST" {
		json.NewEncoder(w).Encode(Response{
			Success: false,
			Message: "Method not allowed",
		})
		return
	}

	// Parse form data (max 10 MB)
	if err := r.ParseMultipartForm(10 << 20); err != nil {
		if err := r.ParseForm(); err != nil {
			json.NewEncoder(w).Encode(Response{
				Success: false,
				Message: "Failed to parse form",
			})
			return
		}
	}

	category := strings.TrimSpace(r.FormValue("category"))
	title := strings.TrimSpace(r.FormValue("title"))
	description := strings.TrimSpace(r.FormValue("description"))
	location := strings.TrimSpace(r.FormValue("location"))

	// Validation
	if category == "" || title == "" || description == "" {
		json.NewEncoder(w).Encode(Response{
			Success: false,
			Message: "Category, title and description are required",
		})
		return
	}

	// Generate unique token
	token := generateToken()

	// Handle file upload if any
	var evidencePath string
	file, header, err := r.FormFile("evidence")
	if err == nil {
		defer file.Close()

		// Create evidence directory if not exists
		evidenceDir := "./uploads/evidence"
		os.MkdirAll(evidenceDir, 0755)

		// Save file with timestamp to avoid duplicates
		filename := fmt.Sprintf("%d_%s", time.Now().Unix(), filepath.Base(header.Filename))
		evidencePath = filepath.Join("uploads/evidence", filename)
		fullPath := filepath.Join(".", evidencePath)

		// Create and write file
		out, err := os.Create(fullPath)
		if err == nil {
			defer out.Close()
			io.Copy(out, file)
			log.Printf("📎 File uploaded: %s", filename)
		}
	}

	// Insert report
	_, err = db.Exec(
		"INSERT INTO reports (token, category, title, description, location, evidence_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
		token, category, title, description, location, evidencePath)

	if err != nil {
		json.NewEncoder(w).Encode(Response{
			Success: false,
			Message: "Failed to save report: " + err.Error(),
		})
		return
	}

	// Update stats after successful insert
	updateStats()

	log.Printf("📝 New report submitted: %s - %s", token, title)

	json.NewEncoder(w).Encode(Response{
		Success: true,
		Message: "Report submitted successfully",
		Data: map[string]string{
			"token": token,
		},
	})
}

func handleTrackReport(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	token := r.URL.Query().Get("token")
	if token == "" {
		json.NewEncoder(w).Encode(Response{
			Success: false,
			Message: "Token is required",
		})
		return
	}

	var report Report
	var evidencePath sql.NullString
	var createdAt string

	err := db.QueryRow(`
		SELECT id, token, category, title, description, location, status, created_at 
		FROM reports WHERE token = ?`, token).Scan(
		&report.ID, &report.Token, &report.Category, &report.Title,
		&report.Description, &report.Location, &report.Status, &createdAt)

	if err != nil {
		if err == sql.ErrNoRows {
			json.NewEncoder(w).Encode(Response{
				Success: false,
				Message: "Report not found with token: " + token,
			})
		} else {
			json.NewEncoder(w).Encode(Response{
				Success: false,
				Message: "Database error: " + err.Error(),
			})
		}
		return
	}

	report.CreatedAt = createdAt
	_ = evidencePath

	json.NewEncoder(w).Encode(Response{
		Success: true,
		Data:    report,
	})
}

func generateToken() string {
	// Use timestamp + random + nanosecond for uniqueness
	timestamp := time.Now().Format("150405")
	random := strings.ToUpper(fmt.Sprintf("%04d", time.Now().Nanosecond()%10000))
	unique := fmt.Sprintf("%d", time.Now().UnixNano())[10:]
	return fmt.Sprintf("CGS-%s-%s%s", timestamp, random, unique)
}

func formatTimeAgo(t time.Time) string {
	now := time.Now()
	duration := now.Sub(t)

	switch {
	case duration < time.Minute:
		seconds := int(duration.Seconds())
		if seconds <= 1 {
			return "just now"
		}
		return fmt.Sprintf("%d seconds ago", seconds)

	case duration < time.Hour:
		minutes := int(duration.Minutes())
		if minutes == 1 {
			return "1 minute ago"
		}
		return fmt.Sprintf("%d minutes ago", minutes)

	case duration < 24*time.Hour:
		hours := int(duration.Hours())
		if hours == 1 {
			return "1 hour ago"
		}
		return fmt.Sprintf("%d hours ago", hours)

	case duration < 30*24*time.Hour:
		days := int(duration.Hours() / 24)
		if days == 1 {
			return "yesterday"
		} else if days < 7 {
			return fmt.Sprintf("%d days ago", days)
		} else if days < 30 {
			weeks := days / 7
			if weeks == 1 {
				return "1 week ago"
			}
			return fmt.Sprintf("%d weeks ago", weeks)
		}

	case duration < 365*24*time.Hour:
		months := int(duration.Hours() / 24 / 30)
		if months == 1 {
			return "1 month ago"
		}
		return fmt.Sprintf("%d months ago", months)

	default:
		years := int(duration.Hours() / 24 / 365)
		if years == 1 {
			return "1 year ago"
		}
		return fmt.Sprintf("%d years ago", years)
	}

	return "recently"
}
