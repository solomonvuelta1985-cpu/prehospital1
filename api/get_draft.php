<?php
/**
 * Get Draft Data API
 * Retrieves draft form data for resuming
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Security headers
header('Content-Type: application/json');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Require authentication
require_login();

// Get current user
$current_user = get_auth_user();
$user_id = $current_user['id'];

try {
    $draft_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$draft_id) {
        throw new Exception('Draft ID is required');
    }

    // Get draft data
    $sql = "SELECT * FROM prehospital_forms WHERE id = ? AND created_by = ? AND status = 'draft'";
    $stmt = db_query($sql, [$draft_id, $user_id]);
    $draft = $stmt->fetch();

    if (!$draft) {
        throw new Exception('Draft not found or unauthorized');
    }

    echo json_encode([
        'success' => true,
        'data' => $draft,
        'message' => 'Draft loaded successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
