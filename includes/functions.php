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
 * @param string $sql SQL query with ? placeholders
 * @param array $params Parameters to bind
 * @param bool $throw If true, re-throws PDOException instead of returning false (use inside transactions)
 * @return PDOStatement|false
 */
function db_query($sql, $params = [], $throw = false) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Query Error: " . $e->getMessage());
        error_log("SQL: " . $sql);
        if ($throw) {
            throw $e;
        }
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
 * Return the fields that prevent a draft from being marked as completed.
 *
 * Drafts are intentionally allowed to be incomplete while they are being
 * autosaved, but the status must not be changed to completed until the same
 * required values enforced by the form are present. Keeping this check on the
 * server also prevents callers from bypassing the browser validation.
 */
function get_record_completion_errors($record) {
    $errors = [];

    $has_value = static function ($value) {
        if (is_array($value)) {
            return count($value) > 0;
        }
        if ($value === null) {
            return false;
        }

        $value = trim((string)$value);
        return $value !== ''
            && $value !== '0'
            && $value !== '[]'
            && $value !== '{}'
            && $value !== '0000-00-00'
            && $value !== '0000-00-00 00:00:00';
    };

    // Ignore generated metadata such as form number and form date when
    // determining whether the user entered anything into the draft.
    $input_fields = [
        'departure_time', 'arrival_time', 'vehicle_used', 'vehicle_details',
        'driver_name', 'arrival_scene_location', 'arrival_scene_time',
        'departure_scene_location', 'departure_scene_time', 'arrival_hospital_name',
        'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time',
        'persons_present', 'patient_name', 'date_of_birth', 'age', 'gender',
        'civil_status', 'address', 'zone', 'occupation', 'place_of_incident',
        'zone_landmark', 'incident_time', 'informant_name', 'informant_address',
        'arrival_type', 'call_arrival_time', 'contact_number', 'relationship_victim',
        'personal_belongings', 'other_belongings', 'emergency_medical',
        'emergency_medical_details', 'emergency_trauma', 'emergency_trauma_details',
        'emergency_ob', 'emergency_ob_details', 'emergency_general',
        'emergency_general_details', 'care_management', 'oxygen_lpm', 'other_care',
        'initial_time', 'initial_bp', 'initial_temp', 'initial_pulse',
        'initial_resp_rate', 'initial_pain_score', 'initial_spo2',
        'initial_spinal_injury', 'initial_consciousness', 'initial_helmet',
        'followup_time', 'followup_bp', 'followup_temp', 'followup_pulse',
        'followup_resp_rate', 'followup_pain_score', 'followup_spo2',
        'followup_spinal_injury', 'followup_consciousness', 'chief_complaints',
        'other_complaints', 'fast_face_drooping', 'fast_arm_weakness',
        'fast_speech_difficulty', 'fast_time_to_call', 'fast_sample_details',
        'ob_baby_status', 'ob_delivery_time', 'ob_placenta', 'ob_lmp', 'ob_aog',
        'ob_edc', 'team_leader_notes', 'team_leader', 'data_recorder', 'logistic',
        'first_aider', 'second_aider', 'hospital_name', 'endorsement_datetime',
        'narrative_report', 'waiver_required', 'waiver_attachment'
    ];

    $has_input = false;
    foreach ($input_fields as $field) {
        if ($has_value($record[$field] ?? null)) {
            $has_input = true;
            break;
        }
    }
    if (!$has_input) {
        $errors[] = 'No form entries were provided.';
    }

    $form_date = trim((string)($record['form_date'] ?? ''));
    if (!$has_value($form_date)) {
        $errors[] = 'Date is required.';
    } elseif (!validate_date($form_date)) {
        $errors[] = 'Date is invalid.';
    } elseif ($form_date > date('Y-m-d')) {
        $errors[] = 'Date cannot be in the future.';
    }

    if (!$has_value($record['patient_name'] ?? null)) {
        $errors[] = 'Patient Name is required.';
    }

    if ((int)($record['age'] ?? 0) <= 0) {
        $errors[] = 'Age is required and must be greater than 0.';
    }

    $gender = strtolower(trim((string)($record['gender'] ?? '')));
    if ($gender === '') {
        $errors[] = 'Gender is required.';
    } elseif (!in_array($gender, ['male', 'female'], true)) {
        $errors[] = 'Gender is invalid.';
    }

    $emergency_types = [
        ['emergency_medical', 'emergency_medical_details', 'Medical'],
        ['emergency_trauma', 'emergency_trauma_details', 'Trauma'],
        ['emergency_ob', 'emergency_ob_details', 'OB'],
        ['emergency_general', 'emergency_general_details', 'General'],
    ];
    $selected_emergency_count = 0;
    foreach ($emergency_types as [$flag, $details, $label]) {
        if (!empty($record[$flag])) {
            $selected_emergency_count++;
            if (!$has_value($record[$details] ?? null)) {
                $errors[] = $label . ' emergency details are required.';
            }
        }
    }
    if ($selected_emergency_count === 0) {
        $errors[] = 'Select at least one Type of Emergency Call.';
    }

    if (!empty($record['waiver_required']) && !$has_value($record['waiver_attachment'] ?? null)) {
        $errors[] = 'A signed waiver document is required when the waiver is enabled.';
    }

    return $errors;
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
 * Validate and securely store an uploaded image inside the private uploads
 * directory. The returned path is the relative path persisted in the record.
 */
function store_secure_image_upload($file, $folder, $prefix) {
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid image upload');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid uploaded image');
    }

    if ((int)$file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Image file size exceeds the 20MB limit');
    }

    $allowed_mime_types = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
    ];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $extension = strtolower((string)pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!isset($allowed_mime_types[$mime_type]) || !in_array($extension, $allowed_extensions, true)) {
        throw new Exception('Invalid image type. Only JPG, PNG, GIF, and WebP files are allowed');
    }

    if (!in_array($extension, $allowed_mime_types[$mime_type], true)) {
        throw new Exception('Image extension does not match its content');
    }

    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        throw new Exception('Uploaded file is not a valid image');
    }

    $folder = trim((string)$folder, '/\\');
    if ($folder === '' || preg_match('/(^|[\\\/])\.\.([\\\/]|$)/', $folder)) {
        throw new Exception('Invalid upload folder');
    }

    $date_folder = date('Y-m-d');
    $relative_directory = 'uploads/' . $folder . '/' . $date_folder;
    $absolute_directory = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $date_folder;
    if (!is_dir($absolute_directory) && !mkdir($absolute_directory, 0750, true)) {
        throw new Exception('Failed to create image upload directory');
    }

    $safe_prefix = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$prefix) ?: 'image';
    $safe_filename = $safe_prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $target_path = $absolute_directory . DIRECTORY_SEPARATOR . $safe_filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception('Failed to save uploaded image');
    }

    return $relative_directory . '/' . $safe_filename;
}

