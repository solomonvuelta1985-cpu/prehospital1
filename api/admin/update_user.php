<?php
/**
 * Update User API Endpoint
 * Allows admin to update user details
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

try {
    // Verify CSRF token
    if (!verify_token($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    // Validate required fields
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!$user_id || empty($full_name) || empty($username) || empty($email)) {
        throw new Exception('All fields are required');
    }

    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        throw new Exception('Invalid role');
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing_user) {
        throw new Exception('User not found');
    }

    // Check if username is already taken by another user
    if ($username !== $existing_user['username']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Username already exists');
        }
    }

    // Check if email is already taken by another user
    if ($email !== $existing_user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists');
        }
    }

    // Update user
    $session_version_update = session_version_available() ? ', session_version = session_version + 1' : '';
    $stmt = $pdo->prepare("
        UPDATE users
        SET full_name = ?, username = ?, email = ?, role = ?, status = ?{$session_version_update}
        WHERE id = ?
    ");

    $stmt->execute([
        $full_name,
        $username,
        $email,
        $role,
        $status,
        $user_id
    ]);

    // Log the action
    error_log("Admin updated user: " . $username . " (ID: " . $user_id . ")");

    // Set success message
    set_flash('User updated successfully', 'success');

    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
