<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';
admin_require_login();

/*
|--------------------------------------------------------------------------
| BACKEND PLACEHOLDER FUNCTIONS
|--------------------------------------------------------------------------
| Replace these with real DB / backend logic later.
|--------------------------------------------------------------------------
*/

function backend_fetch_reports(array $filters = []): array { return admin_reports_fetch($filters); }
function backend_fetch_report_by_id(int $reportId): ?array { return admin_report_find($reportId); }
function backend_fetch_report_media(int $reportId): array { return admin_report_media($reportId); }
function backend_fetch_report_timeline(int $reportId): array { return admin_report_timeline($reportId); }
function backend_update_report_status(int $reportId, string $status, string $note = '', string $resolutionNotes = ''): bool { return admin_report_update_status($reportId, $status, $note, $resolutionNotes); }
function backend_update_report_details(int $reportId, array $payload): bool { return admin_report_update_details($reportId, $payload); }
function backend_mark_report_spam(int $reportId, bool $isSpam, string $reason = ''): bool { return admin_report_mark_spam($reportId, $isSpam, $reason); }
function backend_delete_report(int $reportId): bool { return admin_report_delete($reportId); }
function backend_add_report_note(int $reportId, string $note): bool { return admin_report_add_note($reportId, $note); }
function backend_send_report_message(int $reportId, string $subject, string $message): bool { return admin_report_send_message($reportId, $subject, $message); }
/*
|--------------------------------------------------------------------------
| HANDLE ACTIONS
|--------------------------------------------------------------------------
*/
if (admin_is_post()) {
    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Security validation failed. Please try again.');
        admin_redirect('report_view.php');
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $reportId = (int)($_POST['report_id'] ?? 0);

    if ($action === 'change_status') {
        $status = trim((string)($_POST['status'] ?? 'pending'));
        $statusNote = trim((string)($_POST['status_note'] ?? ''));
        $resolutionNotes = trim((string)($_POST['resolution_notes'] ?? ''));

        if ($reportId <= 0) {
            admin_set_flash('error', 'Invalid report selected.');
        } elseif (backend_update_report_status($reportId, $status, $statusNote, $resolutionNotes)) {
            admin_set_flash('success', 'Report status updated successfully.');
        } else {
            admin_set_flash('error', 'Unable to update report status.');
        }

        admin_redirect('report_view.php?report_id=' . $reportId);
    }

    if ($action === 'update_details') {
        $payload = [
            'title' => trim((string)($_POST['title'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? '')),
            'priority' => trim((string)($_POST['priority'] ?? 'medium')),
            'location' => trim((string)($_POST['location'] ?? '')),
        ];

        if ($reportId <= 0 || $payload['title'] === '' || $payload['description'] === '') {
            admin_set_flash('error', 'Required report fields are missing.');
        } elseif (backend_update_report_details($reportId, $payload)) {
            admin_set_flash('success', 'Report details updated successfully.');
        } else {
            admin_set_flash('error', 'Unable to update report details.');
        }

        admin_redirect('report_view.php?report_id=' . $reportId);
    }

    if ($action === 'mark_spam') {
        $isSpam = isset($_POST['is_spam']) && $_POST['is_spam'] === '1';
        $reason = trim((string)($_POST['spam_reason'] ?? ''));

        if ($reportId <= 0) {
            admin_set_flash('error', 'Invalid report selected.');
        } elseif (backend_mark_report_spam($reportId, $isSpam, $reason)) {
            admin_set_flash('success', $isSpam ? 'Report marked as spam.' : 'Spam flag removed.');
        } else {
            admin_set_flash('error', 'Unable to update spam state.');
        }

        admin_redirect('report_view.php?report_id=' . $reportId);
    }

    if ($action === 'add_note') {
        $note = trim((string)($_POST['internal_note'] ?? ''));

        if ($reportId <= 0 || $note === '') {
            admin_set_flash('error', 'Note cannot be empty.');
        } elseif (backend_add_report_note($reportId, $note)) {
            admin_set_flash('success', 'Internal note added successfully.');
        } else {
            admin_set_flash('error', 'Unable to add internal note.');
        }

        admin_redirect('report_view.php?report_id=' . $reportId);
    }

    if ($action === 'send_message') {
        $subject = trim((string)($_POST['message_subject'] ?? ''));
        $message = trim((string)($_POST['message_body'] ?? ''));

        if ($reportId <= 0 || $subject === '' || $message === '') {
            admin_set_flash('error', 'Message subject and body are required.');
        } elseif (backend_send_report_message($reportId, $subject, $message)) {
            admin_set_flash('success', 'Report-linked message sent successfully.');
        } else {
            admin_set_flash('error', 'Unable to send report-linked message.');
        }

        admin_redirect('report_view.php?report_id=' . $reportId);
    }

    if ($action === 'delete_report') {
        if ($reportId <= 0) {
            admin_set_flash('error', 'Invalid report selected.');
            admin_redirect('report_view.php');
        }

        if (backend_delete_report($reportId)) {
            admin_set_flash('success', 'Report deleted successfully.');
            admin_redirect('report_view.php');
        } else {
            admin_set_flash('error', 'Unable to delete report.');
            admin_redirect('report_view.php?report_id=' . $reportId);
        }
    }
}


function report_status_button_map(): array
{
    return [
        ['label' => 'Approve', 'status' => 'verified', 'icon' => 'fa-circle-check', 'class' => 'success'],
        ['label' => 'Under Review', 'status' => 'in_progress', 'icon' => 'fa-hourglass-half', 'class' => 'secondary'],
        ['label' => 'Mark Resolved', 'status' => 'resolved', 'icon' => 'fa-check-double', 'class' => 'primary'],
        ['label' => 'Return Pending', 'status' => 'pending', 'icon' => 'fa-rotate-left', 'class' => 'ghost'],
    ];
}

/*
|--------------------------------------------------------------------------
| PAGE DATA
|--------------------------------------------------------------------------
*/
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$categoryFilter = trim((string)($_GET['category'] ?? 'all'));
$spamFilter = trim((string)($_GET['spam'] ?? 'all'));
$selectedReportId = (int)($_GET['report_id'] ?? 0);

$reports = backend_fetch_reports([
    'search' => $search,
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'spam' => $spamFilter,
]);

$selectedReport = $selectedReportId > 0 ? backend_fetch_report_by_id($selectedReportId) : null;
if (!$selectedReport && !empty($reports)) {
    $selectedReport = backend_fetch_report_by_id((int)$reports[0]['id']);
    $selectedReportId = (int)($selectedReport['id'] ?? 0);
}
$selectedMedia = $selectedReport ? backend_fetch_report_media((int)$selectedReport['id']) : [];
$timeline = $selectedReport ? backend_fetch_report_timeline((int)$selectedReport['id']) : [];

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
    <title><?php echo admin_page_title('Report Management'); ?></title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body data-theme="<?php echo admin_h($currentTheme); ?>">
    <div class="admin-shell">
        <aside class="admin-sidebar-v2">
            <div class="admin-brand-v2">
                <div class="admin-brand-icon-v2">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div class="admin-brand-copy-v2">
                    <h1>Campus Governance</h1>
                    <p>Administrative Console</p>
                </div>
            </div>

<nav class="admin-menu-v2">
    <a href="<?php echo admin_url('dashboard.php'); ?>">
        <i class="fas fa-table-cells-large"></i>
        <span>Dashboard</span>
    </a>
    <a class="active" href="<?php echo admin_url('report_view.php'); ?>">
        <i class="fas fa-folder-open"></i>
        <span>Reports</span>
    </a>
    <a href="<?php echo admin_url('spam_reports.php'); ?>">
        <i class="fas fa-shield-virus"></i>
        <span>Spam Reports</span>
    </a>
    <a href="<?php echo admin_url('users.php'); ?>">
        <i class="fas fa-users"></i>
        <span>Users</span>
    </a>
    <a href="<?php echo admin_url('messages.php'); ?>">
        <i class="fas fa-envelope"></i>
        <span>Messages</span>
    </a>
</nav>
            <div class="admin-sidebar-footer-v2">
                <a class="admin-logout-v2" href="<?php echo admin_url('logout.php'); ?>">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <main class="admin-main-v2">
            <header class="admin-topbar-v2">
                <div class="admin-topbar-left-v2">
                    <h2>Report Management</h2>
                    <p>Review, approve, resolve, delete, moderate spam, and inspect report media clearly.</p>
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
                                <h3>All Reports</h3>
                                <p>Use filters to locate reports and open one for full moderation.</p>
                            </div>
                        </div>

                        <form method="GET" class="admin-report-filter-grid-v2">
                            <input
                                type="text"
                                name="search"
                                value="<?php echo admin_h($search); ?>"
                                placeholder="Search token, title, category, location..."
                            >

                            <select name="status">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>

                            <select name="category">
                                <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>All categories</option>
                                <option value="academic" <?php echo $categoryFilter === 'academic' ? 'selected' : ''; ?>>Academic</option>
                                <option value="facility" <?php echo $categoryFilter === 'facility' ? 'selected' : ''; ?>>Facility</option>
                                <option value="transport" <?php echo $categoryFilter === 'transport' ? 'selected' : ''; ?>>Transport</option>
                                <option value="harassment" <?php echo $categoryFilter === 'harassment' ? 'selected' : ''; ?>>Harassment</option>
                                <option value="it" <?php echo $categoryFilter === 'it' ? 'selected' : ''; ?>>IT</option>
                                <option value="administrative" <?php echo $categoryFilter === 'administrative' ? 'selected' : ''; ?>>Administrative</option>
                            </select>

                            <select name="spam">
                                <option value="all" <?php echo $spamFilter === 'all' ? 'selected' : ''; ?>>All reports</option>
                                <option value="flagged" <?php echo $spamFilter === 'flagged' ? 'selected' : ''; ?>>Spam flagged</option>
                                <option value="clean" <?php echo $spamFilter === 'clean' ? 'selected' : ''; ?>>Clean only</option>
                            </select>

                            <div class="admin-report-filter-actions-v2">
                                <button class="admin-header-btn-v2" type="submit">
                                    <i class="fas fa-filter"></i>
                                    <span>Apply</span>
                                </button>
                                <a class="admin-header-btn-v2 secondary" href="<?php echo admin_url('report_view.php'); ?>">
                                    <i class="fas fa-rotate-left"></i>
                                    <span>Reset</span>
                                </a>
                            </div>
                        </form>

                        <?php if (!$reports): ?>
                            <div class="admin-empty-v2">No reports found in the database for the selected filters.</div>
                        <?php else: ?>
                            <div class="admin-report-table-wrap-v2">
                                <table class="admin-report-table-v2">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Token</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Location</th>
                                            <th>Media</th>
                                            <th>Spam</th>
                                            <th>Open</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr class="<?php echo (int)$selectedReportId === (int)$report['id'] ? 'is-selected' : ''; ?>" data-open-url="<?php echo admin_url('report_view.php?report_id=' . (int)$report['id']); ?>">
                                                <td><?php echo (int)$report['id']; ?></td>
                                                <td class="token-v2"><?php echo admin_h($report['token']); ?></td>
                                                <td>
                                                    <strong><?php echo admin_h($report['title']); ?></strong>
                                                </td>
                                                <td><?php echo admin_h(admin_prettify((string)$report['category'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo admin_priority_badge_class((string)$report['priority']); ?>">
                                                        <?php echo admin_h(admin_prettify((string)$report['priority'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo admin_status_badge_class((string)$report['status']); ?>">
                                                        <?php echo admin_h(admin_prettify((string)$report['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo admin_h($report['location']); ?></td>
                                                <td><?php echo (int)($report['media_count'] ?? 0); ?></td>
                                                <td>
                                                    <?php if (!empty($report['is_spam'])): ?>
                                                        <span class="badge danger">Flagged</span>
                                                    <?php else: ?>
                                                        <span class="badge verified">Clean</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a class="mini-action-v2" href="<?php echo admin_url('report_view.php?report_id=' . (int)$report['id']); ?>">
                                                        Select
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <aside class="admin-panel-v2 admin-report-detail-panel-v2">
                        <?php if (!$selectedReport): ?>
                            <div class="panel-head-v2">
                                <div>
                                    <h3>Report Details</h3>
                                    <p>Select a report to review details, evidence, status actions, and notes.</p>
                                </div>
                            </div>

                            <div class="admin-empty-v2">
                                No report is currently selected.
                            </div>
                        <?php else: ?>
                            <div class="panel-head-v2 admin-report-head-v2">
                                <div>
                                    <h3><?php echo admin_h($selectedReport['title']); ?></h3>
                                    <p><?php echo admin_h($selectedReport['token']); ?> · Submitted <?php echo admin_h(admin_format_datetime($selectedReport['created_at'] ?? null)); ?></p>
                                </div>
                                <a class="mini-action-v2" href="<?php echo admin_url('report_view.php?report_id=' . (int)$selectedReport['id']); ?>">
                                    Refresh
                                </a>
                            </div>

                            <div class="admin-detail-card-v2 admin-report-summary-v2">
                                <div class="admin-detail-grid-v2">
                                    <div>
                                        <small>Status</small>
                                        <strong><span class="badge <?php echo admin_status_badge_class((string)$selectedReport['status']); ?>"><?php echo admin_h(admin_prettify((string)$selectedReport['status'])); ?></span></strong>
                                    </div>
                                    <div>
                                        <small>Priority</small>
                                        <strong><span class="badge <?php echo admin_priority_badge_class((string)$selectedReport['priority']); ?>"><?php echo admin_h(admin_prettify((string)$selectedReport['priority'])); ?></span></strong>
                                    </div>
                                    <div>
                                        <small>Category</small>
                                        <strong><?php echo admin_h(admin_prettify((string)$selectedReport['category'])); ?></strong>
                                    </div>
                                    <div>
                                        <small>Department</small>
                                        <strong><?php echo admin_h($selectedReport['department_name'] ?: 'Not assigned'); ?></strong>
                                    </div>
                                    <div>
                                        <small>Location</small>
                                        <strong><?php echo admin_h($selectedReport['location'] ?: 'Not provided'); ?></strong>
                                    </div>
                                    <div>
                                        <small>Reporter</small>
                                        <strong><?php echo !empty($selectedReport['is_anonymous']) ? 'Anonymous' : admin_h($selectedReport['notify_email'] ?: 'Tracked by token'); ?></strong>
                                    </div>
                                </div>
                                <div class="admin-detail-description-v2">
                                    <?php echo nl2br(admin_h((string)$selectedReport['description'])); ?>
                                </div>
                                <?php if (!empty($selectedReport['resolution_notes'])): ?>
                                    <div class="admin-callout-v2 success">
                                        <strong>Resolution notes</strong>
                                        <p><?php echo nl2br(admin_h((string)$selectedReport['resolution_notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="admin-detail-section-v2">
                                <h4>Admin Actions</h4>
                                <form method="POST" class="admin-form-stack-v2">
                                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">

                                    <div class="admin-action-grid-v2 admin-action-grid-2col-v2">
                                        <?php foreach (report_status_button_map() as $item): ?>
                                            <button type="submit" name="status" value="<?php echo admin_h($item['status']); ?>" class="admin-status-action-btn-v2 <?php echo admin_h($item['class']); ?>">
                                                <i class="fas <?php echo admin_h($item['icon']); ?>"></i>
                                                <span><?php echo admin_h($item['label']); ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>

                                    <input type="text" name="status_note" placeholder="Short admin note for timeline (optional)">
                                    <textarea name="resolution_notes" placeholder="Resolution notes (used when marking resolved)"><?php echo admin_h((string)($selectedReport['resolution_notes'] ?? '')); ?></textarea>
                                </form>
                            </div>

                            <div class="admin-detail-section-v2">
                                <h4>Edit Report</h4>
                                <form method="POST" class="admin-form-stack-v2">
                                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="update_details">
                                    <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">

                                    <input type="text" name="title" value="<?php echo admin_h((string)$selectedReport['title']); ?>" placeholder="Title" required>
                                    <textarea name="description" placeholder="Description" required><?php echo admin_h((string)$selectedReport['description']); ?></textarea>

                                    <div class="admin-form-two-v2">
                                        <select name="category">
                                            <?php foreach (['Academic','Facility','Safety','Network','Harassment','Administration','Other'] as $category): ?>
                                                <option value="<?php echo admin_h($category); ?>" <?php echo strcasecmp((string)$selectedReport['category'], $category) === 0 ? 'selected' : ''; ?>><?php echo admin_h($category); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="location" value="<?php echo admin_h((string)$selectedReport['location']); ?>" placeholder="Location">
                                    </div>

                                    <div class="admin-form-two-v2">
                                        <select name="priority">
                                            <?php foreach (['low', 'medium', 'high', 'critical'] as $priority): ?>
                                                <option value="<?php echo admin_h($priority); ?>" <?php echo strtolower((string)$selectedReport['priority']) === $priority ? 'selected' : ''; ?>><?php echo admin_h(admin_prettify($priority)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" value="<?php echo admin_h((string)$selectedReport['token']); ?>" disabled>
                                    </div>

                                    <button type="submit" class="admin-header-btn-v2">
                                        <i class="fas fa-pen-to-square"></i>
                                        <span>Save Changes</span>
                                    </button>
                                </form>
                            </div>

                            <div class="admin-detail-section-v2">
                                <h4>Evidence & Media</h4>
                                <?php if (!$selectedMedia): ?>
                                    <div class="admin-empty-v2 small">No media attached to this report.</div>
                                <?php else: ?>
                                    <div class="admin-media-grid-v2 admin-media-grid-wide-v2">
                                        <?php foreach ($selectedMedia as $media): ?>
                                            <div class="admin-media-card-v2">
                                                <?php if (($media['file_type'] ?? '') === 'video'): ?>
                                                    <video controls preload="metadata">
                                                        <source src="<?php echo admin_h($media['file_url']); ?>" type="<?php echo admin_h($media['mime_type'] ?? 'video/mp4'); ?>">
                                                    </video>
                                                <?php elseif (($media['file_type'] ?? '') === 'image'): ?>
                                                    <a href="<?php echo admin_h($media['file_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                        <img src="<?php echo admin_h($media['file_url']); ?>" alt="<?php echo admin_h($media['file_name'] ?? 'Report image'); ?>">
                                                    </a>
                                                <?php else: ?>
                                                    <div class="admin-document-card-v2">
                                                        <i class="fas fa-file-lines"></i>
                                                        <div>
                                                            <strong><?php echo admin_h($media['file_name'] ?? 'Attachment'); ?></strong>
                                                            <p><?php echo admin_h($media['mime_type'] ?? 'Document'); ?></p>
                                                        </div>
                                                        <a class="mini-action-v2" href="<?php echo admin_h($media['file_url']); ?>" target="_blank" rel="noopener noreferrer">Open</a>
                                                    </div>
                                                <?php endif; ?>
                                                <span><?php echo admin_h($media['file_name'] ?? 'Attached file'); ?><?php if (!empty($media['file_size'])): ?> · <?php echo admin_h(number_format(((int)$media['file_size']) / 1024, 1)); ?> KB<?php endif; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="admin-detail-section-v2 admin-two-col-section-v2">
                                <div>
                                    <h4>Spam Control</h4>
                                    <form method="POST" class="admin-form-stack-v2">
                                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                        <input type="hidden" name="action" value="mark_spam">
                                        <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">

                                        <select name="is_spam">
                                            <option value="0" <?php echo empty($selectedReport['spam_score']) ? 'selected' : ''; ?>>Keep as valid report</option>
                                            <option value="1" <?php echo !empty($selectedReport['spam_score']) ? 'selected' : ''; ?>>Flag as spam</option>
                                        </select>
                                        <input type="text" name="spam_reason" value="<?php echo admin_h((string)($selectedReport['spam_reason'] ?? '')); ?>" placeholder="Reason for spam decision">
                                        <button type="submit" class="admin-header-btn-v2 secondary">
                                            <i class="fas fa-shield-virus"></i>
                                            <span>Update Spam State</span>
                                        </button>
                                    </form>
                                </div>

                                <div>
                                    <h4>Internal Note</h4>
                                    <form method="POST" class="admin-form-stack-v2">
                                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                        <input type="hidden" name="action" value="add_note">
                                        <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">
                                        <textarea name="internal_note" placeholder="Write a note for moderation history or staff handoff..." required></textarea>
                                        <button type="submit" class="admin-header-btn-v2 secondary">
                                            <i class="fas fa-note-sticky"></i>
                                            <span>Add Note</span>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="admin-detail-section-v2">
                                <h4>Moderation Timeline</h4>
                                <?php if (!$timeline): ?>
                                    <div class="admin-empty-v2 small">No moderation timeline yet.</div>
                                <?php else: ?>
                                    <div class="admin-timeline-list-v2">
                                        <?php foreach ($timeline as $item): ?>
                                            <div class="admin-timeline-item-v2">
                                                <strong><?php echo admin_h($item['title'] ?? 'Update'); ?></strong>
                                                <p><?php echo admin_h($item['description'] ?? ''); ?></p>
                                                <span><?php echo admin_h(admin_format_datetime($item['created_at'] ?? null)); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="admin-detail-section-v2 danger-zone">
                                <h4>Danger Zone</h4>
                                <p class="admin-muted-v2">Deleting a report removes its timeline, notes, and media links from the moderation interface.</p>
                                <form method="POST" onsubmit="return confirm('Delete this report permanently?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_report">
                                    <input type="hidden" name="report_id" value="<?php echo (int)$selectedReport['id']; ?>">
                                    <button type="submit" class="admin-danger-btn-v2">
                                        <i class="fas fa-trash"></i>
                                        <span>Delete Report</span>
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

    <script>
        document.querySelectorAll('[data-open-url]').forEach(row => {
            row.addEventListener('click', event => {
                const ignore = event.target.closest('a, button, input, textarea, select, label, form');
                if (ignore) return;
                const url = row.getAttribute('data-open-url');
                if (url) window.location.href = url;
            });
        });
    </script>

</body>
</html>