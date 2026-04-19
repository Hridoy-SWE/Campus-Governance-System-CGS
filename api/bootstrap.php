<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('CGS_ROOT', dirname(__DIR__));
define('CGS_DB_PATH', CGS_ROOT . '/database/campus.db');
define('CGS_SCHEMA_PATH', CGS_ROOT . '/database/schema.sql');
define('CGS_UPLOAD_DIR', CGS_ROOT . '/backend/uploads');

function cgs_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(dirname(CGS_DB_PATH))) {
        mkdir(dirname(CGS_DB_PATH), 0777, true);
    }

    if (!is_dir(CGS_UPLOAD_DIR)) {
        mkdir(CGS_UPLOAD_DIR, 0777, true);
    }

    $pdo = new PDO('sqlite:' . CGS_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    cgs_initialize_database($pdo);
    return $pdo;
}

function cgs_initialize_database(PDO $db): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $schemaSql = file_get_contents(CGS_SCHEMA_PATH);
    if ($schemaSql === false) {
        throw new RuntimeException('Failed to read database schema.');
    }
    $db->exec($schemaSql);

    // Ensure profile_image column exists for API/profile consistency.
    try {
        $columns = $db->query("PRAGMA table_info(users)")->fetchAll() ?: [];
        $hasProfileImage = false;

        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'profile_image') {
                $hasProfileImage = true;
                break;
            }
        }

        if (!$hasProfileImage) {
            $db->exec("ALTER TABLE users ADD COLUMN profile_image TEXT");
        }
    } catch (Throwable $e) {
        // Keep project usable even if migration cannot run on an already-modified DB.
    }

    $count = (int)$db->query('SELECT COUNT(*) FROM departments')->fetchColumn();
    if ($count === 0) {
        $stmt = $db->prepare('INSERT INTO departments (code, name, email, phone) VALUES (?, ?, ?, ?)');
        foreach ([
            ['ADMIN', 'Administration', 'admin@university.edu', '+8801000000001'],
            ['CSE', 'Computer Science and Engineering', 'cse@university.edu', '+8801000000002'],
            ['EEE', 'Electrical and Electronic Engineering', 'eee@university.edu', '+8801000000003'],
            ['BBA', 'Business Administration', 'bba@university.edu', '+8801000000004'],
            ['ARCH', 'Architecture', 'architecture@university.edu', '+8801000000005'],
        ] as $row) {
            $stmt->execute($row);
        }
    }

    $deptMap = [];
    foreach ($db->query('SELECT id, code FROM departments') as $row) {
        $deptMap[$row['code']] = (int)$row['id'];
    }

    $users = [
        ['admin', 'admin@university.edu', 'Admin User', 'admin', $deptMap['ADMIN'] ?? null, '+8801700000001', 'admin12345'],
        ['faculty', 'faculty@university.edu', 'Dr. Rahman', 'faculty', $deptMap['CSE'] ?? null, '+8801700000002', 'faculty12345'],
        ['student', 'student@university.edu', 'Rahim Khan', 'student', $deptMap['CSE'] ?? null, '+8801700000003', 'student12345'],
    ];

    $findUser = $db->prepare('SELECT id FROM users WHERE lower(email) = lower(?) OR lower(username) = lower(?) LIMIT 1');
    $insertUser = $db->prepare('
        INSERT INTO users (username, email, password_hash, full_name, role, department_id, phone, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ');
    $updateUser = $db->prepare('
        UPDATE users
        SET username = ?, email = ?, password_hash = ?, full_name = ?, role = ?, department_id = ?, phone = ?, is_active = 1
        WHERE id = ?
    ');

    foreach ($users as [$username, $email, $name, $role, $departmentId, $phone, $plainPassword]) {
        $findUser->execute([$email, $username]);
        $existingId = $findUser->fetchColumn();
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

        if ($existingId) {
            $updateUser->execute([
                $username,
                $email,
                $passwordHash,
                $name,
                $role,
                $departmentId,
                $phone,
                (int)$existingId
            ]);
        } else {
            $insertUser->execute([
                $username,
                $email,
                $passwordHash,
                $name,
                $role,
                $departmentId,
                $phone
            ]);
        }
    }

    $rules = [
        ['duplicate_title', 'text_match', 'duplicate-title', 20],
        ['rapid_repeat_submit', 'rate_limit', '5_per_minute', 40],
        ['blocked_keywords', 'keyword', 'spam scam fraud test', 30],
        ['empty_or_short_content', 'length', 'min_20', 15],
    ];

    $insertRule = $db->prepare('
        INSERT OR IGNORE INTO spam_rules (rule_name, rule_type, rule_value, score, is_active)
        VALUES (?, ?, ?, ?, 1)
    ');
    foreach ($rules as $rule) {
        $insertRule->execute($rule);
    }

    $reportCount = (int)$db->query('SELECT COUNT(*) FROM reports')->fetchColumn();
    if ($reportCount === 0) {
        $studentId = (int)$db->query("SELECT id FROM users WHERE username='student' LIMIT 1")->fetchColumn();
        $facultyId = (int)$db->query("SELECT id FROM users WHERE username='faculty' LIMIT 1")->fetchColumn();
        $deptId = $deptMap['CSE'] ?? null;

        $tokenInsert = $db->prepare('INSERT INTO tokens (token, email, phone, is_active) VALUES (?, ?, ?, 1)');
        $reportInsert = $db->prepare('
            INSERT INTO reports (
                token_id, user_id, department_id, category, title, description,
                location, priority, status, assigned_to, is_anonymous, notify_email,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $historyInsert = $db->prepare('
            INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $seedReports = [
            ['CGS-DEMO-0001', 'student@university.edu', '+8801700000003', 'Facility', 'Broken classroom projector', 'Projector in room CSE-402 is not working during lectures.', 'CSE Building Room 402', 'high', 'pending', null, 0, '2026-04-17 10:00:00'],
            ['CGS-DEMO-0002', 'student@university.edu', '+8801700000003', 'Safety', 'Stairs lighting issue', 'Lights near the main staircase are flickering and unsafe at night.', 'Main Academic Building', 'medium', 'in_progress', $facultyId ?: null, 1, '2026-04-16 18:30:00'],
            ['CGS-DEMO-0003', 'student@university.edu', '+8801700000003', 'Network', 'Slow lab internet connection', 'The internet connection in the software lab becomes very slow in the afternoon.', 'Software Lab 2', 'low', 'resolved', $facultyId ?: null, 1, '2026-04-15 14:15:00'],
        ];

        foreach ($seedReports as [$token, $email, $phone, $category, $title, $description, $location, $priority, $status, $assignedTo, $anon, $createdAt]) {
            $tokenInsert->execute([$token, $email, $phone]);
            $tokenId = (int)$db->lastInsertId();

            $reportInsert->execute([
                $tokenId,
                $studentId ?: null,
                $deptId,
                $category,
                $title,
                $description,
                $location,
                $priority,
                $status,
                $assignedTo,
                $anon,
                $email,
                $createdAt,
                $createdAt
            ]);

            $reportId = (int)$db->lastInsertId();

            $historyInsert->execute([
                $reportId,
                null,
                'pending',
                $studentId ?: null,
                'Report submitted',
                $createdAt
            ]);

            if ($status !== 'pending') {
                $historyInsert->execute([
                    $reportId,
                    'pending',
                    $status,
                    $facultyId ?: null,
                    'Updated by administration',
                    date('Y-m-d H:i:s', strtotime($createdAt . ' +6 hours'))
                ]);
            }
        }
    }

    cgs_refresh_stats($db);
}

function cgs_refresh_stats(PDO $db): void {
    $db->exec("INSERT INTO stats (id) VALUES (1) ON CONFLICT(id) DO NOTHING");
    $db->exec("UPDATE stats SET
        total_reports=(SELECT COUNT(*) FROM reports),
        pending_reports=(SELECT COUNT(*) FROM reports WHERE status='pending'),
        verified_reports=(SELECT COUNT(*) FROM reports WHERE status='verified'),
        in_progress_reports=(SELECT COUNT(*) FROM reports WHERE status='in_progress'),
        resolved_reports=(SELECT COUNT(*) FROM reports WHERE status='resolved'),
        spam_reports=(SELECT COUNT(*) FROM reports WHERE status='spam'),
        critical_reports=(SELECT COUNT(*) FROM reports WHERE priority='critical'),
        total_users=(SELECT COUNT(*) FROM users),
        active_users=(SELECT COUNT(*) FROM users WHERE is_active=1),
        total_departments=(SELECT COUNT(*) FROM departments WHERE is_active=1),
        avg_response_time=COALESCE((SELECT ROUND(AVG((julianday(resolved_at)-julianday(created_at))*24.0), 2) FROM reports WHERE resolved_at IS NOT NULL),0),
        updated_at=CURRENT_TIMESTAMP
        WHERE id=1");
}

function cgs_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cgs_success($data = null, string $message = ''): void {
    $payload = ['success' => true];
    if ($message !== '') {
        $payload['message'] = $message;
    }
    if ($data !== null) {
        $payload['data'] = $data;
    }
    cgs_json($payload, 200);
}

function cgs_error(string $message, int $status = 400, $errors = null): void {
    $payload = ['success' => false, 'message' => $message];
    if ($errors !== null) {
        $payload['errors'] = $errors;
    }
    cgs_json($payload, $status);
}

function cgs_read_json(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        cgs_error('Invalid JSON body', 400);
    }

    return $data;
}

function cgs_current_user(?PDO $db = null): ?array {
    $db = $db ?: cgs_db();
    $token = trim((string)($_COOKIE['cgs_session'] ?? ''));

    if ($token === '') {
        return null;
    }

    $stmt = $db->prepare("
        SELECT u.id, u.username, u.email, u.full_name, u.role, u.department_id, u.profile_image, u.is_active
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.session_token = :token
          AND datetime(s.expires_at) > datetime('now')
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);

    $user = $stmt->fetch();
    if (!$user || (int)$user['is_active'] !== 1) {
        return null;
    }

    return $user;
}

function cgs_require_user(?PDO $db = null): array {
    $user = cgs_current_user($db);
    if (!$user) {
        cgs_error('Authentication required', 401);
    }
    return $user;
}

function cgs_make_session(PDO $db, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    $stmt = $db->prepare('
        INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId,
        $token,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $expiresAt
    ]);

    setcookie('cgs_session', $token, [
        'expires' => time() + 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return $token;
}

function cgs_clear_session(PDO $db): void {
    $token = trim((string)($_COOKIE['cgs_session'] ?? ''));
    if ($token !== '') {
        $stmt = $db->prepare('DELETE FROM sessions WHERE session_token = ?');
        $stmt->execute([$token]);
    }
    setcookie('cgs_session', '', time() - 3600, '/');
}

function cgs_department_id_from_input(PDO $db, $value): ?int {
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return (int)$value;
    }

    $stmt = $db->prepare('SELECT id FROM departments WHERE lower(name)=lower(?) OR lower(code)=lower(?) LIMIT 1');
    $stmt->execute([(string)$value, (string)$value]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function cgs_upsert_demo_user(PDO $db, string $username, string $email, string $fullName, string $role, ?int $departmentId, string $phone, string $plainPassword): array {
    $find = $db->prepare('SELECT id, profile_image FROM users WHERE lower(username)=lower(?) OR lower(email)=lower(?) LIMIT 1');
    $find->execute([$username, $email]);
    $row = $find->fetch();

    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

    if ($row) {
        $db->prepare('
            UPDATE users
            SET username=?, email=?, password_hash=?, full_name=?, role=?, department_id=?, phone=?, is_active=1
            WHERE id=?
        ')->execute([
            $username,
            $email,
            $hash,
            $fullName,
            $role,
            $departmentId,
            $phone,
            (int)$row['id']
        ]);

        $userId = (int)$row['id'];
        $profileImage = $row['profile_image'] ?? null;
    } else {
        $db->prepare('
            INSERT INTO users (username, email, password_hash, full_name, role, department_id, phone, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ')->execute([
            $username,
            $email,
            $hash,
            $fullName,
            $role,
            $departmentId,
            $phone
        ]);

        $userId = (int)$db->lastInsertId();
        $profileImage = null;
    }

    return [
        'id' => $userId,
        'username' => $username,
        'email' => $email,
        'full_name' => $fullName,
        'role' => $role,
        'department_id' => $departmentId,
        'profile_image' => $profileImage,
    ];
}

function cgs_generate_tracking_token(PDO $db): string {
    do {
        $token = 'CGS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $db->prepare('SELECT 1 FROM tokens WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
    } while ($stmt->fetchColumn());

    return $token;
}

function cgs_store_uploads(PDO $db, int $reportId): void {
    if (!isset($_FILES['evidence'])) {
        return;
    }

    $field = $_FILES['evidence'];

    if (!is_array($field['name'])) {
        $entries = [[
            'name' => $field['name'] ?? '',
            'tmp_name' => $field['tmp_name'] ?? '',
            'size' => (int)($field['size'] ?? 0),
            'type' => $field['type'] ?? '',
            'error' => (int)($field['error'] ?? UPLOAD_ERR_NO_FILE),
        ]];
    } else {
        $entries = [];
        foreach ($field['name'] as $i => $name) {
            $entries[] = [
                'name' => $name ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'size' => (int)($field['size'][$i] ?? 0),
                'type' => $field['type'][$i] ?? '',
                'error' => (int)($field['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            ];
        }
    }

    if (!is_dir(CGS_UPLOAD_DIR)) {
        if (!mkdir(CGS_UPLOAD_DIR, 0777, true) && !is_dir(CGS_UPLOAD_DIR)) {
            throw new RuntimeException('Failed to create upload directory.');
        }
    }

    $maxPerFile = 20 * 1024 * 1024;
    $maxTotal = 20 * 1024 * 1024;
    $totalSize = 0;

    $allowedExt = [
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'webp' => 'image',
        'mp4'  => 'video',
        'mov'  => 'video',
        'webm' => 'video',
        'pdf'  => 'document',
        'txt'  => 'document',
        'doc'  => 'document',
        'docx' => 'document',
    ];

    $allowedMime = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $mediaStmt = $db->prepare('
        INSERT INTO report_media (
            report_id, file_path, file_name, mime_type, media_type, file_size
        ) VALUES (?, ?, ?, ?, ?, ?)
    ');

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($entries as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed for file: ' . ($file['name'] ?: 'unknown'));
        }

        $originalName = trim((string)($file['name'] ?? ''));
        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($originalName === '' || $tmpName === '') {
            throw new RuntimeException('Invalid uploaded file data.');
        }
        if (!is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded file source.');
        }
        if ($size <= 0) {
            throw new RuntimeException('Uploaded file is empty: ' . $originalName);
        }
        if ($size > $maxPerFile) {
            throw new RuntimeException('File exceeds 20MB limit: ' . $originalName);
        }

        $totalSize += $size;
        if ($totalSize > $maxTotal) {
            throw new RuntimeException('Total selected upload size cannot exceed 20MB.');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '' || !isset($allowedExt[$ext])) {
            throw new RuntimeException('Unsupported file type: ' . $originalName);
        }

        $mime = $finfo->file($tmpName) ?: 'application/octet-stream';
        if (!in_array($mime, $allowedMime, true)) {
            throw new RuntimeException('Unsupported file MIME type: ' . $originalName);
        }

        $mediaType = $allowedExt[$ext];

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $safeBase = trim((string)$safeBase, '._-');
        if ($safeBase === '') {
            $safeBase = 'file';
        }

        $stored = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $ext;
        $dest = CGS_UPLOAD_DIR . '/' . $stored;

        if (!move_uploaded_file($tmpName, $dest)) {
            throw new RuntimeException('Failed to save uploaded file: ' . $originalName);
        }

        $relative = 'backend/uploads/' . $stored;

        $mediaStmt->execute([
            $reportId,
            $relative,
            $originalName,
            $mime,
            $mediaType,
            $size
        ]);
    }
}

function cgs_timeline(PDO $db, int $reportId): array {
    $stmt = $db->prepare("
        SELECT h.id, h.old_status, h.new_status, h.note, h.created_at, COALESCE(u.full_name, 'System') AS changed_by_name
        FROM report_status_history h
        LEFT JOIN users u ON u.id = h.changed_by
        WHERE h.report_id = ?
        ORDER BY datetime(h.created_at) ASC, h.id ASC
    ");
    $stmt->execute([$reportId]);
    return $stmt->fetchAll() ?: [];
}