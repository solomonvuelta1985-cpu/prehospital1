<?php
/**
 * Diagnostic: Check Draft IDs
 * DELETE THIS FILE after debugging!
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: text/plain');

// Require authentication
require_login();

$current_user = get_auth_user();
$user_id = $current_user['id'];

echo "=== DRAFT ID DIAGNOSTIC ===\n\n";

// Get all drafts for current user
$sql = "SELECT
    pf.id,
    pf.form_number,
    pf.patient_name,
    pf.status,
    pf.created_by
FROM prehospital_forms pf
WHERE pf.created_by = ? AND pf.status = 'draft'
ORDER BY pf.updated_at DESC";

$stmt = db_query($sql, [$user_id]);
$drafts = $stmt->fetchAll();

echo "User ID: $user_id\n";
echo "Number of drafts found: " . count($drafts) . "\n\n";

foreach ($drafts as $index => $draft) {
    echo "Draft #" . ($index + 1) . ":\n";
    echo "  ID Value: " . var_export($draft['id'], true) . "\n";
    echo "  ID Type: " . gettype($draft['id']) . "\n";
    echo "  Form Number: " . var_export($draft['form_number'], true) . "\n";
    echo "  Patient Name: " . var_export($draft['patient_name'], true) . "\n";
    echo "  Status: " . var_export($draft['status'], true) . "\n";
    echo "  Created By: " . var_export($draft['created_by'], true) . "\n";
    echo "  \n";
    echo "  Raw dump:\n";
    var_dump($draft);
    echo "\n---\n\n";
}

// Also check the database directly
echo "\n=== DIRECT DATABASE QUERY ===\n\n";
$direct_sql = "SELECT id, form_number, patient_name FROM prehospital_forms WHERE created_by = ? AND status = 'draft' LIMIT 1";
$direct_stmt = db_query($direct_sql, [$user_id]);
$direct_result = $direct_stmt->fetch(PDO::FETCH_ASSOC);

echo "Direct fetch result:\n";
var_dump($direct_result);

echo "\n=== DELETE THIS FILE AFTER DEBUGGING ===\n";
