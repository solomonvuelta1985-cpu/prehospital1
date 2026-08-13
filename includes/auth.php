<?php
/**
 * Authentication Functions
 * Login, Logout, Role Checking
 */

if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/** Check whether the optional session revocation migration is installed. */
function session_version_available() {
    static $available = null;
    if ($available !== null) {
        return $available;
    }
    $stmt = db_query("SHOW COLUMNS FROM users LIKE 'session_version'");
    $available = (bool)($stmt && $stmt->fetch());
    return $available;
}

/**
 * Require login - redirect if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('Please login to access this page', 'error');
        redirect('../public/login.php');
    }

    // Session idle timeout (30 minutes)
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        logout_user();
        session_start();
        set_flash('Your session has expired due to inactivity. Please login again.', 'warning');
        redirect('../public/login.php');
    }
    $_SESSION['last_activity'] = time();

    // Re-check account state on every request. This invalidates active sessions
    // immediately when an administrator deactivates/restricts an account or
    // changes its role.
    $state_stmt = db_query(
        "SELECT status, is_restricted, role, force_password_change FROM users WHERE id = ? LIMIT 1",
        [$_SESSION['user_id']]
    );
    $state = $state_stmt ? $state_stmt->fetch() : null;
    if (!$state || $state['status'] !== 'active' || (int)$state['is_restricted'] === 1 || $state['role'] !== $_SESSION['user_role']) {
        logout_user();
        session_start();
        set_flash('Your account is no longer authorized. Please contact an administrator.', 'error');
        redirect('../public/login.php');
    }

    // A session version lets administrators revoke all sessions for one user.
    // The query is compatibility-safe until the accompanying migration is run.
    $version_stmt = session_version_available()
        ? db_query("SELECT session_version FROM users WHERE id = ? LIMIT 1", [$_SESSION['user_id']])
        : false;
    if ($version_stmt) {
        $version_row = $version_stmt->fetch();
        if ($version_row && isset($_SESSION['session_version']) && (int)$version_row['session_version'] !== (int)$_SESSION['session_version']) {
            logout_user();
            session_start();
            set_flash('Your session was revoked. Please login again.', 'warning');
            redirect('../public/login.php');
        }
    }

    // Force password change check (skip if already on change_password page)
    $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($current_script !== 'change_password.php' && $current_script !== 'logout.php') {
        if ((int)$state['force_password_change'] === 1) {
            redirect('../public/change_password.php');
        }
    }
}

/**
 * Check if user is admin
 */
