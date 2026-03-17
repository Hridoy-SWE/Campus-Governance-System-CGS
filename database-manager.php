<?php
// database-manager.php
session_start();

// Simple login (default: admin / admin123)
$admin_user = 'admin';
$admin_pass_hash = '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918'; // admin123

if ($_POST['login'] ?? null) {
    $hash = hash('sha256', $_POST['password']);
    if ($_POST['username'] === $admin_user && $hash === $admin_pass_hash) {
        $_SESSION['db_admin'] = true;
    } else {
        $error = 'Invalid credentials';
    }
}

if ($_GET['logout'] ?? null) {
    session_destroy();
    header('Location: database-manager.php');
    exit;
}

$isLoggedIn = $_SESSION['db_admin'] ?? false;

if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Manager Login</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                width: 350px;
            }
            h1 { color: #333; margin-bottom: 30px; text-align: center; }
            input {
                width: 100%;
                padding: 12px;
                margin: 10px 0;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            button {
                width: 100%;
                padding: 12px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
            .error { color: red; text-align: center; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>📊 Database Manager</h1>
            <?php if ($error) echo "<div class='error'>$error</div>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" value="admin" required>
                <input type="password" name="password" placeholder="Password" value="admin123" required>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Database connection
$db = new SQLite3('../database/campus.db');

// Get all tables
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
$tableList = [];
while ($row = $tables->fetchArray()) {
    $tableList[] = $row['name'];
}

$currentTable = $_GET['table'] ?? 'reports';
$query = "SELECT * FROM $currentTable ORDER BY id DESC LIMIT 100;";
$result = $db->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial; background: #f4f6f9; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .table-btn {
            padding: 10px 20px;
            background: <?= $currentTable ?> ? '#667eea' : '#f0f0f0';
            color: <?= $currentTable ?> ? 'white' : '#333';
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .table-btn:hover { background: #5a67d8; color: white; }
        .results {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        tr:hover { background: #f8f9fa; }
        .logout {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Campus Governance Database Manager</h1>
            <a href="?logout=1" class="logout">Logout</a>
        </div>

        <div class="sidebar">
            <h3>Tables</h3>
            <div class="table-buttons">
                <?php foreach ($tableList as $table): ?>
                    <a href="?table=<?= $table ?>" class="table-btn <?= $currentTable == $table ? 'active' : '' ?>">
                        📁 <?= $table ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="results">
            <h3>Table: <?= $currentTable ?></h3>
            <table>
                <?php
                if ($result) {
                    $first = true;
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        if ($first) {
                            echo "<tr>";
                            foreach (array_keys($row) as $col) {
                                echo "<th>" . htmlspecialchars($col) . "</th>";
                            }
                            echo "</tr>";
                            $first = false;
                        }
                        echo "<tr>";
                        foreach ($row as $value) {
                            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                        }
                        echo "</tr>";
                    }
                }
                ?>
            </table>
        </div>
    </div>
</body>
</html>