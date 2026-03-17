<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CGS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-wrapper { display: flex; }
        .admin-sidebar {
            width: 260px;
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            min-height: 100vh;
            position: fixed;
        }
        .admin-main {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 24px;
            color: var(--text-secondary);
            text-decoration: none;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--bg-surface);
            color: var(--accent-primary);
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2>CGS <span style="color: var(--accent-primary);">Admin</span></h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="reports.php"><i class="fas fa-flag"></i> Reports</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php" style="color: var(--accent-danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <div>Welcome, <?= htmlspecialchars($userName) ?>!</div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" id="statsContainer">
                <div class="stat-card">
                    <div class="stat-value" id="totalReports">0</div>
                    <div class="stat-label">Total Reports</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="pendingReports">0</div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="resolvedReports">0</div>
                    <div class="stat-label">Resolved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="verifiedReports">0</div>
                    <div class="stat-label">Verified</div>
                </div>
            </div>

            <script>
                const API_BASE = 'http://localhost:8080/api';

                async function fetchStats() {
                    try {
                        const response = await fetch(`${API_BASE}/stats`);
                        const data = await response.json();
                        
                        if (data.success) {
                            document.getElementById('totalReports').textContent = data.data.total_reports || 0;
                            document.getElementById('pendingReports').textContent = data.data.pending_reports || 0;
                            document.getElementById('resolvedReports').textContent = data.data.resolved_reports || 0;
                            document.getElementById('verifiedReports').textContent = data.data.verified_reports || 0;
                        }
                    } catch (error) {
                        console.error('Failed to fetch stats:', error);
                    }
                }

                fetchStats();
                setInterval(fetchStats, 30000);
            </script>
        </main>
    </div>
</body>
</html>