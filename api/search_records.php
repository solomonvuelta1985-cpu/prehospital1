<?php
/**
 * AJAX Search Records API
 * Returns matching records as JSON for instant search
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

header('Content-Type: application/json');

$current_user = get_auth_user();
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (strlen($search) < 1) {
    echo json_encode(['success' => false, 'message' => 'Search term required']);
    exit;
}

try {
    $where_conditions = [];
    $params = [];

    // Users can only see their own records (admins see all)
    if (!is_admin()) {
        $where_conditions[] = "pf.created_by = ?";
        $params[] = $current_user['id'];
    }

    // Search by patient_name, form_number, or place_of_incident
    $search_param = "%" . $search . "%";
    $where_conditions[] = "(pf.patient_name LIKE ? OR pf.form_number LIKE ? OR pf.place_of_incident LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;

    $where_sql = implode(' AND ', $where_conditions);

    $sql = "SELECT pf.*, u.full_name as created_by_name
            FROM prehospital_forms pf
            LEFT JOIN users u ON pf.created_by = u.id
            WHERE {$where_sql}
            ORDER BY pf.created_at DESC
            LIMIT 50";

    $stmt = db_query($sql, $params);
    $records = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $results = [];
    foreach ($records as $index => $record) {
        // Build emergency types
        $emergencyTypes = [];
        if (!empty($record['emergency_medical'])) {
            $detail = !empty($record['emergency_medical_details']) ? ' - ' . $record['emergency_medical_details'] : '';
            $emergencyTypes[] = 'Medical' . $detail;
        }
        if (!empty($record['emergency_trauma'])) {
            $detail = !empty($record['emergency_trauma_details']) ? ' - ' . $record['emergency_trauma_details'] : '';
            $emergencyTypes[] = 'Trauma' . $detail;
        }
        if (!empty($record['emergency_ob'])) {
            $detail = !empty($record['emergency_ob_details']) ? ' - ' . $record['emergency_ob_details'] : '';
            $emergencyTypes[] = 'OB' . $detail;
        }
        if (!empty($record['emergency_general'])) {
            $detail = !empty($record['emergency_general_details']) ? ' - ' . $record['emergency_general_details'] : '';
            $emergencyTypes[] = 'General' . $detail;
        }

        // Build care management
        $careItems = [];
        if (!empty($record['care_management'])) {
            $decoded = json_decode($record['care_management'], true);
            if (is_array($decoded)) {
                $careItems = array_map('ucfirst', $decoded);
            }
        }
        if (!empty($record['other_care'])) {
            $careItems[] = htmlspecialchars($record['other_care']);
        }

        $results[] = [
            'id' => (int)$record['id'],
            'form_number' => $record['form_number'] ?? '',
            'form_date' => $record['form_date'] ?? '',
            'patient_name' => $record['patient_name'] ?? '',
            'age' => $record['age'] ?? '',
            'gender' => $record['gender'] ?? '',
            'place_of_incident' => $record['place_of_incident'] ?? '',
            'arrival_hospital_name' => $record['arrival_hospital_name'] ?? '',
            'vehicle_used' => $record['vehicle_used'] ?? '',
            'created_by_name' => $record['created_by_name'] ?? 'Unknown',
            'status' => $record['status'] ?? 'draft',
            'care_management' => $record['care_management'] ?? '',
            'other_care' => $record['other_care'] ?? '',
            'emergency_medical' => (int)($record['emergency_medical'] ?? 0),
            'emergency_medical_details' => $record['emergency_medical_details'] ?? '',
            'emergency_trauma' => (int)($record['emergency_trauma'] ?? 0),
            'emergency_trauma_details' => $record['emergency_trauma_details'] ?? '',
            'emergency_ob' => (int)($record['emergency_ob'] ?? 0),
            'emergency_ob_details' => $record['emergency_ob_details'] ?? '',
            'emergency_general' => (int)($record['emergency_general'] ?? 0),
            'emergency_general_details' => $record['emergency_general_details'] ?? '',
            'time_ago' => time_ago($record['updated_at'] ?? $record['created_at']),
        ];
    }

    echo json_encode([
        'success' => true,
        'records' => $results,
        'total' => count($results)
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Search failed']);
}
