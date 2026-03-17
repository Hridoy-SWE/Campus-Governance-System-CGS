-- database/schema.sql
-- COMPLETE ENHANCED SCHEMA for Campus Governance System
-- Includes: Users, Departments, Reports, Tokens, Comments, 
--           Activity Logs, Stats, Notifications

-- Enable foreign keys
PRAGMA foreign_keys = ON;

-- =====================================================
-- 1. DEPARTMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    email TEXT,
    phone TEXT,
    head_of_department TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default departments
INSERT OR IGNORE INTO departments (code, name, email, phone) VALUES
('IT', 'Information Technology', 'it@diu.edu.bd', '01712-345601'),
('FAC', 'Facilities Management', 'facilities@diu.edu.bd', '01712-345602'),
('ACAD', 'Academic Affairs', 'academic@diu.edu.bd', '01712-345603'),
('SEC', 'Security', 'security@diu.edu.bd', '01712-345604'),
('TRANS', 'Transport', 'transport@diu.edu.bd', '01712-345605'),
('LIB', 'Library', 'library@diu.edu.bd', '01712-345606'),
('FIN', 'Finance', 'finance@diu.edu.bd', '01712-345607'),
('HR', 'Human Resources', 'hr@diu.edu.bd', '01712-345608');

-- =====================================================
-- 2. USERS TABLE (for authentication)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'department_head', 'faculty', 'staff', 'student', 'user')),
    department_id INTEGER,
    designation TEXT,
    phone TEXT,
    email_verified INTEGER DEFAULT 0,
    verification_token TEXT,
    reset_token TEXT,
    reset_expires DATETIME,
    is_active INTEGER DEFAULT 1,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Insert default admin user (password: admin123 - SHA256 hash)
INSERT OR IGNORE INTO users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@diu.edu.bd', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'System Administrator', 'admin');

-- Insert sample users
INSERT OR IGNORE INTO users (username, email, password_hash, full_name, role, department_id) VALUES
('faculty1', 'faculty@diu.edu.bd', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'Dr. Rahman', 'faculty', 3),
('dept_it', 'it.head@diu.edu.bd', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'Prof. Karim', 'department_head', 1),
('dept_fac', 'facilities.head@diu.edu.bd', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'Engr. Sultana', 'department_head', 2);

-- =====================================================
-- 3. TOKENS TABLE (for anonymous tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    email TEXT,  -- optional contact email for notifications
    phone TEXT,  -- optional contact phone
    ip_address TEXT,
    user_agent TEXT,
    is_verified INTEGER DEFAULT 0,
    last_accessed DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT (datetime('now', '+90 days'))
);

CREATE INDEX IF NOT EXISTS idx_tokens_token ON tokens(token);
CREATE INDEX IF NOT EXISTS idx_tokens_email ON tokens(email);

-- =====================================================
-- 4. REPORTS TABLE (main issues) - ENHANCED
-- =====================================================
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_id INTEGER,  -- NULL if reported by authenticated user
    user_id INTEGER,   -- NULL if anonymous
    department_id INTEGER,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    location TEXT,
    incident_date DATE,
    incident_time TIME,
    priority TEXT DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'assigned', 'in_progress', 'verified', 'resolved', 'closed', 'rejected')),
    evidence_path TEXT,
    assigned_to INTEGER,  -- user_id of assigned staff
    assigned_at DATETIME,
    due_date DATETIME,
    resolution_notes TEXT,
    resolved_at DATETIME,
    is_anonymous INTEGER DEFAULT 1,
    notify_email INTEGER DEFAULT 0,  -- Whether to send email notifications
    notify_phone INTEGER DEFAULT 0,
    views_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES tokens(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_reports_token_id ON reports(token_id);
CREATE INDEX IF NOT EXISTS idx_reports_user_id ON reports(user_id);
CREATE INDEX IF NOT EXISTS idx_reports_department_id ON reports(department_id);
CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status);
CREATE INDEX IF NOT EXISTS idx_reports_priority ON reports(priority);
CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at);

