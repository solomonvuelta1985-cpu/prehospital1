<?php
/** Small local regression checks for the security hardening helpers. */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$failures = [];
$check = function ($condition, $message) use (&$failures) {
    if (!$condition) $failures[] = $message;
};

if (!empty(APP_ENCRYPTION_KEY)) {
    $encrypted = encrypt_field('confidential patient name');
    $check(strpos($encrypted, 'v2:') === 0, 'new encrypted fields must use the authenticated v2 format');
    $check(decrypt_field($encrypted) === 'confidential patient name', 'authenticated encryption must round-trip');
}

$stream = fopen('php://temp', 'w+');
secure_fputcsv($stream, ['=SUM(A1:A2)', 'normal']);
rewind($stream);
$csv = stream_get_contents($stream);
fclose($stream);
$check(strpos($csv, "'=SUM") === 0, 'CSV formula-like values must be neutralized');

$check(resolve_upload_path('uploads/../outside.txt') === false, 'upload path traversal must be rejected');
$check(session_version_available() === true, 'session revocation migration must be installed');

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}\n");
    exit(1);
}

echo "Security hardening tests passed.\n";
