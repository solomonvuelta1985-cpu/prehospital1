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
try {
if (isset($_FILES['patient_documentation']) && $_FILES['patient_documentation']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['patient_documentation'];

    // Security checks
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Patient documentation upload error: ' . $file['error']);
    }

    // Validate file size (20MB max)
    $maxSize = MAX_FILE_SIZE;
    if ($file['size'] > $maxSize) {
        throw new Exception('Patient documentation file size exceeds 20MB limit');
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
} catch (Exception $e) {
    error_log("File Upload Error: " . $e->getMessage());
    set_flash('File upload error: ' . $e->getMessage(), 'error');
    redirect('../public/prehospital_form.php');
}

// Start transaction
try {
    $pdo->beginTransaction();
    
    // Sanitize and validate inputs
    $form_date = sanitize($_POST['form_date'] ?? '', false); // Don't uppercase date
    if (!validate_date($form_date)) {
        throw new Exception('Invalid form date');
    }
    
    // Generate unique form number
    $form_number = 'PHC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
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

    // Now sanitize after format conversion (don't uppercase time values)
    $departure_time = $departure_time ? sanitize($departure_time, false) : null;
    $arrival_time = $arrival_time ? sanitize($arrival_time, false) : null;

    // Convert 12-hour format to 24-hour if needed (safety fallback for server-side conversion)
    if ($departure_time) {
        $converted = convert_12h_to_24h($departure_time);
        if ($converted !== false) {
            $departure_time = $converted;
        }
    }
    if ($arrival_time) {
        $converted = convert_12h_to_24h($arrival_time);
        if ($converted !== false) {
            $arrival_time = $converted;
        }
    }

    $vehicle_used = !empty($_POST['vehicle_used']) ? sanitize($_POST['vehicle_used'], false) : null;
    // Don't sanitize vehicle_details as it contains JSON - validate and trim only
    $vehicle_details = !empty($_POST['vehicle_details']) ? trim($_POST['vehicle_details']) : null;
    // Validate that vehicle_details is valid JSON if provided
    if ($vehicle_details !== null && $vehicle_details !== '') {
        $test_json = json_decode($vehicle_details, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid vehicle details format');
        }
    }
    $driver_name = !empty($_POST['driver']) ? sanitize($_POST['driver']) : null;

    // Validate times if provided
    if ($departure_time && !validate_time($departure_time)) {
        throw new Exception('Invalid departure time format');
    }
    if ($arrival_time && !validate_time($arrival_time)) {
        throw new Exception('Invalid arrival time format');
    }
    
    // Scene Information
    $arrival_scene_location = sanitize($_POST['arrival_scene_location'] ?? null);
    $arrival_scene_time = sanitize($_POST['arrival_scene_time'] ?? null, false); // Don't uppercase time
    $departure_scene_location = sanitize($_POST['departure_scene_location'] ?? null);
    $departure_scene_time = sanitize($_POST['departure_scene_time'] ?? null, false); // Don't uppercase time

    // Hospital Information
    $arrival_hospital_name = sanitize($_POST['arrival_hospital_name'] ?? null);
    $arrival_hospital_time = sanitize($_POST['arrival_hospital_time'] ?? null, false); // Don't uppercase time
    $departure_hospital_time = sanitize($_POST['departure_hospital_time'] ?? null, false); // Don't uppercase time
    $arrival_station_time = sanitize($_POST['arrival_station_time'] ?? null, false); // Don't uppercase time

    // Convert 12-hour time format to 24-hour if needed
    if ($arrival_scene_time) {
        $converted = convert_12h_to_24h($arrival_scene_time);
        if ($converted !== false) $arrival_scene_time = $converted;
    }
    if ($departure_scene_time) {
        $converted = convert_12h_to_24h($departure_scene_time);
        if ($converted !== false) $departure_scene_time = $converted;
    }
    if ($arrival_hospital_time) {
        $converted = convert_12h_to_24h($arrival_hospital_time);
        if ($converted !== false) $arrival_hospital_time = $converted;
    }
    if ($departure_hospital_time) {
        $converted = convert_12h_to_24h($departure_hospital_time);
        if ($converted !== false) $departure_hospital_time = $converted;
    }
    if ($arrival_station_time) {
        $converted = convert_12h_to_24h($arrival_station_time);
        if ($converted !== false) $arrival_station_time = $converted;
    }

    // Persons Present (collect checkboxes)
    $persons_present = isset($_POST['persons_present']) ? $_POST['persons_present'] : [];
    if (!is_array($persons_present)) {
        $persons_present = [$persons_present];
    }
    $persons_present = array_map(function($val) { return sanitize($val, false); }, $persons_present);
    $persons_present_json = json_encode($persons_present);
    
    // Patient Information (REQUIRED)
    $patient_name = sanitize($_POST['patient_name'] ?? '');
    $date_of_birth = sanitize($_POST['date_of_birth'] ?? '', false); // Don't uppercase date
    $age = (int)($_POST['age'] ?? 0);
    $age_unit = !empty($_POST['age_unit']) ? sanitize($_POST['age_unit'], false) : 'years'; // Don't uppercase enum
    $growth_status = !empty($_POST['growth_status']) ? sanitize($_POST['growth_status'], false) : null; // Don't uppercase enum
    $gender = sanitize($_POST['gender'] ?? '', false); // Don't uppercase gender

    // Normalize date_of_birth: treat whitespace-only or invalid placeholders as empty
    if ($date_of_birth !== null) {
        $date_of_birth = trim($date_of_birth);
        if ($date_of_birth === '' || $date_of_birth === '0000-00-00') {
            $date_of_birth = null;
        }
    }

    // Debug logging
    error_log("Patient validation - Name: '$patient_name', DOB: '" . ($date_of_birth ?? 'NULL') . "', Age: $age, Gender: '$gender'");

    // Detailed validation with specific error messages
    $missing_fields = [];
    if (empty($patient_name)) {
        $missing_fields[] = 'Patient Name';
    }
    // Date of Birth is now optional
    if ($age <= 0) {
        $missing_fields[] = 'Age (must be greater than 0)';
    }
    if (empty($gender)) {
        $missing_fields[] = 'Gender';
    }

    if (!empty($missing_fields)) {
        $missing_list = implode(', ', $missing_fields);
        throw new Exception('Required patient information missing: ' . $missing_list);
    }

    // Validate date of birth only if provided
    if (!empty($date_of_birth) && !validate_date($date_of_birth)) {
        throw new Exception('Invalid date of birth format. Please use the date picker or enter date as YYYY-MM-DD.');
    }

    // Convert empty DOB to null for database
    if (empty($date_of_birth)) {
        $date_of_birth = null;
    }
    
    if (!in_array($gender, ['male', 'female'])) {
        throw new Exception('Invalid gender value');
    }
    
    $civil_status = sanitize($_POST['civil_status'] ?? null, false); // Don't uppercase civil status
    $address = sanitize($_POST['address'] ?? null);
    $zone = sanitize($_POST['zone'] ?? null);
    $occupation = sanitize($_POST['occupation'] ?? null);
    $place_of_incident = sanitize($_POST['place_of_incident'] ?? null);
    $zone_landmark = sanitize($_POST['zone_landmark'] ?? null);
    $incident_time = sanitize($_POST['incident_time'] ?? null, false); // Don't uppercase time

    // Encrypt sensitive fields before storage
    $patient_name = encrypt_field($patient_name);
    $address = encrypt_field($address);

    // Informant Details
    $informant_name = sanitize($_POST['informant_name'] ?? null);
    $informant_address = sanitize($_POST['informant_address'] ?? null);
    $arrival_type = sanitize($_POST['arrival_type'] ?? null, false); // Don't uppercase arrival type (enum value)
    $call_arrival_time = sanitize($_POST['call_arrival_time'] ?? null, false); // Don't uppercase time
    $contact_number = sanitize($_POST['contact_number'] ?? null);
    $relationship_victim = sanitize($_POST['relationship_victim'] ?? null);

    // Convert 12-hour time format to 24-hour if needed
    if ($incident_time) {
        $converted = convert_12h_to_24h($incident_time);
        if ($converted !== false) $incident_time = $converted;
    }
    if ($call_arrival_time) {
        $converted = convert_12h_to_24h($call_arrival_time);
        if ($converted !== false) $call_arrival_time = $converted;
    }
    
    // Personal Belongings
    $personal_belongings = isset($_POST['personal_belongings']) ? $_POST['personal_belongings'] : [];
    if (!is_array($personal_belongings)) {
        $personal_belongings = [$personal_belongings];
    }
    $personal_belongings = array_map(function($val) { return sanitize($val, false); }, $personal_belongings);
    $personal_belongings_json = json_encode($personal_belongings);
    $other_belongings = sanitize($_POST['other_belongings'] ?? null);
    $patient_documentation = $patient_documentation_path; // Store the file path

    // Photo GPS Metadata
    $photo_latitude = !empty($_POST['photo_latitude']) ? floatval($_POST['photo_latitude']) : null;
    $photo_longitude = !empty($_POST['photo_longitude']) ? floatval($_POST['photo_longitude']) : null;
    $photo_address = !empty($_POST['photo_address']) ? sanitize($_POST['photo_address']) : null;
    $photo_datetime = !empty($_POST['photo_datetime']) ? sanitize($_POST['photo_datetime'], false) : null;

    // Validate GPS coordinate ranges
    if ($photo_latitude !== null && ($photo_latitude < -90 || $photo_latitude > 90)) {
        $photo_latitude = null;
    }
    if ($photo_longitude !== null && ($photo_longitude < -180 || $photo_longitude > 180)) {
        $photo_longitude = null;
    }

    // Emergency Call Type
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
    $care_management = array_map(function($val) { return sanitize($val, false); }, $care_management);
    $care_management_json = json_encode($care_management);
    $oxygen_lpm = sanitize($_POST['oxygen_lpm'] ?? null);
    $other_care = sanitize($_POST['other_care'] ?? null);
    
    // Initial Vitals - Handle empty values properly
    $initial_time = !empty($_POST['initial_time']) ? sanitize($_POST['initial_time'], false) : null; // Don't uppercase time
    $initial_bp = !empty($_POST['initial_bp']) ? sanitize($_POST['initial_bp']) : null;
    $initial_temp = (!empty($_POST['initial_temp']) && $_POST['initial_temp'] !== '') ? (float)$_POST['initial_temp'] : null;
    $initial_pulse = (!empty($_POST['initial_pulse']) && $_POST['initial_pulse'] !== '') ? (int)$_POST['initial_pulse'] : null;
    $initial_resp_rate = (!empty($_POST['initial_resp']) && $_POST['initial_resp'] !== '') ? (int)$_POST['initial_resp'] : null;
    $initial_pain_score = (isset($_POST['initial_pain_score']) && $_POST['initial_pain_score'] !== '') ? (int)$_POST['initial_pain_score'] : null;
    $initial_spo2 = (!empty($_POST['initial_spo2']) && $_POST['initial_spo2'] !== '') ? (int)$_POST['initial_spo2'] : null;
    $initial_spinal_injury = !empty($_POST['initial_spinal_injury']) ? sanitize($_POST['initial_spinal_injury'], false) : null; // Don't uppercase enum
    $initial_consciousness = !empty($_POST['initial_consciousness']) ? json_encode(array_map(function($val) { return sanitize($val, false); }, $_POST['initial_consciousness'])) : null; // Don't uppercase enum
    $initial_helmet = !empty($_POST['initial_helmet']) ? json_encode(array_map(function($val) { return sanitize($val, false); }, $_POST['initial_helmet'])) : null; // Multi-select - don't uppercase enum

    // Convert 12-hour time format to 24-hour if needed
    if ($initial_time) {
        $converted = convert_12h_to_24h($initial_time);
        if ($converted !== false) $initial_time = $converted;
    }

    // Follow-up Vitals - Handle empty values properly
    $followup_time = !empty($_POST['followup_time']) ? sanitize($_POST['followup_time'], false) : null; // Don't uppercase time
    $followup_bp = !empty($_POST['followup_bp']) ? sanitize($_POST['followup_bp']) : null;
    $followup_temp = (!empty($_POST['followup_temp']) && $_POST['followup_temp'] !== '') ? (float)$_POST['followup_temp'] : null;
    $followup_pulse = (!empty($_POST['followup_pulse']) && $_POST['followup_pulse'] !== '') ? (int)$_POST['followup_pulse'] : null;
    $followup_resp_rate = (!empty($_POST['followup_resp']) && $_POST['followup_resp'] !== '') ? (int)$_POST['followup_resp'] : null;
    $followup_pain_score = (isset($_POST['followup_pain_score']) && $_POST['followup_pain_score'] !== '') ? (int)$_POST['followup_pain_score'] : null;
    $followup_spo2 = (!empty($_POST['followup_spo2']) && $_POST['followup_spo2'] !== '') ? (int)$_POST['followup_spo2'] : null;
    $followup_spinal_injury = !empty($_POST['followup_spinal_injury']) ? sanitize($_POST['followup_spinal_injury'], false) : null; // Don't uppercase enum
    $followup_consciousness = !empty($_POST['followup_consciousness']) ? json_encode(array_map(function($val) { return sanitize($val, false); }, $_POST['followup_consciousness'])) : null; // Don't uppercase enum

    // Convert 12-hour time format to 24-hour if needed
    if ($followup_time) {
        $converted = convert_12h_to_24h($followup_time);
        if ($converted !== false) $followup_time = $converted;
    }

    // Chief Complaints
    $chief_complaints = isset($_POST['chief_complaints']) ? $_POST['chief_complaints'] : [];
    if (!is_array($chief_complaints)) {
        $chief_complaints = [$chief_complaints];
    }
    $chief_complaints = array_map(function($val) { return sanitize($val, false); }, $chief_complaints);
    $chief_complaints_json = json_encode($chief_complaints);
    $other_complaints = sanitize($_POST['other_complaints'] ?? null);

    // FAST Assessment
    $fast_face_drooping = !empty($_POST['face_drooping']) ? sanitize($_POST['face_drooping'], false) : null;
    $fast_arm_weakness = !empty($_POST['arm_weakness']) ? sanitize($_POST['arm_weakness'], false) : null;
    $fast_speech_difficulty = !empty($_POST['speech_difficulty']) ? sanitize($_POST['speech_difficulty'], false) : null;
    $fast_time_to_call = !empty($_POST['time_to_call']) ? sanitize($_POST['time_to_call'], false) : null;
    // SAMPLE details is stored as JSON - don't sanitize to preserve JSON structure
    // Prepared statements handle SQL injection protection
    $fast_sample_details = $_POST['fast_sample_details'] ?? $_POST['sample_details'] ?? null;
    if ($fast_sample_details !== null) {
        $fast_sample_details = trim($fast_sample_details);
        // Validate it's valid JSON if not empty
        if (!empty($fast_sample_details)) {
            $decoded = json_decode($fast_sample_details, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Not valid JSON - treat as plain text and convert to JSON format
                $fast_sample_details = json_encode(['signs' => $fast_sample_details, 'allergies' => '', 'medications' => '', 'pertinent' => '', 'last_intake' => '', 'events' => '']);
            }
        }
    }

    // OB Information
    $ob_baby_status = !empty($_POST['baby_status']) ? sanitize($_POST['baby_status']) : null;
    $ob_delivery_time = !empty($_POST['ob_delivery_time']) ? sanitize($_POST['ob_delivery_time'], false) : null;
    $ob_placenta = !empty($_POST['placenta']) ? sanitize($_POST['placenta'], false) : null;
    $ob_lmp = !empty($_POST['lmp']) ? sanitize($_POST['lmp'], false) : null;
    $ob_aog = !empty($_POST['aog']) ? sanitize($_POST['aog']) : null;
    $ob_edc = !empty($_POST['edc']) ? sanitize($_POST['edc'], false) : null;

    // Convert 12-hour time format to 24-hour if needed
    if ($ob_delivery_time) {
        $converted = convert_12h_to_24h($ob_delivery_time);
        if ($converted !== false) $ob_delivery_time = $converted;
    }

    // Team Information
    $team_leader_notes = sanitize($_POST['team_leader_notes'] ?? null);
    $team_leader = sanitize($_POST['team_leader'] ?? null);
    $data_recorder = sanitize($_POST['data_recorder'] ?? null);
    $logistic = sanitize($_POST['logistic'] ?? null);
    $first_aider = !empty($_POST['aider1']) ? sanitize($_POST['aider1']) : null;
    $second_aider = !empty($_POST['aider2']) ? sanitize($_POST['aider2']) : null;

    // Narrative Report
    $narrative_report = !empty($_POST['narrative_report']) ? trim($_POST['narrative_report']) : null;

    // Hospital Endorsement
    $hospital_name = sanitize($_POST['hospital_name'] ?? null);
    $endorsement_datetime_raw = $_POST['endorsement_datetime'] ?? null;

    // Handle datetime-local format (YYYY-MM-DDTHH:MM) - convert to MySQL datetime format
    if ($endorsement_datetime_raw && strpos($endorsement_datetime_raw, 'T') !== false) {
        $endorsement_datetime_raw = str_replace('T', ' ', $endorsement_datetime_raw);
        // Add seconds if not present
        if (substr_count($endorsement_datetime_raw, ':') === 1) {
            $endorsement_datetime_raw .= ':00';
        }
    }

    $endorsement_datetime = $endorsement_datetime_raw ? sanitize($endorsement_datetime_raw, false) : null; // Don't uppercase datetime

    // Handle file upload security - Endorsement Attachment
    $endorsement_attachment_path = null;
    if (isset($_FILES['endorsement_attachment']) && $_FILES['endorsement_attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['endorsement_attachment'];

        // Security checks
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Endorsement attachment upload error: ' . $file['error']);
        }

        // Validate file size (20MB max)
        $maxSize = MAX_FILE_SIZE;
        if ($file['size'] > $maxSize) {
            throw new Exception('Endorsement attachment file size exceeds 20MB limit');
        }

        // Validate MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception('Invalid endorsement attachment file type. Only images are allowed.');
        }

        // Validate file extension matches MIME type
        $fileName = strtolower($file['name']);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Endorsement attachment file extension not allowed');
        }

        // Additional security: Check for malicious content
        if (function_exists('exif_imagetype')) {
            $imageType = exif_imagetype($file['tmp_name']);
            if (!$imageType || !in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
                throw new Exception('Invalid endorsement attachment image file');
            }
        }

        // Generate secure filename with date and unique ID
        $uniqueId = bin2hex(random_bytes(16));
        $dateFolder = date('Y-m-d'); // Organize by date: 2026-01-09
        $safeFileName = 'endorsement_' . date('YmdHis') . '_' . $uniqueId . '.' . $extension;

        // Create uploads directory with date subfolder for better organization
        $uploadDir = '../public/uploads/endorsements/' . $dateFolder . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        $targetPath = $uploadDir . $safeFileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save endorsement attachment file');
        }

        // Store relative path for database (accessible via web)
        $endorsement_attachment_path = 'uploads/endorsements/' . $dateFolder . '/' . $safeFileName;
    }

    // Get current user ID
    $created_by = $_SESSION['user_id'];

    // Limit check - prevent huge inserts
    $injuries_data = isset($_POST['injuries_data']) ? json_decode($_POST['injuries_data'], true) : [];
    if (empty($injuries_data) && isset($_POST['injuries'])) {
        // Fallback for old format
        $injuries_data = json_decode($_POST['injuries'], true);
    }
    if (count($injuries_data) > 100) {
        throw new Exception('Too many injuries marked (max 100)');
    }

    // Check if we're updating an existing draft
    $draft_id = isset($_POST['draft_id']) && !empty($_POST['draft_id']) ? (int)$_POST['draft_id'] : null;
    $is_updating_draft = false;

    if ($draft_id) {
        // Verify this draft belongs to the current user and is actually a draft
        $verify_sql = "SELECT id, form_number FROM prehospital_forms WHERE id = ? AND created_by = ? AND status = 'draft'";
        $verify_stmt = db_query($verify_sql, [$draft_id, $created_by], true);
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
        $arrival_hospital_name, $arrival_hospital_time, $departure_hospital_time,
        $arrival_station_time, $persons_present_json,
        $patient_name, $date_of_birth, $age, $age_unit, $growth_status, $gender, $civil_status, $address, $zone, $occupation,
        $place_of_incident, $zone_landmark, $incident_time,
        $informant_name, $informant_address, $arrival_type, $call_arrival_time, $contact_number,
        $relationship_victim, $personal_belongings_json, $other_belongings, $patient_documentation,
        $photo_latitude, $photo_longitude, $photo_address, $photo_datetime,
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
        $team_leader_notes, $team_leader, $data_recorder, $logistic, $first_aider, $second_aider,
        $hospital_name, $endorsement_attachment_path, $endorsement_datetime,
        $narrative_report
    ];

    if ($is_updating_draft) {
        // UPDATE existing draft to completed status
        $sql = "UPDATE prehospital_forms SET
            form_number = ?, form_date = ?, departure_time = ?, arrival_time = ?, vehicle_used = ?, vehicle_details = ?, driver_name = ?,
            arrival_scene_location = ?, arrival_scene_time = ?, departure_scene_location = ?, departure_scene_time = ?,
            arrival_hospital_name = ?, arrival_hospital_time = ?, departure_hospital_time = ?,
            arrival_station_time = ?, persons_present = ?,
            patient_name = ?, date_of_birth = ?, age = ?, age_unit = ?, growth_status = ?, gender = ?, civil_status = ?, address = ?, zone = ?, occupation = ?,
            place_of_incident = ?, zone_landmark = ?, incident_time = ?,
            informant_name = ?, informant_address = ?, arrival_type = ?, call_arrival_time = ?, contact_number = ?,
            relationship_victim = ?, personal_belongings = ?, other_belongings = ?, patient_documentation = ?,
            photo_latitude = ?, photo_longitude = ?, photo_address = ?, photo_datetime = ?,
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
            hospital_name = ?, endorsement_attachment = ?, endorsement_datetime = ?,
            narrative_report = ?,
            status = 'completed',
            updated_at = NOW()
            WHERE id = ? AND created_by = ?";

        $params = array_merge($common_params, [$draft_id, $created_by]);
        $stmt = db_query($sql, $params, true);

        $form_id = $draft_id; // Use the existing draft ID
    } else {
        // INSERT new form
        $sql = "INSERT INTO prehospital_forms (
            form_number, form_date, departure_time, arrival_time, vehicle_used, vehicle_details, driver_name,
            arrival_scene_location, arrival_scene_time, departure_scene_location, departure_scene_time,
            arrival_hospital_name, arrival_hospital_time, departure_hospital_time,
            arrival_station_time, persons_present,
            patient_name, date_of_birth, age, age_unit, growth_status, gender, civil_status, address, zone, occupation,
            place_of_incident, zone_landmark, incident_time,
            informant_name, informant_address, arrival_type, call_arrival_time, contact_number,
            relationship_victim, personal_belongings, other_belongings, patient_documentation,
            photo_latitude, photo_longitude, photo_address, photo_datetime,
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
            hospital_name, endorsement_attachment, endorsement_datetime,
            narrative_report,
            created_by, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
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
            ?, ?, ?,
            ?,
            ?, 'completed'
        )";

        $params = array_merge($common_params, [$created_by]);
        $stmt = db_query($sql, $params, true);

        $form_id = $pdo->lastInsertId();
    }
    
    // Insert injuries if any
    if (!empty($injuries_data) && is_array($injuries_data)) {
        $injury_sql = "INSERT INTO injuries (form_id, injury_number, injury_type, body_view, body_part, coordinate_x, coordinate_y, notes)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        foreach ($injuries_data as $injury) {
            // Get body part, fallback to view-based default if not provided
            $body_part = !empty($injury['bodyPart']) ? sanitize($injury['bodyPart']) :
                        (($injury['view'] ?? 'front') === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)');

            $injury_params = [
                $form_id,
                (int)($injury['id'] ?? 0),
                sanitize($injury['type'] ?? 'other'),
                sanitize($injury['view'] ?? 'front'),
                $body_part,
                round((float)($injury['x'] ?? 0), 2),
                round((float)($injury['y'] ?? 0), 2),
                sanitize($injury['notes'] ?? '')
            ];

            db_query($injury_sql, $injury_params, true);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log activity
    log_activity('form_created', "Created form: $form_number for patient: $patient_name");

    // Set flash message for when user is redirected
    set_flash('Form saved successfully! Form Number: ' . $form_number, 'success');

    // Return JSON response for AJAX handling
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Form saved successfully! Form Number: ' . $form_number,
        'form_number' => $form_number,
        'redirect_url' => '../public/records.php'
    ]);
    exit;

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Form Save Error: " . $e->getMessage());

    set_flash('Error saving form: ' . $e->getMessage(), 'error');
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