/**
 * Resolve a stored uploads-relative path without allowing traversal outside
 * the configured uploads directory.
 */
function resolve_upload_path($relative_path) {
    $relative_path = ltrim(str_replace('\\', '/', (string)$relative_path), '/');
    if (strpos($relative_path, 'uploads/') !== 0 || strpos($relative_path, '..') !== false) {
        return false;
    }

    $relative_file = substr($relative_path, strlen('uploads/'));
    $base_path = realpath(UPLOAD_DIR);
    $file_path = realpath(rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_file));
    if (!$base_path || !$file_path || strpos($file_path, $base_path . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    return $file_path;
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

        // Probabilistic cleanup: 1% chance per request to clean old records
        if (mt_rand(1, 100) === 1) {
            cleanup_rate_limits();
        }

        return true;

    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        // Fail closed - deny request if rate limiting fails (security-first)
        return false;
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
 * Normalize a free-text barangay/locality string into a canonical name.
 * Patient addresses are dirty: trailing ", BAGGAO, CAGAYAN" suffixes, spacing
 * variants, and common typos all refer to the same barangay. This collapses
 * those into one label so per-barangay counts (and the map) are accurate.
 * Display/aggregation only — does not modify stored data.
 *
 * @param string|null $raw Raw address/zone string
 * @return string Canonical UPPERCASE barangay name, or 'UNSPECIFIED'
 */
function normalize_barangay($raw) {
    $s = strtoupper(trim((string)($raw ?? '')));
    if ($s === '') return 'UNSPECIFIED';

    // Drop municipality/province qualifiers and anything after them.
    // Handles "REMUS, BAGGAO, CAGAYAN" and unpunctuated "TAGUNTUNGAN BAGGAO CAGAYAN".
    $s = preg_replace('/[, ]+(BAGGAO|CAGAYAN|LALLO|GATTARAN|LASAM)\b.*$/u', '', $s);

    // Strip purok/zone prefixes and standalone zone numbers tacked onto names.
    $s = preg_replace('/\b(PUROK|ZONE|BRGY\.?|BARANGAY|P\.)\s*/u', '', $s);
    $s = preg_replace('/\bZONE\s*\d+\b/u', '', $s);

    // Collapse punctuation/whitespace.
    $s = preg_replace('/[.,]+/u', ' ', $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));
    if ($s === '') return 'UNSPECIFIED';

    // Known typo / variant -> canonical map.
    static $aliases = [
        'STA MARGARITA'  => 'STA. MARGARITA',
        'SANTA MARGARITA'=> 'STA. MARGARITA',
        'SANTOR BAGGAO'  => 'SANTOR',
        'CALAMANASI'     => 'CALAMANSI',
        'IMURING'        => 'IMURUNG',
        'AWLLAN'         => 'AWALLAN',
        'SAN JOSR'       => 'SAN JOSE',
        'TAYTAY BANTAY'  => 'TAYTAY',
        'AWALLAN KAGURUNGAN' => 'AWALLAN',
    ];
    return $aliases[$s] ?? $s;
}

/**
 * Detect whether a trauma free-text detail describes a vehicular accident.
 * There is no structured "VA" field — staff type it into the trauma "specify"
 * box (stored in emergency_trauma_details). This keyword match derives a VA
 * category for reporting. Keyword-based: may miss/over-match unusual phrasing.
 *
 * @param string|null $text emergency_trauma_details value
 * @return bool true if the text reads like a vehicular accident
 */
function is_vehicular_accident($text) {
    $t = trim((string)($text ?? ''));
    if ($t === '') return false;
    return (bool)preg_match(
        '/\bVA\b|\bV\/A\b|vehicular|vehicle\s*accident|road\s*traffic|\bRTA\b|motor\s*(cycle|vehicle)|motorcycle|collision|hit\s*and\s*run|sideswipe|run\s*over|loss\s*of\s*control/i',
        $t
    );
}

/**
 * Canonical "Consolidated Run" incident categories (poster order, minus OPD
 * which the app does not track). Used by the classifier and the form datalist.
 */
function incident_categories() {
    return [
        'Vehicular Accident', 'Mauling', 'Fall', 'Goring', 'Gunshot', 'Stabbing',
        'Hack Wound', 'Animal Bite', 'Burn', 'Drowning', 'Choking', 'Strangulation',
        'Electrocution', 'Chemical Ingestion', 'Sexual Harassment', 'Stoning',
    ];
}

/**
 * Dropdown options for the "Type of Emergency Call" specify boxes, one list per
 * emergency type. Derived from a frequency analysis of 959 real prehospital_forms
 * records (btrahnqi_pre_hospital_db), ordered most-common first. Responders pick a
 * value or choose "Other" for manual entry. Labels mirror the canonical
 * incident_category / medical_categories() values the app already stores, so a
 * picked value classifies consistently in reports. Named *_specify_options to
 * avoid colliding with the classifier's medical_categories() defined below.
 */
function medical_specify_options() {
    return [
        'GI/Abdominal',             // abdominal/epigastric/hypogastric pain, vomiting, LBM
        'Difficulty of Breathing',  // DOB, SOB, asthma
        'Dizziness/Headache',
        'Chest Pain/Cardiac',
        'Hypertension',
        'Generalized Weakness',     // "body weakness" and its many spellings
        'Fever/Infection',
        'Stroke/CVA',
        'Cardiac Arrest',           // unresponsive / unconscious
        'Seizure',
        'Chemical Ingestion',       // poison / herbicide
    ];
}

function trauma_specify_options() {
    return [
        'Vehicular Accident',       // all V/A, VA, collision, loss of control, MV-MV variants
        'Fall',
        'Mauling',
        'Laceration/Wound',         // lacerated / puncture wounds, work / sports injury
        'Stabbing',
        'Goring',
        'Hack Wound',
        'Stoning',
        'Drowning',
        'Animal Bite',
        'Burn',
        'Gunshot',
    ];
}

function ob_specify_options() {
    return [
        'Labor/Delivery',
        'Vaginal Bleeding/Spotting',
        'Abdominal Pain (OB)',
    ];
}

function general_specify_options() {
    return [
        'Fire Incident',
        'Strangulation',
        'Electrocution',
    ];
}

/**
 * Common destinations used in the Hospital Endorsement section.
 * Aliases keep legacy abbreviations and spelling variants selectable without
 * duplicating them as separate user-facing options.
 */
function hospital_endorsement_options() {
    return [
        [
            'value' => 'BAGGAO DISTRICT HOSPITAL',
            'label' => 'Baggao District Hospital (BDH)',
            'aliases' => [
                'BDH',
                'BAGGAO DISTRICT HOSPITAL',
                'BAGGAO DISTRICT HISPITAL',
                'BAGGAO DISTRICT HOSPITA',
                'BAGGAO DISTRICT HOPITAL',
                'BAGGAO DSTRICT HOSPITAL',
                'BAGGAO DISTRICT HOSPITQL',
                'BAGGAO DISTRICT HOAPITAL',
                'BAGGAO DISTRICT HOSPITAL F',
                'BAGGAO DISRICT HOSPITAL',
            ],
        ],
        [
            'value' => 'MUNICIPAL HEALTH OFFICE',
            'label' => 'Municipal Health Office (MHO)',
            'aliases' => [
                'MHO',
                'MUNICIPAL HEALTH OFFICE',
                'MHO POBLACION',
                'MUNIIPAL HEALTH OFFICE',
                'MDHO',
                'MUNICIPAL HEALTH UNIT',
            ],
        ],
        [
            'value' => 'ALCALA MUNICIPAL HOSPITAL',
            'label' => 'Alcala Municipal Hospital (AMH)',
            'aliases' => [
                'AMH',
                'ALCALA MUNICIPAL HOSPITAL',
                'ALACALA MUNICIPAL HOSPITAL',
            ],
        ],
        [
            'value' => 'RURAL HEALTH UNIT',
            'label' => 'Rural Health Unit (RHU)',
            'aliases' => [
                'RURAL HEALTH UNIT',
                'RHU',
                'RHU POBLACION',
                'RHU SAN JOSE',
            ],
        ],
        [
            'value' => 'NOLASCO HOSPITAL (GATTARAN)',
            'label' => 'Nolasco Hospital (Gattaran)',
            'aliases' => ['NOLASCO HOSPITAL (GATTARAN)'],
        ],
        [
            'value' => 'CVMC',
            'label' => 'Cagayan Valley Medical Center (CVMC)',
            'aliases' => ['CVMC'],
        ],
    ];
}

/** Return the canonical Hospital Endorsement value for a legacy alias. */
function hospital_endorsement_canonical($value) {
    $candidate = trim((string)($value ?? ''));
    if ($candidate === '') return '';

    $normalized = mb_strtoupper(preg_replace('/\s+/', ' ', $candidate), 'UTF-8');
    foreach (hospital_endorsement_options() as $option) {
        foreach ($option['aliases'] as $alias) {
            $aliasNormalized = mb_strtoupper(preg_replace('/\s+/', ' ', trim($alias)), 'UTF-8');
            if ($normalized === $aliasNormalized) return $option['value'];
        }
    }

    return '';
}

/**
 * Classify a free-text incident detail into a canonical category.
 * Ordered keyword map, first match wins. Built for the messy free text staff
 * type into the emergency *_specify boxes (e.g. "V/A ( LOSS OF CONTROL)",
 * "MAULING", "FALL INCIDENT"). Returns null when nothing matches so callers
 * can roll the record into its parent emergency type instead.
 *
 * @param string|null $text an emergency_*_details value
 * @return string|null canonical category label, or null if unclassifiable
 */
function classify_incident_category($text) {
    $t = trim((string)($text ?? ''));
    if ($t === '') return null;

    // VA reuses the dedicated helper so the rule stays consistent.
    if (is_vehicular_accident($t)) return 'Vehicular Accident';

    // Burn has a false-positive guard: "burning micturition/urination/sensation"
    // are medical symptoms, NOT burn incidents — exclude them explicitly.
    if (preg_match('/fire\s*incident|burned|burnt|scald|flame/i', $t)
        || (preg_match('/\bburn\b/i', $t) && !preg_match('/burning\s*(micturition|urination|sensation)/i', $t))) {
        return 'Burn';
    }

    // Ordered: more specific patterns first. Patterns hardened to avoid common
    // false positives (e.g. "stone/gallstone" must NOT become Stoning).
    $map = [
        'Hack Wound'        => '/\bhack/i',
        'Gunshot'           => '/gun\s*shot|gunshot|\bgsw\b/i',          // not bare "shot"
        'Stabbing'          => '/stab|\bstabbed\b|knife|bladed/i',
        'Mauling'           => '/maul/i',
        'Goring'            => '/\bgor(e|ed|ing)?\b|carabao/i',
        'Animal Bite'       => '/\bbite\b|dog\s*bite|snake|rabies/i',
        'Drowning'          => '/drown|submer/i',
        'Choking'           => '/chok|foreign\s*body\s*airway|fbao/i',
        'Strangulation'     => '/strangl|\bhanging\b|\bnoose\b/i',
        'Electrocution'     => '/electro|electrocut/i',
        'Chemical Ingestion'=> '/chemical|ingest|poison|lason/i',
        'Sexual Harassment' => '/sexual|\brape\b|molest/i',
        'Stoning'           => '/\bstoning\b|pukpok\s*bato/i',           // not "stone/gallstone"
        'Fall'              => '/\bfall\b|\bfell\b|nahulog|slipped/i',
    ];
    foreach ($map as $label => $pattern) {
        if (preg_match($pattern, $t)) return $label;
    }
    return null;
}

/**
 * Classify an incident category from ALL of a record's signal fields, not just
 * the specify boxes. Responders often type the incident ("VEHICULAR ACCIDENT",
 * "GORING INCIDENT") into the narrative/complaint instead of the specify field,
 * so this combines them so the category is caught at save time.
 *
 * @param array $r record fields (any subset of: emergency_trauma_details,
 *   emergency_general_details, emergency_medical_details, emergency_ob_details,
 *   other_complaints, team_leader_notes)
 * @return string|null canonical category, or null if nothing matched
 */
/**
 * Decode the helmet/risk-factor field into an array, handling BOTH storage
 * formats: newer JSON (e.g. ["ab","none"]) and legacy plain strings (e.g. "ab").
 *
 * NOTE on meaning: this field (initial_helmet) is a multi-select labeled on the
 * form as "+ AB" (positive Alcohol Breath — patient was intoxicated) and
 * "No Helmet". Despite the column name, "ab" = alcohol breath, NOT a helmet type.
 *
 * @param string|null $value raw initial_helmet column value
 * @return array list of selected codes (e.g. ['ab','none']); [] if none
 */
function decode_helmet($value) {
    $v = trim((string)($value ?? ''));
    if ($v === '') return [];
    $decoded = json_decode($v, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded), 'strlen'));
    }
    // Legacy single plain-string value ("ab" / "none").
    return [strtolower($v)];
}

