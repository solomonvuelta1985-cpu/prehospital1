<?php
/**
 * Helper Functions
 * Sanitization, Validation, Flash Messages, CSRF Protection
 */

if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Sanitize input data and convert to uppercase
 * @param mixed $data - Data to sanitize
 * @param bool $uppercase - Whether to convert to uppercase (default: true)
 */
function sanitize($data, $uppercase = true) {
    if (is_array($data)) {
        return array_map(function($item) use ($uppercase) {
            return sanitize($item, $uppercase);
        }, $data);
    }
    if ($data === null || $data === '') {
        return null;
    }
    $sanitized = htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');

    // Convert to uppercase for consistent data storage (unless disabled)
    if ($uppercase) {
        return mb_strtoupper($sanitized, 'UTF-8');
    }

    return $sanitized;
}

/**
 * Execute safe database query with prepared statements
 */
function db_query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Query Error: " . $e->getMessage());
        error_log("SQL: " . $sql);
        return false;
    }
}

/**
 * Set flash message
 */
function set_flash($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Display and clear flash message
 */
function show_flash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        $class = $alertClass[$flash['type']] ?? 'alert-info';
        
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        
        unset($_SESSION['flash_message']);
    }
}

/**
 * Generate CSRF token
 */
function generate_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Redirect back with validation
 */
function redirect_back() {
    $default = 'index.php';
    $referer = $_SERVER['HTTP_REFERER'] ?? $default;

    // Security: Only allow redirects to same origin
    $parsed = parse_url($referer);
    $current_host = $_SERVER['HTTP_HOST'] ?? '';

    // If no host in referer (relative URL) or same host, allow it
    if (!isset($parsed['host']) || $parsed['host'] === $current_host) {
        redirect($referer);
    }

    // Otherwise redirect to default
    redirect($default);
}

/**
 * Validate date format
 */
function validate_date($date, $format = 'Y-m-d') {
    if (empty($date) || $date === null || !is_string($date)) {
        return false;
    }
    // Trim whitespace that might slip through from Flatpickr or manual input
    $date = trim($date);
    if (empty($date)) {
        return false;
    }
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate time format (accepts both HH:MM and HH:MM:SS)
 */
function validate_time($time) {
    if (empty($time) || !is_string($time)) {
        return false;
    }
    return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', (string)$time);
}

/**
 * Convert 12-hour time format to 24-hour format
 * Handles: "2:30 PM" → "14:30"
 *
 * @param string $time_12h Time in 12-hour format (e.g., "2:30 PM")
 * @return string|false Time in 24-hour format (HH:MM) or false on error
 */
function convert_12h_to_24h($time_12h) {
    if (empty($time_12h) || !is_string($time_12h)) {
        return false;
    }

    // Trim and normalize
    $time_12h = trim($time_12h);

    // Check if already in 24-hour format (no AM/PM)
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time_12h)) {
        return $time_12h; // Already 24-hour format
    }

    // Parse 12-hour format
    $pattern = '/^(0?[1-9]|1[0-2]):([0-5][0-9])\s?(AM|PM)$/i';
    if (!preg_match($pattern, $time_12h, $matches)) {
        return false; // Invalid format
    }

    $hour = (int)$matches[1];
    $minute = $matches[2];
    $period = strtoupper($matches[3]);

    // Convert to 24-hour
    if ($period === 'AM') {
        if ($hour === 12) {
            $hour = 0; // 12:00 AM = 00:00
        }
    } else { // PM
        if ($hour !== 12) {
            $hour += 12; // 1:00 PM = 13:00, but 12:00 PM stays 12:00
        }
    }

    return sprintf('%02d:%s', $hour, $minute);
}

/**
 * Validate datetime format
 */
function validate_datetime($datetime) {
    return validate_date($datetime, 'Y-m-d\TH:i');
}

/**
 * Validate password strength
 * Requirements:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one number
 * - At least one special character
 */
function validate_password_strength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&* etc.)';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Handle file upload
 */
function handle_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0750, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Get client IP address
 * Security: Only trust proxy headers if explicitly enabled
 */
function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Only trust proxy headers if behind a known reverse proxy
    // Set TRUST_PROXY_HEADERS to true in production if using nginx/cloudflare/etc
    $trust_proxy = defined('TRUST_PROXY_HEADERS') && TRUST_PROXY_HEADERS === true;

    if ($trust_proxy) {
        // Only use X-Forwarded-For if explicitly trusted
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get first IP in chain (original client)
            $forwarded_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwarded_ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Simple rate limiting (per session)
 */
function check_rate_limit($action, $max_attempts = 5, $time_window = 300) {
    $key = 'rate_limit_' . $action;

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start_time' => time()];
    }

    $rate_data = $_SESSION[$key];

    // Reset if time window expired
    if (time() - $rate_data['start_time'] > $time_window) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }

    // Check if limit exceeded
    if ($rate_data['count'] >= $max_attempts) {
        return false;
    }

    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * IP-based rate limiting (for APIs and login attempts)
 * Stores in database for persistence across sessions
 */
