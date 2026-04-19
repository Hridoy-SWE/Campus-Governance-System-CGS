<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';
admin_require_login();

/*
|--------------------------------------------------------------------------
| BACKEND PLACEHOLDERS
|--------------------------------------------------------------------------
*/

function backend_messages_threads(): array { return admin_message_threads(); }
function backend_messages_thread_items(int $threadId): array {
    $threads = admin_message_threads();
    $thread = $threads[$threadId - 1] ?? null;
    if (!$thread) return [];
    return admin_message_thread_items_by_tuple(
        isset($thread['report_id']) ? (int)$thread['report_id'] : null,
        isset($thread['recipient_user_id']) ? (int)$thread['recipient_user_id'] : null,
        isset($thread['recipient_token_id']) ? (int)$thread['recipient_token_id'] : null
    );
}
function backend_messages_send(array $payload): bool {
    return admin_message_send([
        'recipient_user_id' => ctype_digit((string)($payload['recipient'] ?? '')) ? (int)$payload['recipient'] : null,
        'recipient_token_id' => null,
        'report_id' => null,
        'subject' => $payload['subject'] ?? '',
        'message' => $payload['message'] ?? '',
    ]);
}

if (admin_is_post()) {
    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Security validation failed. Please try again.');
        admin_redirect('messages.php');
    }

    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'send_message') {
        $payload = [
            'recipient' => trim((string)($_POST['recipient'] ?? '')),
            'subject' => trim((string)($_POST['subject'] ?? '')),
            'message' => trim((string)($_POST['message'] ?? '')),
            'report_token' => trim((string)($_POST['report_token'] ?? '')),
        ];

        if ($payload['recipient'] === '' || $payload['subject'] === '' || $payload['message'] === '') {
            admin_set_flash('error', 'Recipient, subject, and message are required.');
        } elseif (backend_messages_send($payload)) {
            admin_set_flash('success', 'Message sent successfully.');
        } else {
            admin_set_flash('error', 'Unable to send message.');
        }

        admin_redirect('messages.php');
    }
}

$user = admin_user();
$flash = admin_get_flash();
$currentTheme = admin_current_theme();
$csrfToken = admin_create_csrf_token();
$threads = backend_messages_threads();
$selectedThreadId = (int)($_GET['thread_id'] ?? 0);
$selectedItems = $selectedThreadId > 0 ? backend_messages_thread_items($selectedThreadId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_page_title('Messages'); ?></title>

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
            <a href="<?php echo admin_url('users.php'); ?>"><i class="fas fa-users"></i><span>Users</span></a>
            <a class="active" href="<?php echo admin_url('messages.php'); ?>"><i class="fas fa-envelope"></i><span>Messages</span></a>
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
                <h2>Messages</h2>
                <p>Send administrative messages and review conversation threads.</p>
            </div>

            <div class="admin-topbar-right-v2">
                <button type="button" class="admin-icon-btn-v2" id="themeToggleBtn">
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

            <div class="messages-page-grid-v2">
                <section class="admin-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Compose Message</h3>
                            <p>Send direct communication to a user or report-linked recipient.</p>
                        </div>
                    </div>

                    <form method="POST" class="admin-form-stack-v2">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                        <input type="hidden" name="action" value="send_message">

                        <input type="text" name="recipient" placeholder="Recipient email or username" required>
                        <input type="text" name="report_token" placeholder="Related report token (optional)">
                        <input type="text" name="subject" placeholder="Subject" required>
                        <textarea name="message" rows="8" placeholder="Write the message..." required></textarea>

                        <div class="admin-form-actions-v2">
                            <button type="submit" class="admin-header-btn-v2">
                                <i class="fas fa-paper-plane"></i>
                                <span>Send Message</span>
                            </button>
                        </div>
                    </form>
                </section>

                <section class="admin-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Message Threads</h3>
                            <p>Review recent communication history.</p>
                        </div>
                    </div>

                    <?php if (!$threads): ?>
                        <div class="admin-empty-v2">No message threads found yet.</div>
                    <?php else: ?>
                        <div class="message-thread-list-v2">
                            <?php foreach ($threads as $thread): ?>
                                <a class="message-thread-item-v2" href="<?php echo admin_url('messages.php?thread_id=' . (int)$thread['id']); ?>">
                                    <strong><?php echo admin_h($thread['subject']); ?></strong>
                                    <p><?php echo admin_h($thread['recipient']); ?></p>
                                    <span><?php echo admin_h(admin_format_datetime($thread['updated_at'] ?? null)); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="admin-panel-v2 admin-panel-wide">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Selected Conversation</h3>
                            <p>Thread details and history.</p>
                        </div>
                    </div>

                    <?php if (!$selectedItems): ?>
                        <div class="admin-empty-v2">No thread selected.</div>
                    <?php else: ?>
                        <div class="message-conversation-v2">
                            <?php foreach ($selectedItems as $item): ?>
                                <div class="message-bubble-v2">
                                    <strong><?php echo admin_h($item['subject'] ?? 'Message'); ?></strong>
                                    <p><?php echo nl2br(admin_h($item['message'] ?? '')); ?></p>
                                    <span><?php echo admin_h(admin_format_datetime($item['created_at'] ?? null)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
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