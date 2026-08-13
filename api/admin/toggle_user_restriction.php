<?php
/**
 * Toggle User Restriction API
 * Restrict/Unrestrict users from logging in
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Security headers
header('Content-Type: application/json');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Require admin authentication
require_login();
require_admin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    // Verify CSRF token
    if (!verify_token($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token');
    }

    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $is_restricted = isset($_POST['is_restricted']) ? (int)$_POST['is_restricted'] : 0;

    if ($user_id <= 0) {
        throw new Exception('Invalid user ID');
    }

    // Get current user to prevent self-restriction
    $current_user = get_auth_user();
    if ($user_id == $current_user['id']) {
        throw new Exception('You cannot restrict yourself');
    }

    // Check if user exists
    $check_sql = "SELECT id, username, full_name, is_restricted FROM users WHERE id = ?";
    $check_stmt = db_query($check_sql, [$user_id]);
    $user = $check_stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Update restriction status
    if ($is_restricted) {
        // Restricting user
        $session_version_update = session_version_available() ? ', session_version = session_version + 1' : '';
        $update_sql = "UPDATE users SET is_restricted = ?{$session_version_update} WHERE id = ?";
        $update_stmt = db_query($update_sql, [$is_restricted, $user_id]);
    } else {
        // Unrestricting user - also reset failed attempts and unlock
        $session_version_update = session_version_available() ? ', session_version = session_version + 1' : '';
        $update_sql = "UPDATE users SET is_restricted = ?, failed_attempts = 0, locked_until = NULL{$session_version_update} WHERE id = ?";
        $update_stmt = db_query($update_sql, [$is_restricted, $user_id]);
    }

    if (!$update_stmt) {
        throw new Exception('Failed to update user restriction');
    }

    // Log activity
    $action_text = $is_restricted ? 'restricted' : 'unrestricted';
    log_activity('user_restriction_changed', "User {$user['username']} was {$action_text}");

    // Success message
    $message = $is_restricted
        ? "User '{$user['full_name']}' has been restricted from logging in"
        : "User '{$user['full_name']}' has been unrestricted and can login again";

    json_response([
        'success' => true,
        'message' => $message
    ], 200);

} catch (Exception $e) {
    error_log("Toggle User Restriction Error: " . $e->getMessage());

    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
