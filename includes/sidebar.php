<?php
/**
 * Sidebar Navigation Component
 * Requires auth.php to be loaded for user info
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Detect current directory and build correct paths for all sidebar links
$is_admin_page = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$script_dir = dirname($_SERVER['PHP_SELF']);

if (strpos($script_dir, '/public/admin') !== false) {
    // From /public/admin/ (e.g. users.php)
    $base_path = '../';
    $admin_path = '../../admin/';
} elseif (strpos($script_dir, '/admin') !== false && strpos($script_dir, '/public/admin') === false) {
    // From /admin/ (e.g. admin dashboard)
    $base_path = '../public/';
    $admin_path = '';
} else {
    // From /public/ (e.g. records, forms, etc.)
    $base_path = '';
    $admin_path = '../admin/';
}

// Get user info from session via auth function
$current_user = get_auth_user();
$user_full = $current_user['full_name'] ?? $_SESSION['username'] ?? 'Guest';
$user_name = $_SESSION['username'] ?? 'guest';
$user_role = strtolower($current_user['role'] ?? 'guest');

// Get user initials for avatar
$initials = '';
$name_parts = explode(' ', $user_full);
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($user_full, 0, 2));
}
?>

<!-- Mobile Header -->
<div class="mobile-header">
    <div class="mobile-header-content">
        <div class="mobile-logo">
            <img src="<?= $base_path ?>uploads/logo.png" alt="Logo" class="mobile-logo-img">
            <h4>RESQ-link</h4>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <!-- User Profile Dropdown (Mobile) -->
            <div class="user-profile-dropdown">
                <button class="user-profile-btn mobile-avatar-btn" id="mobileUserProfileBtn" type="button">
                    <div class="user-avatar mobile-avatar">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                </button>

                <div class="user-dropdown-menu" id="mobileUserDropdownMenu">
                    <div class="dropdown-header">
                        <div class="dropdown-user-info">
                            <div class="user-avatar large">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                            <div>
                                <div class="dropdown-user-name"><?php echo htmlspecialchars($user_full); ?></div>
                                <div class="dropdown-user-email"><?php echo htmlspecialchars($user_name); ?></div>
                                <span class="dropdown-user-badge"><?php echo htmlspecialchars(strtoupper($user_role)); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="<?= $base_path ?>change_password.php" class="dropdown-item">
                        <span class="material-icons">lock</span>
                        <span>Change Password</span>
                    </a>
                    <a href="<?= $base_path ?>biometric_settings.php" class="dropdown-item">
                        <span class="material-icons">fingerprint</span>
                        <span>Biometric Settings</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= $base_path ?>logout.php" class="dropdown-item logout-item">
                        <span class="material-icons">logout</span>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            <button type="button" id="mobileSidebarToggle" aria-label="Toggle menu" aria-expanded="false">
                <div class="hamburger-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>
        </div>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= $base_path ?>uploads/logo.png" alt="Logo" class="sidebar-logo">
        <h4><span>RESCUE116-link</span></h4>
    </div>

    <ul class="sidebar-menu">
        <!-- Main Section -->
        <li class="sidebar-heading">Overview</li>
        <li>
            <a href="<?= ($user_role === 'admin') ? ($admin_path . 'dashboard.php') : ($base_path . 'dashboard.php') ?>"
               class="<?php echo ($current_page === 'dashboard.php' || $current_page === 'index.php') ? 'active' : ''; ?>"
               title="Dashboard">
                <span class="material-icons">home</span>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Forms Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Pre-Hospital Care</li>
        <li>
            <a href="<?= $base_path ?>prehospital_form.php"
               class="<?php echo ($current_page === 'prehospital_form.php') ? 'active' : ''; ?>"
               title="New Form">
                <span class="material-icons">add_circle</span>
                <span>New Form</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_path ?>drafts.php"
               class="<?php echo ($current_page === 'drafts.php') ? 'active' : ''; ?>"
               title="Resume Draft">
                <span class="material-icons">edit_note</span>
                <span>Resume Draft</span>
                <?php
                // Get draft count for current user
                try {
                    if (isset($current_user['id'])) {
                        $draft_count_result = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE created_by = ? AND status = 'draft'", [$current_user['id']]);
                        $draft_count_data = $draft_count_result->fetch();
                        $draft_count = $draft_count_data ? (int)$draft_count_data['count'] : 0;
                        if ($draft_count > 0):
                ?>
                    <span style="margin-left: auto; background: #dc3545; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; font-weight: 600;"><?= $draft_count ?></span>
                <?php
                        endif;
                    }
                } catch (Exception $e) {
                    // Silently fail if there's a database error
                }
                ?>
            </a>
        </li>
        <li>
            <a href="<?= $base_path ?>records.php"
               class="<?php echo ($current_page === 'records.php') ? 'active' : ''; ?>"
               title="Records">
                <span class="material-icons">table_view</span>
                <span>All Records</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_path ?>reports.php"
               class="<?php echo ($current_page === 'reports.php') ? 'active' : ''; ?>"
               title="Reports & Analytics">
                <span class="material-icons">assessment</span>
                <span>Reports</span>
            </a>
        </li>

        <!-- Account Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Account</li>
        <li>
            <a href="<?= $base_path ?>biometric_settings.php"
               class="<?php echo ($current_page === 'biometric_settings.php') ? 'active' : ''; ?>"
               title="Biometric Settings">
                <span class="material-icons">fingerprint</span>
                <span>Biometric Login</span>
            </a>
        </li>

        <!-- Admin Section -->
        <?php if ($user_role === 'admin'): ?>
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Administration</li>
        <li>
            <a href="<?= $base_path ?>admin/users.php"
               class="<?php echo ($current_page === 'users.php') ? 'active' : ''; ?>"
               title="User Management">
                <span class="material-icons">admin_panel_settings</span>
                <span>User Management</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_path ?>admin/activity_logs.php"
               class="<?php echo ($current_page === 'activity_logs.php') ? 'active' : ''; ?>"
               title="Activity Logs">
                <span class="material-icons">history</span>
                <span>Activity Logs</span>
            </a>
        </li>
        <li>
            <a href="<?= $base_path ?>admin/settings.php"
               class="<?php echo ($current_page === 'settings.php') ? 'active' : ''; ?>"
               title="Settings">
                <span class="material-icons">settings</span>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

</nav>

<!-- Top Navigation Bar (Desktop) -->
<div class="top-navbar" id="topNavbar">
    <button type="button" id="sidebarCollapse" title="Toggle Sidebar">
        <span class="material-icons">menu</span>
    </button>
    <div class="top-navbar-logo">
        <img src="<?= $base_path ?>uploads/logo.png" alt="Logo" class="top-navbar-logo-img">
        <span class="top-navbar-title">RESCUE 116-link</span>
    </div>
    <div class="top-navbar-info">
        <span class="welcome-text">
            Welcome, <?php echo htmlspecialchars($user_full); ?>
        </span>
    </div>

    <!-- User Profile Dropdown -->
    <div class="user-profile-dropdown">
        <button class="user-profile-btn" id="userProfileBtn" type="button">
            <div class="user-avatar">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div class="user-profile-info">
                <span class="user-name"><?php echo htmlspecialchars($user_full); ?></span>
                <span class="user-role"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
            </div>
            <span class="material-icons dropdown-arrow">expand_more</span>
        </button>

        <div class="user-dropdown-menu" id="userDropdownMenu">
            <div class="dropdown-header">
                <div class="dropdown-user-info">
                    <div class="user-avatar large">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                    <div>
                        <div class="dropdown-user-name"><?php echo htmlspecialchars($user_full); ?></div>
                        <div class="dropdown-user-email"><?php echo htmlspecialchars($user_name); ?></div>
                        <span class="dropdown-user-badge"><?php echo htmlspecialchars(strtoupper($user_role)); ?></span>
                    </div>
                </div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="<?= $base_path ?>change_password.php" class="dropdown-item">
                <span class="material-icons">lock</span>
                <span>Change Password</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= $base_path ?>logout.php" class="dropdown-item logout-item">
                <span class="material-icons">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
    --sidebar-width: 260px;
    --sidebar-collapsed-width: 72px;
    --primary-color: #0d6efd;
    --primary-hover: #0b5ed7;
    --sidebar-bg: #1a2332;
    --sidebar-item-hover: rgba(13, 110, 253, 0.1);
    --sidebar-item-active: rgba(13, 110, 253, 0.2);
    --text-primary: #f1f5f9;
    --text-secondary: #94a3b8;
    --accent-color: #0d6efd;
    --border-color: rgba(148, 163, 184, 0.1);
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.15);
}

* {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Material Icons Styling */
.material-icons {
    font-size: 18px;
    display: inline-flex;
    vertical-align: middle;
    align-items: center;
    justify-content: center;
}

