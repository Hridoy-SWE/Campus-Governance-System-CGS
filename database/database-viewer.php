<?php
// database-viewer.php - Simple SQLite Database Viewer
$db = new SQLite3('../database/campus.db');

// Handle queries
$query = $_POST['query'] ?? "SELECT name FROM sqlite_master WHERE type='table';";
$result = $db->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Viewer</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a2e; color: white; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #2d2d44; }
        textarea { width: 100%; height: 100px; background: #2d2d44; color: white; border: 1px solid #666; }
        .btn { background: #8b5cf6; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>📊 SQLite Database Viewer</h1>
    <form method="POST">
        <textarea name="query"><?= htmlspecialchars($query) ?></textarea>
        <button type="submit" class="btn">Run Query</button>
    </form>
    
    <h3>Tables:</h3>
    <?php
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table';");
    while($table = $tables->fetchArray()) {
        echo "<a href='?table={$table['name']}'>📁 {$table['name']}</a> | ";
    }
    ?>
    
    <hr>
    
    <table>
        <?php
        if ($result) {
            $first = true;
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if ($first) {
                    echo "<tr>";
                    foreach (array_keys($row) as $col) {
                        echo "<th>{$col}</th>";
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
</body>
</html>