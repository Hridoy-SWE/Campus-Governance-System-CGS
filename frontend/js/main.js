// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            navMenu.style.display = navMenu.style.display === 'flex' ? 'none' : 'flex';
            navMenu.style.flexDirection = 'column';
            navMenu.style.position = 'absolute';
            navMenu.style.top = '100%';
            navMenu.style.left = '0';
            navMenu.style.right = '0';
            navMenu.style.background = 'white';
            navMenu.style.padding = '1rem';
            navMenu.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
        });
    }
    
    // Update copyright year
    const yearSpan = document.getElementById('current-year');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width/2 - tooltip.offsetWidth/2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
            }
        });
    });
});

// Form validation for report page
function validateReportForm() {
    const title = document.getElementById('issue-title');
    const description = document.getElementById('issue-description');
    const category = document.getElementById('issue-category');
    let isValid = true;
    
    // Reset errors
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    document.querySelectorAll('.form-group').forEach(el => el.classList.remove('error'));
    
    if (!title.value.trim()) {
        showError(title, 'Title is required');
        isValid = false;
    }
    
    if (!description.value.trim()) {
        showError(description, 'Description is required');
        isValid = false;
    } else if (description.value.trim().length < 20) {
        showError(description, 'Description must be at least 20 characters');
        isValid = false;
    }
    
    if (!category.value) {
        showError(category, 'Please select a category');
        isValid = false;
    }
    
    if (isValid) {
        // Simulate submission
        const submitBtn = document.querySelector('.submit-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitBtn.disabled = true;
        
        // Generate tracking token
        const token = generateToken();
        
        setTimeout(() => {
            document.getElementById('tracking-token').textContent = token;
            document.getElementById('success-message').style.display = 'block';
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            
            // Scroll to success message
            document.getElementById('success-message').scrollIntoView({ behavior: 'smooth' });
        }, 2000);
    }
    
    return false; // Prevent form submission for demo
}

function showError(input, message) {
    input.parentElement.classList.add('error');
    const error = document.createElement('div');
    error.className = 'error-message';
    error.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    input.parentElement.appendChild(error);
}

function generateToken() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let token = 'CGS-';
    for (let i = 0; i < 12; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return token;
}

// Track issue function
function trackIssue() {
    const tokenInput = document.getElementById('tracking-token');
    const token = tokenInput.value.trim().toUpperCase();
    
    if (!token) {
        alert('Please enter a tracking token');
        return false;
    }
    
    // Simulate API call
    const statusDiv = document.getElementById('tracking-status');
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching for issue...';
    
    setTimeout(() => {
        // Demo data - in real app, this would come from backend
        const demoStatus = {
            'CGS-ABC123XYZ789': {
                title: 'Broken AC in Room 502',
                status: 'In Progress',
                department: 'Facilities Management',
                priority: 'High',
                submitted: '2026-01-28',
                lastUpdate: '2026-01-30',
                history: [
                    { date: '2026-01-28', status: 'Submitted', remarks: 'Issue reported' },
                    { date: '2026-01-29', status: 'Assigned', remarks: 'Assigned to Facilities Dept.' },
                    { date: '2026-01-30', status: 'In Progress', remarks: 'Technician assigned, parts ordered' }
                ]
            },
            'CGS-DEF456UVW123': {
                title: 'Slow Wi-Fi in Library',
                status: 'Resolved',
                department: 'IT Services',
                priority: 'Medium',
                submitted: '2026-01-25',
                lastUpdate: '2026-01-27',
                history: [
                    { date: '2026-01-25', status: 'Submitted' },
                    { date: '2026-01-26', status: 'In Progress', remarks: 'Network team investigating' },
                    { date: '2026-01-27', status: 'Resolved', remarks: 'Router replaced, speed restored' }
                ]
            }
        };
        
        if (demoStatus[token]) {
            const issue = demoStatus[token];
            let html = `
                <div class="status-card success">
                    <h3><i class="fas fa-check-circle"></i> Issue Found</h3>
                    <div class="status-info">
                        <p><strong>Title:</strong> ${issue.title}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${issue.status.toLowerCase().replace(' ', '-')}">${issue.status}</span></p>
                        <p><strong>Department:</strong> ${issue.department}</p>
                        <p><strong>Priority:</strong> <span class="priority-badge ${issue.priority.toLowerCase()}">${issue.priority}</span></p>
                        <p><strong>Submitted:</strong> ${issue.submitted}</p>
                        <p><strong>Last Update:</strong> ${issue.lastUpdate}</p>
                    </div>
                    <h4>Status History</h4>
                    <div class="timeline">
            `;
            
            issue.history.forEach(entry => {
                html += `
                    <div class="timeline-item">
                        <div class="timeline-date">${entry.date}</div>
                        <div class="timeline-content">
                            <span class="timeline-status">${entry.status}</span>
                            ${entry.remarks ? `<p>${entry.remarks}</p>` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
            statusDiv.innerHTML = html;
        } else {
            statusDiv.innerHTML = `
                <div class="status-card error">
                    <h3><i class="fas fa-exclamation-triangle"></i> Issue Not Found</h3>
                    <p>No issue found with tracking token: <strong>${token}</strong></p>
                    <p>Please check the token and try again.</p>
                </div>
            `;
        }
    }, 1500);
    
    return false;
}