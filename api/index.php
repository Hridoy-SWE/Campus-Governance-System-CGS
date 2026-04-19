<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$db = cgs_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (($frontendPos = strpos($path, '/frontend/')) !== false && str_starts_with($path, '/api/')) {
    $path = substr($path, 0, $frontendPos);
}

$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($path === '/health') {
    cgs_success([
        'status' => 'healthy',
        'time' => date(DATE_RFC3339),
    ]);
}

if ($path === '/api/auth/register' && $method === 'POST') {
    $input = cgs_read_json();

    $username = strtolower(trim((string)($input['username'] ?? '')));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $fullName = trim((string)($input['full_name'] ?? ''));
    $role = strtolower(trim((string)($input['role'] ?? 'student')));
    $phone = trim((string)($input['phone'] ?? ''));
    $departmentId = cgs_department_id_from_input($db, $input['department_id'] ?? null);

    $errors = [];
    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $errors['username'] = 'Username must be 3-50 characters and use only letters, numbers, dot, underscore, or dash.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if (strlen($fullName) < 2) {
        $errors['full_name'] = 'Full name is required.';
    }
    if (!in_array($role, ['admin', 'faculty', 'student', 'department_head'], true)) {
        $errors['role'] = 'Invalid role.';
    }
    if ($errors) {
        cgs_error('Validation failed', 400, $errors);
    }

    $check = $db->prepare('SELECT id FROM users WHERE lower(username)=? OR lower(email)=? LIMIT 1');
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        cgs_error('Username or email already exists', 409);
    }

    $stmt = $db->prepare('
        INSERT INTO users (username, email, password_hash, full_name, role, department_id, phone, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ');
    $stmt->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $fullName,
        $role,
        $departmentId,
        $phone !== '' ? $phone : null,
    ]);

    $userId = (int)$db->lastInsertId();

    cgs_refresh_stats($db);
    cgs_make_session($db, $userId);

    cgs_json([
        'success' => true,
        'message' => 'Account created',
        'data' => [
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'full_name' => $fullName,
                'role' => $role,
                'department_id' => $departmentId,
                'profile_image' => null,
            ],
        ],
    ], 201);
}

if ($path === '/api/auth/login' && $method === 'POST') {
    $input = cgs_read_json();

    $login = strtolower(trim((string)($input['login'] ?? '')));
    $password = (string)($input['password'] ?? '');

    if ($login === '' || $password === '') {
        cgs_error('Login and password are required', 400);
    }

    $stmt = $db->prepare('
        SELECT id, username, email, password_hash, full_name, role, department_id, profile_image, is_active
        FROM users
        WHERE lower(username)=? OR lower(email)=?
        LIMIT 1
    ');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    $demoMap = [
        'admin' => ['admin', 'admin@university.edu', 'Admin User', 'admin', 1, '+8801700000001', 'admin12345'],
        'admin@university.edu' => ['admin', 'admin@university.edu', 'Admin User', 'admin', 1, '+8801700000001', 'admin12345'],
        'faculty' => ['faculty', 'faculty@university.edu', 'Dr. Rahman', 'faculty', 2, '+8801700000002', 'faculty12345'],
        'faculty@university.edu' => ['faculty', 'faculty@university.edu', 'Dr. Rahman', 'faculty', 2, '+8801700000002', 'faculty12345'],
        'student' => ['student', 'student@university.edu', 'Rahim Khan', 'student', 2, '+8801700000003', 'student12345'],
        'student@university.edu' => ['student', 'student@university.edu', 'Rahim Khan', 'student', 2, '+8801700000003', 'student12345'],
    ];

    if (!$user || (int)($user['is_active'] ?? 0) !== 1 || !password_verify($password, (string)($user['password_hash'] ?? ''))) {
        if (isset($demoMap[$login]) && hash_equals($demoMap[$login][6], $password)) {
            [$u, $e, $n, $r, $d, $p, $pw] = $demoMap[$login];
            $user = cgs_upsert_demo_user($db, $u, $e, $n, $r, $d, $p, $pw);
        } else {
            cgs_error('Invalid credentials', 401);
        }
    }

    $db->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([(int)$user['id']]);
    cgs_make_session($db, (int)$user['id']);
    $db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$user['id']]);

    cgs_success([
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'department_id' => $user['department_id'] !== null ? (int)$user['department_id'] : null,
            'profile_image' => $user['profile_image'] ?? null,
        ],
    ], 'Login successful');
}

if ($path === '/api/auth/logout' && $method === 'POST') {
    cgs_clear_session($db);
    cgs_success(['message' => 'Logged out']);
}

if ($path === '/api/auth/me' && $method === 'GET') {
    $user = cgs_require_user($db);

    cgs_success([
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'department_id' => $user['department_id'] !== null ? (int)$user['department_id'] : null,
        'profile_image' => $user['profile_image'] ?? null,
    ]);
}

