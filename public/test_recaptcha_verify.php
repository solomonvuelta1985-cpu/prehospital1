<?php
/**
 * Test reCAPTCHA Server-Side Verification
 * DELETE THIS FILE after debugging!
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: text/plain');

echo "=== reCAPTCHA Server Verification Test ===\n\n";

// Test 1: Check if cURL is available
echo "1. cURL Extension: " . (function_exists('curl_init') ? 'Installed' : 'NOT INSTALLED') . "\n\n";

// Test 2: Try to verify with a fake response (should fail but show us the error)
echo "2. Testing verification with fake response:\n";

$secret_key = RECAPTCHA_SECRET_KEY;
$url = 'https://www.google.com/recaptcha/api/siteverify';
$data = [
    'secret' => $secret_key,
    'response' => 'fake_response_for_testing',
    'remoteip' => '127.0.0.1'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

echo "   HTTP Code: $http_code\n";
echo "   cURL Error Number: $curl_errno\n";
echo "   cURL Error Message: " . ($curl_error ?: 'None') . "\n";
echo "   Response: $result\n\n";

if ($result) {
    $decoded = json_decode($result, true);
    echo "3. Decoded Response:\n";
    print_r($decoded);
    echo "\n";
} else {
    echo "3. FAILED to get response from Google reCAPTCHA!\n";
    echo "   This means your server cannot connect to Google's servers.\n";
    echo "   Possible causes:\n";
    echo "   - Firewall blocking outgoing HTTPS connections\n";
    echo "   - cURL SSL certificate issues\n";
    echo "   - Server has no internet access\n\n";
}

echo "=== DELETE THIS FILE AFTER DEBUGGING ===\n";
