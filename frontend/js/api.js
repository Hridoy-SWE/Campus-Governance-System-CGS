// API Configuration
const API = {
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },

    // Generic request method
    async request(endpoint, options = {}) {
        try {
            const token = localStorage.getItem('token');
            if (token) {
                options.headers = {
                    ...options.headers,
                    'Authorization': `Bearer ${token}`
                };
            }

            const response = await fetch(`${this.baseURL}${endpoint}`, {
                ...options,
                headers: {
                    ...this.headers,
                    ...options.headers
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Something went wrong');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    // Issues
    async getIssues(params = '') {
        return this.request(`/issues${params}`);
    },

    async createIssue(issueData) {
        return this.request('/issues', {
            method: 'POST',
            body: JSON.stringify(issueData)
        });
    },

    async trackIssue(token) {
        return this.request(`/issues/track/${token}`);
    },

    // Auth
    async login(credentials) {
        const data = await this.request('/auth/login', {
            method: 'POST',
            body: JSON.stringify(credentials)
        });
        if (data.token) {
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
        }
        return data;
    },

    async logout() {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/login.html';
    },

    // Dashboard
    async getDashboardStats() {
        return this.request('/dashboard/stats');
    },

    async getChartData() {
        return this.request('/dashboard/charts');
    },

    // Admin
    async getAllIssues() {
        return this.request('/admin/issues');
    },

    async updateIssueStatus(issueId, status) {
        return this.request(`/admin/issues/${issueId}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }
};

// Export for use
window.API = API;