.sidebar-header .material-icons {
    font-size: 20px;
}

.mobile-header .material-icons {
    font-size: 20px;
}

#sidebarCollapse .material-icons {
    font-size: 22px;
}

/* Hamburger Icon Animation */
.hamburger-icon {
    width: 20px;
    height: 16px;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.hamburger-icon span {
    display: block;
    width: 100%;
    height: 2px;
    background: var(--text-primary);
    border-radius: 2px;
    transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1),
                opacity 0.2s ease;
    transform-origin: center;
}

body.sidebar-open .hamburger-icon span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
}

body.sidebar-open .hamburger-icon span:nth-child(2) {
    opacity: 0;
    transform: scaleX(0);
}

body.sidebar-open .hamburger-icon span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
}

.dropdown-arrow {
    font-size: 16px !important;
}

.sidebar-collapsed .sidebar-menu li a .material-icons {
    font-size: 20px;
}

/* Mobile Header */
.mobile-header {
    display: none;
    background: var(--sidebar-bg);
    color: var(--text-primary);
    padding: 16px 20px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1100;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(148, 163, 184, 0.15);
}

.mobile-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-logo {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mobile-logo-img {
    height: 32px;
    width: auto;
}

.mobile-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

#mobileSidebarToggle {
    background: none;
    border: none;
    color: var(--text-primary);
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

#mobileSidebarToggle:hover {
    background: rgba(13, 110, 253, 0.15);
    transform: scale(1.05);
}

