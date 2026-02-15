package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
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
	initDB()
	defer db.Close()

	os.MkdirAll("./uploads", 0755)

	// Serve static files
	http.Handle("/", http.FileServer(http.Dir("./")))

	// API routes
	http.HandleFunc("/api/stats", handleStats)
	http.HandleFunc("/api/reports/latest", handleLatestReports)
	http.HandleFunc("/api/report/submit", handleSubmitReport)
	http.HandleFunc("/api/report/track", handleTrackReport)

	fmt.Println("🚀 Server running on http://localhost:8080")
	log.Fatal(http.ListenAndServe(":8080", nil))
}

func initDB() {
	var err error
	db, err = sql.Open("sqlite3", "./database/campus.db")
	if err != nil {
		log.Fatal("Database connection failed:", err)
	}

	schema, err := os.ReadFile("./database/schema.sql")
	if err != nil {
		log.Fatal("Schema file not found:", err)
	}

	_, err = db.Exec(string(schema))
	if err != nil {
		log.Fatal("Schema execution failed:", err)
	}

	fmt.Println("✅ Database ready")
}

func handleStats(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	var stats Stats
	err := db.QueryRow("SELECT total_reports, verified_reports, pending_reports, resolved_reports FROM stats WHERE id = 1").Scan(
		&stats.TotalReports, &stats.VerifiedReports, &stats.PendingReports, &stats.ResolvedReports)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Stats not found"})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "data": stats})
}

func handleLatestReports(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	rows, err := db.Query("SELECT token, category, title, location, status, created_at FROM reports ORDER BY created_at DESC LIMIT 10")
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Failed to fetch"})
		return
	}
	defer rows.Close()

	var reports []map[string]interface{}
	for rows.Next() {
		var token, category, title, location, status, created_at string
		rows.Scan(&token, &category, &title, &location, &status, &created_at)

		// Mask token
		parts := strings.Split(token, "-")
		maskedToken := token
		if len(parts) == 3 {
			maskedToken = parts[0] + "-" + parts[1][:4] + "..."
		}

		reports = append(reports, map[string]interface{}{
			"token":    maskedToken,
			"category": category,
			"title":    title,
			"location": location,
			"status":   status,
			"date":     created_at,
		})
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "data": reports})
}

func handleSubmitReport(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	r.ParseForm()

	category := r.FormValue("category")
	title := r.FormValue("title")
	description := r.FormValue("description")
	location := r.FormValue("location")

	if category == "" || title == "" || description == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "All fields required"})
		return
	}

	// Generate token
	token := fmt.Sprintf("CGS-%s-%s",
		time.Now().Format("1504"),
		strings.ToUpper(time.Now().Format("05")+fmt.Sprint(time.Now().Nanosecond())[:2]))

	// Insert report
	_, err := db.Exec(
		"INSERT INTO reports (token, category, title, description, location, status) VALUES (?, ?, ?, ?, ?, 'pending')",
		token, category, title, description, location)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Failed to save"})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Report submitted",
		"data":    map[string]string{"token": token},
	})
}

func handleTrackReport(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	token := r.URL.Query().Get("token")
	if token == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Token required"})
		return
	}

	var id int
	var category, title, description, location, status, created_at string

	err := db.QueryRow("SELECT id, category, title, description, location, status, created_at FROM reports WHERE token = ?", token).Scan(
		&id, &category, &title, &description, &location, &status, &created_at)

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "message": "Report not found"})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data": map[string]interface{}{
			"token":       token,
			"category":    category,
			"title":       title,
			"description": description,
			"location":    location,
			"status":      status,
			"created_at":  created_at,
		},
	})
}
