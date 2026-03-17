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

// email notification function
func sendStatusUpdateEmail(email, token, title, status string) {
	fmt.Printf("📧 Email would be sent to: %s\n", email)
	fmt.Printf("   Subject: Report %s status updated to %s\n", token, status)
	fmt.Printf("   Title: %s\n", title)
	// SMTP code will be added later
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

	// Update stats with current data
	updateStats()

	log.Println("✅ Database ready")
	return nil
}

// Helper function to update stats
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

<<<<<<< HEAD
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
=======
    var total, verified, pending, resolved int
    
    // First check if stats table exists and has data
    err := db.QueryRow("SELECT total_reports, verified_reports, pending_reports, resolved_reports FROM stats WHERE id = 1").Scan(
        &total, &verified, &pending, &resolved)
    
    if err != nil {
        // If error, try to create stats table and insert default
        db.Exec("CREATE TABLE IF NOT EXISTS stats (id INTEGER PRIMARY KEY, total_reports INTEGER DEFAULT 0, verified_reports INTEGER DEFAULT 0, pending_reports INTEGER DEFAULT 0, resolved_reports INTEGER DEFAULT 0)")
        db.Exec("INSERT OR IGNORE INTO stats (id, total_reports, verified_reports, pending_reports, resolved_reports) VALUES (1, 0, 0, 0, 0)")
        
        // Try again
        err = db.QueryRow("SELECT total_reports, verified_reports, pending_reports, resolved_reports FROM stats WHERE id = 1").Scan(
            &total, &verified, &pending, &resolved)
        
        if err != nil {
            json.NewEncoder(w).Encode(map[string]interface{}{
                "success": false,
                "message": "Database error: " + err.Error(),
            })
            return
        }
    }

    json.NewEncoder(w).Encode(map[string]interface{}{
        "success": true,
        "data": map[string]int{
            "total_reports":    total,
            "verified_reports": verified,
            "pending_reports":  pending,
            "resolved_reports": resolved,
        },
    })
>>>>>>> eecc6b6d2ecf299c380c2b5e9a6c56046d8fcd75
}
func handleLatestReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	rows, err := db.Query(`
        SELECT token, category, title, location, status, created_at 
        FROM reports 
        ORDER BY created_at DESC 
        LIMIT 10`)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Failed to fetch reports"})
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

		// Parse the date properly
		var timeAgo string
		parsedTime, err := time.Parse("2006-01-02 15:04:05", created_at)
		if err != nil {
			// Try alternative format
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

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    reports,
	})
}

func handleAllReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	rows, err := db.Query(`
        SELECT id, token, category, title, description, location, status, 
               strftime('%Y-%m-%d %H:%M:%S', created_at) as created_at 
        FROM reports 
        ORDER BY created_at DESC
    `)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": err.Error(),
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

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    reports,
	})
}

// Token generation with uniqueness check
func generateToken() string {
	for {
		timestamp := time.Now().Format("150405")
		random := fmt.Sprintf("%04d", time.Now().Nanosecond()%10000)
		unique := fmt.Sprintf("%d", time.Now().UnixNano())[10:]
		token := fmt.Sprintf("CGS-%s-%s%s", timestamp, random, unique)

		var count int
		err := db.QueryRow("SELECT COUNT(*) FROM reports WHERE token = ?", token).Scan(&count)
		if err != nil {
			continue
		}
		if count == 0 {
			return token
		}
		time.Sleep(1 * time.Microsecond)
	}
}

// Time ago formatter
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
	contactEmail := r.FormValue("contact_email")

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

	// Insert token into tokens table if email provided
	if contactEmail != "" {
		_, err := db.Exec(
			"INSERT INTO tokens (token, email) VALUES (?, ?)",
			token, contactEmail)
		if err != nil {
			// Token might already exist, try with different token
			token = generateToken()
			db.Exec("INSERT INTO tokens (token, email) VALUES (?, ?)", token, contactEmail)
		}
	} else {
		db.Exec("INSERT INTO tokens (token) VALUES (?)", token)
	}

	// Handle file upload if any
	var evidencePath string
	file, header, err := r.FormFile("evidence")
	if err == nil {
		defer file.Close()

		evidenceDir := "./uploads/evidence"
		os.MkdirAll(evidenceDir, 0755)

		filename := fmt.Sprintf("%d_%s", time.Now().Unix(), header.Filename)
		evidencePath = filepath.Join("uploads/evidence", filename)
		fullPath := filepath.Join(".", evidencePath)

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

	// Send email notification if email provided
	if contactEmail != "" {
		sendStatusUpdateEmail(contactEmail, token, title, "pending")
	}

	// Update stats after successful insert
	updateStats()

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

<<<<<<< HEAD
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
=======
    json.NewEncoder(w).Encode(APIResponse{Success: true, Data: report})
>>>>>>> eecc6b6d2ecf299c380c2b5e9a6c56046d8fcd75
}