/**
 * Human label for a helmet/risk-factor code.
 * @param string $code 'ab' | 'none'
 * @return string
 */
function helmet_label($code) {
    switch (strtolower(trim((string)$code))) {
        case 'ab':   return '+ AB (Alcohol Breath)';
        case 'none': return 'No Helmet';
        default:     return ucfirst((string)$code);
    }
}

function classify_incident_from_record(array $r) {
    $sig = trim(implode(' ', array_filter([
        $r['emergency_trauma_details']  ?? null,
        $r['emergency_general_details'] ?? null,
        $r['emergency_medical_details'] ?? null,
        $r['emergency_ob_details']      ?? null,
        $r['other_complaints']          ?? null,
        $r['team_leader_notes']         ?? null,
    ], 'strlen')));
    return $sig === '' ? null : classify_incident_category($sig);
}

/**
 * Medical/OB clinical categories for runs that are NOT a trauma incident
 * (no V/A, Fall, Mauling, etc.). Derived from the chief complaint, narrative,
 * and the structured FAST / consciousness / care_management signals. Ordered
 * most-specific first so e.g. a stroke is not mislabelled "Generalized Weakness".
 * Returns one of medical_categories(), or 'Other Medical (unspecified)'.
 *
 * @param array $r a prehospital_forms row (any subset of the read fields)
 * @return string a medical/OB category label
 */
