-- Campus Governance System - Core Database Schema
-- Daffodil International University

-- 1. Tracking tokens for anonymous users
CREATE TABLE tokens (
    token VARCHAR(20) PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Main issues table
CREATE TABLE issues (
    id SERIAL PRIMARY KEY,
    token VARCHAR(20) REFERENCES tokens(token),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    priority VARCHAR(20) DEFAULT 'medium',
    status VARCHAR(50) DEFAULT 'submitted',
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Departments (for assignment)
CREATE TABLE departments (
    id SERIAL PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100)
);

-- 4. Sample data insertion
-- Insert tracking token
INSERT INTO tokens (token) VALUES ('CGS-ABCD1234');

-- Insert departments
INSERT INTO departments (code, name, email) VALUES
('IT', 'Information Technology', 'it@diu.edu.bd'),
('FAC', 'Facilities Management', 'facilities@diu.edu.bd'),
('ACAD', 'Academic Office', 'academic@diu.edu.bd'),
('SEC', 'Security', 'security@diu.edu.bd'),
('TRANS', 'Transport', 'transport@diu.edu.bd');

-- Insert sample issues
INSERT INTO issues (token, title, description, category, priority, status, department) VALUES
('CGS-ABCD1234', 'Broken AC in Room 502', 'AC not working for 2 days, room temperature is very high', 'facilities', 'high', 'in_progress', 'FAC'),
('CGS-ABCD1234', 'Slow WiFi in Library', 'Internet speed drops during peak hours (3-6 PM)', 'it', 'medium', 'submitted', 'IT'),
('CGS-ABCD1234', 'Parking Space Issue', 'Unauthorized vehicles parked in faculty parking area', 'security', 'high', 'resolved', 'SEC');

-- Create a view for quick stats
CREATE VIEW issue_stats AS
SELECT 
    COUNT(*) as total_issues,
    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
    COUNT(CASE WHEN status IN ('submitted', 'in_progress') THEN 1 END) as pending,
    ROUND(AVG(CASE WHEN status = 'resolved' THEN EXTRACT(EPOCH FROM (updated_at - created_at))/3600 END), 1) as avg_hours_to_resolve
FROM issues;

-- Create index for faster queries
CREATE INDEX idx_issues_token ON issues(token);
CREATE INDEX idx_issues_status ON issues(status);
CREATE INDEX idx_issues_department ON issues(department);

-- Display confirmation
SELECT ' Database schema created successfully!' as message;
SELECT ' Sample data inserted:' as info;
SELECT COUNT(*) as token_count FROM tokens;
SELECT COUNT(*) as issue_count FROM issues;
SELECT COUNT(*) as department_count FROM departments;
SELECT * FROM issue_stats;-- Campus Governance System Database
-- Initial Schema for DIU
