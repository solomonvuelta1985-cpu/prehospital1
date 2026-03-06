<?php
/**
 * Secure File Serving Script
 * Serves uploaded files with authentication and security checks
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

// Rate limiting for file downloads
if (!check_rate_limit('file_download', 30, 60)) {
    http_response_code(429);
    die('Too many requests. Please try again later.');
}

// Get file path from query parameter
$filePath = $_GET['file'] ?? '';

if (empty($filePath)) {
    http_response_code(400);
    die('Invalid file path');
}

// Security: Ensure file path is within uploads_private directory
$realPath = realpath($filePath);
$uploadDir = realpath(UPLOAD_DIR);

if (!$realPath || !$uploadDir || strpos($realPath, $uploadDir) !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Check if file exists
if (!file_exists($realPath)) {
    http_response_code(404);
    die('File not found');
}

// Get file info
$fileSize = filesize($realPath);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realPath);
finfo_close($finfo);

// Set headers for secure serving
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Log file access
log_activity('file_access', 'Accessed file: ' . basename($realPath));

// Output file content
readfile($realPath);
exit();
