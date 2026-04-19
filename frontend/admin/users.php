<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';
admin_require_login();

/*
|--------------------------------------------------------------------------
| BACKEND PLACEHOLDERS
|--------------------------------------------------------------------------
*/

function backend_users_fetch(array $filters = []): array { return admin_users_fetch($filters); }
function backend_users_find(int $userId): ?array { return admin_user_find($userId); }
function backend_users_update_status(int $userId, string $status): bool { return admin_user_update_status($userId, $status); }
function backend_users_send_message(int $userId, string $subject, string $message): bool { return admin_user_send_message($userId, $subject, $message); }
function backend_users_recent_messages(int $userId): array { return admin_user_recent_messages($userId); }

if (admin_is_post()) {
    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Security validation failed. Please try again.');
        admin_redirect('users.php');
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'change_status') {
        $status = trim((string)($_POST['status'] ?? 'active'));

        if ($userId <= 0) {
            admin_set_flash('error', 'Invalid user selected.');
        } elseif (backend_users_update_status($userId, $status)) {
            admin_set_flash('success', 'User status updated successfully.');
        } else {
            admin_set_flash('error', 'Unable to update user status.');
        }

        admin_redirect('users.php?user_id=' . $userId);
    }

    if ($action === 'send_message') {
        $subject = trim((string)($_POST['message_subject'] ?? ''));
        $message = trim((string)($_POST['message_body'] ?? ''));

        if ($userId <= 0 || $subject === '' || $message === '') {
            admin_set_flash('error', 'Message subject and body are required.');
        } elseif (backend_users_send_message($userId, $subject, $message)) {
            admin_set_flash('success', 'User message sent successfully.');
        } else {
            admin_set_flash('error', 'Unable to send message.');
        }

        admin_redirect('users.php?user_id=' . $userId);
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? 'all'));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$selectedUserId = (int)($_GET['user_id'] ?? 0);

$users = backend_users_fetch([
    'search' => $search,
    'role' => $roleFilter,
    'status' => $statusFilter,
]);

$selectedUser = $selectedUserId > 0 ? backend_users_find($selectedUserId) : null;
$recentMessages = $selectedUser ? backend_users_recent_messages((int)$selectedUser['id']) : [];

