<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_bootstrap.php';
admin_require_login();

/*
|--------------------------------------------------------------------------
| BACKEND PLACEHOLDERS
|--------------------------------------------------------------------------
*/

function backend_profile_get(int $userId): array { return admin_profile_get($userId); }
function backend_profile_update(int $userId, array $payload): bool { return admin_profile_update($userId, $payload); }
function backend_profile_change_password(int $userId, string $currentPassword, string $newPassword): bool { return admin_profile_change_password($userId, $currentPassword, $newPassword); }

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
$user = admin_user();
$userId = (int)($user['id'] ?: 1);
$flash = admin_get_flash();
$currentTheme = admin_current_theme();
$csrfToken = admin_create_csrf_token();

if (admin_is_post()) {
    if (!admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Security validation failed. Please try again.');
        admin_redirect('profile.php');
    }

    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'update_profile') {
        $payload = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'department' => trim((string)($_POST['department'] ?? '')),
            'designation' => trim((string)($_POST['designation'] ?? '')),
            'bio' => trim((string)($_POST['bio'] ?? '')),
        ];

        if ($payload['name'] === '' || $payload['email'] === '') {
            admin_set_flash('error', 'Name and email are required.');
            admin_redirect('profile.php');
        }

        if (!empty($_FILES['profile_image']['name'])) {
            $storedPhoto = backend_profile_store_photo($_FILES['profile_image']);
            if ($storedPhoto === null) {
                admin_set_flash('error', 'Invalid image upload. Allowed: JPG, PNG, WEBP.');
                admin_redirect('profile.php');
            }

            $payload['profile_image'] = $storedPhoto;
            $_SESSION['admin_user_photo'] = $storedPhoto;
        }

        if (backend_profile_update($userId, $payload)) {
            $_SESSION['admin_user_name'] = $payload['name'];
            $_SESSION['admin_user_email'] = $payload['email'];
            $_SESSION['admin_user_department'] = $payload['department'] !== '' ? $payload['department'] : ($_SESSION['admin_user_department'] ?? 'Administration');

            if (!empty($payload['profile_image'])) {
                $_SESSION['admin_user_photo'] = $payload['profile_image'];
            }

            admin_set_flash('success', 'Profile updated successfully.');
        } else {
            admin_set_flash('error', 'Unable to update profile.');
        }

        admin_redirect('profile.php');
    }

    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            admin_set_flash('error', 'All password fields are required.');
        } elseif (strlen($newPassword) < 8) {
            admin_set_flash('error', 'New password must be at least 8 characters.');
        } elseif ($newPassword !== $confirmPassword) {
            admin_set_flash('error', 'New password and confirm password do not match.');
        } elseif (backend_profile_change_password($userId, $currentPassword, $newPassword)) {
            admin_set_flash('success', 'Password updated successfully.');
        } else {
            admin_set_flash('error', 'Unable to update password.');
        }

        admin_redirect('profile.php');
    }
}

