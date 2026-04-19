(() => {
    const CHATBOT_API_BASE = `${window.location.origin}/api`;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function fetchRealStats() {
        try {
            const response = await fetch(`${CHATBOT_API_BASE}/stats`, { credentials: 'same-origin' });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success || !data?.data) return;
            const mappings = {
                totalReports: data.data.total_reports || 0,
                verifiedReports: data.data.verified_reports || 0,
                pendingReviews: data.data.pending_reports || 0,
                resolvedReports: data.data.resolved_reports || 0,
                pendingReports: data.data.pending_reports || 0,
            };
            Object.entries(mappings).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el) el.textContent = String(value);
            });
        } catch (error) {
            console.error('Failed to fetch stats:', error);
        }
    }

    async function fetchRealReports() {
        try {
            const response = await fetch(`${CHATBOT_API_BASE}/reports/latest`, { credentials: 'same-origin' });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success || !Array.isArray(data?.data)) return;
            if (typeof window.updateReportsList === 'function') window.updateReportsList(data.data);
        } catch (error) {
            console.error('Failed to fetch reports:', error);
        }
    }

    function createChatLinkRow(links = []) {
        if (!Array.isArray(links) || !links.length) return '';
        return `<div class="cgs-chatbot-link-row">${links.map(link => `
            <a class="cgs-chatbot-mini-link" href="${escapeHtml(link.href)}">
                <i class="fas ${escapeHtml(link.icon || 'fa-arrow-right')}"></i>
                <span>${escapeHtml(link.label || 'Open')}</span>
            </a>`).join('')}</div>`;
    }

    function buildChatbotReply(questionRaw) {
        const q = String(questionRaw || '').toLowerCase().trim();
        const responses = [
            { match: ['submit', 'report', 'complaint', 'issue'], reply: 'To submit a report, choose “Submit Anonymous Report” on the landing page or open the full report form. Add category, title, description, and location. After submission, save the generated token carefully.', links: [{ label: 'Open report form', href: 'report.html', icon: 'fa-file-circle-plus' }] },
            { match: ['track', 'token', 'status'], reply: 'Use the tracking token you received after submission. Open the tracking page, paste the token, and the system will show the latest report status and timeline.', links: [{ label: 'Track report', href: 'track.html', icon: 'fa-location-crosshairs' }] },
            { match: ['login', 'sign in', 'sign up', 'register', 'account'], reply: 'Use Sign In if you already have an account. Use Sign Up to create a new one. Admin and faculty can access admin-side moderation after authenticated login.', links: [{ label: 'Sign in', href: 'login.html', icon: 'fa-right-to-bracket' }] },
            { match: ['file', 'upload', 'video', 'image', 'pdf', 'document', '20mb'], reply: 'The report form supports images, short videos, PDFs, and documents. Each file must be 20MB or less, and the full selected upload for one submission must stay within 20MB.', links: [{ label: 'Report form', href: 'report.html', icon: 'fa-upload' }] },
            { match: ['analytics', 'public analytics', 'statistics', 'chart'], reply: 'Public analytics shows public-safe database-backed charts and counts, such as report volume, category distribution, and status trends. It does not expose private user data.', links: [{ label: 'View public analytics', href: 'public-analytics.html', icon: 'fa-chart-pie' }] },
            { match: ['anonymous', 'identity', 'secret'], reply: 'Yes. The reporting flow supports anonymous submission. You can report an issue without adding your personal identity and still track progress later using the generated token.', links: [] },
            { match: ['urgent', 'emergency', 'danger', 'security'], reply: 'For immediate danger or emergency situations, contact campus security first instead of waiting for online review. The system is best for recordkeeping, follow-up, and moderation workflow.', links: [] },
            { match: ['hello', 'hi', 'help'], reply: 'Hello. I can help with report submission, token tracking, login/sign-up guidance, upload rules, anonymous reporting, urgent issue guidance, and public analytics.', links: [{ label: 'Submit report', href: 'report.html', icon: 'fa-file-circle-plus' }, { label: 'Track token', href: 'track.html', icon: 'fa-location-crosshairs' }] },
        ];
        for (const item of responses) {
            if (item.match.some(term => q.includes(term))) {
                return `<p>${escapeHtml(item.reply)}</p>${createChatLinkRow(item.links)}`;
            }
        }
        return `<p>I can help with report submission, token tracking, login/sign-up guidance, upload rules, anonymous reporting, urgent issue guidance, and public analytics.</p>${createChatLinkRow([{ label: 'Submit report', href: 'report.html', icon: 'fa-file-circle-plus' }, { label: 'Track token', href: 'track.html', icon: 'fa-location-crosshairs' }, { label: 'Analytics', href: 'public-analytics.html', icon: 'fa-chart-pie' }])}`;
    }

    function appendChatbotMessage(kind, html) {
        const container = document.getElementById('chatbotMessages');
        if (!container) return;
        const row = document.createElement('div');
        row.className = `cgs-chatbot-msg ${kind}`;
        const bubble = document.createElement('div');
        bubble.className = 'cgs-chatbot-bubble';
        bubble.innerHTML = html;
        row.appendChild(bubble);
        container.appendChild(row);
        container.scrollTop = container.scrollHeight;
    }

    function setupChatbot() {
        const shell = document.getElementById('chatbotShell');
        const toggle = document.getElementById('chatbotToggle');
        const panel = document.getElementById('chatbotPanel');
        const closeBtn = document.getElementById('chatbotClose');
        const form = document.getElementById('chatbotForm');
        const input = document.getElementById('chatbotInput');
        const chips = document.querySelectorAll('[data-chatbot-question]');
        const messages = document.getElementById('chatbotMessages');
        if (!shell || !toggle || !panel || !form || !input || !messages) {
            console.warn('Chatbot init skipped: required elements not found.');
            return;
        }
        if (shell.dataset.chatbotReady === '1') return;
        shell.dataset.chatbotReady = '1';
        const isOpen = () => panel.classList.contains('show');
        const openChatbot = () => {
            panel.classList.add('show');
            panel.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            shell.classList.add('is-open');
            setTimeout(() => input.focus(), 40);
        };
        const closeChatbot = () => {
            panel.classList.remove('show');
            panel.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            shell.classList.remove('is-open');
        };
        const submitQuestion = (value) => {
            const question = String(value || '').trim();
            if (!question) return;
            appendChatbotMessage('user', `<p>${escapeHtml(question)}</p>`);
            const reply = buildChatbotReply(question);
            setTimeout(() => appendChatbotMessage('bot', reply), 180);
        };
        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (isOpen()) closeChatbot(); else openChatbot();
        });
        closeBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            closeChatbot();
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const value = input.value;
            input.value = '';
            submitQuestion(value);
        });
        chips.forEach((chip) => {
            chip.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openChatbot();
                submitQuestion(chip.dataset.chatbotQuestion || chip.textContent || 'Help');
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen()) closeChatbot();
        });
        document.addEventListener('click', (event) => {
            if (!isOpen()) return;
            if (!shell.contains(event.target)) closeChatbot();
        });
        if (!messages.children.length) {
            appendChatbotMessage('bot', `<p>Hello. I am the Campus Help Assistant. I can help with report submission, tracking, login guidance, upload rules, and public analytics.</p>${createChatLinkRow([{ label: 'Submit report', href: 'report.html', icon: 'fa-file-circle-plus' }, { label: 'Track token', href: 'track.html', icon: 'fa-location-crosshairs' }])}`);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchRealStats();
        fetchRealReports();
        setupChatbot();
    });
})();
