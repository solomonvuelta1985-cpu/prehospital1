<?php
/**
 * API: Change User Password (Admin)
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Require admin authentication
require_login();
require_admin();

// Rate limiting - prevent abuse
if (!check_rate_limit('admin_change_password', 10, 300)) {
    set_flash('Too many password change attempts. Please wait 5 minutes.', 'error');
    header('Location: ../../public/admin/users.php');
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Invalid request method', 'error');
    header('Location: ../../public/admin/users.php');
    exit;
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid security token', 'error');
    header('Location: ../../public/admin/users.php');
    exit;
}

// Get and validate input
$user_id = intval($_POST['user_id'] ?? 0);
$generate_temp = isset($_POST['generate_temp']) && $_POST['generate_temp'] === '1';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Generate temporary password if requested
if ($generate_temp) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    $new_password = '';
    for ($i = 0; $i < 12; $i++) {
        $new_password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $confirm_password = $new_password;
}

// Validation
$errors = [];

if ($user_id <= 0) {
    $errors[] = 'Invalid user ID';
}

if (empty($new_password)) {
    $errors[] = 'New password is required';
} else {
    $password_validation = validate_password_strength($new_password);
    if (!$password_validation['valid']) {
        $errors = array_merge($errors, $password_validation['errors']);
    }
}

if ($new_password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

// Verify user exists
if (empty($errors)) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $errors[] = 'User not found';
    }
}

// If there are errors, return
if (!empty($errors)) {
    set_flash(implode('<br>', $errors), 'error');
    header('Location: ../../public/admin/users.php');
    exit;
}

try {
    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password and set force_password_change flag if temp password
    if ($generate_temp) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?");
        $stmt->execute([$password_hash, $user_id]);

        log_activity('admin_temp_password', "Generated temporary password for user: {$user['username']}");
        set_flash("Temporary password for '{$user['username']}': <strong>{$new_password}</strong><br><small>The user will be required to change this on next login.</small>", 'success');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$password_hash, $user_id]);

        set_flash("Password for '{$user['username']}' updated successfully!", 'success');
    }

    header('Location: ../../public/admin/users.php');
    exit;

} catch (PDOException $e) {
    error_log("Error changing password: " . $e->getMessage());
    set_flash('Database error occurred while changing password', 'error');
    header('Location: ../../public/admin/users.php');
    exit;
}
