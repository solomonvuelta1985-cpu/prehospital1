<?php
/**
 * WebAuthn Registration Options API
 * Returns challenge and options for navigator.credentials.create()
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

try {
    $user = get_auth_user();
    $options = webauthn_get_register_options(
        $user['id'],
        $user['username'],
        $user['full_name'] ?: $user['username']
    );

    json_response([
        'success' => true,
        'options' => $options
    ]);
} catch (\Exception $e) {
    error_log('WebAuthn register_options error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to generate registration options'], 500);
}
