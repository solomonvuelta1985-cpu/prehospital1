<?php
/**
 * Create the first administrator without shipping a known password.
 * Usage: php scripts/create_admin.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI-only script.\n");
}

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$username = trim((string)readline('Admin username: '));
$email = trim((string)readline('Admin email: '));
$full_name = trim((string)readline('Admin full name: '));
$password = (string)readline('Admin password: ');

if ($username === '' || $email === '' || $full_name === '' || $password === '') {
    fwrite(STDERR, "All fields are required.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "A valid email address is required.\n");
    exit(1);
}

$password_check = validate_password_strength($password);
if (!$password_check['valid']) {
    fwrite(STDERR, implode("\n", $password_check['errors']) . "\n");
    exit(1);
}

$existing = db_query('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $email]);
if ($existing && $existing->fetch()) {
    fwrite(STDERR, "A user with that username or email already exists.\n");
    exit(1);
}

$stmt = db_query(
    'INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, \'admin\', \'active\')',
    [$username, password_hash($password, PASSWORD_DEFAULT), $email, $full_name],
    true
);

fwrite(STDOUT, "Administrator created successfully (user id {$pdo->lastInsertId()}).\n");
