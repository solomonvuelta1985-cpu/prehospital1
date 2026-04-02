<?php
/**
 * WebAuthn Authentication Options API
 * Returns challenge and options for navigator.credentials.get()
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

$username = sanitize($_POST['username'] ?? '');
if (empty($username)) {
    json_response(['success' => false, 'message' => 'Username is required'], 400);
}

try {
    $options = webauthn_get_login_options($username);

    if ($options === null) {
        // Generic message - don't reveal whether user exists or has credentials
        json_response(['success' => false, 'message' => 'No biometric credentials found. Please log in with your password and register your device in Biometric Settings.'], 404);
    }

    json_response([
        'success' => true,
        'options' => $options
    ]);
} catch (\Exception $e) {
    error_log('WebAuthn login_options error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Failed to generate authentication options'], 500);
}
