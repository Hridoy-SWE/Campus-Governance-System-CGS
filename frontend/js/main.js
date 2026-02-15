// ===== frontend/js/main.js =====
(function() {
    'use strict';

    // DOM Elements
    const reportBtn = document.getElementById('reportNowBtn');
    const trackBtn = document.getElementById('trackBtn');
    const reportModal = document.getElementById('reportModal');
    const trackModal = document.getElementById('trackModal');
    const resultModal = document.getElementById('resultModal');
    const closeButtons = document.querySelectorAll('.modal-close');
    const categoryChips = document.querySelectorAll('.chip');
    const langBtns = document.querySelectorAll('.lang-btn');
    const reportForm = document.getElementById('reportForm');
    const trackForm = document.getElementById('trackForm');
    const progressBar = document.getElementById('progressBar');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const searchInput = document.getElementById('searchReports');
    const searchBtn = document.getElementById('searchBtn');
    
    // State
    let savedTokens = JSON.parse(localStorage.getItem('cgs_tokens')) || [];
    let currentLanguage = localStorage.getItem('cgs_language') || 'en';
    let currentTheme = localStorage.getItem('cgs_theme') || 'dark';
    let reports = []; // Will store loaded reports
    let currentPage = 1;

const API_BASE = 'http://localhost:8080/api';

// Fetch stats from backend
async function fetchStats() {
    try {
        const response = await fetch(`${API_BASE}/stats`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalReports').textContent = data.data.total_reports;
            document.getElementById('verifiedReports').textContent = data.data.verified_reports;
            document.getElementById('pendingReviews').textContent = data.data.pending_reports;
            document.getElementById('resolvedReports').textContent = data.data.resolved_reports;
        }
    } catch (error) {
        console.error('Failed to fetch stats:', error);
    }
}

// Fetch latest reports
async function fetchLatestReports() {
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
        <div class="report-item" onclick="viewReport('${report.token}')">
            <div class="report-header">
                <span class="report-category">${report.category}</span>
                <span class="report-status status-${report.status}">${report.status}</span>
            </div>
            <p class="report-excerpt">${report.title.substring(0, 100)}...</p>
            <div class="report-meta">
                <span>📍 ${report.location || 'Location not specified'}</span>
                <span>🕒 ${timeAgo(report.created_at)}</span>
                <span class="report-token-hint">Token: ${report.token}</span>
            </div>
        </div>
    `).join('');
}

// Helper: time ago formatter
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    return `${days} day${days > 1 ? 's' : ''} ago`;
}

// View report details
window.viewReport = function(token) {
    // Store token and open track modal
    document.getElementById('trackToken').value = token;
    showModal(document.getElementById('trackModal'));
};

// Update report form submission
async function submitReport(formData) {
    try {
        const response = await fetch(`${API_BASE}/report/submit`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Report submitted! Your token: ' + data.data.token);
            saveToken(data.data.token);
            fetchStats(); // Refresh stats
            fetchLatestReports(); // Refresh reports list
            return true;
        } else {
            showNotification(data.message, 'error');
            return false;
        }
    } catch (error) {
        showNotification('Failed to submit report', 'error');
        return false;
    }
}

// Update track report function
async function trackReport(token) {
    try {
        const response = await fetch(`${API_BASE}/report/track?token=${token}`);
        const data = await response.json();
        
        if (data.success) {
            showResult(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Failed to track report', 'error');
    }
}

// Update the report form submit handler
if (reportForm) {
    reportForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('category', document.getElementById('reportCategory').value);
        formData.append('title', document.getElementById('reportTitle').value);
        formData.append('description', document.getElementById('reportDesc').value);
        formData.append('location', document.getElementById('reportLocation').value);
        
        const evidence = document.getElementById('evidence').files[0];
        if (evidence) {
            formData.append('evidence', evidence);
        }
        
        const success = await submitReport(formData);
        if (success) {
            hideModal(reportModal);
            reportForm.reset();
        }
    });
}

// Update track form submit
if (trackForm) {
    trackForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const token = document.getElementById('trackToken').value;
        if (!token) return;
        
        hideModal(trackModal);
        trackReport(token);
    });
}

// Initialize data on page load
document.addEventListener('DOMContentLoaded', () => {
    // Existing initialization
    init();
    
    // Fetch data from backend
    fetchStats();
    fetchLatestReports();
    
    // Refresh stats every 30 seconds
    setInterval(fetchStats, 30000);
    setInterval(fetchLatestReports, 30000);
});
    
    // Set initial theme
    document.body.setAttribute('data-theme', currentTheme);

    // Translations
    const translations = {
        en: {
            // Header
            'logo': '📋 CAMPUS GOVERNANCE SYSTEM',
            'institution': 'DIU ',
            
            // Hero
            'cta-title': 'Speak up. ',
            'cta-title-span': 'Stay anonymous.',
            'cta-subtitle': 'Report campus issues anonymously or with your identity. Track until resolution.',
            'report-btn': 'Submit Anonymous Report',
            'track-btn': 'Track Your Token',
            
            // Stats
            'total-reports': 'Total Reports',
            'verified': 'Verified Incidents',
            'pending': 'Under Review',
            'resolved': 'Resolved',
            
            // Categories
            'all-issues': '🔍 All Issues',
            'academic': '📚 Academic',
            'facility': '🏫 Facility',
            'transport': '🚌 Transport',
            'security': '🛡️ Security',
            'harassment': '⚖️ Harassment',
            'it': '💻 IT Services',
            'admin': '🧾 Administrative',
            'search-placeholder': '🔍 Search reports by keyword, location, or token...',
            'search-btn': 'Search',
            
            // Feed
            'latest-reports': '📌 Latest Reports',
            'load-more': 'Load More Reports ↓',
            
            // Process
            'process-title': 'The Process',
            'step1-title': 'Submit Anonymously',
            'step1-desc': 'No login required. Your identity is protected.',
            'step2-title': 'Receive Secure Token',
            'step2-desc': 'Track, update, or withdraw your report anytime.',
            'step3-title': 'Authority Review',
            'step3-desc': 'Department investigates with SLA enforcement.',
            'step4-title': 'Resolution & Feedback',
            'step4-desc': 'Outcome published. Rate your experience.',
            
            // Emergency
            'emergency-title': '🚨 Emergency Contacts',
            'security': 'Campus Security',
            'security-desc': '24/7 Emergency Response',
            'medical': 'Medical Center',
            'medical-desc': 'First Aid & Emergency',
            'womens-support': 'Women\'s Support',
            'womens-desc': 'Harassment & Safety',
            'fire': 'Fire & Safety',
            'fire-desc': 'Fire Emergency',
            'call-now': 'Call Now',
            
            // Footer
            'about-title': 'About',
            'about-text': 'DIU Campus Governance System ensures transparent, anonymous, and accountable issue resolution for the entire university community.',
            'quick-links': 'Quick Links',
            'submit-report': 'Submit Report',
            'track-report': 'Track Report',
            'contact': 'Contact',
            'copyright': '© 2026 DIU Campus Governance System',
            'tagline': 'Anonymous · Secure · Accountable',
            
            // Modals
            'report-modal-title': 'Submit Anonymous Report',
            'track-modal-title': 'Track Your Report',
            'category': 'Category',
            'select-category': 'Select category',
            'title': 'Title',
            'title-placeholder': 'Brief description',
            'description': 'Full Description',
            'description-placeholder': 'Provide detailed information...',
            'location': 'Location',
            'location-placeholder': 'Building, room number, or area',
            'evidence': 'Attach Evidence (Optional)',
            'anonymous-note': '✅ You are posting anonymously. Your identity is protected.',
            'submit': 'Submit Report →',
            'track-token': 'Enter Tracking Token',
            'token-placeholder': 'e.g., CGS-A7F3-4B9C',
            'track': '🔍 Track Report',
            'recent-tokens': 'Your recent tokens:'
        },
        bn: {
            // Header
            'logo': '📋 ক্যাম্পাস গভর্নেন্স সিস্টেম',
            'institution': 'ডিআইইউ (সিজিএস)',
            
            // Hero
            'cta-title': 'সোচ্চার হোন। ',
            'cta-title-span': 'বেনামে থাকুন।',
            'cta-subtitle': 'বেনামে বা পরিচয় সহ ক্যাম্পাসের সমস্যা রিপোর্ট করুন। সমাধান পর্যন্ত ট্র্যাক করুন।',
            'report-btn': 'বেনামে রিপোর্ট জমা দিন',
            'track-btn': 'আপনার টোকেন ট্র্যাক করুন',
            
            // Stats
            'total-reports': 'মোট রিপোর্ট',
            'verified': 'যাচাইকৃত ঘটনা',
            'pending': 'পর্যালোচনাধীন',
            'resolved': 'সমাধানকৃত',
            
            // Categories
            'all-issues': '🔍 সব সমস্যা',
            'academic': '📚 একাডেমিক',
            'facility': '🏫 সুবিধা',
            'transport': '🚌 পরিবহন',
            'security': '🛡️ নিরাপত্তা',
            'harassment': '⚖️ হয়রানি',
            'it': '💻 আইটি সেবা',
            'admin': '🧾 প্রশাসনিক',
            'search-placeholder': '🔍 কীওয়ার্ড, অবস্থান বা টোকেন দ্বারা অনুসন্ধান...',
            'search-btn': 'অনুসন্ধান',
            
            // Feed
            'latest-reports': '📌 সর্বশেষ রিপোর্ট',
            'load-more': 'আরও রিপোর্ট দেখুন ↓',
            
            // Process
            'process-title': 'পদ্ধতি',
            'step1-title': 'বেনামে জমা দিন',
            'step1-desc': 'লগইনের প্রয়োজন নেই। আপনার পরিচয় সুরক্ষিত।',
            'step2-title': 'নিরাপদ টোকেন গ্রহণ',
            'step2-desc': 'যে কোনো সময় রিপোর্ট ট্র্যাক, আপডেট বা প্রত্যাহার করুন।',
            'step3-title': 'কর্তৃপক্ষের পর্যালোচনা',
            'step3-desc': 'বিভাগ এসএলএ সহ তদন্ত করে।',
            'step4-title': 'সমাধান ও মতামত',
            'step4-desc': 'ফলাফল প্রকাশিত। আপনার অভিজ্ঞতা রেট দিন।',
            
            // Emergency
            'emergency-title': '🚨 জরুরি যোগাযোগ',
            'security': 'ক্যাম্পাস নিরাপত্তা',
            'security-desc': '২৪/৭ জরুরি সেবা',
            'medical': 'মেডিকেল সেন্টার',
            'medical-desc': 'প্রাথমিক চিকিৎসা ও জরুরি সেবা',
            'womens-support': 'নারী সহায়তা',
            'womens-desc': 'হয়রানি ও নিরাপত্তা',
            'fire': 'অগ্নি নিরাপত্তা',
            'fire-desc': 'অগ্নিকাণ্ড জরুরি',
            'call-now': 'কল করুন',
            
            // Footer
            'about-title': 'পরিচিতি',
            'about-text': 'ডিআইইউ ক্যাম্পাস গভর্নেন্স সিস্টেম সম্পূর্ণ বিশ্ববিদ্যালয় সম্প্রদায়ের জন্য স্বচ্ছ, বেনামী এবং জবাবদিহিমূলক সমস্যা সমাধান নিশ্চিত করে।',
            'quick-links': 'দ্রুত লিংক',
            'submit-report': 'রিপোর্ট জমা দিন',
            'track-report': 'রিপোর্ট ট্র্যাক করুন',
            'contact': 'যোগাযোগ',
            'copyright': '© ২০২৬ ডিআইইউ ক্যাম্পাস গভর্নেন্স সিস্টেম',
            'tagline': 'বেনামী · নিরাপদ · জবাবদিহিমূলক',
            
            // Modals
            'report-modal-title': 'বেনামে রিপোর্ট জমা দিন',
            'track-modal-title': 'আপনার রিপোর্ট ট্র্যাক করুন',
            'category': 'বিভাগ',
            'select-category': 'বিভাগ নির্বাচন করুন',
            'title': 'শিরোনাম',
            'title-placeholder': 'সংক্ষিপ্ত বিবরণ',
            'description': 'বিস্তারিত বিবরণ',
            'description-placeholder': 'বিস্তারিত তথ্য দিন...',
            'location': 'অবস্থান',
            'location-placeholder': 'ভবন, রুম নম্বর বা এলাকা',
            'evidence': 'প্রমাণ সংযুক্ত করুন (ঐচ্ছিক)',
            'anonymous-note': '✅ আপনি বেনামে পোস্ট করছেন। আপনার পরিচয় সুরক্ষিত।',
            'submit': 'রিপোর্ট জমা দিন →',
            'track-token': 'ট্র্যাকিং টোকেন লিখুন',
            'token-placeholder': 'যেমন: CGS-A7F3-4B9C',
            'track': '🔍 রিপোর্ট ট্র্যাক করুন',
            'recent-tokens': 'আপনার সাম্প্রতিক টোকেন:'
        }
    };

    // Initialize language
    function setLanguage(lang) {
        currentLanguage = lang;
        localStorage.setItem('cgs_language', lang);
        
        // Update active button
        langBtns.forEach(btn => {
            if (btn.dataset.lang === lang) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        // Update all translatable elements
        updateUILanguage();
    }

    function updateUILanguage() {
        const t = translations[currentLanguage];
        
        // Header
        document.querySelector('.logo').innerHTML = t['logo'];
        document.querySelector('.institution').textContent = t['institution'];
        
        // Hero
        document.querySelector('.cta-title').innerHTML = t['cta-title'] + '<span>' + t['cta-title-span'] + '</span>';
        document.querySelector('.cta-subtitle').textContent = t['cta-subtitle'];
        document.querySelector('.primary-btn').innerHTML = '<span class="btn-icon">📢</span> ' + t['report-btn'];
        document.querySelector('.secondary-btn').innerHTML = '<span class="btn-icon">🔍</span> ' + t['track-btn'];
        
        // Stats
        document.querySelectorAll('.stat-label')[0].textContent = t['total-reports'];
        document.querySelectorAll('.stat-label')[1].textContent = t['verified'];
        document.querySelectorAll('.stat-label')[2].textContent = t['pending'];
        document.querySelectorAll('.stat-label')[3].textContent = t['resolved'];
        
        // Categories
        const chips = document.querySelectorAll('.chip');
        chips[0].innerHTML = t['all-issues'];
        chips[1].innerHTML = t['academic'];
        chips[2].innerHTML = t['facility'];
        chips[3].innerHTML = t['transport'];
        chips[4].innerHTML = t['security'];
        chips[5].innerHTML = t['harassment'];
        chips[6].innerHTML = t['it'];
        chips[7].innerHTML = t['admin'];
        
        document.getElementById('searchReports').placeholder = t['search-placeholder'];
        document.getElementById('searchBtn').textContent = t['search-btn'];
        
        // Feed
        document.querySelector('.feed-title').textContent = t['latest-reports'];
        document.getElementById('loadMoreBtn').textContent = t['load-more'];
        
        // Process
        document.querySelector('.how-it-works .section-title').textContent = t['process-title'];
        const stepTitles = document.querySelectorAll('.step-item h4');
        const stepDescs = document.querySelectorAll('.step-item p');
        stepTitles[0].textContent = t['step1-title'];
        stepTitles[1].textContent = t['step2-title'];
        stepTitles[2].textContent = t['step3-title'];
        stepTitles[3].textContent = t['step4-title'];
        stepDescs[0].textContent = t['step1-desc'];
        stepDescs[1].textContent = t['step2-desc'];
        stepDescs[2].textContent = t['step3-desc'];
        stepDescs[3].textContent = t['step4-desc'];
        
        // Emergency
        document.querySelector('.emergency-contacts-section .section-title').textContent = t['emergency-title'];
        const contactTitles = document.querySelectorAll('.contact-info h4');
        const contactDescs = document.querySelectorAll('.contact-info p');
        const callBtns = document.querySelectorAll('.call-btn');
        contactTitles[0].textContent = t['security'];
        contactDescs[0].textContent = t['security-desc'];
        contactTitles[1].textContent = t['medical'];
        contactDescs[1].textContent = t['medical-desc'];
        contactTitles[2].textContent = t['womens-support'];
        contactDescs[2].textContent = t['womens-desc'];
        contactTitles[3].textContent = t['fire'];
        contactDescs[3].textContent = t['fire-desc'];
        callBtns.forEach(btn => btn.textContent = t['call-now']);
        
        // Footer
        document.querySelectorAll('.footer-col h4')[0].textContent = t['about-title'];
        document.querySelectorAll('.footer-col p')[0].textContent = t['about-text'];
        document.querySelectorAll('.footer-col h4')[1].textContent = t['quick-links'];
        document.querySelectorAll('.footer-col ul li a')[0].textContent = t['submit-report'];
        document.querySelectorAll('.footer-col ul li a')[1].textContent = t['track-report'];
        document.querySelectorAll('.footer-col h4')[2].textContent = t['contact'];
        document.querySelector('.footer-bottom p:first-child').textContent = t['copyright'];
        document.querySelector('.footer-tagline').textContent = t['tagline'];
        
        // Modals
        document.querySelector('#reportModal .modal-header h3').textContent = t['report-modal-title'];
        document.querySelector('#trackModal .modal-header h3').textContent = t['track-modal-title'];
        
        // Form labels
        const formLabels = document.querySelectorAll('#reportForm .form-group label');
        if (formLabels.length >= 6) {
            formLabels[0].innerHTML = t['category'] + ' <span class="required">*</span>';
            formLabels[1].innerHTML = t['title'] + ' <span class="required">*</span>';
            formLabels[2].innerHTML = t['description'] + ' <span class="required">*</span>';
            formLabels[3].textContent = t['location'];
            formLabels[4].textContent = t['evidence'];
        }
        
        document.querySelector('#reportCategory option:first-child').textContent = t['select-category'];
        document.getElementById('reportTitle').placeholder = t['title-placeholder'];
        document.getElementById('reportDesc').placeholder = t['description-placeholder'];
        document.getElementById('reportLocation').placeholder = t['location-placeholder'];
        document.querySelector('.anonymous-notice .checkbox span').textContent = t['anonymous-note'];
        document.querySelector('.submit-btn').innerHTML = t['submit'];
        
        document.querySelector('#trackModal label').textContent = t['track-token'];
        document.getElementById('trackToken').placeholder = t['token-placeholder'];
        document.querySelector('.track-submit').innerHTML = t['track'];
        document.querySelector('.saved-title').textContent = t['recent-tokens'];
    }

    // Generate Token
    function generateToken() {
        const prefix = 'CGS';
        const random = Math.random().toString(36).substring(2, 8).toUpperCase();
        const timestamp = Date.now().toString(36).substring(0, 4).toUpperCase();
        return `${prefix}-${timestamp}-${random}`;
    }

    // Show Modal
    function showModal(modal) {
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // Hide Modal
    function hideModal(modal) {
        if (!modal) return;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Hide all modals
    function hideAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('show');
        });
        document.body.style.overflow = '';
    }

    // Show Notification
    function showNotification(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Save Token
    function saveToken(token) {
        savedTokens.push(token);
        if (savedTokens.length > 5) savedTokens.shift();
        localStorage.setItem('cgs_tokens', JSON.stringify(savedTokens));
        updateTokenList();
    }

    // Update Token List
    function updateTokenList() {
        const tokenList = document.getElementById('tokenList');
        if (!tokenList) return;
        
        if (savedTokens.length === 0) {
            tokenList.innerHTML = '<p style="color: var(--text-muted);">No recent tokens</p>';
            return;
        }
        
        tokenList.innerHTML = savedTokens.map(token => 
            `<div class="token-item">${token}</div>`
        ).join('');
        
        document.querySelectorAll('.token-item').forEach(item => {
            item.addEventListener('click', () => {
                document.getElementById('trackToken').value = item.textContent;
            });
        });
    }

    // Show Result
    function showResult(token, status = 'progress') {
        const resultContent = document.getElementById('resultContent');
        if (!resultContent) return;
        
        const statusText = status === 'pending' ? 'Pending' : 
                          status === 'progress' ? 'In Progress' : 'Resolved';
        const statusClass = status === 'pending' ? 'status-pending' : 
                           status === 'progress' ? 'status-suspect' : 'status-verified';
        
        resultContent.innerHTML = `
            <div style="text-align: center; margin-bottom: 24px;">
                <span class="report-status ${statusClass}">${statusText}</span>
            </div>
            <div style="background: var(--bg-surface); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Token:</strong> ${token}</p>
                <p><strong>Submitted:</strong> ${new Date().toLocaleDateString()}</p>
                <p><strong>Department:</strong> Academic Affairs</p>
            </div>
            <div class="timeline">
                <div style="padding: 8px 0; border-left: 2px solid var(--accent-primary); padding-left: 16px; margin-left: 8px;">
                    <div>${new Date().toLocaleDateString()} - Report submitted</div>
                    <div style="color: var(--text-muted); margin-top: 4px;">Today - Being reviewed</div>
                </div>
            </div>
        `;
        
        showModal(resultModal);
    }

    // Progress Bar
    function updateProgressBar() {
        const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        if (progressBar) {
            progressBar.style.width = scrolled + '%';
        }
    }

    // Testimonial Slider
    let currentTestimonial = 0;
    function rotateTestimonials() {
        const items = document.querySelectorAll('.testimonial-item');
        if (items.length === 0) return;
        
        items.forEach(item => item.classList.remove('active'));
        currentTestimonial = (currentTestimonial + 1) % items.length;
        items[currentTestimonial].classList.add('active');
    }

    // ===== EVENT LISTENERS =====

    // Report button
    if (reportBtn) {
        reportBtn.addEventListener('click', () => {
            showModal(reportModal);
        });
    }

    // Track button
    if (trackBtn) {
        trackBtn.addEventListener('click', () => {
            updateTokenList();
            showModal(trackModal);
        });
    }

    // Category chips
    categoryChips.forEach(chip => {
        chip.addEventListener('click', () => {
            categoryChips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            // Filter reports logic here
        });
    });

    // Close buttons
    closeButtons.forEach(btn => {
        btn.addEventListener('click', hideAllModals);
    });

    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', hideAllModals);
    });

    // Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideAllModals();
        }
    });

    // Report form submit
    if (reportForm) {
        reportForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const category = document.getElementById('reportCategory').value;
            const title = document.getElementById('reportTitle').value;
            const desc = document.getElementById('reportDesc').value;
            
            if (!category || !title || !desc) {
                showNotification('Please fill all required fields', 'error');
                return;
            }
            
            const token = generateToken();
            saveToken(token);
            
            hideModal(reportModal);
            reportForm.reset();
            
            showNotification('Report submitted! Your token: ' + token);
        });
    }

    // Track form submit
    if (trackForm) {
        trackForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const token = document.getElementById('trackToken').value;
            if (!token) return;
            
            hideModal(trackModal);
            showResult(token, 'progress');
        });
    }

    // Language toggle
    langBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const lang = btn.dataset.lang;
            setLanguage(lang);
        });
    });

    // Scroll progress
    window.addEventListener('scroll', updateProgressBar);

    // Load more button
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            // Load more reports logic
            showNotification('Loading more reports...', 'success');
        });
    }

    // Search
    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput.value;
            if (query) {
                showNotification('Searching for: ' + query, 'success');
            }
        });
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchBtn.click();
            }
        });
    }

    // Footer links
    document.getElementById('footerReportBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        showModal(reportModal);
    });
    
    document.getElementById('footerTrackBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        showModal(trackModal);
    });

    // Emergency banner close
    document.querySelector('.close-banner')?.addEventListener('click', function() {
        this.closest('.emergency-banner').style.display = 'none';
    });

    // Initialize
    function init() {
        setLanguage(currentLanguage);
        updateTokenList();
        
        // Start testimonial rotation
        setInterval(rotateTestimonials, 5000);
        
        // Set initial testimonial
        setTimeout(() => {
            document.querySelectorAll('.testimonial-item')[0]?.classList.add('active');
        }, 100);
    }

    init();
})();