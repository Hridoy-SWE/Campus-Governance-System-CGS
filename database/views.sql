CREATE VIEW IF NOT EXISTS vw_report_summary AS
SELECT
    r.id,
    t.token AS tracking_token,
    u.username AS reporter_username,
    u.full_name AS reporter_name,
    d.name AS department_name,
    r.category,
    r.title,
    r.location,
    r.priority,
    r.status,
    r.spam_score,
    r.spam_reason,
    r.views_count,
    r.created_at,
    r.updated_at,
    (SELECT COUNT(*) FROM report_media rm WHERE rm.report_id = r.id) AS media_count,
    (SELECT COUNT(*) FROM comments c WHERE c.report_id = r.id) AS comment_count
FROM reports r
LEFT JOIN tokens t ON r.token_id = t.id
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN departments d ON r.department_id = d.id;

CREATE VIEW IF NOT EXISTS vw_department_summary AS
SELECT
    d.id,
    d.code,
    d.name,
    COUNT(r.id) AS total_reports,
    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) AS pending_reports,
    SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) AS verified_reports,
    SUM(CASE WHEN r.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_reports,
    SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports,
    SUM(CASE WHEN r.status = 'spam' THEN 1 ELSE 0 END) AS spam_reports
FROM departments d
LEFT JOIN reports r ON r.department_id = d.id
GROUP BY d.id, d.code, d.name;

CREATE VIEW IF NOT EXISTS vw_daily_report_counts AS
SELECT
    DATE(created_at) AS report_date,
    COUNT(*) AS total_reports,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_reports,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports
FROM reports
GROUP BY DATE(created_at)
ORDER BY report_date;

CREATE VIEW IF NOT EXISTS vw_monthly_report_counts AS
SELECT
    STRFTIME('%Y-%m', created_at) AS report_month,
    COUNT(*) AS total_reports,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_reports,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports
FROM reports
GROUP BY STRFTIME('%Y-%m', created_at)
ORDER BY report_month;

CREATE VIEW IF NOT EXISTS vw_status_distribution AS
SELECT
    status,
    COUNT(*) AS total_reports
FROM reports
GROUP BY status;

CREATE VIEW IF NOT EXISTS vw_priority_distribution AS
SELECT
    priority,
    COUNT(*) AS total_reports
FROM reports
GROUP BY priority;

CREATE VIEW IF NOT EXISTS vw_spam_summary AS
SELECT
    DATE(created_at) AS report_date,
    COUNT(*) AS total_flagged,
    AVG(spam_score) AS avg_spam_score
FROM reports
WHERE status = 'spam' OR spam_score > 0
GROUP BY DATE(created_at)
ORDER BY report_date;

CREATE VIEW IF NOT EXISTS vw_user_role_summary AS
SELECT
    role,
    COUNT(*) AS total_users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_users
FROM users
GROUP BY role;

CREATE VIEW IF NOT EXISTS vw_resolution_performance AS
SELECT
    department_id,
    COUNT(*) AS resolved_reports,
    AVG((JULIANDAY(resolved_at) - JULIANDAY(created_at)) * 24.0) AS avg_resolution_hours
FROM reports
WHERE resolved_at IS NOT NULL AND status = 'resolved'
GROUP BY department_id;
