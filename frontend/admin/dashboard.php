<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$db = new SQLite3('../../database/campus.db');

// Handle Report Deletion
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
    $stmt->bindValue(':id', $deleteId, SQLITE3_INTEGER);
    $stmt->execute();
    header('Location: dashboard.php?msg=deleted');
    exit;
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $reportId = (int)$_POST['report_id'];
    $newStatus = $_POST['status'];
    $stmt = $db->prepare("UPDATE reports SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->bindValue(':status', $newStatus, SQLITE3_TEXT);
    $stmt->bindValue(':id', $reportId, SQLITE3_INTEGER);
    $stmt->execute();
    header('Location: dashboard.php?msg=updated');
    exit;
}

// Handle Edit Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_report'])) {
    $reportId = (int)$_POST['report_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $location = $_POST['location'];
    
    $stmt = $db->prepare("UPDATE reports SET title = :title, description = :description, category = :category, priority = :priority, location = :location, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':category', $category, SQLITE3_TEXT);
    $stmt->bindValue(':priority', $priority, SQLITE3_TEXT);
    $stmt->bindValue(':location', $location, SQLITE3_TEXT);
    $stmt->bindValue(':id', $reportId, SQLITE3_INTEGER);
    $stmt->execute();
    header('Location: dashboard.php?msg=updated');
    exit;
}

// Get statistics
$statsQuery = $db->query("SELECT COUNT(*) as total FROM reports");
$totalReports = $statsQuery->fetchArray()['total'];

$pendingQuery = $db->query("SELECT COUNT(*) as pending FROM reports WHERE status = 'pending'");
$pendingReports = $pendingQuery->fetchArray()['pending'];

$resolvedQuery = $db->query("SELECT COUNT(*) as resolved FROM reports WHERE status = 'resolved'");
$resolvedReports = $resolvedQuery->fetchArray()['resolved'];

$inProgressQuery = $db->query("SELECT COUNT(*) as in_progress FROM reports WHERE status = 'in_progress'");
$inProgressReports = $inProgressQuery->fetchArray()['in_progress'];

// Get all reports
$reportsQuery = $db->query("SELECT * FROM reports ORDER BY created_at DESC");
$reports = [];
while ($row = $reportsQuery->fetchArray(SQLITE3_ASSOC)) {
    $reports[] = $row;
}

$msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $msg = 'Report deleted successfully!';
    if ($_GET['msg'] == 'updated') $msg = 'Report updated successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Campus Governance System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 1.3rem; font-weight: 700; }
        .sidebar-header h2 span { color: #38bdf8; }
        .sidebar-header p { font-size: 0.75rem; opacity: 0.7; margin-top: 5px; }
        .sidebar-nav { padding: 20px 0; }
        .nav-item {
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .nav-item:hover, .nav-item.active { background: rgba(56,189,248,0.1); color: #38bdf8; border-left-color: #38bdf8; }
        .nav-item i { width: 20px; font-size: 1.1rem; }
        .main-content { flex: 1; margin-left: 280px; padding: 20px 30px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .page-title h1 { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .page-title p { color: #64748b; font-size: 0.9rem; margin-top: 5px; }
        .header-actions { display: flex; align-items: center; gap: 15px; }
        .back-home-btn, .logout-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .back-home-btn { background: #38bdf8; color: white; }
        .back-home-btn:hover { background: #0284c7; transform: translateY(-2px); }
        .logout-btn { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .logout-btn:hover { transform: translateY(-2px); }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-info h3 { font-size: 2rem; font-weight: 700; color: #1e293b; }
        .stat-info p { color: #64748b; font-size: 0.85rem; margin-top: 5px; }
        .stat-icon { width: 50px; height: 50px; background: linear-gradient(135deg, #38bdf8, #0284c7); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; }
        .chart-card h3 { margin-bottom: 20px; color: #1e293b; font-size: 1.1rem; }
        .reports-section { background: white; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; }
        .reports-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .search-box { padding: 8px 15px; border: 1px solid #e2e8f0; border-radius: 8px; width: 250px; }
        .reports-table { width: 100%; border-collapse: collapse; }
        .reports-table th { text-align: left; padding: 12px; background: #f8fafc; color: #475569; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        .reports-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; color: #334155; }
        .reports-table tr:hover { background: #f8fafc; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-resolved { background: #d1fae5; color: #059669; }
        .priority-high, .priority-medium, .priority-low { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #d1fae5; color: #059669; }
        .action-btns { display: flex; gap: 8px; }
        .edit-btn, .delete-btn { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.75rem; transition: all 0.3s ease; }
        .edit-btn { background: #38bdf8; color: white; }
        .edit-btn:hover { background: #0284c7; }
        .delete-btn { background: #ef4444; color: white; }
        .delete-btn:hover { background: #dc2626; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 25px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #475569; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        .success-msg { background: #d1fae5; color: #059669; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); z-index: 100; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>CAMPUS <span>GOVERNANCE</span></h2>
                <p>Admin Dashboard</p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="#" class="nav-item" onclick="location.reload()"><i class="fas fa-flag"></i> Reports</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-header">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Manage and monitor all campus reports</p>
                </div>
                <div class="header-actions">
                    <a href="../index.html" class="back-home-btn"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <?php if ($msg): ?>
            <div class="success-msg">
                <span>✅ <?= $msg ?></span>
                <button onclick="this.parentElement.style.display='none'" style="background:none;border:none;font-size:1.2rem;cursor:pointer;">×</button>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-info"><h3><?= $totalReports ?></h3><p>Total Reports</p></div><div class="stat-icon"><i class="fas fa-flag"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3><?= $pendingReports ?></h3><p>Pending</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3><?= $inProgressReports ?></h3><p>In Progress</p></div><div class="stat-icon"><i class="fas fa-spinner"></i></div></div>
                <div class="stat-card"><div class="stat-info"><h3><?= $resolvedReports ?></h3><p>Resolved</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
            </div>

            <div class="charts-row">
                <div class="chart-card"><h3>Reports by Status</h3><canvas id="statusChart"></canvas></div>
                <div class="chart-card"><h3>Reports by Category</h3><canvas id="categoryChart"></canvas></div>
            </div>

            <div class="reports-section">
                <div class="reports-header">
                    <h3>📋 All Reports</h3>
                    <input type="text" class="search-box" id="searchInput" placeholder="🔍 Search reports...">
                </div>
                <div style="overflow-x: auto;">
                    <table class="reports-table">
                        <thead><tr><th>ID</th><th>Token</th><th>Category</th><th>Title</th><th>Priority</th><th>Status</th><th>Location</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?= $report['id'] ?></td>
                                <td><code><?= htmlspecialchars(substr($report['token'], 0, 15)) ?>...</code></td>
                                <td><?= ucfirst($report['category'] ?? 'General') ?></td>
                                <td><?= htmlspecialchars(substr($report['title'], 0, 30)) ?>...</td>
                                <td><span class="priority-<?= $report['priority'] ?? 'medium' ?>"><?= ucfirst($report['priority'] ?? 'Medium') ?></span></td>
                                <td>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" class="status-badge status-<?= $report['status'] ?? 'pending' ?>" style="padding: 4px 8px;">
                                            <option value="pending" <?= ($report['status'] ?? 'pending') == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="in_progress" <?= ($report['status'] ?? '') == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="resolved" <?= ($report['status'] ?? '') == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td><?= htmlspecialchars(substr($report['location'] ?? 'N/A', 0, 20)) ?></td>
                                <td><?= date('M d, Y', strtotime($report['created_at'])) ?></td>
                                <td class="action-btns">
                                    <button class="edit-btn" onclick='editReport(<?= $report['id'] ?>, <?= json_encode($report['title']) ?>, <?= json_encode($report['description'] ?? '') ?>, <?= json_encode($report['category']) ?>, <?= json_encode($report['priority'] ?? 'medium') ?>, <?= json_encode($report['location'] ?? '') ?>)'>Edit</button>
                                    <button class="delete-btn" onclick="deleteReport(<?= $report['id'] ?>)">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit Report</h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
            <form method="POST">
                <input type="hidden" name="report_id" id="edit_report_id">
                <div class="form-group"><label>Title</label><input type="text" name="title" id="edit_title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="edit_description" rows="4" required></textarea></div>
                <div class="form-group"><label>Category</label><select name="category" id="edit_category"><option value="academic">Academic</option><option value="facility">Facility</option><option value="transport">Transport</option><option value="security">Security</option><option value="it">IT Services</option><option value="admin">Administrative</option></select></div>
                <div class="form-group"><label>Priority</label><select name="priority" id="edit_priority"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                <div class="form-group"><label>Location</label><input type="text" name="location" id="edit_location"></div>
                <button type="submit" name="edit_report" class="edit-btn" style="width:100%;padding:12px;">Update Report</button>
            </form>
        </div>
    </div>

    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header"><h3>Delete Report</h3><button class="modal-close" onclick="closeDeleteModal()">&times;</button></div>
            <p>Are you sure you want to delete this report? This action cannot be undone.</p>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="confirmDelete()" class="delete-btn" style="flex:1;">Yes, Delete</button>
                <button onclick="closeDeleteModal()" class="edit-btn" style="flex:1;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const ctx1 = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: { labels: ['Pending', 'In Progress', 'Resolved'], datasets: [{ data: [<?= $pendingReports ?>, <?= $inProgressReports ?>, <?= $resolvedReports ?>], backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'], borderWidth: 0 }] }
        });

        const ctx2 = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: { labels: ['Academic', 'Facility', 'Security', 'IT', 'Transport'], datasets: [{ label: 'Reports', data: [12, 18, 8, 15, 6], backgroundColor: '#38bdf8', borderRadius: 8 }] }
        });

        document.getElementById('searchInput').addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('.reports-table tbody tr');
            rows.forEach(row => { row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none'; });
        });

        function editReport(id, title, description, category, priority, location) {
            document.getElementById('edit_report_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_priority').value = priority;
            document.getElementById('edit_location').value = location;
            document.getElementById('editModal').classList.add('show');
        }

        let deleteId = null;
        function deleteReport(id) { deleteId = id; document.getElementById('deleteModal').classList.add('show'); }
        function confirmDelete() { if (deleteId) window.location.href = `?delete_id=${deleteId}`; }
        function closeModal() { document.getElementById('editModal').classList.remove('show'); }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('show'); }
    </script>
</body>
</html>