<?php
/**
 * Export Single Record as PDF
 * Uses DOMPDF to generate PDF from HTML template
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Require authentication
require_login();

// Rate limiting
if (!check_rate_limit('export_pdf', 10, 300)) {
    http_response_code(429);
    die('Too many export requests. Please try again later.');
}

// Get record ID
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($record_id <= 0) {
    http_response_code(400);
    die('Invalid record ID');
}

// Ownership check
if (!can_access_record($record_id)) {
    http_response_code(403);
    die('Access denied');
}

try {
    // Load DOMPDF
    require_once __DIR__ . '/../vendor/autoload.php';

    // Get record
    $sql = "SELECT pf.*, u.full_name as creator_name FROM prehospital_forms pf LEFT JOIN users u ON pf.created_by = u.id WHERE pf.id = ?";
    $stmt = db_query($sql, [$record_id]);
    if (!$stmt) {
        http_response_code(500);
        die('Database error');
    }
    $record = $stmt->fetch();

    if (!$record) {
        http_response_code(404);
        die('Record not found');
    }

    // Decrypt sensitive fields
    decrypt_record_fields($record);

    // Get injuries
    $injury_sql = "SELECT * FROM injuries WHERE form_id = ? ORDER BY injury_number";
    $injury_stmt = db_query($injury_sql, [$record_id]);
    $injuries = $injury_stmt ? $injury_stmt->fetchAll() : [];

    $creator_name = $record['creator_name'] ?? 'Unknown';

    // Render HTML template
    ob_start();
    include __DIR__ . '/../includes/pdf_templates/record_template.php';
    $html = ob_get_clean();

    // Generate PDF
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Log activity
    log_activity('pdf_export', "Exported PDF for form: " . ($record['form_number'] ?? $record_id));

    // Output PDF
    $filename = 'PCR_' . ($record['form_number'] ?? $record_id) . '_' . date('Ymd') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);

} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating PDF. Please try again.');
}
