<?php
/**
 * Flutter Native Biometric Login API
 * Called by the Flutter app after native biometric auth succeeds.
 * Returns a short-lived signed token that the WebView redeems to create a session.
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Rate limiting
if (!check_ip_rate_limit('flutter_biometric', 10, 300)) {
    json_response(['success' => false, 'message' => 'Too many attempts. Please try again later.'], 429);
}

$username  = sanitize($_POST['username'] ?? '');
$timestamp = (int)($_POST['timestamp'] ?? 0);
$appKey    = $_POST['app_key'] ?? '';

if (empty($username) || empty($timestamp) || empty($appKey)) {
    json_response(['success' => false, 'message' => 'Missing required fields'], 400);
}

// Validate Flutter app key
if (!hash_equals(FLUTTER_APP_KEY, $appKey)) {
    error_log('Flutter biometric: invalid app key from IP ' . get_client_ip());
    json_response(['success' => false, 'message' => 'Unauthorized'], 403);
}

// Validate timestamp freshness (30-second window, timestamp in milliseconds)
$currentMs = (int)(microtime(true) * 1000);
if (abs($currentMs - $timestamp) > 30000) {
    json_response(['success' => false, 'message' => 'Request expired. Check device clock.'], 400);
}

// Look up user
$stmt = db_query(
    "SELECT id, username, role, status, is_restricted FROM users WHERE username = ? LIMIT 1",
    [$username]
);

if (!$stmt || $stmt->rowCount() === 0) {
    json_response(['success' => false, 'message' => 'Invalid credentials'], 401);
}

$user = $stmt->fetch();

if ($user['status'] !== 'active') {
    json_response(['success' => false, 'message' => 'Account is inactive. Contact administrator.'], 403);
}

if (!empty($user['is_restricted']) && $user['is_restricted'] == 1) {
    json_response(['success' => false, 'message' => 'Account has been restricted. Contact administrator.'], 403);
}

// Generate a short-lived signed one-time token (valid 30 seconds)
$tokenTime = time();
$tokenData = $user['id'] . ':' . $tokenTime;
$tokenSig  = hash_hmac('sha256', $tokenData, FLUTTER_APP_KEY);
$token     = base64_encode($tokenData . ':' . $tokenSig);

json_response([
    'success' => true,
    'token'   => $token,
]);
