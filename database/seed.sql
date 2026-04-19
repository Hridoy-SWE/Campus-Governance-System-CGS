INSERT OR IGNORE INTO departments (code, name, email, phone) VALUES
('ADMIN', 'Administration', 'admin@university.edu', '+8801000000001'),
('CSE', 'Computer Science and Engineering', 'cse@university.edu', '+8801000000002'),
('EEE', 'Electrical and Electronic Engineering', 'eee@university.edu', '+8801000000003'),
('BBA', 'Business Administration', 'bba@university.edu', '+8801000000004'),
('ARCH', 'Architecture', 'architecture@university.edu', '+8801000000005');

INSERT OR IGNORE INTO users (username, email, password_hash, full_name, role, department_id, phone, is_active)
VALUES (
    'admin',
    'admin@university.edu',
    '$2a$12$replace_with_real_bcrypt_hash',
    'System Administrator',
    'admin',
    (SELECT id FROM departments WHERE code = 'ADMIN'),
    '+8801700000001',
    1
);

INSERT OR IGNORE INTO spam_rules (rule_name, rule_type, rule_value, score, is_active) VALUES
('duplicate_title', 'text_match', 'duplicate-title', 20, 1),
('rapid_repeat_submit', 'rate_limit', '5_per_minute', 40, 1),
('blocked_keywords', 'keyword', 'spam scam fraud test', 30, 1),
('empty_or_short_content', 'length', 'min_20', 15, 1);

UPDATE stats
SET
    total_reports = (SELECT COUNT(*) FROM reports),
    pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
    verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
    in_progress_reports = (SELECT COUNT(*) FROM reports WHERE status = 'in_progress'),
    resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved'),
    spam_reports = (SELECT COUNT(*) FROM reports WHERE status = 'spam'),
    critical_reports = (SELECT COUNT(*) FROM reports WHERE priority = 'critical'),
    total_users = (SELECT COUNT(*) FROM users),
    active_users = (SELECT COUNT(*) FROM users WHERE is_active = 1),
    total_departments = (SELECT COUNT(*) FROM departments),
    updated_at = CURRENT_TIMESTAMP
WHERE id = 1;
