<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Demo credentials
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = 'Admin User';
        $_SESSION['user_id'] = 1;
        
        // IMPORTANT: Redirect to dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CGS</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
/* Add these enhanced styles to login.php */
.login-container {
    background: rgba(20, 20, 40, 0.7);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.login-header h1 {
    background: linear-gradient(135deg, #fff, #a5b4fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2.2rem;
}

.form-group input {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
    transition: all 0.3s ease;
}

.form-group input:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
    outline: none;
}

.submit-btn {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    position: relative;
    overflow: hidden;
}

.submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.submit-btn:hover::before {
    left: 100%;
}

.demo-info {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.demo-info code {
    background: rgba(0, 0, 0, 0.3);
    padding: 4px 8px;
    border-radius: 6px;
    color: #fbbf24;
}


        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg-dark);
        }
        .login-container {
            max-width: 400px;
            width: 90%;
            padding: 40px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-modal);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: var(--accent-primary);
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            color: var(--text-primary);
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--accent-primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .submit-btn:hover {
            background: var(--accent-primary-dark);
        }
        .error {
            color: var(--accent-danger);
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: var(--radius);
        }
        .demo-info {
            margin-top: 20px;
            padding: 15px;
            background: var(--bg-surface);
            border-radius: var(--radius);
            text-align: center;
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
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">Login</button>
        </form>
        
        <div class="demo-info">
            <p><strong>Demo Credentials:</strong></p>
            <p>Username: <code>admin</code></p>
            <p>Password: <code>admin123</code></p>
        </div>
    </div>
</body>
</html>