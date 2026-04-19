<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';
admin_require_login();

/*
|--------------------------------------------------------------------------
| BACKEND PLACEHOLDERS
|--------------------------------------------------------------------------
*/

function backend_spam_fetch(array $filters = []): array { return admin_spam_fetch($filters); }
function backend_spam_find(int $reportId): ?array { return admin_report_find($reportId); }
function backend_spam_media(int $reportId): array { return admin_report_media($reportId); }
function backend_spam_restore(int $reportId): bool { return admin_report_mark_spam($reportId, false, ''); }
function backend_spam_delete(int $reportId): bool { return admin_report_delete($reportId); }
function backend_spam_confirm(int $reportId, string $reason): bool { return admin_report_mark_spam($reportId, true, $reason); }

if (admin_is_post()) {
    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Security validation failed. Please try again.');
        admin_redirect('spam_reports.php');
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $reportId = (int)($_POST['report_id'] ?? 0);

    if ($action === 'restore') {
        if ($reportId > 0 && backend_spam_restore($reportId)) {
            admin_set_flash('success', 'Spam flag removed and report restored.');
        } else {
            admin_set_flash('error', 'Unable to restore report.');
        }
        admin_redirect('spam_reports.php');
    }

    if ($action === 'confirm_spam') {
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($reportId > 0 && backend_spam_confirm($reportId, $reason)) {
            admin_set_flash('success', 'Spam confirmed successfully.');
        } else {
            admin_set_flash('error', 'Unable to confirm spam.');
        }
        admin_redirect('spam_reports.php?report_id=' . $reportId);
    }

    if ($action === 'delete') {
        if ($reportId > 0 && backend_spam_delete($reportId)) {
            admin_set_flash('success', 'Spam report deleted successfully.');
        } else {
            admin_set_flash('error', 'Unable to delete spam report.');
        }
        admin_redirect('spam_reports.php');
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$selectedReportId = (int)($_GET['report_id'] ?? 0);

$spamReports = backend_spam_fetch(['search' => $search]);
$selectedReport = $selectedReportId > 0 ? backend_spam_find($selectedReportId) : null;
$selectedMedia = $selectedReport ? backend_spam_media((int)$selectedReport['id']) : [];

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
    <title><?php echo admin_page_title('Spam Reports'); ?></title>

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
            <a class="active" href="<?php echo admin_url('spam_reports.php'); ?>"><i class="fas fa-shield-virus"></i><span>Spam Reports</span></a>
            <a href="<?php echo admin_url('users.php'); ?>"><i class="fas fa-users"></i><span>Users</span></a>
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
                <h2>Spam Reports</h2>
                <p>Dedicated moderation queue for suspicious, repeated, or policy-violating submissions.</p>
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

            <div class="admin-report-layout-v2">
                <section class="admin-panel-v2 admin-report-list-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Flagged Queue</h3>
                            <p>Review suspicious reports before restore or removal.</p>
                        </div>
                    </div>

                    <form method="GET" class="admin-form-stack-v2" style="margin-bottom:16px;">
                        <input type="text" name="search" value="<?php echo admin_h($search); ?>" placeholder="Search token, title, reason...">
                        <div class="admin-form-actions-v2">
                            <button class="admin-header-btn-v2" type="submit"><i class="fas fa-filter"></i><span>Search</span></button>
                        </div>
                    </form>

                    <?php if (!$spamReports): ?>
                        <div class="admin-empty-v2">No spam reports found.</div>
                    <?php else: ?>
                        <div class="spam-list-v2">
                            <?php foreach ($spamReports as $row): ?>
                                <a class="spam-item-v2" href="<?php echo admin_url('spam_reports.php?report_id=' . (int)$row['id']); ?>">
                                    <strong><?php echo admin_h($row['title']); ?></strong>
                                    <p><?php echo admin_h($row['token']); ?></p>
                                    <span><?php echo admin_h($row['spam_reason'] ?? 'No reason recorded'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="admin-panel-v2 admin-report-detail-panel-v2">
                    <?php if (!$selectedReport): ?>
                        <div class="panel-head-v2">
                            <div>
                                <h3>Spam Review</h3>
                                <p>Select a flagged report to inspect its details and media.</p>
                            </div>
                        </div>
                        <div class="admin-empty-v2">No spam report selected.</div>
                    <?php else: ?>
                        <div class="panel-head-v2">
                            <div>
                                <h3><?php echo admin_h($selectedReport['title']); ?></h3>
                                <p><?php echo admin_h($selectedReport['token']); ?></p>
                            </div>
                        </div>

                        <div class="admin-detail-card-v2">
                            <div class="admin-detail-grid-v2">
                                <div><small>Reason</small><strong><?php echo admin_h($selectedReport['spam_reason'] ?? 'No reason'); ?></strong></div>
                                <div><small>Spam Score</small><strong><?php echo admin_h((string)($selectedReport['spam_score'] ?? 'N/A')); ?></strong></div>
                                <div><small>Category</small><strong><?php echo admin_h(admin_prettify((string)($selectedReport['category'] ?? ''))); ?></strong></div>
                                <div><small>Location</small><strong><?php echo admin_h($selectedReport['location'] ?? ''); ?></strong></div>
                            </div>
                            <div class="admin-detail-description-v2"><?php echo nl2br(admin_h((string)($selectedReport['description'] ?? ''))); ?></div>
                        </div>

                        <div class="admin-detail-section-v2">
                            <h4>Attached Media</h4>
                            <?php if (!$selectedMedia): ?>
                                <div class="admin-empty-v2 small">No media attached.</div>
                            <?php else: ?>
                                <div class="admin-media-grid-v2">
                                    <?php foreach ($selectedMedia as $media): ?>
                                        <div class="admin-media-card-v2">
                                            <?php if (($media['file_type'] ?? '') === 'video'): ?>
                                                <video controls preload="metadata">
                                                    <source src="<?php echo admin_h($media['file_url']); ?>">
                                                </video>
                                            <?php else: ?>
                                                <a href="<?php echo admin_h($media['file_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                    <img src="<?php echo admin_h($media['file_url']); ?>" alt="<?php echo admin_h($media['file_name'] ?? 'Report media'); ?>">
                                                </a>
                                            <?php endif; ?>
                                            <span><?php echo admin_h($media['file_name'] ?? 'Attached file'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-detail-section-v2">
                            <h4>Moderation Actions</h4>
                            <div class="admin-form-actions-v2 wrap">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">
                                    <button type="submit" class="admin-header-btn-v2 secondary">
                                        <i class="fas fa-rotate-left"></i>
                                        <span>Restore to Clean</span>
                                    </button>
                                </form>
                            </div>

                            <form method="POST" class="admin-form-stack-v2" style="margin-top:12px;">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                <input type="hidden" name="action" value="confirm_spam">
                                <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">

                                <input type="text" name="reason" placeholder="Confirm spam reason">
                                <button type="submit" class="admin-header-btn-v2 secondary">
                                    <i class="fas fa-shield-virus"></i>
                                    <span>Confirm Spam</span>
                                </button>
                            </form>
                        </div>

                        <div class="admin-detail-section-v2 danger-zone">
                            <h4>Delete Permanently</h4>
                            <form method="POST" onsubmit="return confirm('Delete this spam report permanently?');">
                                <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">
                                <button type="submit" class="admin-danger-btn-v2">
                                    <i class="fas fa-trash"></i>
                                    <span>Delete Spam Report</span>
                                </button>
                            </form>
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