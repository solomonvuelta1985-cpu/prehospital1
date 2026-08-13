<?php
/**
 * Delete Pre-Hospital Care Record
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Security headers
header('Content-Type: application/json');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Require authentication
require_login();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Verify CSRF token
    $csrf_token = $input['csrf_token'] ?? '';
    if (!verify_token($csrf_token)) {
        json_response(['success' => false, 'message' => 'Invalid security token. Please refresh the page.'], 403);
    }

    $record_id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($record_id <= 0) {
        throw new Exception('Invalid record ID');
    }

    // Ownership check - users can only delete their own records, admins can delete all
    if (!can_access_record($record_id)) {
        json_response(['success' => false, 'message' => 'Access denied'], 403);
    }

    // Check if record exists
    $check_sql = "SELECT id, form_number FROM prehospital_forms WHERE id = ?";
    $check_stmt = db_query($check_sql, [$record_id]);
    if (!$check_stmt) {
        throw new Exception('Database error');
    }
    $record = $check_stmt->fetch();

    if (!$record) {
        throw new Exception('Record not found');
    }
    
    // Start transaction - use $pdo directly so exceptions propagate properly
    $pdo->beginTransaction();

    // Delete from all child tables first (in case ON DELETE CASCADE is not set)
    $child_tables = [
        "DELETE FROM injuries WHERE form_id = ?",
        "DELETE FROM form_attachments WHERE form_id = ?",
        "DELETE FROM record_versions WHERE record_id = ?",
    ];

    foreach ($child_tables as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$record_id]);
        } catch (PDOException $e) {
            // Table may not exist - that's OK, continue
            error_log("Delete child table (non-critical): " . $e->getMessage());
        }
    }

    // Delete main record
    $delete_stmt = $pdo->prepare("DELETE FROM prehospital_forms WHERE id = ?");
    $delete_stmt->execute([$record_id]);

    if ($delete_stmt->rowCount() === 0) {
        throw new Exception('Failed to delete record - no rows affected');
    }

    // Commit transaction
    $pdo->commit();
    
    // Log activity
    log_activity('form_deleted', "Deleted form: {$record['form_number']}");
    
    json_response([
        'success' => true,
        'message' => 'Record deleted successfully'
    ], 200);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Delete Record Error: " . $e->getMessage());
    
    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