/* Sidebar Overlay */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 999;
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.35s cubic-bezier(0.32, 0.72, 0, 1),
                visibility 0.35s cubic-bezier(0.32, 0.72, 0, 1);
}

.sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

/* Top Navigation Bar */
.top-navbar {
    position: fixed;
    top: 0;
    left: var(--sidebar-width);
    right: 0;
    height: 64px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    padding: 0;
    z-index: 100;
    transition: left 0.35s cubic-bezier(0.32, 0.72, 0, 1);
}

.sidebar-collapsed .top-navbar {
    left: var(--sidebar-collapsed-width);
}

#sidebarCollapse {
    background: none;
    border: none;
    color: #374151;
    cursor: pointer;
    padding: 10px;
    margin-left: 20px;
    border-radius: 8px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
}

#sidebarCollapse:hover {
    background: #f3f4f6;
    color: var(--primary-color);
    transform: scale(1.05);
}

.top-navbar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: 16px;
}

.top-navbar-logo-img {
    height: 32px;
    width: auto;
}

.top-navbar-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
    letter-spacing: -0.02em;
}

.top-navbar-info {
    margin-left: 24px;
}

.welcome-text {
    color: #6b7280;
    font-size: 13.5px;
    font-weight: 500;
    letter-spacing: -0.01em;
}

/* User Profile Dropdown */
.user-profile-dropdown {
    margin-left: auto;
    margin-right: 20px;
    position: relative;
}

.user-profile-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 12px 6px 6px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-family: inherit;
}

.user-profile-btn:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}

.user-profile-btn:hover .user-avatar {
    transform: scale(1.05);
}

.user-profile-btn:active,
.user-profile-btn.active {
    background: #f3f4f6;
    border-color: var(--primary-color);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--accent-color);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.5px;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.user-avatar.large {
    width: 48px;
    height: 48px;
    font-size: 16px;
}

/* Mobile Avatar - Modern Design */
.mobile-avatar-btn {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    box-shadow: none !important;
}

