const API_BASE = `${window.location.origin}/api`;

async function fetchRealStats() {
    try {
        const response = await fetch(`${API_BASE}/stats`);
        const data = await response.json();
        if (!data.success || !data.data) return;
        const mappings = {
            totalReports: data.data.total_reports || 0,
            verifiedReports: data.data.verified_reports || 0,
            pendingReviews: data.data.pending_reports || 0,
            resolvedReports: data.data.resolved_reports || 0,
            pendingReports: data.data.pending_reports || 0,
        };
        Object.entries(mappings).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        });
    } catch (error) {
        console.error('Failed to fetch stats:', error);
    }
}

async function fetchRealReports() {
    try {
        const response = await fetch(`${API_BASE}/reports/latest`);
        const data = await response.json();
        if (!data.success || !Array.isArray(data.data)) return;
        if (typeof updateReportsList === 'function') updateReportsList(data.data);
    } catch (error) {
        console.error('Failed to fetch reports:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchRealStats();
    fetchRealReports();
});
