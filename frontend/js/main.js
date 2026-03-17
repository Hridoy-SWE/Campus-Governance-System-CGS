// ===== API Service for Campus Governance System =====

const API_BASE = 'http://localhost:8080/api';

// Fetch real stats from database
async function fetchRealStats() {
    try {
        const response = await fetch(`${API_BASE}/stats`);
        const data = await response.json();
        
        if (data.success) {
            // Update the stats in the UI
            document.getElementById('totalReports').textContent = data.data.total_reports || 0;
            document.getElementById('verifiedReports').textContent = data.data.verified_reports || 0;
            document.getElementById('pendingReviews').textContent = data.data.pending_reports || 0;
            document.getElementById('resolvedReports').textContent = data.data.resolved_reports || 0;
        }
    } catch (error) {
        console.error('Failed to fetch stats:', error);
        // Fallback to zeros if API fails
        document.getElementById('totalReports').textContent = '0';
        document.getElementById('verifiedReports').textContent = '0';
        document.getElementById('pendingReviews').textContent = '0';
        document.getElementById('resolvedReports').textContent = '0';
    }
}

// Fetch real latest reports
async function fetchRealReports() {
    try {
        const response = await fetch(`${API_BASE}/reports/latest`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            updateReportsList(data.data);
        }
    } catch (error) {
        console.error('Failed to fetch reports:', error);
    }
}

// Update reports list in UI
function updateReportsList(reports) {
    const reportsList = document.getElementById('reportsList');
    if (!reportsList) return;
    
    reportsList.innerHTML = reports.map(report => `
        <div class="report-item" onclick="viewReport('${report.fullToken || report.token}')">
            <div class="report-header">
                <span class="report-category">${report.category}</span>
                <span class="report-status status-${report.status}">${report.status}</span>
            </div>
            <p class="report-excerpt">${report.title.substring(0, 100)}...</p>
            <div class="report-meta">
                <span>📍 ${report.location || 'Not specified'}</span>
                <span>🕒 ${report.timeAgo || 'recently'}</span>
                <span class="report-token-hint">Token: ${report.token}</span>
            </div>
        </div>
    `).join('');
}

