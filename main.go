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
    ID          int       `json:"id"`
    Token       string    `json:"token"`
    Category    string    `json:"category"`
    Title       string    `json:"title"`
    Description string    `json:"description"`
    Location    string    `json:"location"`
    Status      string    `json:"status"`
    CreatedAt   time.Time `json:"created_at"`
}

type Stats struct {
    TotalReports    int    `json:"total_reports"`
    VerifiedReports int    `json:"verified_reports"`
    PendingReports  int    `json:"pending_reports"`
    ResolvedReports int    `json:"resolved_reports"`
    UpdatedAt       string `json:"updated_at"`
}

type APIResponse struct {
    Success bool        `json:"success"`
    Message string      `json:"message,omitempty"`
    Data    interface{} `json:"data,omitempty"`
}

var db *sql.DB

func main() {
    initDB()
    defer db.Close()

    os.MkdirAll("./uploads", 0755)

    fs := http.FileServer(http.Dir("./"))
    http.Handle("/", fs)

    http.HandleFunc("/api/stats", handleStats)
    http.HandleFunc("/api/reports", handleReports)
    http.HandleFunc("/api/report/submit", handleSubmitReport)
    http.HandleFunc("/api/report/track", handleTrackReport)
    http.HandleFunc("/api/reports/latest", handleLatestReports)

    port := "8080"
    fmt.Printf("🚀 Server starting on http://localhost:%s\n", port)
    fmt.Printf("📊 Database: SQLite (campus.db) - EMPTY (0 reports)\n")
    log.Fatal(http.ListenAndServe(":"+port, nil))
}

func initDB() {
    var err error
    db, err = sql.Open("sqlite3", "./database/campus.db")
    if err != nil {
        log.Fatal("Failed to connect to database:", err)
    }

    schema, err := os.ReadFile("./database/schema.sql")
    if err != nil {
        log.Fatal("Failed to read schema file:", err)
    }

    _, err = db.Exec(string(schema))
    if err != nil {
        log.Fatal("Failed to create tables:", err)
    }

    fmt.Println("✅ Database initialized successfully with ZERO data")
}

func generateToken() string {
    timestamp := time.Now().Format("150405")
    random := fmt.Sprintf("%06d", time.Now().Nanosecond()%1000000)
    return fmt.Sprintf("CGS-%s-%s", timestamp, random[:4])
}

func handleStats(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.Header().Set("Access-Control-Allow-Origin", "*")

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
}
func handleLatestReports(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.Header().Set("Access-Control-Allow-Origin", "*")

    rows, err := db.Query(`
        SELECT id, token, category, title, description, location, status, created_at 
        FROM reports ORDER BY created_at DESC LIMIT 10
    `)
    if err != nil {
        json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Failed to fetch reports"})
        return
    }
    defer rows.Close()

    var reports []Report
    for rows.Next() {
        var r Report
        rows.Scan(&r.ID, &r.Token, &r.Category, &r.Title, &r.Description, &r.Location, &r.Status, &r.CreatedAt)
        parts := strings.Split(r.Token, "-")
        if len(parts) == 3 {
            r.Token = parts[0] + "-" + parts[1][:4] + "..."
        }
        reports = append(reports, r)
    }

    json.NewEncoder(w).Encode(APIResponse{Success: true, Data: reports})
}

func handleReports(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.Header().Set("Access-Control-Allow-Origin", "*")

    category := r.URL.Query().Get("category")
    status := r.URL.Query().Get("status")
    search := r.URL.Query().Get("search")

    query := `SELECT id, token, category, title, description, location, status, created_at FROM reports WHERE 1=1`
    args := []interface{}{}

    if category != "" && category != "all" {
        query += " AND category = ?"
        args = append(args, category)
    }
    if status != "" {
        query += " AND status = ?"
        args = append(args, status)
    }
    if search != "" {
        query += " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)"
        searchTerm := "%" + search + "%"
        args = append(args, searchTerm, searchTerm, searchTerm)
    }

    query += " ORDER BY created_at DESC LIMIT 20"

    rows, err := db.Query(query, args...)
    if err != nil {
        json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Failed to fetch reports"})
        return
    }
    defer rows.Close()

    var reports []Report
    for rows.Next() {
        var r Report
        rows.Scan(&r.ID, &r.Token, &r.Category, &r.Title, &r.Description, &r.Location, &r.Status, &r.CreatedAt)
        reports = append(reports, r)
    }

    json.NewEncoder(w).Encode(APIResponse{Success: true, Data: reports})
}

func handleSubmitReport(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.Header().Set("Access-Control-Allow-Origin", "*")

    if r.Method != http.MethodPost {
        http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
        return
    }

    r.ParseMultipartForm(10 << 20)

    category := r.FormValue("category")
    title := r.FormValue("title")
    description := r.FormValue("description")
    location := r.FormValue("location")

    if category == "" || title == "" || description == "" {
        json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Category, title, and description are required"})
        return
    }

    token := generateToken()

    result, err := db.Exec(`
        INSERT INTO reports (token, category, title, description, location, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    `, token, category, title, description, location)

    if err != nil {
        json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Failed to save report: " + err.Error()})
        return
    }

    id, _ := result.LastInsertId()

    json.NewEncoder(w).Encode(APIResponse{
        Success: true,
        Message: "Report submitted successfully",
        Data: map[string]interface{}{"id": id, "token": token},
    })
}

func handleTrackReport(w http.ResponseWriter, r *http.Request) {
    w.Header().Set("Content-Type", "application/json")
    w.Header().Set("Access-Control-Allow-Origin", "*")

    token := r.URL.Query().Get("token")
    if token == "" {
        json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Token is required"})
        return
    }

    var report Report
    err := db.QueryRow(`
        SELECT id, token, category, title, description, location, status, created_at 
        FROM reports WHERE token = ?
    `, token).Scan(&report.ID, &report.Token, &report.Category, &report.Title,
        &report.Description, &report.Location, &report.Status, &report.CreatedAt)

    if err != nil {
        if err == sql.ErrNoRows {
            json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Report not found"})
        } else {
            json.NewEncoder(w).Encode(APIResponse{Success: false, Message: "Database error"})
        }
        return
    }

    json.NewEncoder(w).Encode(APIResponse{Success: true, Data: report})
}