.mobile-avatar-btn:hover,
.mobile-avatar-btn:active,
.mobile-avatar-btn.active {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

.user-avatar.mobile-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
    border: 2px solid #ffffff;
    transition: all 0.3s ease;
}

.mobile-avatar-btn:hover .user-avatar.mobile-avatar {
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.mobile-avatar-btn.active .user-avatar.mobile-avatar {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3);
}

.user-profile-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    min-width: 0;
}

.user-name {
    font-size: 13.5px;
    font-weight: 600;
    color: #111827;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    letter-spacing: -0.01em;
}

.user-role {
    font-size: 11.5px;
    color: #6b7280;
    line-height: 1.3;
    text-transform: capitalize;
    letter-spacing: -0.01em;
}

.dropdown-arrow {
    color: #9ca3af;
    transition: transform 0.2s ease;
}

.user-profile-btn.active .dropdown-arrow {
    transform: rotate(180deg);
}

.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 280px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12), 0 4px 8px rgba(0, 0, 0, 0.08);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px) scale(0.95);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

.user-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.dropdown-header {
    padding: 20px;
}

.dropdown-user-info {
    display: flex;
    gap: 12px;
    align-items: center;
}

.dropdown-user-name {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 2px;
    letter-spacing: -0.01em;
}

.dropdown-user-email {
    font-size: 12.5px;
    color: #6b7280;
    margin-bottom: 8px;
    letter-spacing: -0.01em;
}

.dropdown-user-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--accent-color);
    color: #ffffff;
    font-size: 10.5px;
    font-weight: 600;
    border-radius: 6px;
    letter-spacing: 0.03em;
}

.dropdown-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    color: #374151;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: -0.01em;
}

.dropdown-item:first-of-type {
    border-radius: 0;
}

.dropdown-item:last-of-type {
    border-radius: 0 0 12px 12px;
}

.dropdown-item:hover {
    background: #f9fafb;
    color: #111827;
    padding-left: 24px;
}

.dropdown-item.logout-item {
    color: #dc2626;
}

.dropdown-item.logout-item:hover {
    background: #fef2f2;
    color: #b91c1c;
}

.dropdown-item .material-icons {
    width: 18px;
    height: 18px;
    font-size: 18px;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    color: var(--text-primary);
    padding: 0;
    z-index: 1000;
    box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
    border-right: 1px solid rgba(148, 163, 184, 0.1);
    display: flex;
    flex-direction: column;
    transition: width 0.35s cubic-bezier(0.32, 0.72, 0, 1);
    overflow: hidden;
}

.sidebar-collapsed .sidebar {
    width: var(--sidebar-collapsed-width);
}

.sidebar-header {
    padding: 20px;
    background: var(--sidebar-bg);
    border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    white-space: nowrap;
    overflow: hidden;
    min-height: 64px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-logo {
    height: 32px;
    width: auto;
    min-width: 32px;
}

.sidebar-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    letter-spacing: -0.02em;
}

.sidebar-header h4 span {
    transition: opacity 0.25s cubic-bezier(0.32, 0.72, 0, 1),
                transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}

.sidebar-collapsed .sidebar-header h4 span {
    opacity: 0;
    transform: translateX(-8px);
    pointer-events: none;
}

.sidebar-menu {
    list-style: none;
    padding: 12px 0;
    margin: 0;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Custom Scrollbar */
.sidebar-menu::-webkit-scrollbar {
    width: 6px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 3px;
    transition: background 0.2s ease;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.5);
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    margin: 2px 12px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 60px;
    font-size: 13.5px;
    font-weight: 500;
    line-height: 1.5;
    white-space: nowrap;
    position: relative;
    gap: 12px;
}

.sidebar-menu li a:hover {
    background: var(--sidebar-item-hover);
    color: var(--text-primary);
    transform: translateX(3px);
}

.sidebar-menu li a:active {
    transform: scale(0.98) translateX(3px);
}

