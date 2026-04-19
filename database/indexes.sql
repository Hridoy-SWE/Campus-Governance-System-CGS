CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_department_id ON users(department_id);
CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at);

CREATE INDEX IF NOT EXISTS idx_tokens_created_at ON tokens(created_at);

CREATE INDEX IF NOT EXISTS idx_reports_token_id ON reports(token_id);
CREATE INDEX IF NOT EXISTS idx_reports_user_id ON reports(user_id);
CREATE INDEX IF NOT EXISTS idx_reports_department_id ON reports(department_id);
CREATE INDEX IF NOT EXISTS idx_reports_assigned_to ON reports(assigned_to);
CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status);
CREATE INDEX IF NOT EXISTS idx_reports_priority ON reports(priority);
CREATE INDEX IF NOT EXISTS idx_reports_category ON reports(category);
CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at);
CREATE INDEX IF NOT EXISTS idx_reports_updated_at ON reports(updated_at);
CREATE INDEX IF NOT EXISTS idx_reports_department_status ON reports(department_id, status);
CREATE INDEX IF NOT EXISTS idx_reports_priority_status ON reports(priority, status);
CREATE INDEX IF NOT EXISTS idx_reports_status_created_at ON reports(status, created_at);

CREATE INDEX IF NOT EXISTS idx_report_media_report_id ON report_media(report_id);
CREATE INDEX IF NOT EXISTS idx_report_media_media_type ON report_media(media_type);

CREATE INDEX IF NOT EXISTS idx_comments_report_id ON comments(report_id);
CREATE INDEX IF NOT EXISTS idx_comments_user_id ON comments(user_id);
CREATE INDEX IF NOT EXISTS idx_comments_token_id ON comments(token_id);
CREATE INDEX IF NOT EXISTS idx_comments_created_at ON comments(created_at);

CREATE INDEX IF NOT EXISTS idx_messages_report_id ON messages(report_id);
CREATE INDEX IF NOT EXISTS idx_messages_sender_user_id ON messages(sender_user_id);
CREATE INDEX IF NOT EXISTS idx_messages_recipient_user_id ON messages(recipient_user_id);
CREATE INDEX IF NOT EXISTS idx_messages_recipient_token_id ON messages(recipient_token_id);
CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at);

CREATE INDEX IF NOT EXISTS idx_notifications_report_id ON notifications(report_id);
CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);

CREATE INDEX IF NOT EXISTS idx_report_status_history_report_id ON report_status_history(report_id);
CREATE INDEX IF NOT EXISTS idx_report_status_history_changed_by ON report_status_history(changed_by);
CREATE INDEX IF NOT EXISTS idx_report_status_history_created_at ON report_status_history(created_at);

CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_entity ON activity_logs(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);
