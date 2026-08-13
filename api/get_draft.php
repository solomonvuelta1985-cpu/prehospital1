<?php
/**
 * Get Draft Data API
 * Retrieves draft form data for resuming
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
    if (!$stmt) {
        throw new Exception('Database error');
    }
    $draft = $stmt->fetch();

    if (!$draft) {
        throw new Exception('Draft not found or unauthorized');
    }

    // Clean up time and date fields - prevent 00:00:00 and 0000-00-00 from being loaded
    $timeFields = [
        'departure_time', 'arrival_time', 'arrival_scene_time', 'departure_scene_time',
        'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time',
        'incident_time', 'call_arrival_time', 'initial_time', 'followup_time',
        'ob_delivery_time'
    ];

    foreach ($timeFields as $field) {
        if (isset($draft[$field])) {
            // Clear time fields if they are '00:00:00' or NULL or empty
            if ($draft[$field] === '00:00:00' || $draft[$field] === null || $draft[$field] === '' ||
                $draft[$field] === '0000-00-00 00:00:00') {
                $draft[$field] = '';
            }
        }
    }

    // Clean up datetime fields
    $dateTimeFields = [
        'endorsement_datetime'
    ];

    foreach ($dateTimeFields as $field) {
        if (isset($draft[$field])) {
            // Clear datetime fields if they are '00:00:00' or NULL or empty
            if ($draft[$field] === '00:00:00' || $draft[$field] === null || $draft[$field] === '' ||
                $draft[$field] === '0000-00-00 00:00:00') {
                $draft[$field] = '';
            }
        }
    }

    // Clean up date-only fields (form_date can be 0000-00-00 in draft state)
    $dateFields = ['form_date', 'date_of_birth', 'ob_lmp', 'ob_edc'];
    foreach ($dateFields as $field) {
        if (isset($draft[$field])) {
            // Clear date fields if they are '0000-00-00' or NULL or empty
            if ($draft[$field] === '0000-00-00' || $draft[$field] === null || $draft[$field] === '' ||
                $draft[$field] === '0000-00-00 00:00:00') {
                $draft[$field] = '';
            }
        }
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
