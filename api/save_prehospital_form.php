<?php
/**
 * Pre-Hospital Care Form - Save API
 * Handles form submission with security and validation
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'");

// Require authentication
require_login();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Rate limiting
if (!check_rate_limit('form_submit', 10, 300)) {
    json_response(['success' => false, 'message' => 'Too many submissions. Please wait.'], 429);
}

// Daily form limit check
$user_id = $_SESSION['user_id'];
if (!check_daily_form_limit($user_id, 50)) {
    json_response(['success' => false, 'message' => 'Daily form submission limit exceeded (50 forms per day).'], 429);
}

// Verify CSRF token
if (!verify_token($_POST['csrf_token'] ?? '')) {
    set_flash('Security token validation failed', 'error');
    json_response(['success' => false, 'message' => 'Invalid security token'], 403);
}


// Handle file upload security - Patient Documentation
$patient_documentation_path = null;
if (isset($_FILES['patient_documentation']) && $_FILES['patient_documentation']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['patient_documentation'];

    // Security checks
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Patient documentation upload error: ' . $file['error']);
    }

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Patient documentation file size exceeds 5MB limit');
    }

    // Validate MIME type
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('Invalid patient documentation file type. Only images are allowed.');
    }

    // Validate file extension matches MIME type
    $fileName = strtolower($file['name']);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('Patient documentation file extension not allowed');
    }

    // Additional security: Check for malicious content
    if (function_exists('exif_imagetype')) {
        $imageType = exif_imagetype($file['tmp_name']);
        if (!$imageType || !in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
            throw new Exception('Invalid patient documentation image file');
        }
    }

    // Generate secure filename with date and unique ID
    $uniqueId = bin2hex(random_bytes(16));
    $dateFolder = date('Y-m-d'); // Organize by date: 2026-01-09
    $safeFileName = 'patient_' . date('YmdHis') . '_' . $uniqueId . '.' . $extension;

    // Create uploads directory with date subfolder for better organization
    $uploadDir = '../public/uploads/patient_docs/' . $dateFolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    $targetPath = $uploadDir . $safeFileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save patient documentation file');
    }

    // Store relative path for database (accessible via web)
    $patient_documentation_path = 'uploads/patient_docs/' . $dateFolder . '/' . $safeFileName;

    // Add metadata comment for tracking
    // File saved with metadata: date, time, unique ID, original extension
}

// Start transaction
try {
    $pdo->beginTransaction();
    
    // Sanitize and validate inputs
    $form_date = sanitize($_POST['form_date'] ?? '');
    if (!validate_date($form_date)) {
        throw new Exception('Invalid form date');
    }
    
    // Generate unique form number
    $form_number = 'PHC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Basic Information
    $departure_time = sanitize($_POST['departure_time'] ?? null);
    $arrival_time = sanitize($_POST['arrival_time'] ?? null);
    $vehicle_used = sanitize($_POST['vehicle_used'] ?? null);
    $vehicle_details = sanitize($_POST['vehicle_details'] ?? null);
    $driver_name = sanitize($_POST['driver_name'] ?? null);
    
    // Validate times if provided
    if ($departure_time && !validate_time($departure_time)) {
        throw new Exception('Invalid departure time format');
    }
    if ($arrival_time && !validate_time($arrival_time)) {
        throw new Exception('Invalid arrival time format');
    }
    
    // Scene Information
    $arrival_scene_location = sanitize($_POST['arrival_scene_location'] ?? null);
    $arrival_scene_time = sanitize($_POST['arrival_scene_time'] ?? null);
    $departure_scene_location = sanitize($_POST['departure_scene_location'] ?? null);
    $departure_scene_time = sanitize($_POST['departure_scene_time'] ?? null);
    
    // Hospital Information
    $arrival_hospital_name = sanitize($_POST['arrival_hospital_name'] ?? null);
    $arrival_hospital_time = sanitize($_POST['arrival_hospital_time'] ?? null);
    $departure_hospital_location = sanitize($_POST['departure_hospital_location'] ?? null);
    $departure_hospital_time = sanitize($_POST['departure_hospital_time'] ?? null);
    $arrival_station_time = sanitize($_POST['arrival_station_time'] ?? null);
    
    // Persons Present (collect checkboxes)
    $persons_present = [];
    $person_fields = ['police', 'brgyOfficials', 'relatives', 'bystanders', 'nonePresent'];
    foreach ($person_fields as $field) {
        if (isset($_POST[$field])) {
            $persons_present[] = $field;
        }
    }
    $persons_present_json = json_encode($persons_present);
    
    // Patient Information (REQUIRED)
    $patient_name = sanitize($_POST['patient_name'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = sanitize($_POST['gender'] ?? '');
    
    if (empty($patient_name) || empty($date_of_birth) || $age <= 0 || empty($gender)) {
        throw new Exception('Patient information is required');
    }
    
    if (!validate_date($date_of_birth)) {
        throw new Exception('Invalid date of birth');
    }
    
    if (!in_array($gender, ['male', 'female'])) {
        throw new Exception('Invalid gender value');
    }
    
    $civil_status = sanitize($_POST['civil_status'] ?? null);
    $address = sanitize($_POST['address'] ?? null);
    $zone = sanitize($_POST['zone'] ?? null);
    $occupation = sanitize($_POST['occupation'] ?? null);
    $place_of_incident = sanitize($_POST['place_of_incident'] ?? null);
    $zone_landmark = sanitize($_POST['zone_landmark'] ?? null);
    $incident_time = sanitize($_POST['incident_time'] ?? null);
    
    // Informant Details
    $informant_name = sanitize($_POST['informant_name'] ?? null);
    $informant_address = sanitize($_POST['informant_address'] ?? null);
    $arrival_type = sanitize($_POST['arrival_type'] ?? null);
    $call_arrival_time = sanitize($_POST['call_arrival_time'] ?? null);
    $contact_number = sanitize($_POST['contact_number'] ?? null);
    $relationship_victim = sanitize($_POST['relationship_victim'] ?? null);
    
    // Personal Belongings
    $personal_belongings = isset($_POST['personal_belongings']) ? $_POST['personal_belongings'] : [];
    if (!is_array($personal_belongings)) {
        $personal_belongings = [$personal_belongings];
    }
    $personal_belongings = array_map('sanitize', $personal_belongings);
    $personal_belongings_json = json_encode($personal_belongings);
    $other_belongings = sanitize($_POST['other_belongings'] ?? null);
    $patient_documentation = $patient_documentation_path; // Store the file path
    
    // Emergency Call Type
    $emergency_medical = isset($_POST['emergency_medical']) ? 1 : 0;
    $emergency_medical_details = sanitize($_POST['emergency_medical_details'] ?? null);
    $emergency_trauma = isset($_POST['emergency_trauma']) ? 1 : 0;
    $emergency_trauma_details = sanitize($_POST['emergency_trauma_details'] ?? null);
    $emergency_ob = isset($_POST['emergency_ob']) ? 1 : 0;
    $emergency_ob_details = sanitize($_POST['emergency_ob_details'] ?? null);
    $emergency_general = isset($_POST['emergency_general']) ? 1 : 0;
    $emergency_general_details = sanitize($_POST['emergency_general_details'] ?? null);
    
    // Care Management
    $care_management = [];
    $care_fields = ['immobilization', 'cpr', 'bandaging', 'woundCare', 'cCollar', 'aed', 'ked'];
    foreach ($care_fields as $field) {
        if (isset($_POST[$field])) {
            $care_management[] = $field;
        }
    }
    $care_management_json = json_encode($care_management);
    $oxygen_lpm = sanitize($_POST['oxygen_lpm'] ?? null);
    $other_care = sanitize($_POST['other_care'] ?? null);
    
    // Initial Vitals - Handle empty values properly
    $initial_time = !empty($_POST['initial_time']) ? sanitize($_POST['initial_time']) : null;
    $initial_bp = !empty($_POST['initial_bp']) ? sanitize($_POST['initial_bp']) : null;
    $initial_temp = (!empty($_POST['initial_temp']) && $_POST['initial_temp'] !== '') ? (float)$_POST['initial_temp'] : null;
    $initial_pulse = (!empty($_POST['initial_pulse']) && $_POST['initial_pulse'] !== '') ? (int)$_POST['initial_pulse'] : null;
    $initial_resp_rate = (!empty($_POST['initial_resp_rate']) && $_POST['initial_resp_rate'] !== '') ? (int)$_POST['initial_resp_rate'] : null;
    $initial_pain_score = (isset($_POST['initial_pain_score']) && $_POST['initial_pain_score'] !== '') ? (int)$_POST['initial_pain_score'] : null;
    $initial_spo2 = (!empty($_POST['initial_spo2']) && $_POST['initial_spo2'] !== '') ? (int)$_POST['initial_spo2'] : null;
    $initial_spinal_injury = !empty($_POST['initial_spinal_injury']) ? sanitize($_POST['initial_spinal_injury']) : null;
    $initial_consciousness = !empty($_POST['initial_consciousness']) ? sanitize($_POST['initial_consciousness']) : null;
    $initial_helmet = !empty($_POST['initial_helmet']) ? sanitize($_POST['initial_helmet']) : null;
    
    // Follow-up Vitals - Handle empty values properly
    $followup_time = !empty($_POST['followup_time']) ? sanitize($_POST['followup_time']) : null;
    $followup_bp = !empty($_POST['followup_bp']) ? sanitize($_POST['followup_bp']) : null;
    $followup_temp = (!empty($_POST['followup_temp']) && $_POST['followup_temp'] !== '') ? (float)$_POST['followup_temp'] : null;
    $followup_pulse = (!empty($_POST['followup_pulse']) && $_POST['followup_pulse'] !== '') ? (int)$_POST['followup_pulse'] : null;
    $followup_resp_rate = (!empty($_POST['followup_resp_rate']) && $_POST['followup_resp_rate'] !== '') ? (int)$_POST['followup_resp_rate'] : null;
    $followup_pain_score = (isset($_POST['followup_pain_score']) && $_POST['followup_pain_score'] !== '') ? (int)$_POST['followup_pain_score'] : null;
    $followup_spo2 = (!empty($_POST['followup_spo2']) && $_POST['followup_spo2'] !== '') ? (int)$_POST['followup_spo2'] : null;
    $followup_spinal_injury = !empty($_POST['followup_spinal_injury']) ? sanitize($_POST['followup_spinal_injury']) : null;
    $followup_consciousness = !empty($_POST['followup_consciousness']) ? sanitize($_POST['followup_consciousness']) : null;
    
    // Chief Complaints
    $chief_complaints = [];
    $complaint_fields = ['chestPain', 'headache', 'blurredVision', 'difficultyBreathing', 'dizziness', 'bodyMalaise'];
    foreach ($complaint_fields as $field) {
        if (isset($_POST[$field])) {
            $chief_complaints[] = $field;
        }
    }
    $chief_complaints_json = json_encode($chief_complaints);
    $other_complaints = sanitize($_POST['other_complaints'] ?? null);
    
    // FAST Assessment
    $fast_face_drooping = sanitize($_POST['fast_face_drooping'] ?? null);
    $fast_arm_weakness = sanitize($_POST['fast_arm_weakness'] ?? null);
    $fast_speech_difficulty = sanitize($_POST['fast_speech_difficulty'] ?? null);
    $fast_time_to_call = sanitize($_POST['fast_time_to_call'] ?? null);
    $fast_sample_details = sanitize($_POST['fast_sample_details'] ?? null);
    
    // OB Information
    $ob_baby_status = sanitize($_POST['ob_baby_status'] ?? null);
    $ob_delivery_time = sanitize($_POST['ob_delivery_time'] ?? null);
    $ob_placenta = sanitize($_POST['ob_placenta'] ?? null);
    $ob_lmp = sanitize($_POST['ob_lmp'] ?? null);
    $ob_aog = sanitize($_POST['ob_aog'] ?? null);
    $ob_edc = sanitize($_POST['ob_edc'] ?? null);
    
    // Team Information
    $team_leader_notes = sanitize($_POST['team_leader_notes'] ?? null);
    $team_leader = sanitize($_POST['team_leader'] ?? null);
    $data_recorder = sanitize($_POST['data_recorder'] ?? null);
    $logistic = sanitize($_POST['logistic'] ?? null);
    $first_aider = sanitize($_POST['first_aider'] ?? null);
    $second_aider = sanitize($_POST['second_aider'] ?? null);
    
    
    // Get current user ID
    $created_by = $_SESSION['user_id'];

    // Limit check - prevent huge inserts
    $injuries_data = isset($_POST['injuries']) ? json_decode($_POST['injuries'], true) : [];
    if (count($injuries_data) > 100) {
        throw new Exception('Too many injuries marked (max 100)');
    }

    // Check if we're updating an existing draft
    $draft_id = isset($_POST['draft_id']) && !empty($_POST['draft_id']) ? (int)$_POST['draft_id'] : null;
    $is_updating_draft = false;

    if ($draft_id) {
        // Verify this draft belongs to the current user and is actually a draft
        $verify_sql = "SELECT id, form_number FROM prehospital_forms WHERE id = ? AND created_by = ? AND status = 'draft'";
        $verify_stmt = db_query($verify_sql, [$draft_id, $created_by]);
        $existing_draft = $verify_stmt->fetch();

        if ($existing_draft) {
            $is_updating_draft = true;
            $form_number = $existing_draft['form_number']; // Keep the same form number
        } else {
            // Draft not found or doesn't belong to user - generate new form number
            $form_number = 'PHC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        }
    } else {
        // No draft_id - generate new form number
        $form_number = 'PHC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    // Prepare common parameters
    $common_params = [
        $form_number, $form_date, $departure_time, $arrival_time, $vehicle_used, $vehicle_details, $driver_name,
        $arrival_scene_location, $arrival_scene_time, $departure_scene_location, $departure_scene_time,
        $arrival_hospital_name, $arrival_hospital_time, $departure_hospital_location, $departure_hospital_time,
        $arrival_station_time, $persons_present_json,
        $patient_name, $date_of_birth, $age, $gender, $civil_status, $address, $zone, $occupation,
        $place_of_incident, $zone_landmark, $incident_time,
        $informant_name, $informant_address, $arrival_type, $call_arrival_time, $contact_number,
        $relationship_victim, $personal_belongings_json, $other_belongings, $patient_documentation,
        $emergency_medical, $emergency_medical_details, $emergency_trauma, $emergency_trauma_details,
        $emergency_ob, $emergency_ob_details, $emergency_general, $emergency_general_details,
        $care_management_json, $oxygen_lpm, $other_care,
        $initial_time, $initial_bp, $initial_temp, $initial_pulse, $initial_resp_rate, $initial_pain_score,
        $initial_spo2, $initial_spinal_injury, $initial_consciousness, $initial_helmet,
        $followup_time, $followup_bp, $followup_temp, $followup_pulse, $followup_resp_rate, $followup_pain_score,
        $followup_spo2, $followup_spinal_injury, $followup_consciousness,
        $chief_complaints_json, $other_complaints,
        $fast_face_drooping, $fast_arm_weakness, $fast_speech_difficulty, $fast_time_to_call, $fast_sample_details,
        $ob_baby_status, $ob_delivery_time, $ob_placenta, $ob_lmp, $ob_aog, $ob_edc,
        $team_leader_notes, $team_leader, $data_recorder, $logistic, $first_aider, $second_aider
    ];

    if ($is_updating_draft) {
        // UPDATE existing draft to completed status
        $sql = "UPDATE prehospital_forms SET
            form_number = ?, form_date = ?, departure_time = ?, arrival_time = ?, vehicle_used = ?, vehicle_details = ?, driver_name = ?,
            arrival_scene_location = ?, arrival_scene_time = ?, departure_scene_location = ?, departure_scene_time = ?,
            arrival_hospital_name = ?, arrival_hospital_time = ?, departure_hospital_location = ?, departure_hospital_time = ?,
            arrival_station_time = ?, persons_present = ?,
            patient_name = ?, date_of_birth = ?, age = ?, gender = ?, civil_status = ?, address = ?, zone = ?, occupation = ?,
            place_of_incident = ?, zone_landmark = ?, incident_time = ?,
            informant_name = ?, informant_address = ?, arrival_type = ?, call_arrival_time = ?, contact_number = ?,
            relationship_victim = ?, personal_belongings = ?, other_belongings = ?, patient_documentation = ?,
            emergency_medical = ?, emergency_medical_details = ?, emergency_trauma = ?, emergency_trauma_details = ?,
            emergency_ob = ?, emergency_ob_details = ?, emergency_general = ?, emergency_general_details = ?,
            care_management = ?, oxygen_lpm = ?, other_care = ?,
            initial_time = ?, initial_bp = ?, initial_temp = ?, initial_pulse = ?, initial_resp_rate = ?, initial_pain_score = ?,
            initial_spo2 = ?, initial_spinal_injury = ?, initial_consciousness = ?, initial_helmet = ?,
            followup_time = ?, followup_bp = ?, followup_temp = ?, followup_pulse = ?, followup_resp_rate = ?, followup_pain_score = ?,
            followup_spo2 = ?, followup_spinal_injury = ?, followup_consciousness = ?,
            chief_complaints = ?, other_complaints = ?,
            fast_face_drooping = ?, fast_arm_weakness = ?, fast_speech_difficulty = ?, fast_time_to_call = ?, fast_sample_details = ?,
            ob_baby_status = ?, ob_delivery_time = ?, ob_placenta = ?, ob_lmp = ?, ob_aog = ?, ob_edc = ?,
            team_leader_notes = ?, team_leader = ?, data_recorder = ?, logistic = ?, first_aider = ?, second_aider = ?,
            status = 'completed',
            updated_at = NOW()
            WHERE id = ? AND created_by = ?";

        $params = array_merge($common_params, [$draft_id, $created_by]);
        $stmt = db_query($sql, $params);

        if (!$stmt) {
            throw new Exception('Failed to update draft to completed');
        }

        $form_id = $draft_id; // Use the existing draft ID
    } else {
        // INSERT new form
        $sql = "INSERT INTO prehospital_forms (
            form_number, form_date, departure_time, arrival_time, vehicle_used, vehicle_details, driver_name,
            arrival_scene_location, arrival_scene_time, departure_scene_location, departure_scene_time,
            arrival_hospital_name, arrival_hospital_time, departure_hospital_location, departure_hospital_time,
            arrival_station_time, persons_present,
            patient_name, date_of_birth, age, gender, civil_status, address, zone, occupation,
            place_of_incident, zone_landmark, incident_time,
            informant_name, informant_address, arrival_type, call_arrival_time, contact_number,
            relationship_victim, personal_belongings, other_belongings, patient_documentation,
            emergency_medical, emergency_medical_details, emergency_trauma, emergency_trauma_details,
            emergency_ob, emergency_ob_details, emergency_general, emergency_general_details,
            care_management, oxygen_lpm, other_care,
            initial_time, initial_bp, initial_temp, initial_pulse, initial_resp_rate, initial_pain_score,
            initial_spo2, initial_spinal_injury, initial_consciousness, initial_helmet,
            followup_time, followup_bp, followup_temp, followup_pulse, followup_resp_rate, followup_pain_score,
            followup_spo2, followup_spinal_injury, followup_consciousness,
            chief_complaints, other_complaints,
            fast_face_drooping, fast_arm_weakness, fast_speech_difficulty, fast_time_to_call, fast_sample_details,
            ob_baby_status, ob_delivery_time, ob_placenta, ob_lmp, ob_aog, ob_edc,
            team_leader_notes, team_leader, data_recorder, logistic, first_aider, second_aider,
            created_by, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, 'completed'
        )";

        $params = array_merge($common_params, [$created_by]);
        $stmt = db_query($sql, $params);

        if (!$stmt) {
            throw new Exception('Failed to save form data');
        }

        $form_id = $pdo->lastInsertId();
    }
    
    // Insert injuries if any
    if (!empty($injuries_data) && is_array($injuries_data)) {
        $injury_sql = "INSERT INTO injuries (form_id, injury_number, injury_type, body_view, coordinate_x, coordinate_y, notes) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        foreach ($injuries_data as $injury) {
            $injury_params = [
                $form_id,
                (int)($injury['id'] ?? 0),
                sanitize($injury['type'] ?? 'other'),
                sanitize($injury['view'] ?? 'front'),
                (int)($injury['x'] ?? 0),
                (int)($injury['y'] ?? 0),
                sanitize($injury['notes'] ?? '')
            ];
            
            $injury_stmt = db_query($injury_sql, $injury_params);
            if (!$injury_stmt) {
                throw new Exception('Failed to save injury data');
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log activity
    log_activity('form_created', "Created form: $form_number for patient: $patient_name");
    
    // Success response
    set_flash('Form saved successfully! Form Number: ' . $form_number, 'success');
    json_response([
        'success' => true,
        'message' => 'Form saved successfully',
        'form_number' => $form_number,
        'form_id' => $form_id
    ], 200);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Form Save Error: " . $e->getMessage());
    
    set_flash('Error saving form: ' . $e->getMessage(), 'error');
    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