function medical_categories() {
    return [
        'Labor/Delivery (OB)', 'Stroke/CVA', 'Cardiac Arrest', 'Chest Pain/Cardiac',
        'Difficulty of Breathing', 'Seizure', 'Hypertension', 'Diabetic/Glucose',
        'GI/Abdominal', 'Fever/Infection', 'Dizziness/Headache', 'Generalized Weakness',
        'Trauma (unspecified)', 'Other Medical (unspecified)',
    ];
}

function classify_medical_category(array $r) {
    // Combine the same free-text signal used by the incident classifier, plus
    // the chief-complaint list (JSON) which carries the clearest medical signal.
    $sig = strtoupper(trim(implode(' ', array_filter([
        $r['emergency_trauma_details']  ?? null,
        $r['emergency_general_details'] ?? null,
        $r['emergency_medical_details'] ?? null,
        $r['emergency_ob_details']      ?? null,
        $r['other_complaints']          ?? null,
        $r['team_leader_notes']         ?? null,
    ], 'strlen'))));

    // chief_complaints is stored as a JSON array (e.g. ["CHEST PAIN","COUGH"]).
    $cc = $r['chief_complaints'] ?? null;
    if (is_string($cc) && $cc !== '') {
        $decoded = json_decode($cc, true);
        if (is_array($decoded)) $sig .= ' ' . strtoupper(implode(' ', $decoded));
    }

    // Consciousness text (e.g. UNCONSCIOUS) is a Cardiac Arrest signal — include it.
    $cons = $r['initial_consciousness'] ?? null;
    if (is_string($cons) && $cons !== '') $sig .= ' ' . strtoupper($cons);

    // care_management mentioning CPR is a Cardiac Arrest signal — inject a token.
    $care = $r['care_management'] ?? null;
    if (is_string($care) && stripos($care, 'CPR') !== false) $sig .= ' CPR';

    // Structured stroke signal: any positive FAST field => Stroke/CVA.
    $fast_positive = false;
    foreach (['fast_face_drooping', 'fast_arm_weakness', 'fast_speech_difficulty', 'fast_time_to_call'] as $f) {
        if (strtolower((string)($r[$f] ?? '')) === 'positive') { $fast_positive = true; break; }
    }
    $flag_ob = !empty($r['emergency_ob']);

    // OB first (incl. vaginal bleeding in an obstetric context).
    if ($flag_ob || preg_match('/LABOR|DELIVERY|BAG OF WATER|WATER DISCHARGE|MUCUS DISCHARGE|\bG[0-9]P[0-9]|AMNIOTIC|OBSTETRIC|PREGNAN|VAGINAL BLEED/', $sig)) {
        return 'Labor/Delivery (OB)';
    }
    // Generic trauma residue: wounds/injuries with no specific incident keyword.
    if (preg_match('/LACERAT|LACEATED|WOUND|ABRASION|CONTUSION|SPORTS INJURY|WORK ?RELATED INJURY|WORK INJURY|FRACTURE|DISLOCAT|PUNCTURE/', $sig)
        && !preg_match('/CHEST PAIN|ABDOMIN|EPIGASTR/', $sig)) {
        return 'Trauma (unspecified)';
    }
    if ($fast_positive || preg_match('/\bCVA\b|STROKE|FACIAL DROOP|SLURRED|ARM WEAKNESS|HEMIPARE|FACE DROOP/', $sig)) return 'Stroke/CVA';
    if (preg_match('/UNRESPONSIVE|UNCONSCIOUS|UNCONCIOUS|\(-\) ?PULSE|NO PULSE|NEGATIVE PULSE|CARDIAC ARREST|\bCPR\b|ASYSTOLE/', $sig)) return 'Cardiac Arrest';
    if (preg_match('/CHEST PAIN|ANGINA|MYOCARD|\bMI\b|PALPITATION/', $sig)) return 'Chest Pain/Cardiac';
    if (preg_match('/DIFFICULTY ?OF ?BREATHING|SHORTNESS OF BREATH|\bDOB\b|DYSPNEA|ASTHMA|BREATHING/', $sig)) return 'Difficulty of Breathing';
    if (preg_match('/SEIZURE|CONVULS|EPILEP/', $sig)) return 'Seizure';
    if (preg_match('/HYPERTEN|HIGH BLOOD|ELEVATED BP|\bHPN\b/', $sig)) return 'Hypertension';
    if (preg_match('/HYPOGLYCEM|HYPERGLYCEM|DIABET|HIGH SUGAR|LOW SUGAR|BLOOD SUGAR/', $sig)) return 'Diabetic/Glucose';
    if (preg_match('/VOMIT|DIARRHEA|LOOSE STOOL|ABDOMINAL|STOMACH|GASTRO|HYPERACID|EPIGASTR|FLANK PAIN|\bRLQ\b|LOWER QUADRANT|BACK ?PAIN/', $sig)) return 'GI/Abdominal';
    if (preg_match('/FEVER|CHILL|FLU|COUGH|COLDS|INFLUENZA|INFECTION/', $sig)) return 'Fever/Infection';
    if (preg_match('/DIZZ|HEAD ?ACHE|VERTIGO|BLURRED VISION|FAINT|SYNCOPE/', $sig)) return 'Dizziness/Headache';
    if (preg_match('/BODY ?WEAK|MALAISE|BODY ?PAIN|GENERALIZED WEAK|NUMBNESS|WEAKNESS/', $sig)) return 'Generalized Weakness';
    return 'Other Medical (unspecified)';
}