if ($path === '/api/profile/upload-image' && $method === 'POST') {
    $user = cgs_require_user($db);

    if (!isset($_FILES['profile_image'])) {
        cgs_error('Profile image file is required', 400);
    }

    $file = $_FILES['profile_image'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        cgs_error('Profile image upload failed', 400);
    }

    $maxSize = 5 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);

    if ($size <= 0) {
        cgs_error('Uploaded profile image is empty', 400);
    }
    if ($size > $maxSize) {
        cgs_error('Profile image must be 5MB or less', 400);
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $originalName = trim((string)($file['name'] ?? ''));

    if ($tmpName === '' || $originalName === '') {
        cgs_error('Invalid profile image upload', 400);
    }
    if (!is_uploaded_file($tmpName)) {
        cgs_error('Invalid uploaded profile image source', 400);
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        cgs_error('Only JPG, JPEG, PNG, and WEBP images are allowed for profile photo', 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName) ?: 'application/octet-stream';
    if (!in_array($mime, $allowedMime, true)) {
        cgs_error('Invalid profile image type', 400);
    }

    if (!is_dir(CGS_UPLOAD_DIR)) {
        if (!mkdir(CGS_UPLOAD_DIR, 0777, true) && !is_dir(CGS_UPLOAD_DIR)) {
            cgs_error('Failed to create upload directory', 500);
        }
    }

    $storedName = 'profile_' . (int)$user['id'] . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $absolutePath = CGS_UPLOAD_DIR . '/' . $storedName;
    $relativePath = 'backend/uploads/' . $storedName;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        cgs_error('Failed to save profile image', 500);
    }

    $oldImage = $user['profile_image'] ?? null;

    $stmt = $db->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
    $stmt->execute([$relativePath, (int)$user['id']]);

    if ($oldImage && is_string($oldImage) && str_starts_with($oldImage, 'backend/uploads/')) {
        $oldAbsolute = CGS_ROOT . '/' . $oldImage;
        if (is_file($oldAbsolute) && $oldAbsolute !== $absolutePath) {
            @unlink($oldAbsolute);
        }
    }

    cgs_success([
        'profile_image' => $relativePath,
    ], 'Profile image updated');
}

if ($path === '/api/stats' && $method === 'GET') {
    cgs_refresh_stats($db);
    $row = $db->query('SELECT * FROM stats WHERE id = 1')->fetch() ?: [];
    cgs_success($row);
}

if ($path === '/api/reports/latest' && $method === 'GET') {
    $rows = $db->query("
        SELECT r.id, t.token, r.category, r.title, r.description, COALESCE(r.location,'') AS location,
               r.priority, r.status, r.created_at, r.updated_at
        FROM reports r
        LEFT JOIN tokens t ON t.id = r.token_id
        ORDER BY datetime(r.created_at) DESC
        LIMIT 8
    ")->fetchAll() ?: [];

    cgs_success(array_map(static function(array $row): array {
        $row['id'] = (int)$row['id'];
        return $row;
    }, $rows));
}

if ($path === '/api/report/submit' && $method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $input = str_contains($contentType, 'application/json') ? cgs_read_json() : $_POST;

    $category = trim((string)($input['category'] ?? ''));
    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $location = trim((string)($input['location'] ?? ''));
    $priority = strtolower(trim((string)($input['priority'] ?? 'medium')));
    $notifyEmail = trim((string)($input['notify_email'] ?? $input['email'] ?? ''));
    $notifyPhone = trim((string)($input['notify_phone'] ?? $input['phone'] ?? ''));
    $departmentId = cgs_department_id_from_input($db, $input['department_id'] ?? ($input['department'] ?? 'CSE'));
    $isAnonymous = !isset($input['is_anonymous'])
        ? 1
        : (in_array(strtolower((string)$input['is_anonymous']), ['1', 'true', 'yes', 'on'], true) ? 1 : 0);

    $errors = [];
    if ($category === '') $errors['category'] = 'Category is required.';
    if (strlen($title) < 5) $errors['title'] = 'Title must be at least 5 characters.';
    if (strlen($description) < 10) $errors['description'] = 'Description must be at least 10 characters.';
    if (!in_array($priority, ['low', 'medium', 'high', 'critical'], true)) $errors['priority'] = 'Priority is invalid.';
    if ($errors) {
        cgs_error('Validation failed', 400, $errors);
    }

    $currentUser = cgs_current_user($db);
    $tokenValue = cgs_generate_tracking_token($db);

    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO tokens (token, email, phone, is_active) VALUES (?, ?, ?, 1)')
            ->execute([
                $tokenValue,
                $notifyEmail !== '' ? $notifyEmail : null,
                $notifyPhone !== '' ? $notifyPhone : null,
            ]);

        $tokenId = (int)$db->lastInsertId();

        $db->prepare('
            INSERT INTO reports (
                token_id, user_id, department_id, category, title, description,
                location, priority, status, is_anonymous, notify_email, notify_phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $tokenId,
            $currentUser['id'] ?? null,
            $departmentId,
            $category,
            $title,
            $description,
            $location !== '' ? $location : null,
            $priority,
            'pending',
            $isAnonymous,
            $notifyEmail !== '' ? $notifyEmail : null,
            $notifyPhone !== '' ? $notifyPhone : null,
        ]);

        $reportId = (int)$db->lastInsertId();

        $db->prepare('INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)')
            ->execute([$reportId, null, 'pending', $currentUser['id'] ?? null, 'Report submitted']);

        cgs_store_uploads($db, $reportId);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        cgs_error('Failed to submit report: ' . $e->getMessage(), 500);
    }

    cgs_refresh_stats($db);

    cgs_json([
        'success' => true,
        'message' => 'Report submitted',
        'data' => [
            'report_id' => $reportId,
            'token' => $tokenValue,
        ],
    ], 201);
}

