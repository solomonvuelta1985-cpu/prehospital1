<?php
/**
 * WebAuthn Credentials Management API
 * GET: List user's registered credentials
 * DELETE: Remove a credential
 * Requires authenticated session
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require authentication
require_login();

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List credentials
    $stmt = db_query(
        "SELECT id, credential_name, created_at, last_used_at FROM webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC",
        [$userId]
    );

    $credentials = [];
    if ($stmt) {
        while ($row = $stmt->fetch()) {
            $credentials[] = [
                'id' => $row['id'],
                'name' => $row['credential_name'],
                'created_at' => $row['created_at'],
                'last_used_at' => $row['last_used_at']
            ];
        }
    }

    json_response(['success' => true, 'credentials' => $credentials]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_method'] ?? '') === 'DELETE') {
    // Delete credential (using POST with _method=DELETE for browser compatibility)
    if (!verify_token($_POST['csrf_token'] ?? '')) {
        json_response(['success' => false, 'message' => 'Invalid security token'], 403);
    }

    $credentialId = intval($_POST['credential_id'] ?? 0);
    if ($credentialId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid credential ID'], 400);
    }

    // Get credential name for logging before deleting
    $stmt = db_query(
        "SELECT credential_name FROM webauthn_credentials WHERE id = ? AND user_id = ?",
        [$credentialId, $userId]
    );

    if (!$stmt || $stmt->rowCount() === 0) {
        json_response(['success' => false, 'message' => 'Credential not found'], 404);
    }

    $cred = $stmt->fetch();

    $result = db_query(
        "DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?",
        [$credentialId, $userId]
    );

    if ($result) {
        log_activity('webauthn_remove', "Removed biometric credential: {$cred['credential_name']}");
        json_response(['success' => true, 'message' => 'Credential removed successfully']);
    } else {
        json_response(['success' => false, 'message' => 'Failed to remove credential'], 500);
    }

} else {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}
