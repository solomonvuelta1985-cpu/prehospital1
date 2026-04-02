<?php
/**
 * WebAuthn Registration Verification API
 * Verifies attestation response and stores credential
 * Requires authenticated session
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/webauthn_helper.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Require authentication
require_login();

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Invalid security token'], 403);
}

// Validate input
$clientDataJSON = $_POST['clientDataJSON'] ?? '';
$attestationObject = $_POST['attestationObject'] ?? '';
$credentialName = sanitize($_POST['credential_name'] ?? '');

if (empty($clientDataJSON) || empty($attestationObject)) {
    json_response(['success' => false, 'message' => 'Missing registration data'], 400);
}

if (empty($credentialName)) {
    $credentialName = 'My Device';
}

try {
    $credential = webauthn_verify_registration($clientDataJSON, $attestationObject);

    // Store credential in database
    $sql = "INSERT INTO webauthn_credentials (user_id, credential_id, public_key, credential_name, sign_count, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    $result = db_query($sql, [
        $_SESSION['user_id'],
        $credential['credential_id'],
        $credential['public_key'],
        $credentialName,
        $credential['sign_count']
    ]);

    if ($result) {
        log_activity('webauthn_register', "Registered biometric credential: {$credentialName}");
        json_response([
            'success' => true,
            'message' => 'Biometric credential registered successfully'
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Failed to save credential'], 500);
    }
} catch (\Exception $e) {
    error_log('WebAuthn register error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Registration verification failed: ' . $e->getMessage()], 400);
}