-- =====================================================
-- 5. COMMENTS TABLE (for discussions on reports)
-- =====================================================
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    user_id INTEGER,  -- NULL if anonymous
    token_id INTEGER, -- used if anonymous
    comment TEXT NOT NULL,
    is_staff_response INTEGER DEFAULT 0,
    is_private INTEGER DEFAULT 0,  -- private notes only visible to staff
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (token_id) REFERENCES tokens(id)
);

CREATE INDEX IF NOT EXISTS idx_comments_report_id ON comments(report_id);

-- =====================================================
-- 6. NOTIFICATIONS TABLE (Email/SMS queue) - NEW
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    recipient_type TEXT CHECK (recipient_type IN ('user', 'token_email', 'department')),
    recipient TEXT,  -- email address or phone
    subject TEXT,
    message TEXT NOT NULL,
    type TEXT CHECK (type IN ('status_update', 'comment', 'assignment', 'resolution')),
    sent_at DATETIME,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    retry_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notifications_report ON notifications(report_id);
CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);

-- =====================================================
-- 7. ACTIVITY LOGS TABLE (audit trail)
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT CHECK (entity_type IN ('report', 'user', 'comment', 'notification', 'department')),
    entity_id INTEGER,
    old_value TEXT,
    new_value TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_logs_entity ON activity_logs(entity_type, entity_id);

-- =====================================================
-- 8. STATS TABLE (cached statistics)
-- =====================================================
CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    total_reports INTEGER DEFAULT 0,
    pending_reports INTEGER DEFAULT 0,
    verified_reports INTEGER DEFAULT 0,
    resolved_reports INTEGER DEFAULT 0,
    in_progress_reports INTEGER DEFAULT 0,
    critical_reports INTEGER DEFAULT 0,
    total_users INTEGER DEFAULT 0,
    active_users INTEGER DEFAULT 0,
    total_departments INTEGER DEFAULT 0,
    avg_response_time REAL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert initial stats
INSERT OR IGNORE INTO stats (id) VALUES (1);

-- =====================================================
-- 9. TRIGGERS for automatic updates
-- =====================================================

-- Update stats after report insert
CREATE TRIGGER IF NOT EXISTS update_stats_after_report_insert 
AFTER INSERT ON reports
BEGIN
    UPDATE stats SET 
        total_reports = (SELECT COUNT(*) FROM reports),
        pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
        verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
        resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved'),
        in_progress_reports = (SELECT COUNT(*) FROM reports WHERE status = 'in_progress'),
        critical_reports = (SELECT COUNT(*) FROM reports WHERE priority = 'critical'),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = 1;
END;

-- Update stats after report update
CREATE TRIGGER IF NOT EXISTS update_stats_after_report_update 
AFTER UPDATE OF status ON reports
BEGIN
    UPDATE stats SET 
        pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
        verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
        resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved'),
        in_progress_reports = (SELECT COUNT(*) FROM reports WHERE status = 'in_progress'),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = 1;
END;

