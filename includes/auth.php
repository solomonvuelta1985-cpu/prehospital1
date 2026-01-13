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

/**
 * Require login - redirect if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('Please login to access this page', 'error');
        redirect('../public/login.php');
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

    // Session regeneration on admin access (prevent session fixation)
    if (!isset($_SESSION['admin_session_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['admin_session_regenerated'] = true;
    }
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

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // Update last login
    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    db_query($update_sql, [$user['id']]);

    // Log activity
    log_activity('user_login', 'User logged in: ' . $username);

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
