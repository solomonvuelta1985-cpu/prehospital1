<?php
/**
 * Database Configuration
 * PDO Connection Setup
 */

// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

// Security: Disable error display in production
// For production, set these in php.ini or .htaccess for better security
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL); // Still log all errors, just don't display them

// For development, uncomment this line to see errors:
// ini_set('display_errors', '1');

// Environment detection (must be before database config)
$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) ||
                strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0;
define('IS_LOCALHOST', $is_localhost);

// Database credentials - Environment specific
if (IS_LOCALHOST) {
    // LOCAL DEVELOPMENT DATABASE
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pre_hospital_db'); // Your local database name
    define('DB_USER', 'root'); // Default XAMPP user
    define('DB_PASS', ''); // Default XAMPP password (empty)
} else {
    // PRODUCTION DATABASE
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'btrahnqi_pre_hospital_db'); // Production database name
    define('DB_USER', 'btrahnqi_richmond'); // Production database user
    define('DB_PASS', 'Almondmamon@17'); // Production database password
}
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'Pre-Hospital Care System');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// reCAPTCHA settings (get keys from https://www.google.com/recaptcha/admin)
// PRODUCTION: Replace with your own reCAPTCHA keys from Google
// Register your domain at: https://www.google.com/recaptcha/admin
// IMPORTANT: Add 'localhost' to allowed domains in reCAPTCHA admin for local testing
define('RECAPTCHA_SITE_KEY', '6LeJ5kUsAAAAAHJQkM9upH2rVKIFb15MikEPG1gw');
define('RECAPTCHA_SECRET_KEY', '6LeJ5kUsAAAAACnDq8gUHRuIFgD3To17FLUB2WO7');

// Force HTTPS redirect - ONLY ON PRODUCTION
// Disabled for localhost development
if (!IS_LOCALHOST) {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: " . $redirect_url, true, 301);
        exit();
    }
}

// Session configuration - Detect mobile webview for conditional settings
$is_mobile_webview_request = (
    (isset($_SERVER['HTTP_USER_AGENT']) && (
        stripos($_SERVER['HTTP_USER_AGENT'], 'wv') !== false ||
        stripos($_SERVER['HTTP_USER_AGENT'], 'WebView') !== false ||
        stripos($_SERVER['HTTP_USER_AGENT'], 'Replit') !== false
    )) ||
    isset($_GET['mobile_app']) ||
    (isset($_SERVER['HTTP_X_MOBILE_APP']) && $_SERVER['HTTP_X_MOBILE_APP'] === 'true')
);

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// Desktop: Strict (more secure) | Mobile: Lax (more compatible)
ini_set('session.cookie_samesite', $is_mobile_webview_request ? 'Lax' : 'Strict');

// Add secure flag ONLY if HTTPS is actually enabled
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set security headers conditionally based on mobile webview detection
// Desktop users get full protection, mobile webviews get compatibility mode
if (!$is_mobile_webview_request) {
    // Full security for desktop browsers
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
} else {
    // Mobile webview - allow framing but keep other protections
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // X-Frame-Options intentionally omitted for mobile webview compatibility
}

// PDO Database Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Generate CSP nonce for inline scripts/styles
if (!isset($_SESSION['csp_nonce'])) {
    $_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
}
define('CSP_NONCE', $_SESSION['csp_nonce']);