$user = admin_user();
$flash = admin_get_flash();
$currentTheme = admin_current_theme();
$csrfToken = admin_create_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_page_title('Users'); ?></title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body data-theme="<?php echo admin_h($currentTheme); ?>">
<div class="admin-shell">
    <aside class="admin-sidebar-v2">
        <div class="admin-brand-v2">
            <div class="admin-brand-icon-v2"><i class="fas fa-shield-halved"></i></div>
            <div class="admin-brand-copy-v2">
                <h1>Campus Governance</h1>
                <p>Administrative Console</p>
            </div>
        </div>

        <nav class="admin-menu-v2">
            <a href="<?php echo admin_url('dashboard.php'); ?>"><i class="fas fa-table-cells-large"></i><span>Dashboard</span></a>
            <a href="<?php echo admin_url('report_view.php'); ?>"><i class="fas fa-folder-open"></i><span>Reports</span></a>
            <a href="<?php echo admin_url('spam_reports.php'); ?>"><i class="fas fa-shield-virus"></i><span>Spam Reports</span></a>
            <a class="active" href="<?php echo admin_url('users.php'); ?>"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="<?php echo admin_url('messages.php'); ?>"><i class="fas fa-envelope"></i><span>Messages</span></a>
        </nav>

        <div class="admin-sidebar-footer-v2">
            <a class="admin-logout-v2" href="<?php echo admin_url('logout.php'); ?>">
                <i class="fas fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="admin-main-v2">
        <header class="admin-topbar-v2">
            <div class="admin-topbar-left-v2">
                <h2>User Management</h2>
                <p>Search users, review account status, block or unblock accounts, and send administrative messages.</p>
            </div>

            <div class="admin-topbar-right-v2">
                <button type="button" class="admin-icon-btn-v2" id="themeToggleBtn" aria-label="Toggle theme">
                    <i class="fas <?php echo $currentTheme === 'light' ? 'fa-sun' : 'fa-moon'; ?>" id="themeToggleIcon"></i>
                </button>

                <a class="admin-header-btn-v2" href="<?php echo admin_url('profile.php'); ?>">
                    <i class="fas fa-user-gear"></i>
                    <span><?php echo admin_h($user['name']); ?></span>
                </a>
            </div>
        </header>

        <section class="admin-content-v2">
            <?php if ($flash): ?>
                <div class="admin-alert-v2 admin-alert-<?php echo admin_h($flash['type']); ?>">
                    <?php echo admin_h($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="admin-report-layout-v2">
                <section class="admin-panel-v2 admin-report-list-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Users Directory</h3>
                            <p>Search and open a user for detailed review and action.</p>
                        </div>
                    </div>

                    <form method="GET" class="admin-report-filter-grid-v2 users-filter-grid-v2">
                        <input type="text" name="search" value="<?php echo admin_h($search); ?>" placeholder="Search name, email, department...">

                        <select name="role">
                            <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All roles</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="faculty" <?php echo $roleFilter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                            <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Student</option>
                        </select>

                        <select name="status">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>

                        <div class="admin-report-filter-actions-v2">
                            <button class="admin-header-btn-v2" type="submit"><i class="fas fa-filter"></i><span>Apply</span></button>
                            <a class="admin-header-btn-v2 secondary" href="<?php echo admin_url('users.php'); ?>"><i class="fas fa-rotate-left"></i><span>Reset</span></a>
                        </div>
                    </form>

                    <?php if (!$users): ?>
                        <div class="admin-empty-v2">No users found in the database yet.</div>
                    <?php else: ?>
                        <div class="admin-report-table-wrap-v2">
                            <table class="admin-report-table-v2">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Open</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($users as $row): ?>
                                    <tr>
                                        <td><?php echo (int)$row['id']; ?></td>
                                        <td><strong><?php echo admin_h($row['name']); ?></strong></td>
                                        <td><?php echo admin_h($row['email']); ?></td>
                                        <td><span class="badge verified"><?php echo admin_h(admin_prettify((string)$row['role'])); ?></span></td>
                                        <td><?php echo admin_h($row['department'] ?? ''); ?></td>
                                        <td><span class="badge <?php echo admin_status_badge_class((string)$row['status']); ?>"><?php echo admin_h(admin_prettify((string)$row['status'])); ?></span></td>
                                        <td><a class="mini-action-v2" href="<?php echo admin_url('users.php?user_id=' . (int)$row['id']); ?>">Open</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="admin-panel-v2 admin-report-detail-panel-v2">
                    <?php if (!$selectedUser): ?>
                        <div class="panel-head-v2">
                            <div>
                                <h3>User Details</h3>
                                <p>Select a user to review details and take action.</p>
                            </div>
                        </div>
                        <div class="admin-empty-v2">No user selected.</div>
                    <?php else: ?>
                        <div class="panel-head-v2">
                            <div>
                                <h3><?php echo admin_h($selectedUser['name']); ?></h3>
                                <p><?php echo admin_h($selectedUser['email']); ?></p>
                            </div>
                        </div>

                        <div class="admin-detail-card-v2">
                            <div class="admin-detail-grid-v2">
                                <div><small>Role</small><strong><?php echo admin_h(admin_prettify((string)$selectedUser['role'])); ?></strong></div>
                                <div><small>Status</small><strong><span class="badge <?php echo admin_status_badge_class((string)$selectedUser['status']); ?>"><?php echo admin_h(admin_prettify((string)$selectedUser['status'])); ?></span></strong></div>
                                <div><small>Department</small><strong><?php echo admin_h($selectedUser['department'] ?? ''); ?></strong></div>
                                <div><small>Phone</small><strong><?php echo admin_h($selectedUser['phone'] ?? ''); ?></strong></div>
                            </div>
                        </div>

                        <div class="admin-detail-section-v2">
                            <h4>Account Actions</h4>
                            <div class="admin-action-grid-v2">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$selectedUser['id']; ?>">
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="admin-status-action-btn-v2">Set Active</button>
                                </form>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$selectedUser['id']; ?>">
                                    <input type="hidden" name="status" value="blocked">
                                    <button type="submit" class="admin-status-action-btn-v2">Block User</button>
                                </form>
                            </div>
                        </div>

                        <div class="admin-detail-section-v2">
                            <h4>Send Message</h4>
                            <form method="POST" class="admin-form-stack-v2">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="user_id" value="<?php echo (int)$selectedUser['id']; ?>">

                                <input type="text" name="message_subject" placeholder="Subject" required>
                                <textarea name="message_body" placeholder="Write the message..." required></textarea>

                                <button type="submit" class="admin-header-btn-v2 secondary">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Send Message</span>
                                </button>
                            </form>
                        </div>

                        <div class="admin-detail-section-v2">
                            <h4>Recent Messages</h4>
                            <?php if (!$recentMessages): ?>
                                <div class="admin-empty-v2 small">No recent messages found.</div>
                            <?php else: ?>
                                <div class="admin-timeline-list-v2">
                                    <?php foreach ($recentMessages as $item): ?>
                                        <div class="admin-timeline-item-v2">
                                            <strong><?php echo admin_h($item['subject']); ?></strong>
                                            <p><?php echo admin_h($item['message']); ?></p>
                                            <span><?php echo admin_h(admin_format_datetime($item['created_at'] ?? null)); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    const body = document.body;
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeToggleIcon = document.getElementById('themeToggleIcon');
    const profileMenuBtn = document.getElementById('profileMenuBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileMenuWrap = document.getElementById('profileMenuWrap');

    function applyTheme(theme) {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        body.setAttribute('data-theme', nextTheme);
        localStorage.setItem('cgs_theme', nextTheme);
        if (themeToggleIcon) {
            themeToggleIcon.className = 'fas ' + (nextTheme === 'light' ? 'fa-sun' : 'fa-moon');
        }
    }

    const savedTheme = localStorage.getItem('cgs_theme');
    if (savedTheme === 'light' || savedTheme === 'dark') {
        applyTheme(savedTheme);
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            const currentTheme = body.getAttribute('data-theme') || 'dark';
            applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    }

    if (profileMenuBtn && profileDropdown && profileMenuWrap) {
        profileMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!profileMenuWrap.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
})();
</script>
</body>
</html>