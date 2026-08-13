<?php
/**
 * Get Record Version History API
 * Returns the change history for a specific record
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Security headers
header('Content-Type: application/json');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

require_login();

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($record_id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid record ID'], 400);
}

// Ownership check
if (!can_access_record($record_id)) {
    json_response(['success' => false, 'message' => 'Access denied'], 403);
}

try {
    $history = get_record_history($record_id);

    // Format for display
    $formatted = array_map(function($version) {
        $changes = json_decode($version['changes_json'], true) ?: [];
        return [
            'id' => $version['id'],
            'user' => $version['full_name'] ?? $version['username'] ?? 'Unknown',
            'date' => date('M d, Y h:i A', strtotime($version['created_at'])),
            'changes_count' => count($changes),
            'changes' => $changes
        ];
    }, $history);

    json_response([
        'success' => true,
        'data' => $formatted,
        'total' => count($formatted)
    ]);

} catch (Exception $e) {
    error_log("Record history error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error retrieving history'], 500);
}
