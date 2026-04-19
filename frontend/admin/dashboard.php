<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';
admin_require_login();

$user = admin_user();
$flash = admin_get_flash();
$currentTheme = admin_current_theme();

$stats = backend_fetch_dashboard_stats();
$recentReports = backend_fetch_recent_reports(8);
$priorityQueue = backend_fetch_priority_queue(6);
$activityFeed = backend_fetch_recent_activity(8);
$categories = backend_fetch_category_overview();

$now = new DateTimeImmutable();
$calendarYear = (int)$now->format('Y');
$calendarMonth = (int)$now->format('m');
$todayKey = $now->format('Y-m-d');
$monthCounts = backend_fetch_calendar_report_counts($calendarYear, $calendarMonth);
$calendarWeeks = build_calendar_matrix($calendarYear, $calendarMonth);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_page_title('Dashboard'); ?></title>

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
            <a class="active" href="<?php echo admin_url('dashboard.php'); ?>"><i class="fas fa-table-cells-large"></i><span>Dashboard</span></a>
            <a href="<?php echo admin_url('report_view.php'); ?>"><i class="fas fa-folder-open"></i><span>Reports</span></a>
            <a href="<?php echo admin_url('spam_reports.php'); ?>"><i class="fas fa-shield-virus"></i><span>Spam Reports</span></a>
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
                <h2>Dashboard</h2>
                <p>Overview of reports, moderation workload, users, and date-based submission activity.</p>
            </div>

            <div class="admin-topbar-right-v2">
                <button type="button" class="admin-icon-btn-v2" id="themeToggleBtn">
                    <i class="fas <?php echo $currentTheme === 'light' ? 'fa-sun' : 'fa-moon'; ?>" id="themeToggleIcon"></i>
                </button>

                <a class="admin-header-btn-v2" href="<?php echo admin_url('report_view.php'); ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Manage Reports</span>
                </a>

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

            <div class="admin-stat-grid-v2">
                <div class="admin-stat-card-v2"><div class="stat-icon-wrap"><i class="fas fa-folder-open"></i></div><div class="stat-body"><strong><?php echo (int)$stats['total_reports']; ?></strong><span>Total Reports</span></div></div>
                <div class="admin-stat-card-v2"><div class="stat-icon-wrap"><i class="fas fa-hourglass-half"></i></div><div class="stat-body"><strong><?php echo (int)$stats['pending_reports']; ?></strong><span>Pending</span></div></div>
                <div class="admin-stat-card-v2"><div class="stat-icon-wrap"><i class="fas fa-badge-check"></i></div><div class="stat-body"><strong><?php echo (int)$stats['verified_reports']; ?></strong><span>Verified</span></div></div>
                <div class="admin-stat-card-v2"><div class="stat-icon-wrap"><i class="fas fa-gears"></i></div><div class="stat-body"><strong><?php echo (int)$stats['in_progress_reports']; ?></strong><span>In Progress</span></div></div>
                <div class="admin-stat-card-v2"><div class="stat-icon-wrap"><i class="fas fa-circle-check"></i></div><div class="stat-body"><strong><?php echo (int)$stats['resolved_reports']; ?></strong><span>Resolved</span></div></div>
                <div class="admin-stat-card-v2 warning"><div class="stat-icon-wrap"><i class="fas fa-shield-virus"></i></div><div class="stat-body"><strong><?php echo (int)$stats['spam_reports']; ?></strong><span>Spam Flagged</span></div></div>
            </div>

            <div class="admin-dashboard-grid-v2 dashboard-with-calendar-v2">
                <section class="admin-panel-v2 admin-panel-large">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Recent Reports</h3>
                            <p>Newest submissions waiting for review or action.</p>
                        </div>
                        <a href="<?php echo admin_url('report_view.php'); ?>" class="panel-link-v2">See All</a>
                    </div>

                    <?php if (!$recentReports): ?>
                        <div class="admin-empty-v2">No reports found in the database yet.</div>
                    <?php else: ?>
                        <div class="report-list-v2">
                            <?php foreach ($recentReports as $report): ?>
                                <div class="report-row-v2">
                                    <div class="report-row-main-v2">
                                        <div class="report-row-top-v2">
                                            <strong><?php echo admin_h($report['title']); ?></strong>
                                            <span class="token-v2"><?php echo admin_h($report['token']); ?></span>
                                        </div>
                                        <div class="report-meta-v2">
                                            <span><i class="fas fa-layer-group"></i> <?php echo admin_h(admin_prettify((string)$report['category'])); ?></span>
                                            <span><i class="fas fa-location-dot"></i> <?php echo admin_h($report['location']); ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo admin_h(admin_format_datetime($report['created_at'])); ?></span>
                                            <span><i class="fas fa-paperclip"></i> <?php echo (int)($report['media_count'] ?? 0); ?> media</span>
                                        </div>
                                    </div>

                                    <div class="report-row-badges-v2">
                                        <span class="badge <?php echo admin_priority_badge_class((string)$report['priority']); ?>"><?php echo admin_h(admin_prettify((string)$report['priority'])); ?></span>
                                        <span class="badge <?php echo admin_status_badge_class((string)$report['status']); ?>"><?php echo admin_h(admin_prettify((string)$report['status'])); ?></span>
                                        <?php if (!empty($report['is_spam'])): ?><span class="badge danger">Spam</span><?php endif; ?>
                                        <a href="<?php echo admin_url('report_view.php?report_id=' . (int)$report['id']); ?>" class="mini-action-v2">Open</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="admin-panel-v2 calendar-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3><?php echo admin_h($now->format('F Y')); ?></h3>
                            <p>Report count by date.</p>
                        </div>
                    </div>

                    <div class="calendar-grid-v2">
                        <div class="calendar-weekdays-v2">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>

                        <div class="calendar-days-v2">
                            <?php foreach ($calendarWeeks as $week): ?>
                                <?php foreach ($week as $day): ?>
                                    <?php
                                    $dateKey = $day->format('Y-m-d');
                                    $count = (int)($monthCounts[$dateKey] ?? 0);
                                    $isCurrentMonth = $day->format('m') === $now->format('m');
                                    $isToday = $dateKey === $todayKey;
                                    ?>
                                    <div class="calendar-day-v2 <?php echo !$isCurrentMonth ? 'muted' : ''; ?> <?php echo $isToday ? 'today' : ''; ?>">
                                        <strong><?php echo admin_h($day->format('j')); ?></strong>
                                        <span><?php echo $count; ?> report<?php echo $count === 1 ? '' : 's'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <section class="admin-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Priority Queue</h3>
                            <p>Urgent and sensitive reports to review first.</p>
                        </div>
                    </div>

                    <?php if (!$priorityQueue): ?>
                        <div class="admin-empty-v2">No priority queue items available.</div>
                    <?php else: ?>
                        <div class="priority-list-v2">
                            <?php foreach ($priorityQueue as $item): ?>
                                <div class="priority-item-v2">
                                    <div class="priority-top-v2">
                                        <strong><?php echo admin_h($item['title']); ?></strong>
                                        <span class="token-v2"><?php echo admin_h($item['token']); ?></span>
                                    </div>
                                    <p><?php echo admin_h($item['location'] ?? ''); ?></p>
                                    <div class="table-actions">
                                        <span class="badge <?php echo admin_priority_badge_class((string)$item['priority']); ?>"><?php echo admin_h(admin_prettify((string)$item['priority'])); ?></span>
                                        <span class="badge <?php echo admin_status_badge_class((string)$item['status']); ?>"><?php echo admin_h(admin_prettify((string)$item['status'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="admin-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Category Overview</h3>
                            <p>Distribution of report volume.</p>
                        </div>
                    </div>

                    <?php if (!$categories): ?>
                        <div class="admin-empty-v2">No category summary found yet.</div>
                    <?php else: ?>
                        <div class="category-list-v2">
                            <?php foreach ($categories as $row): ?>
                                <div class="category-item-v2">
                                    <span><?php echo admin_h($row['label']); ?></span>
                                    <strong><?php echo (int)$row['count']; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="admin-panel-v2 admin-panel-wide">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Recent Administrative Activity</h3>
                            <p>Latest review and moderation actions.</p>
                        </div>
                    </div>

                    <?php if (!$activityFeed): ?>
                        <div class="admin-empty-v2">No recent administrative activity found.</div>
                    <?php else: ?>
                        <div class="activity-list-v2">
                            <?php foreach ($activityFeed as $activity): ?>
                                <div class="activity-item-v2">
                                    <div class="activity-dot-v2"></div>
                                    <div>
                                        <strong><?php echo admin_h($activity['title']); ?></strong>
                                        <p><?php echo admin_h($activity['description']); ?></p>
                                    </div>
                                    <span><?php echo admin_h(admin_format_datetime($activity['created_at'])); ?></span>
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
})();
</script>
</body>
</html>