// Submit report to database
async function submitReportToDB(formData) {
    try {
        const response = await fetch(`${API_BASE}/report/submit`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(`Report submitted! Token: ${data.data.token}`, 'success');
            
            // Save token to localStorage
            const tokens = JSON.parse(localStorage.getItem('cgs_tokens') || '[]');
            tokens.push(data.data.token);
            if (tokens.length > 5) tokens.shift();
            localStorage.setItem('cgs_tokens', JSON.stringify(tokens));
            
            // Refresh stats and reports
            fetchRealStats();
            fetchRealReports();
            
            return true;
        } else {
            showToast(data.message || 'Failed to submit report', 'error');
            return false;
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
        return false;
    }
}

// Track report by token
async function trackReportInDB(token) {
    try {
        const response = await fetch(`${API_BASE}/report/track?token=${token}`);
        const data = await response.json();
        
        if (data.success) {
            displayTrackResult(data.data);
        } else {
            showToast(data.message || 'Report not found', 'error');
        }
    } catch (error) {
        showToast('Failed to track report', 'error');
    }
}

// Call these on page load
document.addEventListener('DOMContentLoaded', function() {
    // Your existing init code...
    fetchRealStats();
    fetchRealReports();
    
    // Refresh every 30 seconds
    setInterval(fetchRealStats, 30000);
    setInterval(fetchRealReports, 30000);
});
const API = (function() {
    'use strict';

    // Base URL - change this to your actual backend URL
    const BASE_URL = 'http://localhost:8080/api';
    
    // ===== Helper Functions =====
    async function handleResponse(response) {
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `HTTP error ${response.status}`);
        }
        return response.json();
    }

    function getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
        };
        
        // Add auth token if available
        const token = sessionStorage.getItem('cgs_token');
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        return headers;
    }

    // ===== Public API Methods =====
    return {
        // ===== Issues =====
        issues: {
            // Get all issues (with filters)
            async getAll(filters = {}) {
                const params = new URLSearchParams(filters).toString();
                const response = await fetch(`${BASE_URL}/issues?${params}`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get single issue by token
            async getByToken(token) {
                const response = await fetch(`${BASE_URL}/issues/track/${token}`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Create new issue
            async create(issueData) {
                const response = await fetch(`${BASE_URL}/issues`, {
                    method: 'POST',
                    headers: getHeaders(),
                    body: JSON.stringify(issueData)
                });
                return handleResponse(response);
            },
            
            // Update issue (department/admin only)
            async update(id, updateData) {
                const response = await fetch(`${BASE_URL}/issues/${id}`, {
                    method: 'PUT',
                    headers: getHeaders(),
                    body: JSON.stringify(updateData)
                });
                return handleResponse(response);
            },
            
            // Get issues by department
            async getByDepartment(dept) {
                const response = await fetch(`${BASE_URL}/issues/department/${dept}`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Add remark to issue
            async addRemark(id, remark) {
                const response = await fetch(`${BASE_URL}/issues/${id}/remarks`, {
                    method: 'POST',
                    headers: getHeaders(),
                    body: JSON.stringify({ remark })
                });
                return handleResponse(response);
            },
            
            // Escalate issue
            async escalate(id, reason) {
                const response = await fetch(`${BASE_URL}/issues/${id}/escalate`, {
                    method: 'POST',
                    headers: getHeaders(),
                    body: JSON.stringify({ reason })
                });
                return handleResponse(response);
            }
        },
        
        // ===== Statistics =====
        stats: {
            // Get dashboard statistics
            async getDashboard() {
                const response = await fetch(`${BASE_URL}/stats/dashboard`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get department statistics
            async getDepartments() {
                const response = await fetch(`${BASE_URL}/stats/departments`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get trends data
            async getTrends(period = 'month') {
                const response = await fetch(`${BASE_URL}/stats/trends?period=${period}`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            }
        },
        
        // ===== Authentication =====
        auth: {
            // Login
            async login(credentials) {
                const response = await fetch(`${BASE_URL}/auth/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(credentials)
                });
                const data = await handleResponse(response);
                
                if (data.token) {
                    sessionStorage.setItem('cgs_token', data.token);
                    sessionStorage.setItem('cgs_user', JSON.stringify(data.user));
                }
                
                return data;
            },
            
            // Register
            async register(userData) {
                const response = await fetch(`${BASE_URL}/auth/register`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(userData)
                });
                return handleResponse(response);
            },
            
            // Logout
            logout() {
                sessionStorage.removeItem('cgs_token');
                sessionStorage.removeItem('cgs_user');
            },
            
            // Get current user
            getCurrentUser() {
                const user = sessionStorage.getItem('cgs_user');
                return user ? JSON.parse(user) : null;
            },
            
            // Check if logged in
            isAuthenticated() {
                return !!sessionStorage.getItem('cgs_token');
            }
        },
        
        // ===== Departments =====
        departments: {
            // Get all departments
            async getAll() {
                const response = await fetch(`${BASE_URL}/departments`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get department details
            async getById(id) {
                const response = await fetch(`${BASE_URL}/departments/${id}`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get department issues
            async getIssues(id) {
                const response = await fetch(`${BASE_URL}/departments/${id}/issues`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            }
        },
        
        // ===== Admin =====
        admin: {
            // Get all users
            async getUsers() {
                const response = await fetch(`${BASE_URL}/admin/users`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Create user
            async createUser(userData) {
                const response = await fetch(`${BASE_URL}/admin/users`, {
                    method: 'POST',
                    headers: getHeaders(),
                    body: JSON.stringify(userData)
                });
                return handleResponse(response);
            },
            
            // Update user
            async updateUser(id, userData) {
                const response = await fetch(`${BASE_URL}/admin/users/${id}`, {
                    method: 'PUT',
                    headers: getHeaders(),
                    body: JSON.stringify(userData)
                });
                return handleResponse(response);
            },
            
            // Delete user
            async deleteUser(id) {
                const response = await fetch(`${BASE_URL}/admin/users/${id}`, {
                    method: 'DELETE',
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get system logs
            async getLogs() {
                const response = await fetch(`${BASE_URL}/admin/logs`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            },
            
            // Get analytics
            async getAnalytics() {
                const response = await fetch(`${BASE_URL}/admin/analytics`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            }
        },
        
        // ===== Tokens =====
        tokens: {
            // Verify token
            async verify(token) {
                const response = await fetch(`${BASE_URL}/tokens/verify`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token })
                });
                return handleResponse(response);
            },
            
            // Get token status
            async getStatus(token) {
                const response = await fetch(`${BASE_URL}/tokens/${token}/status`, {
                    headers: getHeaders()
                });
                return handleResponse(response);
            }
        },
        
        // ===== File Upload =====
        upload: {
            // Upload evidence
            async evidence(file) {
                const formData = new FormData();
                formData.append('file', file);
                
                const response = await fetch(`${BASE_URL}/upload/evidence`, {
                    method: 'POST',
                    headers: {
                        'Authorization': getHeaders()['Authorization']
                    },
                    body: formData
                });
                return handleResponse(response);
            }
        }
    };
})();

// Make API globally available
window.API = API;