if ($path === '/api/report/track' && $method === 'GET') {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        cgs_error('Token is required', 400);
    }

    $stmt = $db->prepare("
        SELECT r.id, t.token, r.category, r.title, r.description, COALESCE(r.location,'') AS location,
               r.priority, r.status, r.created_at, r.updated_at,
               COALESCE(r.resolution_notes,'') AS resolution_notes
        FROM reports r
        JOIN tokens t ON t.id = r.token_id
        WHERE t.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $report = $stmt->fetch();

    if (!$report) {
        cgs_error('Report not found', 404);
    }

    $report['id'] = (int)$report['id'];
    $report['timeline'] = cgs_timeline($db, (int)$report['id']);
    cgs_success($report);
}

if ($path === '/api/report/date-counts' && $method === 'GET') {
    $year = preg_replace('/[^0-9]/', '', (string)($_GET['year'] ?? ''));
    $month = preg_replace('/[^0-9]/', '', (string)($_GET['month'] ?? ''));

    if ($year === '' || $month === '') {
        cgs_error('year and month are required', 400);
    }

    $stmt = $db->prepare("
        SELECT date(created_at) AS report_date, COUNT(*) AS report_count
        FROM reports
        WHERE strftime('%Y', created_at) = ? AND strftime('%m', created_at) = ?
        GROUP BY date(created_at)
        ORDER BY date(created_at)
    ");
    $stmt->execute([$year, str_pad($month, 2, '0', STR_PAD_LEFT)]);

    $out = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $out[$row['report_date']] = (int)$row['report_count'];
    }
    cgs_success($out);
}

if ($path === '/api/public/analytics' && $method === 'GET') {
    cgs_refresh_stats($db);

    $stats = $db->query('SELECT * FROM stats WHERE id = 1')->fetch() ?: [];
    $statusRows = $db->query("SELECT status, COUNT(*) AS total FROM reports GROUP BY status ORDER BY total DESC")->fetchAll() ?: [];
    $statusCounts = [
        'pending' => 0,
        'verified' => 0,
        'in_progress' => 0,
        'resolved' => 0,
    ];

    foreach ($statusRows as $row) {
        $statusCounts[(string)$row['status']] = (int)$row['total'];
    }

    $categoryRows = $db->query("SELECT category, COUNT(*) AS total FROM reports GROUP BY category ORDER BY total DESC, category ASC")->fetchAll() ?: [];
    $recentReports = $db->query("
        SELECT r.title, r.category, COALESCE(r.location,'') AS location, r.status, r.created_at
        FROM reports r
        ORDER BY datetime(r.created_at) DESC
        LIMIT 6
    ")->fetchAll() ?: [];

    $monthlyRows = $db->query("
        SELECT strftime('%Y-%m', created_at) AS ym, COUNT(*) AS total
        FROM reports
        WHERE datetime(created_at) >= datetime('now','start of month','-5 months')
        GROUP BY strftime('%Y-%m', created_at)
        ORDER BY ym ASC
    ")->fetchAll() ?: [];

    $monthlyCounts = [];
    $cursor = new DateTimeImmutable('first day of -5 months');
    for ($i = 0; $i < 6; $i++) {
        $key = $cursor->format('Y-m');
        $label = $cursor->format('M Y');
        $monthlyCounts[$key] = [
            'key' => $key,
            'label' => $label,
            'total' => 0,
        ];
        $cursor = $cursor->modify('+1 month');
    }

    foreach ($monthlyRows as $row) {
        $key = (string)($row['ym'] ?? '');
        if (isset($monthlyCounts[$key])) {
            $monthlyCounts[$key]['total'] = (int)$row['total'];
        }
    }

    cgs_success([
        'stats' => $stats,
        'status_counts' => $statusCounts,
        'category_counts' => array_map(static function(array $row): array {
            return [
                'category' => (string)$row['category'],
                'total' => (int)$row['total'],
            ];
        }, $categoryRows),
        'monthly_counts' => array_values($monthlyCounts),
        'recent_reports' => array_map(static function(array $row): array {
            return [
                'title' => (string)$row['title'],
                'category' => (string)$row['category'],
                'location' => (string)$row['location'],
                'status' => (string)$row['status'],
                'created_at' => (string)$row['created_at'],
            ];
        }, $recentReports),
    ]);
}

cgs_error('Endpoint not found', 404);