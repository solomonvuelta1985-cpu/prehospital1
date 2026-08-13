<?php
/**
 * API: Toggle User Status (Activate/Deactivate)
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Require admin authentication
require_login();
require_admin();

// Rate limiting - prevent abuse
if (!check_rate_limit('admin_toggle_status', 20, 300)) {
    echo json_encode(['success' => false, 'message' => 'Too many status change attempts. Please wait 5 minutes.']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get and validate input
$user_id = intval($_POST['user_id'] ?? 0);
$status = $_POST['status'] ?? '';

// Validation
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

if (!in_array($status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Check if trying to deactivate self
$current_user = get_auth_user();
if ($user_id == $current_user['id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
    exit;
}

// Verify user exists
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

try {
    // Update status
    $session_version_update = session_version_available() ? ', session_version = session_version + 1' : '';
    $stmt = $pdo->prepare("UPDATE users SET status = ?{$session_version_update} WHERE id = ?");
    $stmt->execute([$status, $user_id]);

    $action = $status === 'active' ? 'activated' : 'deactivated';
    echo json_encode([
        'success' => true,
        'message' => "User '{$user['username']}' {$action} successfully!"
    ]);

} catch (PDOException $e) {
    error_log("Error toggling user status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
