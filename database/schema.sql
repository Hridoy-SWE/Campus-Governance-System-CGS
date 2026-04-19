PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA temp_store = MEMORY;
PRAGMA cache_size = -20000;
PRAGMA busy_timeout = 5000;

CREATE TABLE IF NOT EXISTS departments (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    code            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL UNIQUE,
    email           TEXT,
    phone           TEXT,
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT NOT NULL UNIQUE,
    email           TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    full_name       TEXT NOT NULL,
    role            TEXT NOT NULL CHECK (role IN ('admin','faculty','student','department_head')),
    department_id   INTEGER,
    phone           TEXT,
    profile_photo   TEXT,
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    last_login      DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    session_token   TEXT NOT NULL UNIQUE,
    ip_address      TEXT,
    user_agent      TEXT,
    expires_at      DATETIME NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tokens (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    token           TEXT NOT NULL UNIQUE,
    email           TEXT,
    phone           TEXT,
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME
);

CREATE TABLE IF NOT EXISTS spam_rules (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    rule_name       TEXT NOT NULL UNIQUE,
    rule_type       TEXT NOT NULL,
    rule_value      TEXT NOT NULL,
    score           INTEGER NOT NULL DEFAULT 0 CHECK (score >= 0),
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reports (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    token_id            INTEGER,
    user_id             INTEGER,
    department_id       INTEGER,
    category            TEXT NOT NULL,
    title               TEXT NOT NULL,
    description         TEXT NOT NULL,
    location            TEXT,
    incident_date       DATE,
    incident_time       TIME,
    priority            TEXT NOT NULL DEFAULT 'medium' CHECK (priority IN ('low','medium','high','critical')),
    status              TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','verified','in_progress','resolved','rejected','spam')),
    assigned_to         INTEGER,
    is_anonymous        INTEGER NOT NULL DEFAULT 1 CHECK (is_anonymous IN (0,1)),
    notify_email        TEXT,
    notify_phone        TEXT,
    spam_score          INTEGER NOT NULL DEFAULT 0 CHECK (spam_score >= 0),
    spam_reason         TEXT,
    views_count         INTEGER NOT NULL DEFAULT 0 CHECK (views_count >= 0),
    resolution_notes    TEXT,
    resolved_at         DATETIME,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS report_media (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id       INTEGER NOT NULL,
    file_path       TEXT NOT NULL,
    file_name       TEXT NOT NULL,
    mime_type       TEXT NOT NULL,
    media_type      TEXT NOT NULL CHECK (media_type IN ('image','video','document')),
    file_size       INTEGER NOT NULL DEFAULT 0 CHECK (file_size >= 0),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id           INTEGER NOT NULL,
    user_id             INTEGER,
    token_id            INTEGER,
    comment             TEXT NOT NULL,
    is_staff_response   INTEGER NOT NULL DEFAULT 0 CHECK (is_staff_response IN (0,1)),
    is_private          INTEGER NOT NULL DEFAULT 0 CHECK (is_private IN (0,1)),
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS messages (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_user_id      INTEGER,
    recipient_user_id   INTEGER,
    recipient_token_id  INTEGER,
    report_id           INTEGER,
    subject             TEXT NOT NULL,
    message             TEXT NOT NULL,
    status              TEXT NOT NULL DEFAULT 'sent' CHECK (status IN ('draft','sent','delivered','read','failed')),
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_token_id) REFERENCES tokens(id) ON DELETE SET NULL,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id           INTEGER,
    recipient_type      TEXT NOT NULL CHECK (recipient_type IN ('user','token','email','phone','department')),
    recipient           TEXT NOT NULL,
    subject             TEXT,
    message             TEXT NOT NULL,
    type                TEXT NOT NULL CHECK (type IN ('email','sms','system')),
    status              TEXT NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','sent','failed','cancelled')),
    retry_count         INTEGER NOT NULL DEFAULT 0 CHECK (retry_count >= 0),
    sent_at             DATETIME,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS report_status_history (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id       INTEGER NOT NULL,
    old_status      TEXT,
    new_status      TEXT NOT NULL CHECK (new_status IN ('pending','verified','in_progress','resolved','rejected','spam')),
    changed_by      INTEGER,
    note            TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER,
    action          TEXT NOT NULL,
    entity_type     TEXT NOT NULL,
    entity_id       INTEGER,
    old_value       TEXT,
    new_value       TEXT,
    ip_address      TEXT,
    user_agent      TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS stats (
    id                  INTEGER PRIMARY KEY CHECK (id = 1),
    total_reports       INTEGER NOT NULL DEFAULT 0 CHECK (total_reports >= 0),
    pending_reports     INTEGER NOT NULL DEFAULT 0 CHECK (pending_reports >= 0),
    verified_reports    INTEGER NOT NULL DEFAULT 0 CHECK (verified_reports >= 0),
    in_progress_reports INTEGER NOT NULL DEFAULT 0 CHECK (in_progress_reports >= 0),
    resolved_reports    INTEGER NOT NULL DEFAULT 0 CHECK (resolved_reports >= 0),
    spam_reports        INTEGER NOT NULL DEFAULT 0 CHECK (spam_reports >= 0),
    critical_reports    INTEGER NOT NULL DEFAULT 0 CHECK (critical_reports >= 0),
    total_users         INTEGER NOT NULL DEFAULT 0 CHECK (total_users >= 0),
    active_users        INTEGER NOT NULL DEFAULT 0 CHECK (active_users >= 0),
    total_departments   INTEGER NOT NULL DEFAULT 0 CHECK (total_departments >= 0),
    avg_response_time   REAL NOT NULL DEFAULT 0,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO stats (id) VALUES (1);

CREATE TRIGGER IF NOT EXISTS trg_reports_updated_at
AFTER UPDATE ON reports
FOR EACH ROW
BEGIN
    UPDATE reports SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_users_updated_at
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_departments_updated_at
AFTER UPDATE ON departments
FOR EACH ROW
BEGIN
    UPDATE departments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_spam_rules_updated_at
AFTER UPDATE ON spam_rules
FOR EACH ROW
BEGIN
    UPDATE spam_rules SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;
