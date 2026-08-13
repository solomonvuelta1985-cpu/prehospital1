<?php
/**
 * Export Pre-Hospital Care Records to CSV
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Require authentication
require_login();

// Rate limiting for exports
if (!check_rate_limit('export', 5, 300)) {
    http_response_code(429);
    die('Too many export requests. Please try again in 5 minutes.');
}

// Get filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$month_filter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$emergency_filter = isset($_GET['emergency']) ? sanitize($_GET['emergency']) : '';
$vehicle_filter = isset($_GET['vehicle']) ? sanitize($_GET['vehicle']) : '';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Validate sort option
$allowed_sorts = [
    'newest' => 'created_at DESC',
    'oldest' => 'created_at ASC',
    'date_desc' => 'form_date DESC',
    'date_asc' => 'form_date ASC',
    'name_asc' => 'patient_name ASC',
    'name_desc' => 'patient_name DESC',
];
$order_sql = $allowed_sorts[$sort_by] ?? 'created_at DESC';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(form_number LIKE ? OR patient_name LIKE ? OR place_of_incident LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "form_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "form_date <= ?";
    $params[] = $date_to;
}

if ($month_filter >= 1 && $month_filter <= 12) {
    $where_conditions[] = "MONTH(form_date) = ?";
    $params[] = $month_filter;
}

if ($year_filter >= 2000 && $year_filter <= 2099) {
    $where_conditions[] = "YEAR(form_date) = ?";
    $params[] = $year_filter;
}

if (!empty($emergency_filter)) {
    $valid_emergency = ['medical', 'trauma', 'ob', 'general'];
    if (in_array($emergency_filter, $valid_emergency)) {
        $where_conditions[] = "emergency_{$emergency_filter} = 1";
    }
}

if (!empty($vehicle_filter)) {
    $where_conditions[] = "vehicle_used = ?";
    $params[] = $vehicle_filter;
}

// Non-admin users can only export their own records
if (!is_admin()) {
    $where_conditions[] = "created_by = ?";
    $params[] = $_SESSION['user_id'];
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get records
$sql = "SELECT
    form_number, form_date, patient_name, age, age_unit, growth_status, gender, civil_status,
    address, occupation, place_of_incident, incident_time,
    vehicle_used, driver_name, arrival_hospital_name,
    emergency_medical, emergency_medical_details,
    emergency_trauma, emergency_trauma_details,
    emergency_ob, emergency_ob_details,
    emergency_general, emergency_general_details,
    care_management,
    initial_bp, initial_temp, initial_pulse, initial_spo2, initial_consciousness, initial_helmet,
    team_leader, narrative_report, status, created_at
    FROM prehospital_forms
    $where_sql
    ORDER BY $order_sql";

$stmt = db_query($sql, $params);
$records = ($stmt) ? $stmt->fetchAll() : [];

// Set headers for CSV download
$filename = 'prehospital_records_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
secure_fputcsv($output, [
    'Form Number',
    'Form Date',
    'Patient Name',
    'Age',
    'Age Unit',
    'Growth Status',
    'Gender',
    'Civil Status',
    'Address',
    'Occupation',
    'Type of Emergency Call',
    'Incident Time',
    'Vehicle Used',
    'Driver',
    'Care Management',
    'Initial BP',
    'Initial Temp',
    'Initial Pulse',
    'Initial SPO2',
    'Consciousness',
    'Helmet Status',
    'Team Leader',
    'Narrative Report',
    'Status',
    'Created At'
]);

// Add data rows
foreach ($records as $record) {
    // Build emergency type string with details
    $emergencyTypes = [];
    if ($record['emergency_medical']) {
        $detail = $record['emergency_medical_details'] ? ' - ' . $record['emergency_medical_details'] : '';
        $emergencyTypes[] = 'Medical' . $detail;
    }
    if ($record['emergency_trauma']) {
        $detail = $record['emergency_trauma_details'] ? ' - ' . $record['emergency_trauma_details'] : '';
        $emergencyTypes[] = 'Trauma' . $detail;
    }
    if ($record['emergency_ob']) {
        $detail = $record['emergency_ob_details'] ? ' - ' . $record['emergency_ob_details'] : '';
        $emergencyTypes[] = 'OB' . $detail;
    }
    if ($record['emergency_general']) {
        $detail = $record['emergency_general_details'] ? ' - ' . $record['emergency_general_details'] : '';
        $emergencyTypes[] = 'General' . $detail;
    }
    $emergencyTypeStr = $emergencyTypes ? implode('; ', $emergencyTypes) : '';

    // Build care management string
    $careItems = [];
    if ($record['care_management']) {
        $decoded = json_decode($record['care_management'], true);
        if (is_array($decoded)) {
            $careItems = array_map('ucfirst', $decoded);
        }
    }
    $careManagementStr = $careItems ? implode(', ', $careItems) : '';

    // Build consciousness string
    $consciousnessStr = '';
    if (!empty($record['initial_consciousness'])) {
        $decoded = json_decode($record['initial_consciousness'], true);
        if (is_array($decoded)) {
            $consciousnessStr = implode(', ', array_map('ucfirst', $decoded));
        }
    }

    // Build helmet string — decode_helmet() handles both JSON and legacy formats.
    $helmetStr = implode(', ', array_map('helmet_label', decode_helmet($record['initial_helmet'] ?? '')));

    secure_fputcsv($output, [
        $record['form_number'],
        $record['form_date'],
        $record['patient_name'],
        $record['age'],
        ucfirst($record['age_unit'] ?? 'years'),
        ucfirst($record['growth_status'] ?? ''),
        ucfirst($record['gender']),
        ucfirst($record['civil_status'] ?: ''),
        $record['address'],
        $record['occupation'],
        $emergencyTypeStr,
        $record['incident_time'],
        ucfirst($record['vehicle_used'] ?: ''),
        $record['driver_name'],
        $careManagementStr,
        $record['initial_bp'],
        $record['initial_temp'],
        $record['initial_pulse'],
        $record['initial_spo2'],
        $consciousnessStr,
        $helmetStr,
        $record['team_leader'],
        $record['narrative_report'],
        ucfirst($record['status']),
        $record['created_at']
    ]);
}

fclose($output);
exit;