.sidebar-menu li a.active {
    background: var(--sidebar-item-active);
    color: #87e87cff;
    font-weight: 600;
    transform: translateX(0);
    animation: menuItemActivate 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes menuItemActivate {
    0% {
        background: transparent;
        transform: translateX(-4px);
        opacity: 0.7;
    }
    30% {
        background: var(--sidebar-item-hover);
    }
    60% {
        transform: translateX(5px);
        opacity: 1;
    }
    100% {
        background: var(--sidebar-item-active);
        transform: translateX(0);
        opacity: 1;
    }
}

.sidebar-menu li a.active::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 22px;
    background: var(--accent-color);
    border-radius: 0 4px 4px 0;
    box-shadow: 0 0 12px rgba(13, 110, 253, 0.6);
    animation: slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideIn {
    0% {
        transform: translateY(-50%) scaleY(0);
        opacity: 0;
        height: 0;
    }
    60% {
        transform: translateY(-50%) scaleY(1.1);
        height: 26px;
    }
    100% {
        transform: translateY(-50%) scaleY(1);
        opacity: 1;
        height: 22px;
    }
}

.sidebar-menu li a .material-icons {
    min-width: 24px;
    text-align: center;
    color: inherit;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sidebar-menu li a:hover .material-icons {
    transform: scale(1.08);
}

.sidebar-menu li a.active .material-icons {
    animation: iconPulse 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes iconPulse {
    0% {
        transform: scale(1);
        opacity: 0.7;
    }
    40% {
        transform: scale(1.2);
        opacity: 1;
    }
    70% {
        transform: scale(0.95);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.sidebar-menu li a span:not(.material-icons) {
    font-size: 13.5px;
    transition: opacity 0.25s cubic-bezier(0.32, 0.72, 0, 1),
                transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
    font-weight: 500;
}

.sidebar-collapsed .sidebar-menu li a span:not(.material-icons) {
    opacity: 0;
    transform: translateX(-8px);
}

.sidebar-collapsed .sidebar-menu li a {
    justify-content: center;
    padding: 14px 10px;
    margin: 4px 10px;
}

.sidebar-collapsed .sidebar-menu li a span:not(.material-icons) {
    opacity: 0;
    transform: translateX(-8px);
    pointer-events: none;
    position: absolute;
}

.sidebar-collapsed .sidebar-menu li a .material-icons {
    margin: 0;
}

.sidebar-collapsed .sidebar-menu li a.active::before {
    left: -10px;
}

.sidebar-divider {
    border-top: 1px solid rgba(148, 163, 184, 0.15);
    margin: 12px 16px;
}

.sidebar-heading {
    padding: 14px 20px 8px;
    font-size: 10.5px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 600;
    letter-spacing: 0.05em;
    white-space: nowrap;
    overflow: hidden;
    transition: opacity 0.25s cubic-bezier(0.32, 0.72, 0, 1),
                padding 0.35s cubic-bezier(0.32, 0.72, 0, 1);
}

.sidebar-collapsed .sidebar-heading {
    opacity: 0;
    padding: 8px 0;
    margin: 0;
}

.sidebar-collapsed .sidebar-divider {
    margin: 8px 16px;
}

.sidebar-collapsed .sidebar-logo {
    margin: 0 auto;
}

/* Page transition animation */
@keyframes pageFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pageSlideUp {
    from { transform: translateY(8px); }
    to { transform: translateY(0); }
}

/* Main Content */
.content {
    margin-left: var(--sidebar-width);
    padding: 0;
    padding-top: 64px;
    min-height: 100vh;
    background: #f8fafc;
    transition: margin-left 0.35s cubic-bezier(0.32, 0.72, 0, 1);
    animation: pageFadeIn 0.25s ease-out, pageSlideUp 0.3s cubic-bezier(0, 0, 0.2, 1);
}

/* Stagger child elements for a polished feel */
.content .stat-card,
.content .chart-card,
.content .action-card,
.content .table-card,
.content .activity-card,
.content .table-container {
    animation: pageFadeIn 0.3s ease-out both, pageSlideUp 0.35s cubic-bezier(0, 0, 0.2, 1) both;
}

.content .row:nth-child(2) .stat-card,
.content .row:nth-child(2) .chart-card { animation-delay: 0.03s; }
.content .row:nth-child(3) .stat-card,
.content .row:nth-child(3) .chart-card { animation-delay: 0.06s; }
.content .row:nth-child(4) .chart-card,
.content .row:nth-child(4) .table-card,
.content .row:nth-child(4) .table-container { animation-delay: 0.09s; }
.content .row:nth-child(5) .table-card,
.content .row:nth-child(5) .action-card,
.content .row:nth-child(5) .table-container { animation-delay: 0.12s; }
.content .row:nth-child(6) .action-card { animation-delay: 0.15s; }

.sidebar-collapsed .content {
    margin-left: var(--sidebar-collapsed-width);
}

/* Tooltips for Collapsed Sidebar */
.sidebar-collapsed .sidebar-menu li {
    position: relative;
}

.sidebar-collapsed .sidebar-menu li a::after {
    content: attr(title);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: #0f172a;
    color: #f1f5f9;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    margin-left: 8px;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.sidebar-collapsed .sidebar-menu li a:hover::after {
    opacity: 1;
    visibility: visible;
}

/* Responsive */
@media (max-width: 768px) {
    .mobile-header {
        display: block;
    }

    .top-navbar {
        display: none;
    }

    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
        will-change: transform;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar-collapsed .sidebar {
        width: 280px;
    }

    /* Body scroll lock when sidebar is open */
    body.sidebar-open {
        overflow: hidden;
        position: fixed;
        width: 100%;
        top: 0;
        left: 0;
    }

    /* Content scale-down effect for native feel */
    .content {
        margin-left: 0;
        padding: 0;
        padding-top: 70px;
        transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
    }

    body.sidebar-open .content {
        transform: scale(0.97);
        transform-origin: right center;
    }

    body.sidebar-open .mobile-header {
        transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
        transform: scale(0.97);
        transform-origin: right top;
    }

    .sidebar-collapsed .content {
        margin-left: 0;
    }

    /* Disable tooltips on mobile */
    .sidebar-collapsed .sidebar-menu li a::after {
        display: none;
    }

    /* Show text on mobile even in collapsed mode */
    .sidebar-collapsed .sidebar-menu li a span:not(.material-icons),
    .sidebar-collapsed .sidebar-header h4 span,
    .sidebar-collapsed .sidebar-heading {
        display: inline;
        font-size: inherit;
        text-indent: 0;
        opacity: 1;
        transform: none;
        pointer-events: auto;
        position: static;
    }

    .sidebar-collapsed .sidebar-menu li a {
        justify-content: flex-start;
        padding: 11px 16px;
        margin: 2px 12px;
    }

    .sidebar-collapsed .sidebar-menu li a .material-icons {
        margin-right: 0;
        min-width: 24px;
    }

    /* User Profile Dropdown - Mobile adjustments */
    .user-profile-info {
        display: none;
    }

    .dropdown-arrow {
        display: none;
    }

    .user-dropdown-menu {
        min-width: 260px;
        right: -8px;
    }
}

@media print {
    .sidebar,
    .top-navbar,
    .mobile-header,
    .sidebar-overlay {
        display: none !important;
    }

    .content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
}
</style>

<script nonce="<?php echo CSP_NONCE; ?>">
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const body = document.body;

    // ========== Mobile Sidebar Open/Close Helpers ==========
    function openSidebar() {
        body.dataset.scrollY = window.scrollY;
        body.style.top = `-${window.scrollY}px`;
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
        body.classList.add('sidebar-open');
        if (mobileSidebarToggle) mobileSidebarToggle.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        body.classList.remove('sidebar-open');
        sidebar.style.transform = '';
        sidebarOverlay.style.opacity = '';
        if (mobileSidebarToggle) mobileSidebarToggle.setAttribute('aria-expanded', 'false');

        // Restore scroll position
        const scrollY = parseInt(body.dataset.scrollY || '0');
        body.style.top = '';
        window.scrollTo(0, scrollY);
    }

    // ========== Desktop: Toggle sidebar collapse/expand ==========
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            body.classList.toggle('sidebar-collapsed');
            const isCollapsed = body.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    }

    // ========== Mobile: Toggle sidebar visibility ==========
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            closeSidebar();
        });
    }

    // Close mobile sidebar when clicking a menu link
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            // Add click animation
            this.style.transition = 'all 0.15s ease';
            this.style.transform = 'scale(0.96) translateX(3px)';

            setTimeout(() => {
                this.style.transform = '';
            }, 150);

            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // ========== Touch Swipe to Close ==========
    let touchStartX = 0;
    let touchStartY = 0;
    let touchCurrentX = 0;
    let isSwiping = false;

    sidebar.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        touchCurrentX = touchStartX;
        isSwiping = false;
    }, { passive: true });

    sidebar.addEventListener('touchmove', function(e) {
        touchCurrentX = e.touches[0].clientX;
        const deltaX = touchCurrentX - touchStartX;
        const deltaY = Math.abs(e.touches[0].clientY - touchStartY);

        // Only track horizontal left swipes (ignore vertical scrolling)
        if (deltaX < -10 && deltaY < 50) {
            isSwiping = true;
            // Real-time drag follow
            const progress = Math.max(deltaX, -280);
            sidebar.style.transition = 'none';
            sidebar.style.transform = `translateX(${progress}px)`;
            sidebarOverlay.style.transition = 'none';
            sidebarOverlay.style.opacity = Math.max(0, 1 + (progress / 280));
        }
    }, { passive: true });

    sidebar.addEventListener('touchend', function(e) {
        sidebar.style.transition = '';
        sidebarOverlay.style.transition = '';

        if (isSwiping && (touchCurrentX - touchStartX) < -80) {
            // Swipe threshold met: close sidebar
            closeSidebar();
        } else if (isSwiping) {
            // Snap back open
            sidebar.style.transform = '';
            sidebarOverlay.style.opacity = '';
        }
        isSwiping = false;
    }, { passive: true });

    // ========== Restore sidebar state on page load ==========
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 768) {
        body.classList.add('sidebar-collapsed');
    }

    // Animate active menu item on page load
    const activeMenuItem = document.querySelector('.sidebar-menu a.active');
    if (activeMenuItem) {
        activeMenuItem.style.animation = 'none';
        setTimeout(() => {
            activeMenuItem.style.animation = '';
        }, 10);
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // ========== User Profile Dropdown Functionality ==========
    const userProfileBtn = document.getElementById('userProfileBtn');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    const mobileUserProfileBtn = document.getElementById('mobileUserProfileBtn');
    const mobileUserDropdownMenu = document.getElementById('mobileUserDropdownMenu');

    // Desktop dropdown
    if (userProfileBtn && userDropdownMenu) {
        userProfileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfileBtn.classList.toggle('active');
            userDropdownMenu.classList.toggle('show');

            if (mobileUserProfileBtn && mobileUserDropdownMenu) {
                mobileUserProfileBtn.classList.remove('active');
                mobileUserDropdownMenu.classList.remove('show');
            }
        });
    }

    // Mobile dropdown
    if (mobileUserProfileBtn && mobileUserDropdownMenu) {
        mobileUserProfileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileUserProfileBtn.classList.toggle('active');
            mobileUserDropdownMenu.classList.toggle('show');

            if (userProfileBtn && userDropdownMenu) {
                userProfileBtn.classList.remove('active');
                userDropdownMenu.classList.remove('show');
            }
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (userProfileBtn && userDropdownMenu) {
            if (!userProfileBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                userProfileBtn.classList.remove('active');
                userDropdownMenu.classList.remove('show');
            }
        }

        if (mobileUserProfileBtn && mobileUserDropdownMenu) {
            if (!mobileUserProfileBtn.contains(e.target) && !mobileUserDropdownMenu.contains(e.target)) {
                mobileUserProfileBtn.classList.remove('active');
                mobileUserDropdownMenu.classList.remove('show');
            }
        }
    });

    // ========== Escape key: close sidebar + dropdowns ==========
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close mobile sidebar
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            }
            // Close dropdowns
            if (userProfileBtn && userDropdownMenu) {
                userProfileBtn.classList.remove('active');
                userDropdownMenu.classList.remove('show');
            }
            if (mobileUserProfileBtn && mobileUserDropdownMenu) {
                mobileUserProfileBtn.classList.remove('active');
                mobileUserDropdownMenu.classList.remove('show');
            }
        }
    });
});
</script>

<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
