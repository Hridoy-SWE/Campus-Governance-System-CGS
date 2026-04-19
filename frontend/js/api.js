const API_BASE = `${window.location.origin}/api`;

// API Configuration
const API = {
    baseURL: API_BASE,

    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },

    // Generic request method
    async request(endpoint, options = {}) {
        try {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                ...options,
                credentials: 'include',
                headers: {
                    ...this.headers,
                    ...(options.headers || {})
                }
            });

            let data = null;
            try {
                data = await response.json();
            } catch (parseError) {
                data = null;
            }

            if (!response.ok) {
                throw new Error(data?.message || `Request failed with status ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    // Auth
    async login({ login, password }) {
        const data = await this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ login, password })
        });

        // Backend is session/cookie based, but store user locally for UI convenience
        if (data?.success && data?.data?.user) {
            localStorage.setItem('user', JSON.stringify(data.data.user));
        }

        return data;
    },

    async register({
        username,
        email,
        password,
        full_name,
        role,
        department_id = null,
        phone = ''
    }) {
        return this.request('/auth/register', {
            method: 'POST',
            body: JSON.stringify({
                username,
                email,
                password,
                full_name,
                role,
                department_id,
                phone
            })
        });
    },

    async me() {
        return this.request('/auth/me', {
            method: 'GET'
        });
    },

    async logout() {
        try {
            await this.request('/auth/logout', {
                method: 'POST'
            });
        } finally {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
        }
    },

    // Public data
    async getStats() {
        return this.request('/stats', {
            method: 'GET'
        });
    },

    async getLatestReports() {
        return this.request('/reports/latest', {
            method: 'GET'
        });
    },

    async submitReport(reportData) {
        return this.request('/report/submit', {
            method: 'POST',
            body: JSON.stringify(reportData)
        });
    },

    async trackReport(token) {
        return this.request(`/report/track?token=${encodeURIComponent(token)}`, {
            method: 'GET'
        });
    },

    async getReportDateCounts() {
        return this.request('/report/date-counts', {
            method: 'GET'
        });
    },

    // Admin / faculty
    async getUsers() {
        return this.request('/admin/users', {
            method: 'GET'
        });
    },

    async createUser(userData) {
        return this.request('/admin/users', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    },

    async updateUserStatus(userId, status) {
        return this.request(`/admin/users/${encodeURIComponent(userId)}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status })
        });
    },

    async getAdminReports() {
        return this.request('/admin/reports', {
            method: 'GET'
        });
    },

    async updateReportStatus(reportId, status) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status })
        });
    },

    async updateReportDetails(reportId, details) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}/details`, {
            method: 'PATCH',
            body: JSON.stringify(details)
        });
    },

    async deleteReport(reportId) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}`, {
            method: 'DELETE'
        });
    },

    async getReportMedia(reportId) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}/media`, {
            method: 'GET'
        });
    },

    async getComments(reportId) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}/comments`, {
            method: 'GET'
        });
    },

    async addComment(reportId, content) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}/comments`, {
            method: 'POST',
            body: JSON.stringify({ content })
        });
    },

    async getSpamReports() {
        return this.request('/admin/spam-reports', {
            method: 'GET'
        });
    },

    async updateSpamState(reportId, is_spam) {
        return this.request(`/admin/reports/${encodeURIComponent(reportId)}/spam`, {
            method: 'PATCH',
            body: JSON.stringify({ is_spam })
        });
    }
};

// Export for use
window.API = API;