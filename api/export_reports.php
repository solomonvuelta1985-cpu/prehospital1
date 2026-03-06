<?php
/**
 * Export Reports API Endpoint
 * Export filtered report data to CSV format
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

// Rate limiting for exports
if (!check_rate_limit('export', 5, 300)) {
    http_response_code(429);
    die('Too many export requests. Please try again in 5 minutes.');
}

// Get current user
$current_user = get_auth_user();
$user_id = $current_user['id'];
$is_admin = is_admin();

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build WHERE clause
$where = ["1=1"];
$params = [];

if ($date_from) {
    $where[] = "DATE(pf.form_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where[] = "DATE(pf.form_date) <= ?";
    $params[] = $date_to;
}

if ($status_filter !== 'all') {
    $where[] = "pf.status = ?";
    $params[] = $status_filter;
}

if ($user_filter > 0) {
    $where[] = "pf.created_by = ?";
    $params[] = $user_filter;
} elseif (!$is_admin) {
    // Non-admin users only see their own data
    $where[] = "pf.created_by = ?";
    $params[] = $user_id;
}

$where_clause = implode(' AND ', $where);

// Get detailed report data
$sql = "
    SELECT
        pf.form_number,
        pf.form_date,
        pf.departure_time,
        pf.patient_name,
        pf.age,
        pf.gender,
        pf.place_of_incident,
        pf.vehicle_used,
        pf.arrival_hospital_name,
        pf.arrival_hospital_time,
        pf.status,
        CASE
            WHEN pf.emergency_medical = 1 THEN 'Medical'
            WHEN pf.emergency_trauma = 1 THEN 'Trauma'
            WHEN pf.emergency_ob = 1 THEN 'Obstetric'
            WHEN pf.emergency_general = 1 THEN 'General'
            ELSE 'Not Specified'
        END as emergency_type,
        pf.initial_bp,
        pf.initial_temp,
        pf.initial_pulse,
        pf.initial_resp_rate,
        pf.initial_spo2,
        u.full_name as created_by_name,
        pf.created_at,
        (SELECT COUNT(*) FROM injuries WHERE form_id = pf.id) as injury_count
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    WHERE $where_clause
    ORDER BY pf.form_date DESC, pf.departure_time DESC
";

$stmt = db_query($sql, $params);
if (!$stmt) {
    http_response_code(500);
    die('Database query failed. Please try again.');
}
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="prehospital_report_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
$headers = [
    'Form Number',
    'Form Date',
    'Departure Time',
    'Patient Name',
    'Age',
    'Gender',
    'Place of Incident',
    'Vehicle Used',
    'Destination Hospital',
    'Arrival Time',
    'Status',
    'Emergency Type',
    'Initial BP',
    'Initial Temp (°C)',
    'Initial Pulse',
    'Initial Resp Rate',
    'Initial SpO2',
    'Created By',
    'Date Created',
    'Injury Count'
];
fputcsv($output, $headers);

// Write data rows
foreach ($records as $record) {
    $row = [
        $record['form_number'],
        $record['form_date'],
        $record['departure_time'],
        decrypt_field($record['patient_name'] ?? ''),
        $record['age'],
        ucfirst($record['gender'] ?? ''),
        $record['place_of_incident'],
        $record['vehicle_used'],
        $record['arrival_hospital_name'],
        $record['arrival_hospital_time'],
        ucfirst($record['status']),
        $record['emergency_type'],
        $record['initial_bp'],
        $record['initial_temp'],
        $record['initial_pulse'],
        $record['initial_resp_rate'],
        $record['initial_spo2'],
        $record['created_by_name'],
        $record['created_at'],
        $record['injury_count']
    ];
    fputcsv($output, $row);
}

// Add summary statistics at the bottom
fputcsv($output, []); // Empty row
fputcsv($output, ['SUMMARY STATISTICS']);
fputcsv($output, ['Total Records', count($records)]);

// Calculate summary stats
$total = count($records);
$completed = count(array_filter($records, fn($r) => $r['status'] === 'completed'));
$draft = count(array_filter($records, fn($r) => $r['status'] === 'draft'));
$archived = count(array_filter($records, fn($r) => $r['status'] === 'archived'));

fputcsv($output, ['Completed Forms', $completed]);
fputcsv($output, ['Draft Forms', $draft]);
fputcsv($output, ['Archived Forms', $archived]);

fputcsv($output, []); // Empty row
fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
fputcsv($output, ['Generated by', $current_user['full_name'] . ' (' . $current_user['username'] . ')']);
fputcsv($output, ['Date Range', $date_from . ' to ' . $date_to]);

// Close output stream
fclose($output);

// Log activity
log_activity('report_export', 'Exported report with ' . $total . ' records (CSV)');

exit;