/**
 * Resolve the single best category for a record, in priority order:
 *   1. a saved incident_category (trauma incident persisted on the row),
 *   2. a trauma incident inferred from the free text,
 *   3. a medical/OB clinical category from the complaint + structured signals.
 * Always returns a non-empty label (worst case 'Other Medical (unspecified)').
 *
 * @param array $r a prehospital_forms row
 * @return string the resolved category label
 */
function resolve_record_category(array $r) {
    $saved = isset($r['incident_category']) ? trim((string)$r['incident_category']) : '';
    if ($saved !== '') return $saved;

    $incident = classify_incident_from_record($r);
    if ($incident !== null) return $incident;

    return classify_medical_category($r);
}

/**
 * Build a "Consolidated Run" tally from prehospital_forms rows.
 * Prefers a structured `incident_category` column when present (Phase 2);
 * otherwise classifies the four *_details free-text fields. Also returns the
 * parent emergency-type totals (Medical/Trauma/OB/General).
 *
 * @param array $rows rows with emergency_* flags, *_details, and optionally incident_category
 * @return array ['parents' => [...], 'categories' => [label => count], 'uncategorized' => int]
 */
function consolidated_run_counts(array $rows) {
    $parents = ['Medical' => 0, 'Trauma' => 0, 'OB' => 0, 'General' => 0];
    $categories = [];
    $uncategorized = 0;

    foreach ($rows as $r) {
        if (!empty($r['emergency_medical'])) $parents['Medical']++;
        if (!empty($r['emergency_trauma']))  $parents['Trauma']++;
        if (!empty($r['emergency_ob']))      $parents['OB']++;
        if (!empty($r['emergency_general'])) $parents['General']++;

        // Resolve to a single category: saved incident_category -> trauma incident
        // from text -> medical/OB clinical category. Always non-empty, so a record
        // only stays "uncategorized" when it carries no usable signal at all.
        $cat = resolve_record_category($r);

        if ($cat !== '') {
            $categories[$cat] = ($categories[$cat] ?? 0) + 1;
        } else {
            $uncategorized++;
        }
    }
    arsort($categories);
    return ['parents' => $parents, 'categories' => $categories, 'uncategorized' => $uncategorized];
}

