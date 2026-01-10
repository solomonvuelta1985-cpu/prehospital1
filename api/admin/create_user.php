<?php
/**
 * API: Create New User
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header('Content-Type: application/json');

// Require admin authentication
require_login();
require_admin();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Rate limiting - prevent abuse
if (!check_rate_limit('admin_create_user', 10, 300)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many user creation attempts. Please wait 5 minutes.']);
    exit;
}

// Get and validate input
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$status = $_POST['status'] ?? 'active';

// Validation
$errors = [];

if (empty($full_name)) {
    $errors[] = 'Full name is required';
}

if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} else {
    $password_validation = validate_password_strength($password);
    if (!$password_validation['valid']) {
        $errors = array_merge($errors, $password_validation['errors']);
    }
}

if (!in_array($role, ['user', 'admin'])) {
    $errors[] = 'Invalid role';
}

if (!in_array($status, ['active', 'inactive'])) {
    $errors[] = 'Invalid status';
}

// Check if username already exists
if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $errors[] = 'Username already exists';
    }
}

// Check if email already exists
if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = 'Email already exists';
    }
}

// If there are errors, return
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors),
        'errors' => $errors
    ]);
    exit;
}

try {
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, full_name, email, role, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $username,
        $password_hash,
        $full_name,
        $email,
        $role,
        $status
    ]);

    // Log the action
    error_log("Admin created new user: " . $username . " (Role: " . $role . ")");

    // Set success flash message for page reload
    set_flash("User '$username' created successfully!", 'success');

    echo json_encode([
        'success' => true,
        'message' => "User '$username' created successfully!",
        'user' => [
            'id' => $pdo->lastInsertId(),
            'username' => $username,
            'full_name' => $full_name,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error creating user: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while creating user'
    ]);
    exit;
}
