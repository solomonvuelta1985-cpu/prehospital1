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

// Load environment variables from .env file
require_once __DIR__ . '/env_loader.php';
load_env();

// Environment detection (must be before database config)
$is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) ||
                strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 ||
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0;
define('IS_LOCALHOST', $is_localhost);

// Database credentials from .env (with fallbacks for backward compatibility)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'pre_hospital_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'Pre-Hospital Care System');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 20971520); // 20MB

// reCAPTCHA settings from .env
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// Encryption key from .env (used for data encryption at rest)
define('APP_ENCRYPTION_KEY', getenv('APP_ENCRYPTION_KEY') ?: '');

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

// HSTS header - production only (forces HTTPS for 1 year)
if (!IS_LOCALHOST) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Generate CSP nonce per-request for inline scripts/styles (must happen BEFORE CSP header)
$_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
define('CSP_NONCE', $_SESSION['csp_nonce']);

// Global Content-Security-Policy
$csp_nonce_val = CSP_NONCE;
$csp = "default-src 'self'; "
     . "script-src 'self' 'nonce-{$csp_nonce_val}' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com; "
     . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
     . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
     . "img-src 'self' data: blob: https://nominatim.openstreetmap.org; "
     . "connect-src 'self' https://nominatim.openstreetmap.org https://www.google.com https://cdn.jsdelivr.net; "
     . "frame-src https://www.google.com https://www.gstatic.com; "
     . "object-src 'none'; "
     . "base-uri 'self';";
header("Content-Security-Policy: {$csp}");

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

// CSP_NONCE already defined above (before CSP header)