/**
 * Get relative time string (e.g., "2 hours ago", "just now")
 * @param string $datetime MySQL datetime string
 * @return string Human-readable relative time
 */
function time_ago($datetime) {
    if (empty($datetime)) return 'unknown';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'unknown';
    $diff = time() - $timestamp;
    if ($diff < 0) return 'just now';
    if ($diff < 60) return 'just now';
    if ($diff < 3600) { $mins = floor($diff / 60); return $mins . ' min' . ($mins !== 1 ? 's' : '') . ' ago'; }
    if ($diff < 86400) { $hours = floor($diff / 3600); return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago'; }
    if ($diff < 604800) { $days = floor($diff / 86400); return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago'; }
    if ($diff < 2592000) { $weeks = floor($diff / 604800); return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' ago'; }
    if ($diff < 31536000) { $months = floor($diff / 2592000); return $months . ' month' . ($months !== 1 ? 's' : '') . ' ago'; }
    $years = floor($diff / 31536000);
    return $years . ' year' . ($years !== 1 ? 's' : '') . ' ago';
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
 * Check if current user can access a specific record
 * Admins can access all records, regular users can only access their own
 */
function can_access_record($record_id) {
    if (is_admin()) {
        return true;
    }
    $user_id = $_SESSION['user_id'] ?? 0;
    $sql = "SELECT id FROM prehospital_forms WHERE id = ? AND created_by = ?";
    $stmt = db_query($sql, [$record_id, $user_id]);
    return $stmt && $stmt->fetch() !== false;
}

/**
 * Encrypt a field value using authenticated AES-256-GCM.
 * Existing AES-256-CBC values remain readable through decrypt_field() for a
 * safe rolling migration. Production refuses to silently store plaintext.
 */
function encrypt_field($plaintext) {
    if (empty($plaintext)) {
        return $plaintext;
    }

    if (empty(APP_ENCRYPTION_KEY)) {
        if (defined('APP_ENV') && APP_ENV === 'production') {
            throw new RuntimeException('APP_ENCRYPTION_KEY is required in production');
        }
        return $plaintext;
    }

    $key = hash('sha256', APP_ENCRYPTION_KEY, true);
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if ($encrypted === false || strlen($tag) !== 16) {
        error_log("Encryption failed for field");
        throw new RuntimeException('Unable to encrypt sensitive field');
    }

    return 'v2:' . base64_encode($iv . $tag . $encrypted);
}

/**
 * Decrypt a field value encrypted with encrypt_field()
 * Returns plaintext, or original value if not encrypted or key not set
 */
function decrypt_field($ciphertext) {
    if (empty($ciphertext)) {
        return $ciphertext;
    }

    if (empty(APP_ENCRYPTION_KEY)) {
        if (defined('APP_ENV') && APP_ENV === 'production') {
            throw new RuntimeException('APP_ENCRYPTION_KEY is required to decrypt production data');
        }
        return $ciphertext;
    }

    $key = hash('sha256', APP_ENCRYPTION_KEY, true);

    // Authenticated format introduced by this hardening pass.
    if (strpos((string)$ciphertext, 'v2:') === 0) {
        $decoded = base64_decode(substr($ciphertext, 3), true);
        if ($decoded === false || strlen($decoded) < 29) {
            return $ciphertext;
        }
        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $encrypted = substr($decoded, 28);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted === false ? $ciphertext : $decrypted;
    }

    // Legacy unauthenticated AES-256-CBC format for existing records.
    $decoded = base64_decode($ciphertext, true);
    if ($decoded === false || strlen($decoded) < 17) {
        return $ciphertext;
    }
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);

    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        return $ciphertext; // Decryption failed, return original
    }

    return $decrypted;
}

/**
 * Encrypt sensitive fields in a record array before saving
 */
function encrypt_record_fields(&$data, $fields = ['patient_name', 'address']) {
    foreach ($fields as $field) {
        if (isset($data[$field]) && !empty($data[$field])) {
            $data[$field] = encrypt_field($data[$field]);
        }
    }
}

/**
 * Decrypt sensitive fields in a record array after reading
 */
function decrypt_record_fields(&$record, $fields = ['patient_name', 'address']) {
    if (!$record) return;
    foreach ($fields as $field) {
        if (isset($record[$field]) && !empty($record[$field])) {
            $record[$field] = decrypt_field($record[$field]);
        }
    }
}

/**
 * Compute diff between old and new record data
 * Returns array of changed fields with old and new values
 */
function compute_record_diff($old, $new) {
    $changes = [];
    $skip_fields = ['id', 'created_at', 'updated_at', 'created_by'];

    foreach ($new as $key => $new_value) {
        if (in_array($key, $skip_fields)) continue;
        $old_value = $old[$key] ?? null;

        // Normalize for comparison
        $old_str = trim((string)($old_value ?? ''));
        $new_str = trim((string)($new_value ?? ''));

        if ($old_str !== $new_str) {
            $changes[$key] = [
                'old' => $old_str,
                'new' => $new_str
            ];
        }
    }
    return $changes;
}

/**
 * Save a version snapshot before updating a record
 */
function save_record_version($record_id, $changes) {
    if (empty($changes)) return;

    $user_id = $_SESSION['user_id'] ?? 0;
    $changes_json = json_encode($changes);

    $sql = "INSERT INTO record_versions (record_id, user_id, changes_json) VALUES (?, ?, ?)";
    db_query($sql, [$record_id, $user_id, $changes_json]);
}

/**
 * Get version history for a record
 */
function get_record_history($record_id) {
    $sql = "SELECT rv.*, u.username, u.full_name
            FROM record_versions rv
            LEFT JOIN users u ON rv.user_id = u.id
            WHERE rv.record_id = ?
            ORDER BY rv.created_at DESC";
    $stmt = db_query($sql, [$record_id]);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Get app setting from database
 */
function get_app_setting($key, $default = '') {
    $sql = "SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1";
    $stmt = db_query($sql, [$key]);
    if ($stmt) {
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }
    return $default;
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

/**
 * Write a CSV row without allowing spreadsheet applications to interpret
 * patient-controlled values as formulas when the export is opened.
 */
function secure_fputcsv($stream, array $fields) {
    $safe_fields = array_map(function ($value) {
        if (!is_string($value) || $value === '') {
            return $value;
        }
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }, $fields);
    return fputcsv($stream, $safe_fields);
}
