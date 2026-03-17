// ===== Authentication Module for Campus Governance System =====

const Auth = (function() {
    'use strict';

    // ===== State =====
    let currentUser = null;
    let isAuthenticated = false;

    // ===== DOM Elements =====
    function getElements() {
        return {
            loggedOutView: document.getElementById('loggedOutView'),
            loggedInView: document.getElementById('loggedInView'),
            userNameDisplay: document.getElementById('userNameDisplay'),
            userMenu: document.getElementById('userMenu'),
            loginModal: document.getElementById('loginModal'),
            registerModal: document.getElementById('registerModal'),
            loginForm: document.getElementById('loginForm'),
            registerForm: document.getElementById('registerForm'),
            toastContainer: document.getElementById('toastContainer')
        };
    }

    // ===== Toast Notifications =====
    function showToast(message, type = 'success') {
        const elements = getElements();
        if (!elements.toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        elements.toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // ===== Modal Functions =====
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    };

    window.switchModal = function(closeId, openId) {
        closeModal(closeId);
        openModal(openId);
    };

    // ===== User Menu =====
    window.toggleUserMenu = function() {
        const elements = getElements();
        if (elements.userMenu) {
            elements.userMenu.style.display = 
                elements.userMenu.style.display === 'none' || !elements.userMenu.style.display 
                ? 'block' 
                : 'none';
        }
    };

    // Close user menu when clicking outside
    document.addEventListener('click', function(e) {
        const elements = getElements();
        if (elements.userMenu && 
            !e.target.closest('.user-menu-container') && 
            elements.userMenu.style.display === 'block') {
            elements.userMenu.style.display = 'none';
        }
    });

    // ===== Login Handler =====
    window.handleLogin = function(e) {
        e.preventDefault();
        
        const email = document.getElementById('loginEmail')?.value;
        const password = document.getElementById('loginPassword')?.value;
        
        // Validation
        if (!email || !password) {
            showToast('Please fill in all fields', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Signing in...';
        submitBtn.disabled = true;
        
        // Simulate API call (replace with actual API call)
        setTimeout(() => {
            // Demo credentials
            if (email === 'admin' || email === 'admin@diu.edu.bd') {
                // Success
                const userData = {
                    id: 1,
                    name: 'Admin User',
                    email: email,
                    role: 'admin',
                    department: 'Administration'
                };
                
                // Save to session
                sessionStorage.setItem('cgs_logged_in', 'true');
                sessionStorage.setItem('cgs_user', JSON.stringify(userData));
                sessionStorage.setItem('cgs_token', 'demo-token-' + Date.now());
                
                // Update UI
                updateAuthUI(true, userData);
                
                closeModal('loginModal');
                showToast('Logged in successfully!', 'success');
            } 
            else if (email === 'faculty' || email === 'faculty@diu.edu.bd') {
                // Faculty login
                const userData = {
                    id: 2,
                    name: 'Dr. Rahman',
                    email: email,
                    role: 'faculty',
                    department: 'CSE'
                };
                
                sessionStorage.setItem('cgs_logged_in', 'true');
                sessionStorage.setItem('cgs_user', JSON.stringify(userData));
                sessionStorage.setItem('cgs_token', 'demo-token-' + Date.now());
                
                updateAuthUI(true, userData);
                closeModal('loginModal');
                showToast('Logged in successfully!', 'success');
            }
            else if (email === 'student' || email === 'student@diu.edu.bd') {
                // Student login
                const userData = {
                    id: 3,
                    name: 'Rahim Khan',
                    email: email,
                    role: 'student',
                    department: 'CSE'
                };
                
                sessionStorage.setItem('cgs_logged_in', 'true');
                sessionStorage.setItem('cgs_user', JSON.stringify(userData));
                sessionStorage.setItem('cgs_token', 'demo-token-' + Date.now());
                
                updateAuthUI(true, userData);
                closeModal('loginModal');
                showToast('Logged in successfully!', 'success');
            }
            else {
                // Failed login
                showToast('Invalid credentials. Try: admin / faculty / student', 'error');
            }
            
            // Reset button
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }, 1000);
    };

    // ===== Register Handler =====
    window.handleRegister = function(e) {
        e.preventDefault();
        
        const name = document.getElementById('regName')?.value;
        const email = document.getElementById('regEmail')?.value;
        const role = document.getElementById('regRole')?.value;
        const department = document.getElementById('regDepartment')?.value;
        const password = document.getElementById('regPassword')?.value;
        const confirm = document.getElementById('regConfirmPassword')?.value;
        
        // Validation
        if (!name || !email || !password || !confirm) {
            showToast('Please fill in all required fields', 'error');
            return;
        }
        
        if (password !== confirm) {
            showToast('Passwords do not match!', 'error');
            return;
        }
        
        if (password.length < 6) {
            showToast('Password must be at least 6 characters', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creating account...';
        submitBtn.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            // Create user data
            const userData = {
                id: Date.now(),
                name: name,
                email: email,
                role: role,
                department: department || 'Not specified'
            };
            
            // In real app, you would send to backend here
            console.log('Registration data:', userData);
            
            showToast('Registration successful! Please login.', 'success');
            switchModal('registerModal', 'loginModal');
            
            // Reset button
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            
            // Clear form
            e.target.reset();
        }, 1000);
    };

    // ===== Logout Handler =====
    window.logout = function() {
        // Clear session
        sessionStorage.removeItem('cgs_logged_in');
        sessionStorage.removeItem('cgs_user');
        sessionStorage.removeItem('cgs_token');
        
        // Update UI
        updateAuthUI(false);
        
        // Close user menu if open
        const elements = getElements();
        if (elements.userMenu) {
            elements.userMenu.style.display = 'none';
        }
        
        showToast('Logged out successfully', 'success');
        
        // Redirect to home if on protected page
        const protectedPages = ['dashboard.html', 'department.html', 'admin/index.php'];
        const currentPage = window.location.pathname.split('/').pop();
        if (protectedPages.includes(currentPage)) {
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1500);
        }
    };

    // ===== Update Auth UI =====
    function updateAuthUI(isLoggedIn, userData = null) {
        const elements = getElements();
        
        if (!elements.loggedOutView || !elements.loggedInView) return;
        
        if (isLoggedIn && userData) {
            elements.loggedOutView.style.display = 'none';
            elements.loggedInView.style.display = 'flex';
            
            if (elements.userNameDisplay) {
                // Show appropriate name based on role
                if (userData.role === 'admin') {
                    elements.userNameDisplay.textContent = 'Admin';
                } else if (userData.role === 'faculty') {
                    elements.userNameDisplay.textContent = 'Dr. ' + userData.name.split(' ')[0];
                } else {
                    elements.userNameDisplay.textContent = userData.name.split(' ')[0];
                }
            }
            
            // Update dropdown menu based on role
            updateUserMenu(userData);
        } else {
            elements.loggedOutView.style.display = 'flex';
            elements.loggedInView.style.display = 'none';
        }
    }

    // ===== Update User Menu based on Role =====
    function updateUserMenu(userData) {
        const elements = getElements();
        if (!elements.userMenu) return;
        
        // Get all menu items
        const menuLinks = elements.userMenu.querySelectorAll('a, button');
        
        // Show/hide based on role
        menuLinks.forEach(link => {
            const href = link.getAttribute('href');
            
            if (href === 'admin/index.php') {
                // Only show admin panel to admins
                if (userData.role === 'admin') {
                    link.style.display = 'flex';
                } else {
                    link.style.display = 'none';
                }
            }
            
            if (href === 'department.html') {
                // Show department portal to faculty and admins
                if (userData.role === 'faculty' || userData.role === 'admin') {
                    link.style.display = 'flex';
                } else {
                    link.style.display = 'none';
                }
            }
            
            if (href === 'dashboard.html') {
                // Show dashboard to all logged in users
                link.style.display = 'flex';
            }
        });
    }

    // ===== Check Login Status on Load =====
    function checkLoginStatus() {
        const isLoggedIn = sessionStorage.getItem('cgs_logged_in') === 'true';
        const userData = JSON.parse(sessionStorage.getItem('cgs_user'));
        
        if (isLoggedIn && userData) {
            updateAuthUI(true, userData);
        }
    }

    // ===== Add Demo Credentials Helper =====
    function addDemoCredentials() {
        const loginModal = document.getElementById('loginModal');
        if (!loginModal) return;
        
        const demoDiv = document.createElement('div');
        demoDiv.className = 'demo-credentials';
        demoDiv.style.marginTop = '20px';
        demoDiv.style.padding = '15px';
        demoDiv.style.background = 'rgba(59, 130, 246, 0.1)';
        demoDiv.style.borderRadius = '6px';
        demoDiv.style.border = '1px solid var(--border-color)';
        demoDiv.innerHTML = `
            <p style="color: var(--text-secondary); margin-bottom: 10px;"><strong>Demo Credentials:</strong></p>
            <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--accent-primary);">Admin:</span>
                    <span style="color: var(--text-primary);">admin / any password</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--accent-primary);">Faculty:</span>
                    <span style="color: var(--text-primary);">faculty / any password</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--accent-primary);">Student:</span>
                    <span style="color: var(--text-primary);">student / any password</span>
                </div>
            </div>
        `;
        
        const modalBody = loginModal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.appendChild(demoDiv);
        }
    }

    // ===== Initialize =====
    function init() {
        checkLoginStatus();
        addDemoCredentials();
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'L' to open login modal
            if (e.key === 'l' && e.ctrlKey) {
                e.preventDefault();
                if (!sessionStorage.getItem('cgs_logged_in')) {
                    openModal('loginModal');
                }
            }
        });
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', init);

    // Return public methods
    return {
        checkLoginStatus,
        logout: window.logout,
        getCurrentUser: () => JSON.parse(sessionStorage.getItem('cgs_user')),
        isAuthenticated: () => sessionStorage.getItem('cgs_logged_in') === 'true'
    };
})();

// Make Auth globally available
window.Auth = Auth;