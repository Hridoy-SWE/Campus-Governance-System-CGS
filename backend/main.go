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

var db *sql.DB

func main() {
	// Initialize database
	if err := initDB(); err != nil {
		log.Fatal("❌ Database initialization failed:", err)
	}
	defer db.Close()

	// Create uploads directory if not exists
	os.MkdirAll("./uploads", 0755)

	// Serve static files (frontend)
	http.Handle("/", http.FileServer(http.Dir("./frontend")))

	// API routes with CORS
	http.HandleFunc("/api/stats", enableCORS(handleStats))
	http.HandleFunc("/api/reports/latest", enableCORS(handleLatestReports))
	http.HandleFunc("/api/report/submit", enableCORS(handleSubmitReport))
	http.HandleFunc("/api/report/track", enableCORS(handleTrackReport))
	http.HandleFunc("/api/reports/all", enableCORS(handleAllReports))

	fmt.Println("🚀 Server running on http://localhost:8080")
	fmt.Println("📁 Database: ./database/campus.db")
	fmt.Println("📂 Frontend: ./frontend/")
	log.Fatal(http.ListenAndServe(":8080", nil))
}

// CORS middleware
func enableCORS(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type")

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
		return fmt.Errorf("failed to check stats: %v", err)
	}

	if statsCount == 0 {
		_, err = db.Exec("INSERT INTO stats (total_reports, verified_reports, pending_reports, resolved_reports) VALUES (0, 0, 0, 0)")
		if err != nil {
			return fmt.Errorf("failed to insert stats: %v", err)
		}
	}

	log.Println("✅ Database ready")
	return nil
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

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    stats,
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
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "Failed to fetch reports",
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

		// Format date
		created, _ := time.Parse("2006-01-02 15:04:05", created_at)
		timeAgo := formatTimeAgo(created)

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

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    reports,
	})
}

func handleAllReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	rows, err := db.Query(`
		SELECT token, category, title, location, status, created_at 
		FROM reports 
		ORDER BY created_at DESC`)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "Failed to fetch reports",
		})
		return
	}
	defer rows.Close()

	var reports []map[string]interface{}
	for rows.Next() {
		var token, category, title, location, status, created_at string
		rows.Scan(&token, &category, &title, &location, &status, &created_at)

		reports = append(reports, map[string]interface{}{
			"token":     token,
			"category":  category,
			"title":     title,
			"location":  location,
			"status":    status,
			"createdAt": created_at,
		})
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    reports,
	})
}

func handleSubmitReport(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	if r.Method != "POST" {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "Method not allowed",
		})
		return
	}

	// Parse form data
	if err := r.ParseMultipartForm(10 << 20); err != nil { // 10 MB max
		if err := r.ParseForm(); err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"message": "Failed to parse form",
			})
			return
		}
	}

	category := r.FormValue("category")
	title := r.FormValue("title")
	description := r.FormValue("description")
	location := r.FormValue("location")

	// Validation
	if category == "" || title == "" || description == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "Category, title and description are required",
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
		filename := fmt.Sprintf("%d_%s", time.Now().Unix(), header.Filename)
		evidencePath = filepath.Join("uploads/evidence", filename)
		fullPath := filepath.Join(".", evidencePath)

		// Create and write file
		out, err := os.Create(fullPath)
		if err == nil {
			defer out.Close()
			io.Copy(out, file)
		}
	}

	// Insert report
	_, err = db.Exec(
		"INSERT INTO reports (token, category, title, description, location, evidence_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')",
		token, category, title, description, location, evidencePath)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "Failed to save report: " + err.Error(),
		})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Report submitted successfully",
		"data": map[string]string{
			"token": token,
		},
	})
}

func handleTrackReport(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	token := r.URL.Query().Get("token")
	if token == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "Token is required",
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
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"message": "Report not found with token: " + token,
			})
		} else {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"message": "Database error: " + err.Error(),
			})
		}
		return
	}

	report.CreatedAt = createdAt
	_ = evidencePath

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    report,
	})
}

func generateToken() string {
	timestamp := time.Now().Format("150405")
	random := strings.ToUpper(fmt.Sprintf("%04d", time.Now().Nanosecond()%10000))
	return fmt.Sprintf("CGS-%s-%s", timestamp, random)
}

func formatTimeAgo(t time.Time) string {
	duration := time.Since(t)

	if duration < time.Minute {
		return "just now"
	} else if duration < time.Hour {
		minutes := int(duration.Minutes())
		return fmt.Sprintf("%d minute%s ago", minutes, plural(minutes))
	} else if duration < 24*time.Hour {
		hours := int(duration.Hours())
		return fmt.Sprintf("%d hour%s ago", hours, plural(hours))
	} else {
		days := int(duration.Hours() / 24)
		return fmt.Sprintf("%d day%s ago", days, plural(days))
	}
}

func plural(n int) string {
	if n == 1 {
		return ""
	}
	return "s"
}
