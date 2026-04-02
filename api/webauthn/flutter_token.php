<?php
/**
 * Flutter Native Biometric Token Redemption
 * The Flutter app navigates the WebView here after getting a token from flutter_login.php.
 * Validates the token, creates an authenticated session, and redirects to dashboard.
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    exit('Missing token');
}

// Decode token
$decoded = base64_decode($token, true);
if ($decoded === false) {
    http_response_code(400);
    exit('Invalid token');
}

// Token format: "user_id:timestamp:hmac_signature"
$parts = explode(':', $decoded, 3);
if (count($parts) !== 3) {
    http_response_code(400);
    exit('Invalid token format');
}

[$userId, $tokenTime, $tokenSig] = $parts;
$userId    = (int)$userId;
$tokenTime = (int)$tokenTime;

// Validate HMAC signature
$expectedSig = hash_hmac('sha256', $userId . ':' . $tokenTime, FLUTTER_APP_KEY);
if (!hash_equals($expectedSig, $tokenSig)) {
    error_log('Flutter token: invalid signature for user_id=' . $userId);
    http_response_code(403);
    exit('Invalid token');
}

// Validate token age (30 seconds max)
if (time() - $tokenTime > 30) {
    http_response_code(400);
    exit('Token has expired. Please try again.');
}

// Verify user still exists and is active
$stmt = db_query(
    "SELECT id, username, role, status, is_restricted FROM users WHERE id = ? LIMIT 1",
    [$userId]
);

if (!$stmt || $stmt->rowCount() === 0) {
    http_response_code(401);
    exit('User not found');
}

$user = $stmt->fetch();

if ($user['status'] !== 'active' || (!empty($user['is_restricted']) && $user['is_restricted'] == 1)) {
    http_response_code(403);
    exit('Account not accessible');
}

// Create authenticated session (sets cookies in the WebView's cookie jar)
setup_user_session($user, 'user_login_flutter');
log_activity('flutter_biometric_login', 'Flutter native biometric login: ' . $user['username']);

// Redirect to dashboard
header('Location: /prehospital/public/dashboard.php');
exit;
