<?php
/**
 * Autosave Draft API
 * Automatically saves form data as draft for recovery
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Security headers
header('Content-Type: application/json');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Require authentication
require_login();

// Get current user
$current_user = get_auth_user();
$user_id = $current_user['id'];

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid data format');
    }

    // Check if updating existing draft or creating new
    $draft_id = isset($data['draft_id']) && !empty($data['draft_id']) ? (int)$data['draft_id'] : null;

    // Prepare form data
    $form_data = [
        'form_date' => $data['form_date'] ?? null,
        'departure_time' => $data['departure_time'] ?? null,
        'arrival_time' => $data['arrival_time'] ?? null,
        'vehicle_used' => $data['vehicle_used'] ?? null,
        'vehicle_details' => $data['vehicle_details'] ?? null,
        'driver_name' => $data['driver'] ?? null,
        'arrival_scene_location' => $data['arrival_scene_location'] ?? null,
        'arrival_scene_time' => $data['arrival_scene_time'] ?? null,
        'departure_scene_location' => $data['departure_scene_location'] ?? null,
        'departure_scene_time' => $data['departure_scene_time'] ?? null,
        'arrival_hospital_name' => $data['arrival_hospital_name'] ?? null,
        'arrival_hospital_time' => $data['arrival_hospital_time'] ?? null,
        'departure_hospital_location' => $data['departure_hospital_location'] ?? null,
        'departure_hospital_time' => $data['departure_hospital_time'] ?? null,
        'arrival_station_time' => $data['arrival_station'] ?? null,
        'persons_present' => isset($data['persons_present']) ? json_encode($data['persons_present']) : null,

        // Patient Information
        'patient_name' => $data['patient_name'] ?? null,
        'date_of_birth' => $data['date_of_birth'] ?? null,
        'age' => isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null,
        'gender' => $data['gender'] ?? null,
        'civil_status' => $data['civil_status'] ?? null,
        'address' => $data['address'] ?? null,
        'zone' => $data['zone'] ?? null,
        'occupation' => $data['occupation'] ?? null,
        'place_of_incident' => $data['place_of_incident'] ?? null,
        'zone_landmark' => $data['zone_landmark'] ?? null,
        'incident_time' => $data['incident_time'] ?? null,
        'informant_name' => $data['informant_name'] ?? null,
        'informant_address' => $data['informant_address'] ?? null,
        'arrival_type' => $data['arrival_type'] ?? null,
        'call_arrival_time' => $data['call_arrival_time'] ?? null,
        'contact_number' => $data['contact_number'] ?? null,
        'relationship_victim' => $data['relationship_victim'] ?? null,
        'personal_belongings' => isset($data['personal_belongings']) ? json_encode($data['personal_belongings']) : null,
        'other_belongings' => $data['other_belongings'] ?? null,

        // Emergency Information
        'emergency_medical' => isset($data['emergency_type']) && in_array('medical', $data['emergency_type']) ? 1 : 0,
        'emergency_medical_details' => $data['medical_specify'] ?? null,
        'emergency_trauma' => isset($data['emergency_type']) && in_array('trauma', $data['emergency_type']) ? 1 : 0,
        'emergency_trauma_details' => $data['trauma_specify'] ?? null,
        'emergency_ob' => isset($data['emergency_type']) && in_array('ob', $data['emergency_type']) ? 1 : 0,
        'emergency_ob_details' => $data['ob_specify'] ?? null,
        'emergency_general' => isset($data['emergency_type']) && in_array('general', $data['emergency_type']) ? 1 : 0,
        'emergency_general_details' => $data['general_specify'] ?? null,
        'care_management' => isset($data['care_management']) ? json_encode($data['care_management']) : null,
        'oxygen_lpm' => $data['oxygen_lpm'] ?? null,
        'other_care' => $data['other_care'] ?? null,

        // Vitals
        'initial_time' => $data['initial_time'] ?? null,
        'initial_bp' => $data['initial_bp'] ?? null,
        'initial_temp' => isset($data['initial_temp']) && $data['initial_temp'] !== '' ? (float)$data['initial_temp'] : null,
        'initial_pulse' => isset($data['initial_pulse']) && $data['initial_pulse'] !== '' ? (int)$data['initial_pulse'] : null,
        'initial_resp_rate' => isset($data['initial_resp']) && $data['initial_resp'] !== '' ? (int)$data['initial_resp'] : null,
        'initial_pain_score' => isset($data['initial_pain_score']) && $data['initial_pain_score'] !== '' ? (int)$data['initial_pain_score'] : null,
        'initial_spo2' => isset($data['initial_spo2']) && $data['initial_spo2'] !== '' ? (int)$data['initial_spo2'] : null,
        'initial_spinal_injury' => $data['initial_spinal_injury'] ?? null,
        'initial_consciousness' => $data['initial_consciousness'] ?? null,
        'initial_helmet' => $data['initial_helmet'] ?? null,

        'followup_time' => $data['followup_time'] ?? null,
        'followup_bp' => $data['followup_bp'] ?? null,
        'followup_temp' => isset($data['followup_temp']) && $data['followup_temp'] !== '' ? (float)$data['followup_temp'] : null,
        'followup_pulse' => isset($data['followup_pulse']) && $data['followup_pulse'] !== '' ? (int)$data['followup_pulse'] : null,
        'followup_resp_rate' => isset($data['followup_resp']) && $data['followup_resp'] !== '' ? (int)$data['followup_resp'] : null,
        'followup_pain_score' => isset($data['followup_pain_score']) && $data['followup_pain_score'] !== '' ? (int)$data['followup_pain_score'] : null,
        'followup_spo2' => isset($data['followup_spo2']) && $data['followup_spo2'] !== '' ? (int)$data['followup_spo2'] : null,
        'followup_spinal_injury' => $data['followup_spinal_injury'] ?? null,
        'followup_consciousness' => $data['followup_consciousness'] ?? null,

        // Assessment
        'chief_complaints' => isset($data['chief_complaints']) ? json_encode($data['chief_complaints']) : null,
        'other_complaints' => $data['other_complaints'] ?? null,
        'fast_face_drooping' => $data['face_drooping'] ?? null,
        'fast_arm_weakness' => $data['arm_weakness'] ?? null,
        'fast_speech_difficulty' => $data['speech_difficulty'] ?? null,
        'fast_time_to_call' => $data['time_to_call'] ?? null,
        'fast_sample_details' => $data['sample_details'] ?? null,

        // OB Information
        'ob_baby_status' => $data['baby_status'] ?? null,
        'ob_delivery_time' => $data['delivery_time'] ?? null,
        'ob_placenta' => $data['placenta'] ?? null,
        'ob_lmp' => $data['lmp'] ?? null,
        'ob_aog' => $data['aog'] ?? null,
        'ob_edc' => $data['edc'] ?? null,

        // Team Information
        'team_leader_notes' => $data['team_leader_notes'] ?? null,
        'team_leader' => $data['team_leader'] ?? null,
        'data_recorder' => $data['data_recorder'] ?? null,
        'logistic' => $data['logistic'] ?? null,
        'first_aider' => $data['aider1'] ?? null,
        'second_aider' => $data['aider2'] ?? null,

        // Hospital Endorsement
        'endorsement' => $data['endorsement'] ?? null,
        'hospital_name' => $data['hospital_name'] ?? null,
        'endorsement_datetime' => $data['endorsement_datetime'] ?? null,

        'status' => 'draft',
        'created_by' => $user_id
    ];

    if ($draft_id) {
        // Update existing draft
        $check_sql = "SELECT id, form_number FROM prehospital_forms WHERE id = ? AND created_by = ? AND status = 'draft'";
        $check_stmt = db_query($check_sql, [$draft_id, $user_id]);

        $existing_draft = $check_stmt->fetch();
        if (!$existing_draft) {
            throw new Exception('Draft not found or unauthorized');
        }
        $form_number = $existing_draft['form_number'];

        // Build UPDATE query
        $update_fields = [];
        $update_values = [];
        foreach ($form_data as $key => $value) {
            if ($key !== 'created_by') {
                $update_fields[] = "$key = ?";
                $update_values[] = $value;
            }
        }
        $update_values[] = $draft_id;

        $update_sql = "UPDATE prehospital_forms SET " . implode(', ', $update_fields) . " WHERE id = ?";
        db_query($update_sql, $update_values);

        echo json_encode([
            'success' => true,
            'message' => 'Draft updated',
            'draft_id' => $draft_id,
            'form_number' => $form_number,
            'timestamp' => date('h:i A')
        ]);

    } else {
        // Create new draft
        // Generate form number
        $form_number = 'PHC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $form_data['form_number'] = $form_number;

        // Build INSERT query
        $fields = array_keys($form_data);
        $placeholders = array_fill(0, count($fields), '?');

        $insert_sql = "INSERT INTO prehospital_forms (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        // Execute the insert
        global $pdo;
        $stmt = $pdo->prepare($insert_sql);
        $result = $stmt->execute(array_values($form_data));

        if (!$result) {
            throw new Exception('Failed to insert draft: ' . json_encode($stmt->errorInfo()));
        }

        // Get the last inserted ID
        $draft_id = (int)$pdo->lastInsertId();

        // Log for debugging
        error_log("AUTOSAVE INSERT SUCCESS - Draft ID: {$draft_id}, Form Number: {$form_number}");

        if ($draft_id === 0) {
            error_log("ERROR: lastInsertId() returned 0 after INSERT!");
            throw new Exception('Failed to get draft ID after insert');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Draft saved',
            'draft_id' => $draft_id,
            'form_number' => $form_number,
            'timestamp' => date('h:i A')
        ]);
    }

} catch (Exception $e) {
    // Log the error to php_error.log
    error_log("━━━ AUTOSAVE ERROR ━━━");
    error_log("Message: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("━━━━━━━━━━━━━━━━━━━━━");

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ]);
}