function is_admin() {
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin role
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('Access denied. Admin privileges required.', 'error');
        redirect('../public/index.php');
    }

    // IP Whitelist check for admin panel
    $whitelist_sql = "SELECT setting_value FROM app_settings WHERE setting_key = 'admin_ip_whitelist' LIMIT 1";
    $whitelist_stmt = db_query($whitelist_sql);
    if ($whitelist_stmt) {
        $whitelist_row = $whitelist_stmt->fetch();
        if ($whitelist_row && !empty(trim($whitelist_row['setting_value']))) {
            $allowed_ips = array_filter(array_map('trim', explode("\n", $whitelist_row['setting_value'])));
            $client_ip = get_client_ip();
            if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
                log_activity('admin_ip_blocked', "Admin access denied from IP: $client_ip");
                set_flash('Access denied. Your IP is not authorized for admin access.', 'error');
                redirect('../public/index.php');
            }
        }
    }

    // Session regeneration on admin access (prevent session fixation)
    if (!isset($_SESSION['admin_session_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['admin_session_regenerated'] = true;
    }
}

/**
 * Set up authenticated session for a user
 * Shared by password login and WebAuthn biometric login
 *
 * @param array $user User record with id, username, role
 * @param string $loginMethod Activity log label (e.g., 'user_login', 'user_login_webauthn')
 */
function setup_user_session($user, $loginMethod = 'user_login') {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    $version_stmt = session_version_available()
        ? db_query("SELECT session_version FROM users WHERE id = ? LIMIT 1", [$user['id']])
        : false;
    if ($version_stmt) {
        $version_row = $version_stmt->fetch();
        if ($version_row && isset($version_row['session_version'])) {
            $_SESSION['session_version'] = (int)$version_row['session_version'];
        }
    }

    // Update last login
    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    db_query($update_sql, [$user['id']]);

    // Record login history
    $ip = get_client_ip();
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $login_history_sql = "INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)";
    db_query($login_history_sql, [$user['id'], $ip, $user_agent]);

    // Log activity
    log_activity($loginMethod, 'User logged in: ' . $user['username']);
}

/**
 * Login user
 */
function login_user($username, $password, $recaptcha_response = null) {
    global $pdo;

    // IP-based rate limiting (more strict for login)
    if (!check_ip_rate_limit('login', 10, 600)) {
        return ['success' => false, 'message' => 'Too many login attempts from your IP. Please try again in 10 minutes.'];
    }

    // Note: Session-based rate limiting removed - we use per-user restriction instead
    // to allow multiple users to try logging in from the same browser/session

    // Verify reCAPTCHA if provided
    if ($recaptcha_response && !verify_recaptcha($recaptcha_response)) {
        return ['success' => false, 'message' => 'CAPTCHA verification failed. Please try again.'];
    }

    // Check if account is locked
    $lock_status = is_account_locked($username);
    if ($lock_status && isset($lock_status['locked'])) {
        $minutes = $lock_status['minutes_remaining'];
        return ['success' => false, 'message' => "Account is temporarily locked due to multiple failed login attempts. Try again in $minutes minute(s)."];
    }

    $sql = "SELECT id, username, password, role, status, is_restricted, failed_attempts FROM users WHERE username = ? LIMIT 1";
    $stmt = db_query($sql, [$username]);

    if (!$stmt || $stmt->rowCount() === 0) {
        // Don't reveal if username exists - generic error
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    $user = $stmt->fetch();

    // Check restriction FIRST - before any other checks
    if (isset($user['is_restricted']) && $user['is_restricted'] == 1) {
        // Log the restricted login attempt
        log_activity('restricted_login_attempt', "Restricted user '{$username}' attempted to login");
        return ['success' => false, 'message' => 'Your account has been restricted. Please contact the administrator.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Account is inactive. Contact administrator.'];
    }

    if (!password_verify($password, $user['password'])) {
        // Record failed attempt - this may auto-restrict the user
        record_failed_attempt($username, 5, 15);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    // Successful login - reset failed attempts
    reset_failed_attempts($username);

    // Set up the authenticated session
    setup_user_session($user, 'user_login');

    return ['success' => true, 'message' => 'Login successful'];
}

/**
 * Logout user
 */
function logout_user() {
    if (is_logged_in()) {
        log_activity('user_logout', 'User logged out');
    }
    
    $_SESSION = [];
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Get authenticated user information
 */
function get_auth_user() {
    if (!is_logged_in()) {
        return [
            'id' => 0,
            'username' => 'Guest',
            'role' => 'guest',
            'email' => '',
            'full_name' => 'Guest User'
        ];
    }

    global $pdo;
    $sql = "SELECT id, username, role, email, full_name FROM users WHERE id = ? LIMIT 1";
    $stmt = db_query($sql, [$_SESSION['user_id']]);

    if ($stmt && $stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fallback if user not found in database
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'Unknown',
        'role' => $_SESSION['user_role'] ?? 'user',
        'email' => '',
        'full_name' => $_SESSION['username'] ?? 'Unknown User'
    ];
}

/**
 * Define page access permissions by role
 * Returns array of allowed pages for each role
 */
function get_role_permissions() {
    return [
        'admin' => [
            // Admin pages
            'admin/dashboard.php',
            'admin/users.php',
            'admin/settings.php',
            'admin/reports.php',
            'admin/logs.php',

            // User pages (admin has access to all user pages too)
            'index.php',
            'dashboard.php',
            'profile.php',
            'form_a.php',
            'form_b.php',
            'my_records.php',
            'view_record.php',
            'edit_record.php'
        ],
        'user' => [
            // User pages only
            'index.php',
            'dashboard.php',
            'profile.php',
            'form_a.php',
            'form_b.php',
            'my_records.php',
            'view_record.php',
            'edit_record.php'
        ]
    ];
}

/**
 * Check if current user has permission to access a page
 *
 * @param string $page_path The page path relative to public directory (e.g., 'admin/users.php')
 * @return bool True if user has permission, false otherwise
 */
function has_page_permission($page_path) {
    if (!is_logged_in()) {
        return false;
    }

    $role = $_SESSION['user_role'] ?? 'user';
    $permissions = get_role_permissions();

    // If role not defined, deny access
    if (!isset($permissions[$role])) {
        return false;
    }

    // Normalize the page path
    $page_path = str_replace('\\', '/', $page_path);
    $page_path = ltrim($page_path, '/');

    // Check if page is in allowed list
    return in_array($page_path, $permissions[$role]);
}

/**
 * Require permission to access a specific page
 * Redirects if user doesn't have permission
 *
 * @param string $page_path The page path to check permission for
 */
function require_page_permission($page_path) {
    require_login();

    if (!has_page_permission($page_path)) {
        set_flash('Access denied. You do not have permission to access this page.', 'error');

        // Redirect based on role
        if (is_admin()) {
            redirect('../admin/dashboard.php');
        } else {
            redirect('../public/index.php');
        }
        exit;
    }
}

/**
 * Get current page name from script
 * Returns the page name relative to public directory
 */
function get_current_page() {
    $script = $_SERVER['SCRIPT_NAME'];
    $script = str_replace('\\', '/', $script);

    // Extract path after 'public/'
    if (preg_match('#/public/(.+)$#', $script, $matches)) {
        return $matches[1];
    }

    // Fallback to basename
    return basename($script);
}

/**
 * Auto-check page permission based on current script
 * Call this at the top of protected pages
 */
function check_current_page_permission() {
    $current_page = get_current_page();
    require_page_permission($current_page);
}
