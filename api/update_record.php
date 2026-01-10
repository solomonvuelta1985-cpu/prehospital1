<?php
/**
 * Update Pre-Hospital Care Record
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Invalid request method', 'error');
    redirect('../public/records.php');
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    set_flash('Security token validation failed', 'error');
    redirect('../public/records.php');
}

try {
    $record_id = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
    
    if ($record_id <= 0) {
        throw new Exception('Invalid record ID');
    }
    
    // Check if record exists
    $check_sql = "SELECT id, form_number FROM prehospital_forms WHERE id = ?";
    $check_stmt = db_query($check_sql, [$record_id]);
    $existing_record = $check_stmt->fetch();
    
    if (!$existing_record) {
        throw new Exception('Record not found');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Sanitize and validate inputs
    $form_date = sanitize($_POST['form_date'] ?? '');
    if (!validate_date($form_date)) {
        throw new Exception('Invalid form date');
    }
    
    // Basic Information
    $departure_time = !empty($_POST['departure_time']) ? $_POST['departure_time'] : null;
    $arrival_time = !empty($_POST['arrival_time']) ? $_POST['arrival_time'] : null;

    // Strip seconds from time if present (HTML5 time input may include seconds)
    // Do this BEFORE sanitization to preserve the time format
    if ($departure_time && strlen(trim($departure_time)) === 8 && substr_count($departure_time, ':') === 2) {
        $departure_time = substr(trim($departure_time), 0, 5); // Convert HH:MM:SS to HH:MM
    }
    if ($arrival_time && strlen(trim($arrival_time)) === 8 && substr_count($arrival_time, ':') === 2) {
        $arrival_time = substr(trim($arrival_time), 0, 5); // Convert HH:MM:SS to HH:MM
    }

    // Now sanitize after format conversion
    $departure_time = $departure_time ? sanitize($departure_time) : null;
    $arrival_time = $arrival_time ? sanitize($arrival_time) : null;

    $vehicle_used = !empty($_POST['vehicle_used']) ? sanitize($_POST['vehicle_used']) : null;
    $vehicle_details = !empty($_POST['vehicle_details']) ? sanitize($_POST['vehicle_details']) : null;
    $driver_name = !empty($_POST['driver_name']) ? sanitize($_POST['driver_name']) : null;
    $arrival_station = !empty($_POST['arrival_station']) ? sanitize($_POST['arrival_station']) : null;

    // Scene Information
    $arrival_scene_location = !empty($_POST['arrival_scene_location']) ? sanitize($_POST['arrival_scene_location']) : null;
    $arrival_scene_time = !empty($_POST['arrival_scene_time']) ? sanitize($_POST['arrival_scene_time']) : null;
    $departure_scene_location = !empty($_POST['departure_scene_location']) ? sanitize($_POST['departure_scene_location']) : null;
    $departure_scene_time = !empty($_POST['departure_scene_time']) ? sanitize($_POST['departure_scene_time']) : null;

    // Hospital Information
    $arrival_hospital_name = !empty($_POST['arrival_hospital_name']) ? sanitize($_POST['arrival_hospital_name']) : null;
    $arrival_hospital_time = !empty($_POST['arrival_hospital_time']) ? sanitize($_POST['arrival_hospital_time']) : null;
    $departure_hospital_location = !empty($_POST['departure_hospital_location']) ? sanitize($_POST['departure_hospital_location']) : null;
    $departure_hospital_time = !empty($_POST['departure_hospital_time']) ? sanitize($_POST['departure_hospital_time']) : null;

    // Persons Present
    $persons_present = isset($_POST['persons_present']) ? $_POST['persons_present'] : [];
    if (!is_array($persons_present)) {
        $persons_present = [$persons_present];
    }
    $persons_present = array_map('sanitize', $persons_present);
    $persons_present_json = json_encode($persons_present);

    // Patient Information (REQUIRED)
    $patient_name = sanitize($_POST['patient_name'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = sanitize($_POST['gender'] ?? '');
    $civil_status = !empty($_POST['civil_status']) ? sanitize($_POST['civil_status']) : null;

    if (empty($patient_name) || empty($date_of_birth) || $age <= 0 || empty($gender)) {
        throw new Exception('Patient information is required');
    }

    if (!validate_date($date_of_birth)) {
        throw new Exception('Invalid date of birth');
    }

    if (!in_array($gender, ['male', 'female'])) {
        throw new Exception('Invalid gender value');
    }

    $address = !empty($_POST['address']) ? sanitize($_POST['address']) : null;
    $zone = !empty($_POST['zone']) ? sanitize($_POST['zone']) : null;
    $occupation = !empty($_POST['occupation']) ? sanitize($_POST['occupation']) : null;
    $place_of_incident = !empty($_POST['place_of_incident']) ? sanitize($_POST['place_of_incident']) : null;
    $zone_landmark = !empty($_POST['zone_landmark']) ? sanitize($_POST['zone_landmark']) : null;
    $incident_time = !empty($_POST['incident_time']) ? sanitize($_POST['incident_time']) : null;

    // Informant Details
    $informant_name = !empty($_POST['informant_name']) ? sanitize($_POST['informant_name']) : null;
    $informant_address = !empty($_POST['informant_address']) ? sanitize($_POST['informant_address']) : null;
    $arrival_type = !empty($_POST['arrival_type']) ? sanitize($_POST['arrival_type']) : null;
    $call_arrival_time = !empty($_POST['call_arrival_time']) ? sanitize($_POST['call_arrival_time']) : null;
    $contact_number = !empty($_POST['contact_number']) ? sanitize($_POST['contact_number']) : null;
    $relationship_victim = !empty($_POST['relationship_victim']) ? sanitize($_POST['relationship_victim']) : null;

    // Personal Belongings
    $personal_belongings = isset($_POST['personal_belongings']) ? $_POST['personal_belongings'] : [];
    if (!is_array($personal_belongings)) {
        $personal_belongings = [$personal_belongings];
    }
    $personal_belongings = array_map('sanitize', $personal_belongings);
    $personal_belongings_json = json_encode($personal_belongings);
    $other_belongings = !empty($_POST['other_belongings']) ? sanitize($_POST['other_belongings']) : null;

    // Handle file upload security - Patient Documentation
    // Get existing patient documentation from database first
    $existing_patient_doc_sql = "SELECT patient_documentation FROM prehospital_forms WHERE id = ?";
    $existing_patient_doc_stmt = db_query($existing_patient_doc_sql, [$record_id]);
    $existing_patient_doc_row = $existing_patient_doc_stmt->fetch();
    $patient_documentation_path = $existing_patient_doc_row['patient_documentation'] ?? null;

    // Only process upload if a new file is provided
    if (isset($_FILES['patient_documentation']) && $_FILES['patient_documentation']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['patient_documentation'];

        // Security checks
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Patient documentation upload error: ' . $file['error']);
        }

        // Check file size (max 5MB)
        $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $maxFileSize) {
            throw new Exception('Patient documentation file size exceeds 5MB limit');
        }

        // Validate MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMimeType = mime_content_type($file['tmp_name']);
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            throw new Exception('Invalid patient documentation file type. Only JPG, PNG, GIF, and WebP are allowed');
        }

        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid patient documentation file extension');
        }

        // Additional check: verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Uploaded file is not a valid image');
        }

        // Generate safe filename with metadata
        $timestamp = date('YmdHis');
        $uniqueId = bin2hex(random_bytes(8));
        $safeFileName = 'patient_' . $timestamp . '_' . $uniqueId . '.' . $fileExtension;

        // Create uploads directory structure with date-based organization
        $dateFolder = date('Y-m-d');
        $uploadDir = dirname(__DIR__) . '/public/uploads/patient_docs/' . $dateFolder;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create patient documentation upload directory');
            }
        }

        // Move file to uploads directory
        $targetPath = $uploadDir . '/' . $safeFileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save patient documentation file');
        }

        // Store relative path for database (accessible via web)
        $patient_documentation_path = 'uploads/patient_docs/' . $dateFolder . '/' . $safeFileName;
    }

    // Emergency Call Types
    $emergency_medical = isset($_POST['emergency_type']) && in_array('medical', $_POST['emergency_type']) ? 1 : 0;
    $emergency_medical_details = !empty($_POST['medical_specify']) ? sanitize($_POST['medical_specify']) : null;
    $emergency_trauma = isset($_POST['emergency_type']) && in_array('trauma', $_POST['emergency_type']) ? 1 : 0;
    $emergency_trauma_details = !empty($_POST['trauma_specify']) ? sanitize($_POST['trauma_specify']) : null;
    $emergency_ob = isset($_POST['emergency_type']) && in_array('ob', $_POST['emergency_type']) ? 1 : 0;
    $emergency_ob_details = !empty($_POST['ob_specify']) ? sanitize($_POST['ob_specify']) : null;
    $emergency_general = isset($_POST['emergency_type']) && in_array('general', $_POST['emergency_type']) ? 1 : 0;
    $emergency_general_details = !empty($_POST['general_specify']) ? sanitize($_POST['general_specify']) : null;

    // Care Management
    $care_management = isset($_POST['care_management']) ? $_POST['care_management'] : [];
    if (!is_array($care_management)) {
        $care_management = [$care_management];
    }
    $care_management = array_map('sanitize', $care_management);
    $care_management_json = json_encode($care_management);
    $oxygen_lpm = !empty($_POST['oxygen_lpm']) ? sanitize($_POST['oxygen_lpm']) : null;
    $other_care = !empty($_POST['other_care']) ? sanitize($_POST['other_care']) : null;

    // Initial Vitals - Handle empty values properly
    $initial_time = !empty($_POST['initial_time']) ? sanitize($_POST['initial_time']) : null;
    $initial_bp = !empty($_POST['initial_bp']) ? sanitize($_POST['initial_bp']) : null;
    $initial_temp = (!empty($_POST['initial_temp']) && $_POST['initial_temp'] !== '') ? (float)$_POST['initial_temp'] : null;
    $initial_pulse = (!empty($_POST['initial_pulse']) && $_POST['initial_pulse'] !== '') ? (int)$_POST['initial_pulse'] : null;
    $initial_resp_rate = (!empty($_POST['initial_resp']) && $_POST['initial_resp'] !== '') ? (int)$_POST['initial_resp'] : null;
    $initial_pain_score = (!empty($_POST['initial_pain_score']) && $_POST['initial_pain_score'] !== '') ? (int)$_POST['initial_pain_score'] : null;
    $initial_spo2 = (!empty($_POST['initial_spo2']) && $_POST['initial_spo2'] !== '') ? (int)$_POST['initial_spo2'] : null;
    $initial_spinal_injury = !empty($_POST['initial_spinal_injury']) ? sanitize($_POST['initial_spinal_injury']) : null;
    $initial_consciousness = !empty($_POST['initial_consciousness']) ? sanitize($_POST['initial_consciousness']) : null;
    $initial_helmet = !empty($_POST['initial_helmet']) ? sanitize($_POST['initial_helmet']) : null;

    // Follow-up Vitals
    $followup_time = !empty($_POST['followup_time']) ? sanitize($_POST['followup_time']) : null;
    $followup_bp = !empty($_POST['followup_bp']) ? sanitize($_POST['followup_bp']) : null;
    $followup_temp = (!empty($_POST['followup_temp']) && $_POST['followup_temp'] !== '') ? (float)$_POST['followup_temp'] : null;
    $followup_pulse = (!empty($_POST['followup_pulse']) && $_POST['followup_pulse'] !== '') ? (int)$_POST['followup_pulse'] : null;
    $followup_resp_rate = (!empty($_POST['followup_resp']) && $_POST['followup_resp'] !== '') ? (int)$_POST['followup_resp'] : null;
    $followup_pain_score = (!empty($_POST['followup_pain_score']) && $_POST['followup_pain_score'] !== '') ? (int)$_POST['followup_pain_score'] : null;
    $followup_spo2 = (!empty($_POST['followup_spo2']) && $_POST['followup_spo2'] !== '') ? (int)$_POST['followup_spo2'] : null;
    $followup_spinal_injury = !empty($_POST['followup_spinal_injury']) ? sanitize($_POST['followup_spinal_injury']) : null;
    $followup_consciousness = !empty($_POST['followup_consciousness']) ? sanitize($_POST['followup_consciousness']) : null;

    // Chief Complaints
    $chief_complaints = isset($_POST['chief_complaints']) ? $_POST['chief_complaints'] : [];
    if (!is_array($chief_complaints)) {
        $chief_complaints = [$chief_complaints];
    }
    $chief_complaints = array_map('sanitize', $chief_complaints);
    $chief_complaints_json = json_encode($chief_complaints);
    $other_complaints = !empty($_POST['other_complaints']) ? sanitize($_POST['other_complaints']) : null;

    // FAST Assessment
    $fast_face_drooping = !empty($_POST['face_drooping']) ? sanitize($_POST['face_drooping']) : null;
    $fast_arm_weakness = !empty($_POST['arm_weakness']) ? sanitize($_POST['arm_weakness']) : null;
    $fast_speech_difficulty = !empty($_POST['speech_difficulty']) ? sanitize($_POST['speech_difficulty']) : null;
    $fast_time_to_call = !empty($_POST['time_to_call']) ? sanitize($_POST['time_to_call']) : null;
    $fast_sample_details = !empty($_POST['sample_details']) ? sanitize($_POST['sample_details']) : null;

    // OB Information
    $ob_baby_status = !empty($_POST['baby_status']) ? sanitize($_POST['baby_status']) : null;
    $ob_delivery_time = !empty($_POST['delivery_time']) ? sanitize($_POST['delivery_time']) : null;
    $ob_placenta = !empty($_POST['placenta']) ? sanitize($_POST['placenta']) : null;
    $ob_lmp = !empty($_POST['lmp']) ? sanitize($_POST['lmp']) : null;
    $ob_aog = !empty($_POST['aog']) ? sanitize($_POST['aog']) : null;
    $ob_edc = !empty($_POST['edc']) ? sanitize($_POST['edc']) : null;

    // Team Information
    $team_leader_notes = !empty($_POST['team_leader_notes']) ? sanitize($_POST['team_leader_notes']) : null;
    $team_leader = !empty($_POST['team_leader']) ? sanitize($_POST['team_leader']) : null;
    $data_recorder = !empty($_POST['data_recorder']) ? sanitize($_POST['data_recorder']) : null;
    $logistic = !empty($_POST['logistic']) ? sanitize($_POST['logistic']) : null;
    $first_aider = !empty($_POST['aider1']) ? sanitize($_POST['aider1']) : null;
    $second_aider = !empty($_POST['aider2']) ? sanitize($_POST['aider2']) : null;

    // Hospital Endorsement
    $endorsement = !empty($_POST['endorsement']) ? sanitize($_POST['endorsement']) : null;
    $hospital_name = !empty($_POST['hospital_name']) ? sanitize($_POST['hospital_name']) : null;
    $endorsement_datetime = !empty($_POST['endorsement_datetime']) ? sanitize($_POST['endorsement_datetime']) : null;

    // Handle file upload security - Endorsement Attachment
    // Get existing endorsement attachment from database first
    $existing_endorsement_sql = "SELECT endorsement_attachment FROM prehospital_forms WHERE id = ?";
    $existing_endorsement_stmt = db_query($existing_endorsement_sql, [$record_id]);
    $existing_endorsement_row = $existing_endorsement_stmt->fetch();
    $endorsement_attachment_path = $existing_endorsement_row['endorsement_attachment'] ?? null;

    // Only process upload if a new file is provided
    if (isset($_FILES['endorsement_attachment']) && $_FILES['endorsement_attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['endorsement_attachment'];

        // Security checks
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Endorsement attachment upload error: ' . $file['error']);
        }

        // Check file size (max 5MB)
        $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $maxFileSize) {
            throw new Exception('Endorsement attachment file size exceeds 5MB limit');
        }

        // Validate MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMimeType = mime_content_type($file['tmp_name']);
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            throw new Exception('Invalid endorsement attachment file type. Only JPG, PNG, GIF, and WebP are allowed');
        }

        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid endorsement attachment file extension');
        }

        // Additional check: verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Uploaded file is not a valid image');
        }

        // Generate safe filename with metadata
        $timestamp = date('YmdHis');
        $uniqueId = bin2hex(random_bytes(8));
        $safeFileName = 'endorsement_' . $timestamp . '_' . $uniqueId . '.' . $fileExtension;

        // Create uploads directory structure with date-based organization
        $dateFolder = date('Y-m-d');
        $uploadDir = dirname(__DIR__) . '/public/uploads/endorsements/' . $dateFolder;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create endorsement attachment upload directory');
            }
        }

        // Move file to uploads directory
        $targetPath = $uploadDir . '/' . $safeFileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save endorsement attachment file');
        }

        // Store relative path for database (accessible via web)
        $endorsement_attachment_path = 'uploads/endorsements/' . $dateFolder . '/' . $safeFileName;
    }

    // Waiver
    $waiver_patient_signature = !empty($_POST['patient_signature']) ? sanitize($_POST['patient_signature']) : null;
    $waiver_witness_signature = !empty($_POST['witness_signature']) ? sanitize($_POST['witness_signature']) : null;
    
    // Update main form
    $sql = "UPDATE prehospital_forms SET
        form_date = ?,
        departure_time = ?,
        arrival_time = ?,
        vehicle_used = ?,
        vehicle_details = ?,
        driver_name = ?,
        arrival_station_time = ?,
        arrival_scene_location = ?,
        arrival_scene_time = ?,
        departure_scene_location = ?,
        departure_scene_time = ?,
        arrival_hospital_name = ?,
        arrival_hospital_time = ?,
        departure_hospital_location = ?,
        departure_hospital_time = ?,
        persons_present = ?,
        patient_name = ?,
        date_of_birth = ?,
        age = ?,
        gender = ?,
        civil_status = ?,
        address = ?,
        zone = ?,
        occupation = ?,
        place_of_incident = ?,
        zone_landmark = ?,
        incident_time = ?,
        informant_name = ?,
        informant_address = ?,
        arrival_type = ?,
        call_arrival_time = ?,
        contact_number = ?,
        relationship_victim = ?,
        personal_belongings = ?,
        other_belongings = ?,
        patient_documentation = ?,
        emergency_medical = ?,
        emergency_medical_details = ?,
        emergency_trauma = ?,
        emergency_trauma_details = ?,
        emergency_ob = ?,
        emergency_ob_details = ?,
        emergency_general = ?,
        emergency_general_details = ?,
        care_management = ?,
        oxygen_lpm = ?,
        other_care = ?,
        initial_time = ?,
        initial_bp = ?,
        initial_temp = ?,
        initial_pulse = ?,
        initial_resp_rate = ?,
        initial_pain_score = ?,
        initial_spo2 = ?,
        initial_spinal_injury = ?,
        initial_consciousness = ?,
        initial_helmet = ?,
        followup_time = ?,
        followup_bp = ?,
        followup_temp = ?,
        followup_pulse = ?,
        followup_resp_rate = ?,
        followup_pain_score = ?,
        followup_spo2 = ?,
        followup_spinal_injury = ?,
        followup_consciousness = ?,
        chief_complaints = ?,
        other_complaints = ?,
        fast_face_drooping = ?,
        fast_arm_weakness = ?,
        fast_speech_difficulty = ?,
        fast_time_to_call = ?,
        fast_sample_details = ?,
        ob_baby_status = ?,
        ob_delivery_time = ?,
        ob_placenta = ?,
        ob_lmp = ?,
        ob_aog = ?,
        ob_edc = ?,
        team_leader_notes = ?,
        team_leader = ?,
        data_recorder = ?,
        logistic = ?,
        first_aider = ?,
        second_aider = ?,
        endorsement = ?,
        hospital_name = ?,
        endorsement_attachment = ?,
        endorsement_datetime = ?,
        waiver_patient_signature = ?,
        waiver_witness_signature = ?,
        updated_at = NOW()
        WHERE id = ?";

    $params = [
        $form_date,
        $departure_time,
        $arrival_time,
        $vehicle_used,
        $vehicle_details,
        $driver_name,
        $arrival_station,
        $arrival_scene_location,
        $arrival_scene_time,
        $departure_scene_location,
        $departure_scene_time,
        $arrival_hospital_name,
        $arrival_hospital_time,
        $departure_hospital_location,
        $departure_hospital_time,
        $persons_present_json,
        $patient_name,
        $date_of_birth,
        $age,
        $gender,
        $civil_status,
        $address,
        $zone,
        $occupation,
        $place_of_incident,
        $zone_landmark,
        $incident_time,
        $informant_name,
        $informant_address,
        $arrival_type,
        $call_arrival_time,
        $contact_number,
        $relationship_victim,
        $personal_belongings_json,
        $other_belongings,
        $patient_documentation_path,
        $emergency_medical,
        $emergency_medical_details,
        $emergency_trauma,
        $emergency_trauma_details,
        $emergency_ob,
        $emergency_ob_details,
        $emergency_general,
        $emergency_general_details,
        $care_management_json,
        $oxygen_lpm,
        $other_care,
        $initial_time,
        $initial_bp,
        $initial_temp,
        $initial_pulse,
        $initial_resp_rate,
        $initial_pain_score,
        $initial_spo2,
        $initial_spinal_injury,
        $initial_consciousness,
        $initial_helmet,
        $followup_time,
        $followup_bp,
        $followup_temp,
        $followup_pulse,
        $followup_resp_rate,
        $followup_pain_score,
        $followup_spo2,
        $followup_spinal_injury,
        $followup_consciousness,
        $chief_complaints_json,
        $other_complaints,
        $fast_face_drooping,
        $fast_arm_weakness,
        $fast_speech_difficulty,
        $fast_time_to_call,
        $fast_sample_details,
        $ob_baby_status,
        $ob_delivery_time,
        $ob_placenta,
        $ob_lmp,
        $ob_aog,
        $ob_edc,
        $team_leader_notes,
        $team_leader,
        $data_recorder,
        $logistic,
        $first_aider,
        $second_aider,
        $endorsement,
        $hospital_name,
        $endorsement_attachment_path,
        $endorsement_datetime,
        $waiver_patient_signature,
        $waiver_witness_signature,
        $record_id
    ];

    $stmt = db_query($sql, $params);
    
    if (!$stmt) {
        throw new Exception('Failed to update record');
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log activity
    log_activity('form_updated', "Updated form: {$existing_record['form_number']} for patient: $patient_name");
    
    // Success response
    set_flash('Record updated successfully!', 'success');
    redirect('../public/view_record.php?id=' . $record_id);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Update Record Error: " . $e->getMessage());
    
    set_flash('Error updating record: ' . $e->getMessage(), 'error');
    redirect('../public/records.php');
}