-- Update user stats
CREATE TRIGGER IF NOT EXISTS update_stats_after_user_insert 
AFTER INSERT ON users
BEGIN
    UPDATE stats SET 
        total_users = (SELECT COUNT(*) FROM users),
        active_users = (SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = 1;
END;

-- =====================================================
-- 10. NOTIFICATION TRIGGER - NEW
-- =====================================================
CREATE TRIGGER IF NOT EXISTS create_notification_on_status_change 
AFTER UPDATE OF status ON reports
WHEN NEW.status != OLD.status
BEGIN
    -- Create notification for the reporter (if they provided email)
    INSERT INTO notifications (report_id, recipient_type, recipient, subject, message, type)
    SELECT 
        NEW.id,
        CASE 
            WHEN NEW.user_id IS NOT NULL THEN 'user'
            WHEN NEW.token_id IN (SELECT id FROM tokens WHERE email IS NOT NULL) THEN 'token_email'
            ELSE NULL
        END,
        CASE 
            WHEN NEW.user_id IS NOT NULL THEN (SELECT email FROM users WHERE id = NEW.user_id)
            WHEN NEW.token_id IN (SELECT id FROM tokens WHERE email IS NOT NULL) THEN (SELECT email FROM tokens WHERE id = NEW.token_id)
            ELSE NULL
        END,
        'Report Status Updated',
        'Your report "' || (SELECT title FROM reports WHERE id = NEW.id) || '" has been updated to: ' || NEW.status,
        'status_update'
    WHERE 
        (NEW.user_id IS NOT NULL OR NEW.token_id IN (SELECT id FROM tokens WHERE email IS NOT NULL));
END;

-- =====================================================
-- 11. INSERT SAMPLE DATA
-- =====================================================

-- Insert sample tokens
INSERT OR IGNORE INTO tokens (token, email, ip_address) VALUES
('CGS-123456-7890', 'anon1@example.com', '192.168.1.1'),
('CGS-234567-8901', NULL, '192.168.1.2'),
('CGS-345678-9012', 'anon2@example.com', '192.168.1.3'),
('CGS-456789-0123', NULL, '192.168.1.4'),
('CGS-567890-1234', 'anon3@example.com', '192.168.1.5');

-- Insert sample reports with notification flag
INSERT OR IGNORE INTO reports (
    token_id, department_id, category, title, description, location, 
    priority, status, incident_date, is_anonymous, notify_email, created_at
) VALUES
(1, 2, 'facility', 'Broken AC in Room 502', 'AC not working for 3 days. Room temperature is very high.', 
 'Academic Building A, Room 502', 'high', 'in_progress', date('now', '-2 days'), 1, 1, datetime('now', '-2 days')),
(2, 4, 'security', 'Unauthorized person in girls hostel', 'Suspicious person spotted near girls hostel at night.', 
 'Girls Hostel 3', 'critical', 'assigned', date('now', '-5 days'), 1, 0, datetime('now', '-5 days')),
(3, 1, 'it', 'Slow WiFi in Library', 'Internet speed drops significantly during peak hours.', 
 'Central Library', 'medium', 'resolved', date('now', '-7 days'), 1, 1, datetime('now', '-7 days')),
(4, 3, 'academic', 'Exam result discrepancy', 'Results for CSE 301 seem incorrect.', 
 'CSE Department', 'high', 'pending', date('now', '-1 day'), 1, 1, datetime('now', '-1 day')),
(5, 5, 'transport', 'Bus delay on Mirpur route', 'Morning bus consistently late by 30-45 minutes.', 
 'Mirpur Bus Stop', 'medium', 'in_progress', date('now', '-3 days'), 1, 0, datetime('now', '-3 days'));

-- Insert sample comments
INSERT OR IGNORE INTO comments (report_id, user_id, token_id, comment, is_staff_response) VALUES
(1, NULL, 1, 'This has been going on for a week. Please fix it ASAP.', 0),
(1, 3, NULL, 'Technician has been assigned. Parts ordered. Estimated completion: 2 days.', 1),
(2, NULL, 2, 'This is very scary. We need better security.', 0),
(2, 4, NULL, 'Security patrol increased in that area. Investigation ongoing.', 1),
(3, NULL, 3, 'WiFi is still slow even after they said it was fixed.', 0);

-- Final stats update
UPDATE stats SET 
    total_reports = (SELECT COUNT(*) FROM reports),
    pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
    verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
    resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved'),
    in_progress_reports = (SELECT COUNT(*) FROM reports WHERE status = 'in_progress'),
    total_users = (SELECT COUNT(*) FROM users WHERE is_active = 1),
    active_users = (SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL),
    total_departments = (SELECT COUNT(*) FROM departments),
    updated_at = CURRENT_TIMESTAMP
WHERE id = 1;

-- =====================================================
-- 12. VERIFICATION QUERIES
-- =====================================================
SELECT '✅ Database Enhanced Successfully!' as message;
SELECT '📊 Tables: users, departments, reports, tokens, comments, notifications, activity_logs, stats' as info;
SELECT '👥 Users: ' || (SELECT COUNT(*) FROM users) as users_count;
SELECT '📝 Reports: ' || (SELECT COUNT(*) FROM reports) as reports_count;
SELECT '🔑 Tokens: ' || (SELECT COUNT(*) FROM tokens) as tokens_count;
SELECT '💬 Comments: ' || (SELECT COUNT(*) FROM comments) as comments_count;
SELECT '📈 Stats: ' || total_reports || ' total, ' || pending_reports || ' pending, ' || resolved_reports || ' resolved' FROM stats;