function check_ip_rate_limit($action, $max_attempts = 5, $time_window = 300) {
    global $pdo;

    $ip_address = get_client_ip();
    $action_key = sanitize($action);
    $current_time = time();

    try {
        // Check if rate limit record exists
        $sql = "SELECT attempt_count, window_start FROM rate_limits
                WHERE ip_address = ? AND action = ? LIMIT 1";
        $stmt = db_query($sql, [$ip_address, $action_key]);
        $rate_data = $stmt ? $stmt->fetch() : null;

        if (!$rate_data) {
            // First attempt - create record
            $insert_sql = "INSERT INTO rate_limits (ip_address, action, attempt_count, window_start)
                          VALUES (?, ?, 1, ?)";
            db_query($insert_sql, [$ip_address, $action_key, $current_time]);
            return true;
        }

        $window_start = (int)$rate_data['window_start'];
        $attempt_count = (int)$rate_data['attempt_count'];

        // Check if time window expired
        if (($current_time - $window_start) > $time_window) {
            // Reset window
            $update_sql = "UPDATE rate_limits
                          SET attempt_count = 1, window_start = ?
                          WHERE ip_address = ? AND action = ?";
            db_query($update_sql, [$current_time, $ip_address, $action_key]);
            return true;
        }

        // Check if limit exceeded
        if ($attempt_count >= $max_attempts) {
            return false;
        }

        // Increment counter
        $update_sql = "UPDATE rate_limits
                      SET attempt_count = attempt_count + 1
                      WHERE ip_address = ? AND action = ?";
        db_query($update_sql, [$ip_address, $action_key]);
        return true;

    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        // Fail open - allow request if rate limiting fails
        return true;
    }
}

/**
 * Clean up old rate limit records (call this periodically)
 */
function cleanup_rate_limits($older_than_hours = 24) {
    global $pdo;

    try {
        $cutoff_time = time() - ($older_than_hours * 3600);
        $sql = "DELETE FROM rate_limits WHERE window_start < ?";
        db_query($sql, [$cutoff_time]);
    } catch (Exception $e) {
        error_log("Rate limit cleanup failed: " . $e->getMessage());
    }
}

/**
 * Check daily form submission limit per user
 */
function check_daily_form_limit($user_id, $max_forms = 50) {
    global $pdo;

    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as form_count FROM prehospital_forms
            WHERE created_by = ? AND DATE(created_at) = ?";
    $stmt = db_query($sql, [$user_id, $today]);

    if ($stmt) {
        $result = $stmt->fetch();
        return $result['form_count'] < $max_forms;
    }

    return false; // Error checking, deny submission
}

/**
 * Log activity
 */
function log_activity($action, $details = '') {
    global $pdo;

    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = get_client_ip();

    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())";

    db_query($sql, [$user_id, $action, $details, $ip_address]);
}

/**
 * Check if account is locked due to failed login attempts
 */
function is_account_locked($username) {
    global $pdo;

    try {
        $sql = "SELECT failed_attempts, locked_until FROM users WHERE username = ? LIMIT 1";
        $stmt = db_query($sql, [$username]);
        $user = $stmt ? $stmt->fetch() : null;

        if (!$user) {
            return false;
        }

        // Check if account is locked
        if ($user['locked_until']) {
            $locked_until = strtotime($user['locked_until']);
            $current_time = time();

            if ($current_time < $locked_until) {
                $minutes_left = ceil(($locked_until - $current_time) / 60);
                return [
                    'locked' => true,
                    'minutes_remaining' => $minutes_left
                ];
            } else {
                // Lock expired - reset
                reset_failed_attempts($username);
                return false;
            }
        }

        return false;
    } catch (Exception $e) {
        error_log("Account lock check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Record failed login attempt
 */
function record_failed_attempt($username, $max_attempts = 5, $lockout_minutes = 15) {
    global $pdo;

    try {
        $sql = "SELECT failed_attempts FROM users WHERE username = ? LIMIT 1";
        $stmt = db_query($sql, [$username]);
        $user = $stmt ? $stmt->fetch() : null;

        if (!$user) {
            return;
        }

        $failed_attempts = (int)$user['failed_attempts'] + 1;

        if ($failed_attempts >= $max_attempts) {
            // PERMANENTLY RESTRICT the account - Admin must unrestrict manually
            $locked_until = date('Y-m-d H:i:s', time() + ($lockout_minutes * 60));
            $update_sql = "UPDATE users
                          SET failed_attempts = ?, locked_until = ?, is_restricted = 1
                          WHERE username = ?";
            db_query($update_sql, [$failed_attempts, $locked_until, $username]);

            log_activity('account_restricted', "Account RESTRICTED for user: $username after $failed_attempts failed login attempts. Admin action required.");
        } else {
            // Increment failed attempts
            $update_sql = "UPDATE users
                          SET failed_attempts = ?
                          WHERE username = ?";
            db_query($update_sql, [$failed_attempts, $username]);
        }
    } catch (Exception $e) {
        error_log("Record failed attempt error: " . $e->getMessage());
    }
}

/**
 * Reset failed login attempts on successful login
 */
function reset_failed_attempts($username) {
    global $pdo;

    try {
        $sql = "UPDATE users
                SET failed_attempts = 0, locked_until = NULL
                WHERE username = ?";
        db_query($sql, [$username]);
    } catch (Exception $e) {
        error_log("Reset failed attempts error: " . $e->getMessage());
    }
}

/**
 * Escape output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verify reCAPTCHA response
 */
function verify_recaptcha($response) {
    $secret_key = RECAPTCHA_SECRET_KEY;

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $response,
        'remoteip' => get_client_ip()
    ];

    // Use cURL for better reliability
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($result === false || $http_code !== 200) {
        error_log("reCAPTCHA verification failed: HTTP $http_code, cURL error: $curl_error");
        return false;
    }

    $result = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("reCAPTCHA JSON decode error: " . json_last_error_msg());
        return false;
    }

    if (!isset($result['success'])) {
        error_log("reCAPTCHA response missing 'success' field");
        return false;
    }

    if (!$result['success']) {
        error_log("reCAPTCHA verification failed: " . json_encode($result));
    }

    return $result['success'];
}

/**
 * JSON response helper
 */
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
