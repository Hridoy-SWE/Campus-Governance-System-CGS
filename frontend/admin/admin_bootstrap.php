<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ADMIN_APP_NAME', 'Campus Governance Admin');
define('ADMIN_BASE_URL', '/frontend/admin');
define('ADMIN_UPLOAD_DIR', __DIR__ . '/uploads/profile/');
define('ADMIN_UPLOAD_URL', 'uploads/profile/');
define('DEFAULT_THEME', 'dark');
define('MAIN_SITE_URL', '/frontend/index.html');
define('STUDENT_DASHBOARD_URL', '/frontend/dashboard.html');
define('ADMIN_DB_PATH', realpath(__DIR__ . '/../../database/campus.db') ?: (__DIR__ . '/../../database/campus.db'));

if (!is_dir(ADMIN_UPLOAD_DIR)) {
    @mkdir(ADMIN_UPLOAD_DIR, 0777, true);
}

function admin_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function admin_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return rtrim(ADMIN_BASE_URL, '/') . ($path !== '' ? '/' . $path : '');
}

function admin_page_title(string $title): string
{
    return $title . ' · ' . ADMIN_APP_NAME;
}

function admin_asset_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return '/' . $path;
}

function admin_current_theme(): string
{
    return $_SESSION['admin_theme'] ?? DEFAULT_THEME;
}

function admin_set_theme(string $theme): void
{
    $_SESSION['admin_theme'] = in_array($theme, ['dark', 'light'], true) ? $theme : DEFAULT_THEME;
}

function admin_create_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf_token(?string $token): bool
{
    return isset($_SESSION['admin_csrf_token']) && is_string($token) && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function admin_set_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function admin_get_flash(): ?array
{
    if (!isset($_SESSION['admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $flash;
}

function admin_redirect(string $path): void
{
    header('Location: ' . admin_url($path));
    exit;
}

function admin_redirect_absolute(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function admin_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . ADMIN_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    return $pdo;
}

function admin_prettify(string $value): string
{
    $value = str_replace('_', ' ', trim($value));
    return ucwords($value);
}

function admin_safe_excerpt(?string $text, int $limit = 100): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '…', 'UTF-8');
    }
    return strlen($text) <= $limit ? $text : substr($text, 0, max(0, $limit - 3)) . '...';
}

function admin_format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return admin_h($value);
    }
    return date('M d, Y · h:i A', $ts);
}

function admin_status_badge_class(string $status): string
{
    $status = strtolower(trim($status));
    return match ($status) {
        'resolved' => 'resolved',
        'verified', 'approved', 'active' => 'verified',
        'in_progress', 'progress', 'reviewing' => 'progress',
        'blocked', 'spam' => 'danger',
        default => 'pending',
    };
}

function admin_priority_badge_class(string $priority): string
{
    $priority = strtolower(trim($priority));
    return match ($priority) {
        'critical' => 'priority-critical',
        'high' => 'priority-high',
        'low' => 'priority-low',
        default => 'priority-medium',
    };
}

function admin_login_user(array $user): void
{
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user_id'] = (int)($user['id'] ?? 0);
    $_SESSION['admin_user_name'] = (string)($user['full_name'] ?? $user['name'] ?? 'Admin User');
    $_SESSION['admin_user_email'] = (string)($user['email'] ?? '');
    $_SESSION['admin_user_role'] = (string)($user['role'] ?? 'admin');
    $_SESSION['admin_user_department'] = (string)($user['department_name'] ?? $user['department'] ?? 'Administration');

    $photo = (string)($user['profile_image'] ?? $user['profile_photo'] ?? $user['photo'] ?? '');
    $_SESSION['admin_user_photo'] = $photo;
}

