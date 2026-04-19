<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';

function admin_safe_redirect_target(?string $target): string
{
    $target = trim((string)$target);

    if ($target === '') {
        return 'dashboard.php';
    }

    // Only allow local admin php targets without protocol/host
    if (
        str_contains($target, '://') ||
        str_starts_with($target, '//') ||
        str_contains($target, "\n") ||
        str_contains($target, "\r")
    ) {
        return 'dashboard.php';
    }

    $allowed = [
        'dashboard.php',
        'report_view.php',
        'spam_reports.php',
        'users.php',
        'messages.php',
        'profile.php',
    ];

    return in_array($target, $allowed, true) ? $target : 'dashboard.php';
}

$redirectTarget = admin_safe_redirect_target($_GET['redirect'] ?? 'dashboard.php');

if (admin_sync_from_backend_session()) {
    admin_redirect($redirectTarget);
}

$flash = admin_get_flash();
$csrfToken = admin_create_csrf_token();
$error = '';
$loginValue = '';

if (admin_is_post()) {
    $loginValue = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $submittedTheme = (string)($_POST['theme'] ?? DEFAULT_THEME);
    $redirectTarget = admin_safe_redirect_target($_POST['redirect'] ?? 'dashboard.php');

    admin_set_theme($submittedTheme);

    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } elseif ($loginValue === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $user = backend_admin_find_user_for_login($loginValue);

        if (!$user) {
            $error = 'Invalid credentials.';
        } elseif (backend_admin_user_is_blocked($user)) {
            $error = 'This account is blocked. Please contact administration.';
        } elseif (!backend_admin_verify_password($user, $password)) {
            $error = 'Invalid credentials.';
        } else {
            admin_login_user($user);
            admin_set_flash('success', 'Login successful.');
            admin_redirect($redirectTarget);
        }
    }
}

