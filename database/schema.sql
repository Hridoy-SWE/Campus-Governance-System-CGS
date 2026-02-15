-- database/schema.sql
-- SQLite schema for Campus Governance System
-- FIXED VERSION - Auto updates stats

-- Drop tables if they exist
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS stats;

-- Reports table
CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    category TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    location TEXT,
    evidence_path TEXT,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX idx_reports_token ON reports(token);
CREATE INDEX idx_reports_status ON reports(status);
CREATE INDEX idx_reports_created ON reports(created_at);

-- Stats table
CREATE TABLE stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    total_reports INTEGER DEFAULT 0,
    verified_reports INTEGER DEFAULT 0,
    pending_reports INTEGER DEFAULT 0,
    resolved_reports INTEGER DEFAULT 0
);

-- Insert initial stats with ZERO values
INSERT INTO stats (total_reports, verified_reports, pending_reports, resolved_reports) 
VALUES (0, 0, 0, 0);

-- Function to update stats (called by triggers)
CREATE TRIGGER update_stats_after_insert 
AFTER INSERT ON reports
BEGIN
    UPDATE stats SET 
        total_reports = (SELECT COUNT(*) FROM reports),
        pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
        verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
        resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved')
    WHERE id = 1;
END;

CREATE TRIGGER update_stats_after_update 
AFTER UPDATE OF status ON reports
BEGIN
    UPDATE stats SET 
        total_reports = (SELECT COUNT(*) FROM reports),
        pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
        verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
        resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved')
    WHERE id = 1;
END;

CREATE TRIGGER update_stats_after_delete 
AFTER DELETE ON reports
BEGIN
    UPDATE stats SET 
        total_reports = (SELECT COUNT(*) FROM reports),
        pending_reports = (SELECT COUNT(*) FROM reports WHERE status = 'pending'),
        verified_reports = (SELECT COUNT(*) FROM reports WHERE status = 'verified'),
        resolved_reports = (SELECT COUNT(*) FROM reports WHERE status = 'resolved')
    WHERE id = 1;
END;