$profile = backend_profile_get($userId);
$profileImage = $profile['profile_image'] ?? ($profile['profile_photo'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_page_title('Profile'); ?></title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body data-theme="<?php echo admin_h($currentTheme); ?>">
<div class="admin-shell">
    <aside class="admin-sidebar-v2">
        <div class="admin-brand-v2">
            <div class="admin-brand-icon-v2"><i class="fas fa-shield-halved"></i></div>
            <div class="admin-brand-copy-v2">
                <h1>Campus Governance</h1>
                <p>Administrative Console</p>
            </div>
        </div>

        <nav class="admin-menu-v2">
            <a href="<?php echo admin_url('dashboard.php'); ?>"><i class="fas fa-table-cells-large"></i><span>Dashboard</span></a>
            <a href="<?php echo admin_url('report_view.php'); ?>"><i class="fas fa-folder-open"></i><span>Reports</span></a>
            <a href="<?php echo admin_url('spam_reports.php'); ?>"><i class="fas fa-shield-virus"></i><span>Spam Reports</span></a>
            <a href="<?php echo admin_url('users.php'); ?>"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="<?php echo admin_url('messages.php'); ?>"><i class="fas fa-envelope"></i><span>Messages</span></a>
        </nav>

        <div class="admin-sidebar-footer-v2">
            <a class="admin-logout-v2" href="<?php echo admin_url('logout.php'); ?>">
                <i class="fas fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="admin-main-v2">
        <header class="admin-topbar-v2">
            <div class="admin-topbar-left-v2">
                <h2>Profile Settings</h2>
                <p>Manage admin identity, profile image, designation, department, and account security.</p>
            </div>

            <div class="admin-topbar-right-v2">
                <button type="button" class="admin-icon-btn-v2" id="themeToggleBtn" aria-label="Toggle theme">
                    <i class="fas <?php echo $currentTheme === 'light' ? 'fa-sun' : 'fa-moon'; ?>" id="themeToggleIcon"></i>
                </button>

                <a class="admin-header-btn-v2" href="<?php echo admin_url('profile.php'); ?>">
                    <i class="fas fa-user-gear"></i>
                    <span><?php echo admin_h($user['name']); ?></span>
                </a>
            </div>
        </header>

        <section class="admin-content-v2">
            <?php if ($flash): ?>
                <div class="admin-alert-v2 admin-alert-<?php echo admin_h($flash['type']); ?>">
                    <?php echo admin_h($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="admin-page-grid-v2 profile-page-grid-v2">
                <aside class="admin-panel-v2">
                    <div class="profile-identity-card-v2">
                        <div class="profile-identity-avatar-v2">
                            <?php if (!empty($profileImage)): ?>
                                <img src="<?php echo admin_h($profileImage); ?>" alt="Profile Photo">
                            <?php else: ?>
                                <i class="fas fa-user-shield"></i>
                            <?php endif; ?>
                        </div>

                        <h3><?php echo admin_h($profile['name']); ?></h3>
                        <p><?php echo admin_h($profile['designation']); ?></p>

                        <div class="profile-meta-list-v2">
                            <div class="profile-meta-item-v2">
                                <small>Email</small>
                                <strong><?php echo admin_h($profile['email']); ?></strong>
                            </div>
                            <div class="profile-meta-item-v2">
                                <small>Role</small>
                                <strong><?php echo admin_h(admin_prettify((string)$profile['role'])); ?></strong>
                            </div>
                            <div class="profile-meta-item-v2">
                                <small>Department</small>
                                <strong><?php echo admin_h($profile['department']); ?></strong>
                            </div>
                            <div class="profile-meta-item-v2">
                                <small>Last Updated</small>
                                <strong><?php echo admin_h(admin_format_datetime($profile['updated_at'])); ?></strong>
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="admin-panel-v2">
                    <div class="panel-head-v2">
                        <div>
                            <h3>Profile Information</h3>
                            <p>Update visible admin details and profile image.</p>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="admin-form-stack-v2">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="admin-form-two-v2">
                            <div>
                                <label>Full Name</label>
                                <input type="text" name="name" value="<?php echo admin_h($profile['name']); ?>" required>
                            </div>
                            <div>
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo admin_h($profile['email']); ?>" required>
                            </div>
                        </div>

                        <div class="admin-form-two-v2">
                            <div>
                                <label>Phone</label>
                                <input type="text" name="phone" value="<?php echo admin_h($profile['phone']); ?>">
                            </div>
                            <div>
                                <label>Department</label>
                                <input type="text" name="department" value="<?php echo admin_h($profile['department']); ?>">
                            </div>
                        </div>

                        <div class="admin-form-two-v2">
                            <div>
                                <label>Designation</label>
                                <input type="text" name="designation" value="<?php echo admin_h($profile['designation']); ?>">
                            </div>
                            <div>
                                <label>Profile Photo</label>
                                <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                                <div class="field-help-v2">Allowed: JPG, PNG, WEBP</div>
                            </div>
                        </div>

                        <div>
                            <label>Short Bio</label>
                            <textarea name="bio" rows="5"><?php echo admin_h($profile['bio']); ?></textarea>
                        </div>

                        <div class="admin-form-actions-v2">
                            <button type="submit" class="admin-header-btn-v2">
                                <i class="fas fa-floppy-disk"></i>
                                <span>Save Profile</span>
                            </button>
                        </div>
                    </form>

                    <div class="divider-v2"></div>

                    <div class="panel-head-v2">
                        <div>
                            <h3>Change Password</h3>
                            <p>Update account password securely.</p>
                        </div>
                    </div>

                    <form method="POST" class="admin-form-stack-v2">
                        <input type="hidden" name="csrf_token" value="<?php echo admin_h($csrfToken); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="admin-form-two-v2">
                            <div>
                                <label>New Password</label>
                                <input type="password" name="new_password" required>
                            </div>
                            <div>
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="admin-form-actions-v2">
                            <button type="submit" class="admin-header-btn-v2 secondary">
                                <i class="fas fa-key"></i>
                                <span>Update Password</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    const body = document.body;
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeToggleIcon = document.getElementById('themeToggleIcon');
    const profileMenuBtn = document.getElementById('profileMenuBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileMenuWrap = document.getElementById('profileMenuWrap');

    function applyTheme(theme) {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        body.setAttribute('data-theme', nextTheme);
        localStorage.setItem('cgs_theme', nextTheme);
        if (themeToggleIcon) {
            themeToggleIcon.className = 'fas ' + (nextTheme === 'light' ? 'fa-sun' : 'fa-moon');
        }
    }

    const savedTheme = localStorage.getItem('cgs_theme');
    if (savedTheme === 'light' || savedTheme === 'dark') {
        applyTheme(savedTheme);
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            const currentTheme = body.getAttribute('data-theme') || 'dark';
            applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    }

    if (profileMenuBtn && profileDropdown && profileMenuWrap) {
        profileMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!profileMenuWrap.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
})();
</script>
</body>
</html>