$currentTheme = admin_current_theme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_page_title('Admin Login'); ?></title>

    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .admin-auth-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px;
        }

        .admin-auth-shell {
            width: min(1200px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(360px, 0.85fr);
            gap: 24px;
        }

        .admin-auth-showcase,
        .admin-auth-card {
            border-radius: 28px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(17, 28, 46, 0.72);
            box-shadow: 0 24px 60px rgba(0,0,0,0.22);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            position: relative;
            overflow: hidden;
        }

        body[data-theme="light"] .admin-auth-showcase,
        body[data-theme="light"] .admin-auth-card {
            background: #eef1f5;
            border-color: rgba(198,205,216,0.9);
            box-shadow: 0 24px 60px rgba(17,24,39,0.10);
        }

        .admin-auth-showcase::before,
        .admin-auth-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08), transparent 32%);
            pointer-events: none;
        }

        .admin-auth-showcase,
        .admin-auth-card {
            padding: 30px;
        }

        .admin-auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: rgba(98, 227, 255, 0.10);
            border: 1px solid rgba(98, 227, 255, 0.18);
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 18px;
        }

        .admin-auth-hero h1 {
            font-size: clamp(2rem, 3vw, 3rem);
            line-height: 1.05;
            margin-bottom: 14px;
        }

        .admin-auth-hero p {
            color: var(--text-secondary);
            line-height: 1.7;
            max-width: 64ch;
        }

        .admin-auth-feature-grid {
            display: grid;
            gap: 14px;
            margin-top: 24px;
        }

        .admin-auth-feature-card {
            display: grid;
            grid-template-columns: 52px minmax(0, 1fr);
            gap: 14px;
            padding: 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
        }

        body[data-theme="light"] .admin-auth-feature-card {
            background: #e9edf2;
            border-color: rgba(198,205,216,0.86);
        }

        .admin-auth-feature-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: var(--gradient-primary);
            color: #fff;
            font-size: 1.05rem;
            box-shadow: 0 14px 28px rgba(53,99,233,0.16);
        }

        .admin-auth-feature-card h3 {
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .admin-auth-feature-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.94rem;
        }

        .admin-auth-note {
            margin-top: 22px;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.94rem;
        }

        body[data-theme="light"] .admin-auth-note {
            background: #e9edf2;
            border-color: rgba(198,205,216,0.86);
        }

        .admin-auth-card-top {
            text-align: center;
            margin-bottom: 22px;
        }

        .admin-auth-logo {
            width: 72px;
            height: 72px;
            margin: 0 auto 14px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: var(--gradient-primary);
            color: #fff;
            font-size: 1.5rem;
            box-shadow: 0 18px 36px rgba(53,99,233,0.18);
        }

        .admin-auth-card-top h2 {
            margin-bottom: 6px;
        }

        .admin-auth-card-top p {
            color: var(--text-secondary);
        }

        .admin-alert {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .admin-alert-success {
            background: rgba(16, 185, 129, 0.12);
            border-color: rgba(16, 185, 129, 0.20);
            color: #d6fff0;
        }

        .admin-alert-error {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.20);
            color: #ffdce2;
        }

        body[data-theme="light"] .admin-alert-success {
            background: #def2ea;
            border-color: #c2e3d7;
            color: #0f8160;
        }

        body[data-theme="light"] .admin-alert-error {
            background: #f7e1e4;
            border-color: #ebc5ca;
            color: #b53e47;
        }

        .admin-auth-form {
            display: grid;
            gap: 16px;
        }

        .admin-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .admin-input-wrap {
            position: relative;
        }

        .admin-input-wrap > i:first-child {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .admin-input-wrap input {
            width: 100%;
            min-height: 52px;
            padding: 0 48px 0 48px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.05);
            color: var(--text-primary);
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }

        .admin-input-wrap input:focus {
            border-color: rgba(124,156,255,0.38);
            box-shadow: 0 0 0 4px rgba(124,156,255,0.10);
        }

        body[data-theme="light"] .admin-input-wrap input {
            background: #ffffff;
            border-color: rgba(198,205,216,0.9);
        }

        .admin-password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: transparent;
            color: var(--text-muted);
            border: none;
            cursor: pointer;
        }

        .admin-auth-options {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .admin-check-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 0.92rem;
        }

        .admin-theme-switch {
            min-height: 40px;
            padding: 0 12px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text-primary);
            cursor: pointer;
        }

        body[data-theme="light"] .admin-theme-switch {
            background: #e9edf2;
            border-color: rgba(198,205,216,0.86);
        }

        .admin-btn {
            min-height: 50px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .admin-btn-primary,
        .admin-auth-submit {
            background: var(--gradient-primary);
            color: #fff;
            box-shadow: 0 16px 32px rgba(53,99,233,0.18);
        }

        .admin-auth-footer {
            margin-top: 18px;
            text-align: center;
        }

        .admin-auth-footer a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
        }

        @media (max-width: 980px) {
            .admin-auth-shell {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .admin-auth-showcase,
            .admin-auth-card {
                padding: 22px;
            }
        }
    </style>
</head>
<body data-theme="<?php echo admin_h($currentTheme); ?>">
    <div class="admin-auth-page">
        <div class="admin-auth-shell">
            <section class="admin-auth-showcase">
                <div class="admin-auth-badge">
                    <i class="fas fa-shield-halved"></i>
                    <span>Protected Administrative Access</span>
                </div>

                <div class="admin-auth-hero">
                    <h1>Secure access to campus reports, moderation, users, and administrative workflow.</h1>
                    <p>
                        This page is a protected fallback login for authorized admin or faculty users. In the normal user journey, authenticated admin users should be routed directly from the main site into the admin dashboard without seeing this page.
                    </p>
                </div>

                <div class="admin-auth-feature-grid">
                    <div class="admin-auth-feature-card">
                        <div class="admin-auth-feature-icon">
                            <i class="fas fa-folder-tree"></i>
                        </div>
                        <div>
                            <h3>Central report management</h3>
                            <p>Review submitted reports, update statuses, inspect evidence, resolve cases, and manage moderation flow from one workspace.</p>
                        </div>
                    </div>

                    <div class="admin-auth-feature-card">
                        <div class="admin-auth-feature-icon">
                            <i class="fas fa-users-gear"></i>
                        </div>
                        <div>
                            <h3>User and faculty control</h3>
                            <p>Manage accounts, role-based access, profile settings, and administrative visibility across the system.</p>
                        </div>
                    </div>

                    <div class="admin-auth-feature-card">
                        <div class="admin-auth-feature-icon">
                            <i class="fas fa-envelope-circle-check"></i>
                        </div>
                        <div>
                            <h3>Messages and escalation</h3>
                            <p>Handle report-linked messaging, moderation notes, user communication, and departmental follow-up actions.</p>
                        </div>
                    </div>
                </div>

                <div class="admin-auth-note">
                    This page should remain available as a direct fallback entry for authorized administrators, but it should not be the normal destination after main-site login or signup.
                </div>
            </section>

            <section class="admin-auth-card">
                <div class="admin-auth-card-top">
                    <div class="admin-auth-logo">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h2>Admin Login</h2>
                    <p>Campus Governance Administrative Console</p>
                </div>

                <?php if ($flash): ?>
                    <div class="admin-alert admin-alert-<?php echo admin_h($flash['type']); ?>">
                        <?php echo admin_h($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="admin-alert admin-alert-error">
                        <?php echo admin_h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="admin-auth-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                    <input type="hidden" name="theme" id="themeInput" value="<?php echo admin_h($currentTheme); ?>">
                    <input type="hidden" name="redirect" value="<?php echo admin_h($redirectTarget); ?>">

                    <div class="admin-form-group">
                        <label for="login">Username or Email</label>
                        <div class="admin-input-wrap">
                            <i class="fas fa-user"></i>
                            <input
                                type="text"
                                id="login"
                                name="login"
                                value="<?php echo admin_h($loginValue); ?>"
                                placeholder="Enter admin or faculty username/email"
                                autocomplete="username"
                                required
                            >
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label for="password">Password</label>
                        <div class="admin-input-wrap">
                            <i class="fas fa-lock"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >
                            <button type="button" class="admin-password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="admin-auth-options">
                        <label class="admin-check-wrap">
                            <input type="checkbox" checked disabled>
                            <span>Authorized access only</span>
                        </label>

                        <button type="button" class="admin-theme-switch" id="themeToggleBtn">
                            <i class="fas <?php echo $currentTheme === 'light' ? 'fa-sun' : 'fa-moon'; ?>" id="themeToggleIcon"></i>
                            <span id="themeToggleText"><?php echo $currentTheme === 'light' ? 'Light Mode' : 'Dark Mode'; ?></span>
                        </button>
                    </div>

                    <button type="submit" class="admin-btn admin-auth-submit">
                        <i class="fas fa-right-to-bracket"></i>
                        <span>Login to Admin Panel</span>
                    </button>
                </form>

                <div class="admin-auth-footer">
                    <a href="../index.html">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Main Website</span>
                    </a>
                </div>
            </section>
        </div>
    </div>

    <script>
        (function () {
            const body = document.body;
            const themeInput = document.getElementById('themeInput');
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const themeToggleText = document.getElementById('themeToggleText');

            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');

            function applyTheme(theme) {
                const nextTheme = theme === 'light' ? 'light' : 'dark';
                body.setAttribute('data-theme', nextTheme);
                themeInput.value = nextTheme;
                localStorage.setItem('cgs_theme', nextTheme);

                if (nextTheme === 'light') {
                    themeToggleIcon.className = 'fas fa-sun';
                    themeToggleText.textContent = 'Light Mode';
                } else {
                    themeToggleIcon.className = 'fas fa-moon';
                    themeToggleText.textContent = 'Dark Mode';
                }
            }

            const storedTheme = localStorage.getItem('cgs_theme');
            if (storedTheme === 'light' || storedTheme === 'dark') {
                applyTheme(storedTheme);
            }

            themeToggleBtn.addEventListener('click', function () {
                const currentTheme = body.getAttribute('data-theme') || 'dark';
                applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });

            togglePassword.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                togglePasswordIcon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        })();
    </script>
</body>
</html>