function admin_is_logged_in(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function admin_sync_from_backend_session(): bool
{
    if (admin_is_logged_in()) {
        return true;
    }

    $token = trim((string)($_COOKIE['cgs_session'] ?? ''));
    if ($token === '') {
        return false;
    }

    $sql = "
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.role,
            COALESCE(d.name,'Administration') AS department_name,
            COALESCE(u.profile_image, u.profile_photo, '') AS profile_image
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE s.session_token = :token
          AND datetime(s.expires_at) > datetime('now')
          AND u.is_active = 1
        LIMIT 1
    ";

    $stmt = admin_db()->prepare($sql);
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if (!in_array((string)$user['role'], ['admin', 'faculty'], true)) {
        return false;
    }

    admin_login_user($user);
    return true;
}

function admin_require_login(): void
{
    if (!admin_sync_from_backend_session()) {
        admin_set_flash('error', 'Please sign in from the main website to continue.');
        admin_redirect_absolute(MAIN_SITE_URL);
    }
}

function admin_logout(): void
{
    $token = trim((string)($_COOKIE['cgs_session'] ?? ''));
    if ($token !== '') {
        try {
            $stmt = admin_db()->prepare('DELETE FROM sessions WHERE session_token = :token');
            $stmt->execute([':token' => $token]);
        } catch (Throwable $e) {
        }
        setcookie('cgs_session', '', time() - 3600, '/');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
    session_destroy();
}

function admin_user(): array
{
    admin_sync_from_backend_session();
    return [
        'id' => $_SESSION['admin_user_id'] ?? 0,
        'name' => $_SESSION['admin_user_name'] ?? 'Admin User',
        'email' => $_SESSION['admin_user_email'] ?? '',
        'role' => $_SESSION['admin_user_role'] ?? 'admin',
        'department' => $_SESSION['admin_user_department'] ?? 'Administration',
        'photo' => $_SESSION['admin_user_photo'] ?? '',
    ];
}

function backend_admin_find_user_for_login(string $login): ?array
{
    $stmt = admin_db()->prepare("
        SELECT u.*, COALESCE(d.name,'Administration') AS department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE lower(u.username)=lower(:login) OR lower(u.email)=lower(:login)
        LIMIT 1
    ");
    $stmt->execute([':login' => trim($login)]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }
    if (!in_array((string)($user['role'] ?? ''), ['admin', 'faculty'], true)) {
        return null;
    }

    return $user;
}

function backend_admin_user_is_blocked(array $user): bool
{
    return (int)($user['is_active'] ?? 1) !== 1;
}

function backend_admin_verify_password(array $user, string $password): bool
{
    return password_verify($password, (string)($user['password_hash'] ?? ''));
}

function admin_query_all(string $sql, array $params = []): array
{
    $stmt = admin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function admin_query_one(string $sql, array $params = []): ?array
{
    $stmt = admin_db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function backend_fetch_dashboard_stats(): array
{
    $row = admin_query_one("
        SELECT
            (SELECT COUNT(*) FROM reports) AS total_reports,
            (SELECT COUNT(*) FROM reports WHERE status='pending') AS pending_reports,
            (SELECT COUNT(*) FROM reports WHERE status='verified') AS verified_reports,
            (SELECT COUNT(*) FROM reports WHERE status='in_progress') AS in_progress_reports,
            (SELECT COUNT(*) FROM reports WHERE status='resolved') AS resolved_reports,
            (SELECT COUNT(*) FROM reports WHERE status='spam' OR spam_score > 0) AS spam_reports,
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM users WHERE is_active = 0) AS blocked_users
    ");
    return $row ?: [];
}

function backend_fetch_recent_reports(int $limit = 8): array
{
    return admin_query_all("
        SELECT
            r.id,
            COALESCE(t.token,'') AS token,
            r.title,
            r.category,
            COALESCE(r.location,'Not specified') AS location,
            r.priority,
            r.status,
            r.created_at,
            (SELECT COUNT(*) FROM report_media rm WHERE rm.report_id = r.id) AS media_count
        FROM reports r
        LEFT JOIN tokens t ON t.id = r.token_id
        ORDER BY datetime(r.created_at) DESC
        LIMIT $limit
    ");
}

function backend_fetch_priority_queue(int $limit = 6): array
{
    return admin_query_all("
        SELECT
            r.id,
            COALESCE(t.token,'') AS token,
            r.title,
            r.priority,
            r.status,
            COALESCE(r.location,'Not specified') AS location,
            r.created_at
        FROM reports r
        LEFT JOIN tokens t ON t.id = r.token_id
        WHERE r.status IN ('pending','verified','in_progress')
        ORDER BY CASE r.priority
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            ELSE 4
        END, datetime(r.created_at) DESC
        LIMIT $limit
    ");
}

function backend_fetch_recent_activity(int $limit = 8): array
{
    $rows = admin_query_all("
        SELECT action, entity_type, entity_id, COALESCE(new_value,'') AS new_value, created_at
        FROM activity_logs
        ORDER BY datetime(created_at) DESC
        LIMIT $limit
    ");

    $out = [];
    foreach ($rows as $row) {
        $action = (string)($row['action'] ?? 'activity');
        $entityType = (string)($row['entity_type'] ?? 'item');
        $entityId = (int)($row['entity_id'] ?? 0);
        $newValue = trim((string)($row['new_value'] ?? ''));

        $out[] = [
            'title' => admin_prettify($action),
            'description' => trim(sprintf(
                '%s #%d %s',
                admin_prettify($entityType),
                $entityId,
                $newValue !== '' ? '→ ' . admin_prettify($newValue) : ''
            )),
            'created_at' => $row['created_at'] ?? '',
        ];
    }

    return $out;
}

function backend_fetch_category_overview(): array
{
    $rows = admin_query_all("
        SELECT category, COUNT(*) AS total
        FROM reports
        GROUP BY category
        ORDER BY total DESC, category ASC
    ");

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'label' => admin_prettify((string)($row['category'] ?? 'Unknown')),
            'count' => (int)($row['total'] ?? 0)
        ];
    }
    return $out;
}

function backend_fetch_calendar_report_counts(int $year, int $month): array
{
    $monthKey = sprintf('%04d-%02d', $year, $month);
    $rows = admin_query_all("
        SELECT date(created_at) AS report_date, COUNT(*) AS total
        FROM reports
        WHERE strftime('%Y-%m', created_at) = :month
        GROUP BY date(created_at)
    ", [':month' => $monthKey]);

    $out = [];
    foreach ($rows as $row) {
        $out[$row['report_date']] = (int)$row['total'];
    }
    return $out;
}

function admin_reports_fetch(array $filters = []): array
{
    $sql = "
        SELECT
            r.*,
            COALESCE(t.token,'') AS token,
            COALESCE(assign.full_name,'') AS assigned_name,
            COALESCE(d.name,'') AS department_name,
            (SELECT COUNT(*) FROM report_media rm WHERE rm.report_id = r.id) AS media_count
        FROM reports r
        LEFT JOIN tokens t ON t.id = r.token_id
        LEFT JOIN users assign ON assign.id = r.assigned_to
        LEFT JOIN departments d ON d.id = r.department_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (lower(r.title) LIKE :search OR lower(r.description) LIKE :search OR lower(COALESCE(r.location,'')) LIKE :search OR lower(COALESCE(t.token,'')) LIKE :search)";
        $params[':search'] = '%' . strtolower(trim((string)$filters['search'])) . '%';
    }

    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $sql .= " AND r.status = :status";
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['category']) && $filters['category'] !== 'all') {
        $sql .= " AND r.category = :category";
        $params[':category'] = $filters['category'];
    }

    $sql .= " ORDER BY datetime(r.created_at) DESC";
    return admin_query_all($sql, $params);
}

function admin_report_find(int $reportId): ?array
{
    return admin_query_one("
        SELECT
            r.*,
            COALESCE(t.token,'') AS token,
            COALESCE(d.name,'') AS department_name,
            COALESCE(assign.full_name,'') AS assigned_name
        FROM reports r
        LEFT JOIN tokens t ON t.id = r.token_id
        LEFT JOIN departments d ON d.id = r.department_id
        LEFT JOIN users assign ON assign.id = r.assigned_to
        WHERE r.id = :id
        LIMIT 1
    ", [':id' => $reportId]);
}

function admin_report_media(int $reportId): array
{
    $rows = admin_query_all("
        SELECT id, file_path, media_type AS file_type, file_name, mime_type, file_size, created_at
        FROM report_media
        WHERE report_id = :id
        ORDER BY datetime(created_at) ASC
    ", [':id' => $reportId]);

    foreach ($rows as &$row) {
        $path = trim((string)($row['file_path'] ?? ''));
        $row['file_url'] = $path === '' ? '' : admin_asset_url('../../backend/' . ltrim($path, '/'));
    }
    unset($row);

    return $rows;
}

function admin_report_timeline(int $reportId): array
{
    $rows = admin_query_all("
        SELECT
            h.id,
            h.old_status,
            h.new_status,
            COALESCE(h.note,'') AS note,
            h.created_at,
            COALESCE(u.full_name,'System') AS changed_by_name
        FROM report_status_history h
        LEFT JOIN users u ON u.id = h.changed_by
        WHERE h.report_id = :id
        ORDER BY datetime(h.created_at) ASC, h.id ASC
    ", [':id' => $reportId]);

    foreach ($rows as &$row) {
        $old = trim((string)($row['old_status'] ?? ''));
        $new = trim((string)($row['new_status'] ?? ''));
        $row['title'] = $old === '' ? 'Report submitted' : admin_prettify($old) . ' → ' . admin_prettify($new);

        $parts = [];
        if ($new !== '') {
            $parts[] = 'Status: ' . admin_prettify($new);
        }
        if (trim((string)$row['note']) !== '') {
            $parts[] = trim((string)$row['note']);
        }
        $parts[] = 'By ' . (string)($row['changed_by_name'] ?? 'System');
        $row['description'] = implode(' • ', $parts);
    }
    unset($row);

    return $rows;
}

function admin_report_update_status(int $reportId, string $status, string $note = '', string $resolutionNotes = ''): bool
{
    $report = admin_report_find($reportId);
    if (!$report) {
        return false;
    }

    $allowed = ['pending', 'verified', 'in_progress', 'resolved', 'spam'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    $user = admin_user();
    $db = admin_db();

    $stmt = $db->prepare("
        UPDATE reports
        SET status = :status,
            resolution_notes = CASE
                WHEN :status='resolved' AND :resolution_notes <> '' THEN :resolution_notes
                WHEN :status='resolved' AND COALESCE(resolution_notes,'') = '' THEN :note
                ELSE resolution_notes
            END,
            resolved_at = CASE WHEN :status='resolved' THEN CURRENT_TIMESTAMP ELSE resolved_at END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $ok = $stmt->execute([
        ':status' => $status,
        ':resolution_notes' => trim($resolutionNotes),
        ':note' => trim($note),
        ':id' => $reportId,
    ]);

    if ($ok) {
        $stmt2 = $db->prepare("
            INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, note)
            VALUES (:rid, :old, :new, :uid, :note)
        ");
        $stmt2->execute([
            ':rid' => $reportId,
            ':old' => $report['status'],
            ':new' => $status,
            ':uid' => $user['id'] ?: null,
            ':note' => trim($note) !== '' ? trim($note) : 'Updated from admin panel',
        ]);
    }

    return $ok;
}

function admin_report_update_details(int $reportId, array $payload): bool
{
    $stmt = admin_db()->prepare("
        UPDATE reports
        SET title=:title, description=:description, category=:category, priority=:priority, location=:location, updated_at=CURRENT_TIMESTAMP
        WHERE id=:id
    ");

    return $stmt->execute([
        ':title' => $payload['title'] ?? '',
        ':description' => $payload['description'] ?? '',
        ':category' => $payload['category'] ?? '',
        ':priority' => $payload['priority'] ?? 'medium',
        ':location' => $payload['location'] ?? '',
        ':id' => $reportId
    ]);
}

function admin_report_mark_spam(int $reportId, bool $isSpam, string $reason = ''): bool
{
    $report = admin_report_find($reportId);
    if (!$report) {
        return false;
    }

    $db = admin_db();
    $stmt = $db->prepare("
        UPDATE reports
        SET status=:status, spam_score=:score, spam_reason=:reason, updated_at=CURRENT_TIMESTAMP
        WHERE id=:id
    ");

    $ok = $stmt->execute([
        ':status' => $isSpam ? 'spam' : 'pending',
        ':score' => $isSpam ? 100 : 0,
        ':reason' => $reason,
        ':id' => $reportId
    ]);

    if ($ok) {
        $user = admin_user();
        $db->prepare("
            INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, note)
            VALUES (:rid, :old, :new, :uid, :note)
        ")->execute([
            ':rid' => $reportId,
            ':old' => $report['status'],
            ':new' => $isSpam ? 'spam' : 'pending',
            ':uid' => $user['id'] ?: null,
            ':note' => $reason !== '' ? $reason : ($isSpam ? 'Marked as spam' : 'Returned to pending review')
        ]);
    }

    return $ok;
}

function admin_report_delete(int $reportId): bool
{
    $db = admin_db();
    $db->beginTransaction();

    try {
        foreach (['report_media', 'comments', 'messages', 'notifications', 'report_status_history'] as $table) {
            $stmt = $db->prepare("DELETE FROM $table WHERE report_id = :id");
            $stmt->execute([':id' => $reportId]);
        }

        $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
        $stmt->execute([':id' => $reportId]);

        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function admin_report_add_note(int $reportId, string $note): bool
{
    $user = admin_user();
    $stmt = admin_db()->prepare("
        INSERT INTO comments (report_id, user_id, comment, is_staff_response, is_private)
        VALUES (:rid, :uid, :comment, 1, 0)
    ");
    return $stmt->execute([
        ':rid' => $reportId,
        ':uid' => $user['id'] ?: null,
        ':comment' => $note
    ]);
}

function admin_report_send_message(int $reportId, string $subject, string $message): bool
{
    $report = admin_report_find($reportId);
    if (!$report) {
        return false;
    }

    $user = admin_user();
    $stmt = admin_db()->prepare("
        INSERT INTO messages (sender_user_id, recipient_token_id, report_id, subject, message, status)
        VALUES (:uid, :token_id, :rid, :subject, :message, 'sent')
    ");

    return $stmt->execute([
        ':uid' => $user['id'] ?: null,
        ':token_id' => $report['token_id'] ?: null,
        ':rid' => $reportId,
        ':subject' => $subject,
        ':message' => $message
    ]);
}

function admin_users_fetch(array $filters = []): array
{
    $sql = "
        SELECT
            u.*,
            COALESCE(d.name,'') AS department_name,
            (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) AS reports_count
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (lower(u.full_name) LIKE :search OR lower(u.email) LIKE :search OR lower(u.username) LIKE :search)";
        $params[':search'] = '%' . strtolower(trim((string)$filters['search'])) . '%';
    }

    if (!empty($filters['role']) && $filters['role'] !== 'all') {
        $sql .= " AND u.role = :role";
        $params[':role'] = $filters['role'];
    }

    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $sql .= $filters['status'] === 'blocked' ? " AND u.is_active = 0" : " AND u.is_active = 1";
    }

    $sql .= " ORDER BY datetime(u.created_at) DESC";
    return admin_query_all($sql, $params);
}

function admin_user_find(int $userId): ?array
{
    return admin_query_one("
        SELECT u.*, COALESCE(d.name,'') AS department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.id = :id
    ", [':id' => $userId]);
}

function admin_user_update_status(int $userId, string $status): bool
{
    $active = $status === 'blocked' ? 0 : 1;
    $stmt = admin_db()->prepare("
        UPDATE users
        SET is_active=:active, updated_at=CURRENT_TIMESTAMP
        WHERE id=:id
    ");
    return $stmt->execute([
        ':active' => $active,
        ':id' => $userId
    ]);
}

function admin_user_recent_messages(int $userId): array
{
    return admin_query_all("
        SELECT *
        FROM messages
        WHERE recipient_user_id = :id OR sender_user_id = :id
        ORDER BY datetime(created_at) DESC
        LIMIT 12
    ", [':id' => $userId]);
}

function admin_user_send_message(int $userId, string $subject, string $message): bool
{
    $sender = admin_user();
    $stmt = admin_db()->prepare("
        INSERT INTO messages (sender_user_id, recipient_user_id, subject, message, status)
        VALUES (:sender, :recipient, :subject, :message, 'sent')
    ");
    return $stmt->execute([
        ':sender' => $sender['id'] ?: null,
        ':recipient' => $userId,
        ':subject' => $subject,
        ':message' => $message
    ]);
}

function admin_message_threads(): array
{
    $sql = "
        SELECT
            m.report_id,
            m.recipient_user_id,
            m.recipient_token_id,
            MAX(m.created_at) AS last_at,
            MAX(m.subject) AS last_subject,
            COUNT(*) AS total_messages,
            COALESCE(u.full_name, t.token, 'General') AS recipient_label
        FROM messages m
        LEFT JOIN users u ON u.id = m.recipient_user_id
        LEFT JOIN tokens t ON t.id = m.recipient_token_id
        GROUP BY COALESCE(m.report_id,0), COALESCE(m.recipient_user_id,0), COALESCE(m.recipient_token_id,0)
        ORDER BY datetime(last_at) DESC
    ";

    $rows = admin_query_all($sql);
    foreach ($rows as $i => $row) {
        $rows[$i]['id'] = $i + 1;
    }
    return $rows;
}

function admin_message_thread_items_by_tuple(?int $reportId, ?int $recipientUserId, ?int $recipientTokenId): array
{
    $sql = "
        SELECT m.*, COALESCE(sender.full_name,'System') AS sender_name
        FROM messages m
        LEFT JOIN users sender ON sender.id = m.sender_user_id
        WHERE COALESCE(m.report_id,0)=:rid
          AND COALESCE(m.recipient_user_id,0)=:ruid
          AND COALESCE(m.recipient_token_id,0)=:rtid
        ORDER BY datetime(m.created_at) ASC
    ";

    return admin_query_all($sql, [
        ':rid' => $reportId ?: 0,
        ':ruid' => $recipientUserId ?: 0,
        ':rtid' => $recipientTokenId ?: 0
    ]);
}

function admin_message_send(array $payload): bool
{
    $sender = admin_user();
    $stmt = admin_db()->prepare("
        INSERT INTO messages (
            sender_user_id, recipient_user_id, recipient_token_id, report_id, subject, message, status
        ) VALUES (
            :sender, :recipient_user_id, :recipient_token_id, :report_id, :subject, :message, 'sent'
        )
    ");

    return $stmt->execute([
        ':sender' => $sender['id'] ?: null,
        ':recipient_user_id' => ($payload['recipient_user_id'] ?? null) ?: null,
        ':recipient_token_id' => ($payload['recipient_token_id'] ?? null) ?: null,
        ':report_id' => ($payload['report_id'] ?? null) ?: null,
        ':subject' => $payload['subject'] ?? '',
        ':message' => $payload['message'] ?? '',
    ]);
}

function admin_spam_fetch(array $filters = []): array
{
    $sql = "
        SELECT r.*, COALESCE(t.token,'') AS token
        FROM reports r
        LEFT JOIN tokens t ON t.id = r.token_id
        WHERE r.status='spam' OR r.spam_score > 0 OR COALESCE(r.spam_reason,'') <> ''
    ";
    $params = [];

    if (!empty($filters['search'])) {
        $sql .= " AND (lower(r.title) LIKE :search OR lower(COALESCE(t.token,'')) LIKE :search)";
        $params[':search'] = '%' . strtolower(trim((string)$filters['search'])) . '%';
    }

    $sql .= " ORDER BY datetime(r.updated_at) DESC, datetime(r.created_at) DESC";
    return admin_query_all($sql, $params);
}

function admin_profile_get(int $userId): array
{
    $row = admin_query_one("
        SELECT u.*, COALESCE(d.name,'Administration') AS department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.id = :id
    ", [':id' => $userId]) ?: [];

    return [
        'id' => $row['id'] ?? $userId,
        'name' => $row['full_name'] ?? 'Admin User',
        'email' => $row['email'] ?? '',
        'role' => $row['role'] ?? 'admin',
        'department' => $row['department_name'] ?? 'Administration',
        'phone' => $row['phone'] ?? '',
        'designation' => $row['designation'] ?? '',
        'bio' => $row['bio'] ?? '',
        'profile_image' => $row['profile_image'] ?? ($row['profile_photo'] ?? ''),
        'updated_at' => $row['updated_at'] ?? '',
    ];
}

function admin_profile_update(int $userId, array $payload): bool
{
    $stmt = admin_db()->prepare("
        UPDATE users
        SET
            full_name = :name,
            email = :email,
            phone = :phone,
            designation = :designation,
            bio = :bio,
            profile_image = COALESCE(:profile_image, profile_image),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    return $stmt->execute([
        ':name' => $payload['name'] ?? '',
        ':email' => $payload['email'] ?? '',
        ':phone' => $payload['phone'] ?? '',
        ':designation' => $payload['designation'] ?? '',
        ':bio' => $payload['bio'] ?? '',
        ':profile_image' => $payload['profile_image'] ?? null,
        ':id' => $userId
    ]);
}

function admin_profile_change_password(int $userId, string $currentPassword, string $newPassword): bool
{
    $user = admin_query_one("SELECT password_hash FROM users WHERE id=:id", [':id' => $userId]);
    if (!$user || !password_verify($currentPassword, (string)$user['password_hash'])) {
        return false;
    }

    $stmt = admin_db()->prepare("
        UPDATE users
        SET password_hash=:hash, updated_at=CURRENT_TIMESTAMP
        WHERE id=:id
    ");

    return $stmt->execute([
        ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => $userId
    ]);
}

function backend_profile_store_photo(array $file): ?string
{
    if (empty($file['name']) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $allowedMime = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp'
    ];

    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return null;
    }

    $filename = 'profile_' . time() . '_' . bin2hex(random_bytes(6)) . $allowedMime[$mime];
    $destination = ADMIN_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }

    return ADMIN_UPLOAD_URL . $filename;
}

function build_calendar_matrix(int $year, int $month): array
{
    $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $start = $firstDay->modify('monday this week');
    $lastDay = $firstDay->modify('last day of this month');
    $end = $lastDay->modify('sunday this week');

    $weeks = [];
    $cursor = $start;

    while ($cursor <= $end) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = $cursor;
            $cursor = $cursor->modify('+1 day');
        }
        $weeks[] = $week;
    }

    return $weeks;
}