<?php
session_start();

// Simple authentication for demo
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // In production, validate against database
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Demo credentials
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = 'Admin User';
        $_SESSION['user_id'] = 1;
        header('Location: dashboard.php');
        exit;
    } elseif ($username === 'faculty' && $password === 'faculty123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_role'] = 'faculty';
        $_SESSION['user_name'] = 'Dr. Rahman';
        $_SESSION['user_id'] = 2;
        $_SESSION['department_id'] = 3;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// If not logged in, show login page
if (!$isLoggedIn):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CGS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: var(--accent-primary);
            font-size: 2rem;
        }
        .demo-credentials {
            background: var(--bg-surface);
            padding: 15px;
            border-radius: var(--radius);
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .demo-credentials p {
            margin: 5px 0;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🏛️ CGS Admin</h1>
            <p>Campus Governance System</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: rgba(239,68,68,0.1); color: var(--accent-danger); padding: 10px; border-radius: var(--radius); margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" class="submit-btn">Login</button>
        </form>
        
        <div class="demo-credentials">
            <p><strong>Demo Credentials:</strong></p>
            <p>👑 Admin: admin / admin123</p>
            <p>👨‍🏫 Faculty: faculty / faculty123</p>
        </div>
    </div>
</body>
</html>
<?php
    exit;
endif;

// If logged in, redirect to dashboard
header('Location: dashboard.php');
exit;
?>