<?php
// admin.php - Campus Governance System Admin Panel
session_start();

// Simple authentication (hardcoded for demo)
$admin_username = 'admin';
$admin_password = 'diu@123';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $login_error = 'Invalid username or password';
    }
}

// Check if logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// If not logged in, show login form
if (!$is_logged_in):
// ... rest of login form code ...

<?php
else:
    // If logged in, show admin dashboard
    
    // Database connection - PATH FIXED for frontend folder
$db = new SQLite3(__DIR__ . '/../database/campus.db');    
    // Handle status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $report_id = $_POST['report_id'];
        $new_status = $_POST['status'];
        $stmt = $db->prepare("UPDATE reports SET status = :status WHERE id = :id");
        $stmt->bindValue(':status', $new_status, SQLITE3_TEXT);
        $stmt->bindValue(':id', $report_id, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    // Get statistics
    $stats = $db->querySingle("SELECT * FROM stats WHERE id = 1", true);
    
    // Get all reports
    $reports_result = $db->query("SELECT * FROM reports ORDER BY created_at DESC LIMIT 50");
    $reports = [];
    while ($row = $reports_result->fetchArray(SQLITE3_ASSOC)) {
        $reports[] = $row;
    }
?>