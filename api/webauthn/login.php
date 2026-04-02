<?php
/**
 * WebAuthn Authentication Verification API
 * Verifies assertion response and logs user in
 * Does NOT require authenticated session (used on login page)
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/webauthn_helper.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Invalid security token'], 403);
}

// Rate limiting
if (!check_ip_rate_limit('webauthn_login', 10, 600)) {
    json_response(['success' => false, 'message' => 'Too many attempts. Please try again later.'], 429);
}

// Validate input
$clientDataJSON = $_POST['clientDataJSON'] ?? '';
$authenticatorData = $_POST['authenticatorData'] ?? '';
$signature = $_POST['signature'] ?? '';
$credentialId = $_POST['credentialId'] ?? '';

if (empty($clientDataJSON) || empty($authenticatorData) || empty($signature) || empty($credentialId)) {
    json_response(['success' => false, 'message' => 'Missing authentication data'], 400);
}

try {
    $result = webauthn_verify_login($clientDataJSON, $authenticatorData, $signature, $credentialId);

    if ($result['success']) {
        // Look up full user record for session setup
        $stmt = db_query(
            "SELECT id, username, role, status, is_restricted FROM users WHERE id = ? LIMIT 1",
            [$result['user_id']]
        );

        if (!$stmt || $stmt->rowCount() === 0) {
            json_response(['success' => false, 'message' => 'User not found'], 401);
        }

        $user = $stmt->fetch();

        // Check account status
        if (isset($user['is_restricted']) && $user['is_restricted'] == 1) {
            log_activity('restricted_login_attempt', "Restricted user '{$user['username']}' attempted WebAuthn login");
            json_response(['success' => false, 'message' => 'Your account has been restricted. Please contact the administrator.'], 403);
        }

        if ($user['status'] !== 'active') {
            json_response(['success' => false, 'message' => 'Account is inactive. Contact administrator.'], 403);
        }

        // Set up authenticated session
        setup_user_session($user, 'user_login_webauthn');

        // Determine redirect URL
        $redirect = ($user['role'] === 'admin') ? '../admin/dashboard.php' : '../public/dashboard.php';

        json_response([
            'success' => true,
            'message' => 'Authentication successful',
            'redirect' => $redirect
        ]);
    }

    json_response(['success' => false, 'message' => 'Authentication failed'], 401);
} catch (\Exception $e) {
    error_log('WebAuthn login error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Biometric authentication failed. Please try again or use your password.'], 401);
}
