<?php
/**
 * Edit Pre-Hospital Care Record
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/version.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Require authentication
require_login();

// Get record ID
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($record_id <= 0) {
    set_flash('Invalid record ID', 'error');
    redirect('records.php');
}

// Get record details
$sql = "SELECT * FROM prehospital_forms WHERE id = ?";
$stmt = db_query($sql, [$record_id]);
$record = $stmt->fetch();

if (!$record) {
    set_flash('Record not found', 'error');
    redirect('records.php');
}

// Get injuries for this record
$injury_sql = "SELECT * FROM injuries WHERE form_id = ? ORDER BY injury_number";
$injury_stmt = db_query($injury_sql, [$record_id]);
$injuries = $injury_stmt->fetchAll();

// Generate CSRF token
$csrf_token = generate_token();

// Get current user
$current_user = get_auth_user();

// Decode JSON fields
$persons_present = json_decode($record['persons_present'] ?? '[]', true);
$persons_present = is_array($persons_present) ? $persons_present : [];

$personal_belongings = json_decode($record['personal_belongings'] ?? '[]', true);
$personal_belongings = is_array($personal_belongings) ? $personal_belongings : [];

// Normalize enum/radio fields to lowercase for consistent comparison with form values
// Note: vehicle_used is NOT lowercased because its ENUM includes camelCase 'fireTruck'
$record['gender'] = isset($record['gender']) ? strtolower($record['gender']) : '';
$record['civil_status'] = isset($record['civil_status']) ? strtolower($record['civil_status']) : '';
$record['arrival_type'] = isset($record['arrival_type']) ? strtolower($record['arrival_type']) : '';
$record['initial_spinal_injury'] = isset($record['initial_spinal_injury']) ? strtolower($record['initial_spinal_injury']) : '';
$record['followup_spinal_injury'] = isset($record['followup_spinal_injury']) ? strtolower($record['followup_spinal_injury']) : '';
$record['initial_helmet'] = isset($record['initial_helmet']) ? strtolower($record['initial_helmet']) : '';
$record['ob_placenta'] = isset($record['ob_placenta']) ? strtolower($record['ob_placenta']) : '';
$record['fast_face_drooping'] = isset($record['fast_face_drooping']) ? strtolower($record['fast_face_drooping']) : '';
$record['fast_arm_weakness'] = isset($record['fast_arm_weakness']) ? strtolower($record['fast_arm_weakness']) : '';
$record['fast_speech_difficulty'] = isset($record['fast_speech_difficulty']) ? strtolower($record['fast_speech_difficulty']) : '';
$record['fast_time_to_call'] = isset($record['fast_time_to_call']) ? strtolower($record['fast_time_to_call']) : '';

// Clean up date and time fields - don't show invalid/empty values
$dateTimeFields = [
    'departure_time', 'arrival_time', 'arrival_scene_time', 'departure_scene_time',
    'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time',
    'incident_time', 'call_arrival_time', 'initial_time', 'followup_time',
    'ob_delivery_time', 'endorsement_datetime'
];

foreach ($dateTimeFields as $field) {
    if (isset($record[$field])) {
        // Clear time fields if they are '00:00:00' or NULL or empty
        if ($record[$field] === '00:00:00' || $record[$field] === null || $record[$field] === '' ||
            $record[$field] === '0000-00-00 00:00:00') {
            $record[$field] = '';
        }
    }
}

// Clean up date-only fields
$dateFields = ['form_date', 'date_of_birth', 'ob_lmp', 'ob_edc'];
foreach ($dateFields as $field) {
    if (isset($record[$field])) {
        // Clear date fields if they are '0000-00-00' or NULL or empty
        if ($record[$field] === '0000-00-00' || $record[$field] === null || $record[$field] === '' ||
            $record[$field] === '0000-00-00 00:00:00') {
            $record[$field] = '';
        }
    }
}
$care_management = json_decode($record['care_management'] ?? '[]', true);
$care_management = is_array($care_management) ? $care_management : [];

$chief_complaints = json_decode($record['chief_complaints'] ?? '[]', true);
$chief_complaints = is_array($chief_complaints) ? $chief_complaints : [];

$initial_consciousness = json_decode($record['initial_consciousness'] ?? '[]', true);
if (!is_array($initial_consciousness)) {
    // Handle legacy single ENUM value (e.g. "alert") by wrapping in array
    $initial_consciousness = !empty($record['initial_consciousness']) ? [strtolower($record['initial_consciousness'])] : [];
}

$followup_consciousness = json_decode($record['followup_consciousness'] ?? '[]', true);
if (!is_array($followup_consciousness)) {
    // Handle legacy single ENUM value by wrapping in array
    $followup_consciousness = !empty($record['followup_consciousness']) ? [strtolower($record['followup_consciousness'])] : [];
}

// Decode helmet status (multi-select)
$initial_helmet = json_decode($record['initial_helmet'] ?? '[]', true);
if (!is_array($initial_helmet)) {
    // Fallback for old single-value format
    $initial_helmet = !empty($record['initial_helmet']) ? [strtolower($record['initial_helmet'])] : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Record - <?php echo e($record['form_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <link href="css/prehospital-form.css" rel="stylesheet">
    <style>
        /* Sidebar Layout Compatibility Fixes */
        body {
            overflow: auto !important;
            height: auto !important;
        }

        .content {
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding-bottom: 0 !important;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .form-container {
            overflow: visible !important;
            height: auto !important;
            max-width: 100%;
            margin-bottom: 0;
            padding-bottom: 0;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .form-body {
            overflow: visible !important;
            max-height: none !important;
            padding-bottom: 1rem !important;
            flex: 1;
        }

        .navigation-buttons {
            position: sticky !important;
            bottom: 0 !important;
            left: 0;
            right: 0;
            background: #ffffff !important;
            margin-top: auto !important;
            margin-bottom: 0 !important;
            z-index: 1000 !important;
            border-top: 4px solid #0066cc !important;
            padding: 1rem 2rem !important;
            display: flex !important;
            justify-content: space-between !important;
            gap: 1rem !important;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.08) !important;
            flex-shrink: 0 !important;
        }

        /* Ensure all content is visible */
        .tab-content {
            overflow: visible !important;
            padding-bottom: 0 !important;
        }

        /* Consistent spacing for all tabs */
        .tab-pane {
            padding-bottom: 0 !important;
        }

        /* Minimal space at the bottom of form content */
        .form-section {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }

        /* Fix Notiflix Report button underline */
        div[id^="NotiflixReportWrap"] button {
            text-decoration: none !important;
        }

        div[id^="NotiflixReportWrap"] button:hover,
        div[id^="NotiflixReportWrap"] button:focus,
        div[id^="NotiflixReportWrap"] button:active {
            text-decoration: none !important;
        }

        /* Clean Corporate Navigation Tabs with Subtle Arrows */
        .tabs-container {
            margin-bottom: 1.5rem;
            overflow-x: auto;
            overflow-y: visible;
        }

        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            display: flex;
            flex-wrap: nowrap;
            gap: 0;
            padding: 0;
            position: relative;
        }

        .nav-item {
            flex: 0 0 auto;
            min-width: auto;
            position: relative;
            margin: 0;
        }

        .nav-link {
            background: transparent;
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 0.75rem 0.875rem;
            font-weight: 600;
            font-size: 0.875rem;
            font-style: italic;
            text-align: center;
            position: relative;
            border-radius: 0;
            transition: all 0.2s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        /* Subtle arrow separator */
        .nav-item:not(:last-child) .nav-link::after {
            content: '›';
            position: absolute;
            right: -4px;
            font-size: 1.25rem;
            color: #dee2e6;
            font-weight: 300;
            z-index: 1;
        }

        .nav-link:hover {
            color: #0066cc;
            background: rgba(0, 102, 204, 0.05);
            border-bottom-color: #0066cc;
        }

        .nav-link.active {
            color: #0066cc;
            background: transparent;
            border-bottom-color: #0066cc;
            font-weight: 700;
        }

        .nav-link.active::after {
            color: #0066cc !important;
        }

        /* Completed tabs - subtle green indicator */
        .nav-link.completed:not(.active) {
            color: #059669;
            position: relative;
        }

        .nav-link.completed:not(.active)::before {
            content: '✓';
            position: absolute;
            left: 0.5rem;
            font-size: 0.75rem;
            color: #059669;
        }

        /* Mobile responsive - cleaner approach */
        @media (max-width: 992px) {
            .nav-link {
                font-size: 0.8rem;
                padding: 0.65rem 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .nav-link {
                font-size: 0.75rem;
                padding: 0.6rem 0.65rem;
                gap: 0.25rem;
            }

            .nav-item:not(:last-child) .nav-link::after {
                font-size: 1.1rem;
                right: -3px;
            }
        }

        @media (max-width: 576px) {
            .tabs-container {
                margin-left: -0.5rem;
                margin-right: -0.5rem;
                padding: 0 0.25rem;
            }

            .nav-link {
                font-size: 0.7rem;
                padding: 0.5rem 0.4rem;
            }

            .nav-item:not(:last-child) .nav-link::after {
                display: none; /* Hide arrows on very small screens */
            }
        }

        /* Flatpickr Mobile Optimization */
        .flatpickr-mobile .flatpickr-time {
            max-height: none !important;
        }

        .flatpickr-mobile .flatpickr-time input {
            font-size: 18px !important;
            padding: 12px !important;
        }

        .flatpickr-mobile .numInputWrapper {
            width: 70px !important;
        }

        .flatpickr-calendar {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
            border-radius: 8px !important;
            margin: 0 auto !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
        }

        /* Prevent calendar from being cut off */
        .flatpickr-calendar.open {
            z-index: 9999 !important;
        }

        /* Dark backdrop for mobile calendar */
        @media (max-width: 768px) {
            /* Add backdrop when calendar is open */
            body.flatpickr-mobile-open::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 9998;
            }

            .flatpickr-calendar {
                position: fixed !important;
                left: 50% !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
                margin: 0 !important;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
            }
        }

        .flatpickr-time input.flatpickr-hour,
        .flatpickr-time input.flatpickr-minute {
            font-weight: 600 !important;
            font-size: 16px !important;
        }

        .flatpickr-time .flatpickr-time-separator {
            font-weight: 600 !important;
            font-size: 16px !important;
        }

        /* Mobile-specific improvements */
        @media (max-width: 768px) {
            /* Make native datetime/time inputs more touch-friendly when flatpickr is not active */
            input[type="time"],
            input[type="datetime-local"],
            input[type="date"] {
                font-size: 16px !important; /* Prevents zoom on iOS */
                min-height: 48px !important; /* Better touch target */
                padding: 12px !important;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
            }

            /* Flatpickr calendar mobile optimization */
            .flatpickr-calendar {
                width: 320px !important;
                max-width: calc(100vw - 20px) !important;
                font-size: 16px !important;
                position: fixed !important;
                left: 50% !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
                margin: 0 !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
            }

            /* Flatpickr wrapper positioning */
            .flatpickr-calendar.inline {
                position: relative !important;
                transform: none !important;
                left: auto !important;
                top: auto !important;
            }

            /* Larger day cells for easier tapping */
            .flatpickr-calendar .flatpickr-day {
                height: 42px !important;
                line-height: 42px !important;
                max-width: 42px !important;
                font-size: 16px !important;
            }

            /* Month navigation buttons */
            .flatpickr-calendar .flatpickr-prev-month,
            .flatpickr-calendar .flatpickr-next-month {
                padding: 12px !important;
            }

            /* Time input improvements */
            .flatpickr-time input {
                font-size: 20px !important;
                padding: 14px 8px !important;
                min-height: 50px !important;
            }

            .flatpickr-time .arrowUp,
            .flatpickr-time .arrowDown {
                padding: 8px !important;
                height: 36px !important;
                width: 36px !important;
            }

            .flatpickr-time .arrowUp:after,
            .flatpickr-time .arrowDown:after {
                border-width: 6px !important;
            }

            .numInputWrapper {
                width: 80px !important;
            }

            /* AM/PM toggle styling for mobile */
            .flatpickr-am-pm {
                font-size: 18px !important;
                font-weight: 700 !important;
                padding: 14px 12px !important;
                min-width: 60px !important;
                cursor: pointer !important;
                background: #0066cc !important;
                color: white !important;
                border-radius: 6px !important;
                margin-left: 8px !important;
            }

            .flatpickr-am-pm:hover {
                background: #0052a3 !important;
            }

            /* Month dropdown for better mobile selection */
            .flatpickr-monthDropdown-months {
                font-size: 16px !important;
                padding: 8px !important;
            }

            /* Year input */
            .flatpickr-current-month input.cur-year {
                font-size: 16px !important;
                padding: 4px !important;
            }

            /* Flatpickr input field styling */
            .flatpickr-input {
                font-size: 16px !important;
                min-height: 48px !important;
                padding: 12px !important;
            }

            /* Flatpickr weekday labels */
            .flatpickr-weekdays {
                padding: 8px 0 !important;
            }

            .flatpickr-weekday {
                font-size: 14px !important;
                font-weight: 600 !important;
            }

            /* Current month/year dropdown styling */
            .flatpickr-current-month {
                padding: 12px 0 !important;
            }

            .flatpickr-current-month .flatpickr-monthDropdown-months {
                min-height: 40px !important;
            }
        }

        /* Extra small devices */
        @media (max-width: 576px) {
            .flatpickr-calendar {
                width: calc(100vw - 30px) !important;
                max-width: 340px !important;
                padding: 8px !important;
            }

            input[type="time"],
            input[type="datetime-local"],
            input[type="date"] {
                font-size: 16px !important;
                min-height: 50px !important;
            }

            /* Smaller day cells for very small screens */
            .flatpickr-calendar .flatpickr-day {
                height: 38px !important;
                line-height: 38px !important;
                max-width: 38px !important;
                font-size: 14px !important;
            }
        }

        /* UPPERCASE ALL TEXT INPUTS */
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="url"],
        textarea,
        select {
            text-transform: uppercase !important;
        }

        /* Keep placeholders in normal case for readability */
        input::placeholder,
        textarea::placeholder {
            text-transform: none !important;
        }

        /* ========================================
           DESKTOP/LAPTOP FIT OPTIMIZATION
           ======================================== */
        @media (min-width: 1024px) {
            .content {
                max-height: 100vh;
                overflow-y: auto;
            }

            .form-body {
                min-height: auto !important;
                padding-bottom: 1rem !important;
            }

            .navigation-buttons {
                position: sticky !important;
                bottom: 0;
                margin-top: 0 !important;
            }
        }

        /* Standard laptop (1366x768) specific */
        @media (min-width: 1024px) and (max-height: 800px) {
            .content {
                max-height: 100vh;
            }

            .form-header {
                padding: 0.65rem 1.5rem !important;
            }

            .form-header h1 {
                font-size: 1.1rem !important;
            }

            .form-header .subtitle {
                font-size: 0.75rem !important;
                margin-top: 0.15rem !important;
            }

            .progress-container {
                padding: 0.4rem 1.5rem 0.35rem !important;
            }

            .progress {
                height: 4px !important;
            }

            #stepIndicator {
                font-size: 0.75rem !important;
            }

            .tabs-container {
                margin-bottom: 0.5rem !important;
            }

            .nav-link {
                padding: 0.5rem 0.65rem !important;
                font-size: 0.75rem !important;
            }

            .form-body {
                padding: 0.65rem 1.5rem !important;
                min-height: auto !important;
            }

            .form-section {
                margin-bottom: 0 !important;
            }

            .navigation-buttons {
                padding: 0.65rem 1.5rem !important;
                border-top-width: 2px !important;
            }

            .navigation-buttons .btn {
                padding: 0.5rem 1.25rem !important;
                font-size: 0.8rem !important;
            }
        }

        /* Large desktop screens (1920x1080+) */
        @media (min-width: 1600px) {
            .form-section {
                max-width: 1400px;
            }
        }
    </style>
</head>
<body class="loading">
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="form-container">
        <div class="form-header">
            <div class="skeleton-overlay skeleton skeleton-header"></div>
            <h1><i class="bi bi-pencil-square"></i> EDIT PRE-HOSPITAL CARE FORM</h1>
            <p class="subtitle" style="margin-left: 2.15rem;">Record #<?php echo e($record['form_number']); ?> • Edited by <?php echo e($current_user['full_name']); ?></p>
        </div>

        <div class="progress-container">
            <div class="skeleton-overlay skeleton skeleton-progress"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.85rem; font-weight: 600; color: #0066cc;">
                    <i class="bi bi-list-check"></i> Form Progress
                </span>
                <span id="stepIndicator" style="font-size: 0.85rem; font-weight: 500; color: #6c757d;">
                    Step 1 of 6
                </span>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" id="progressBar"></div>
            </div>
        </div>

        <div class="tabs-container">
            <div class="skeleton-overlay skeleton skeleton-tabs"></div>
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab1" data-bs-toggle="tab" data-bs-target="#section1" type="button" role="tab">
                        Basic Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab2" data-bs-toggle="tab" data-bs-target="#section2" type="button" role="tab">
                        Patient
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab3" data-bs-toggle="tab" data-bs-target="#section3" type="button" role="tab">
                        Emergency
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab4" data-bs-toggle="tab" data-bs-target="#section4" type="button" role="tab">
                        Vitals
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab5" data-bs-toggle="tab" data-bs-target="#section5" type="button" role="tab">
                        Assessment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab6" data-bs-toggle="tab" data-bs-target="#section6" type="button" role="tab">
                        Team
                    </button>
                </li>
            </ul>
        </div>

        <?php // Flash messages now handled by Notiflix - see JavaScript below ?>

        <form id="editForm" class="form-body" method="POST" action="../api/update_record.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="record_id" value="<?php echo $record_id; ?>">
            <input type="hidden" name="injuries_data" id="injuriesData" value='<?php echo json_encode($injuries); ?>'>
            <input type="hidden" name="narrative_report" id="narrativeReportField" value="<?php echo e($record['narrative_report'] ?? ''); ?>">
            
            <div class="tab-content" id="formTabContent">
                <!-- Section 1: Basic Information -->
                <div class="tab-pane fade show active" id="section1" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-info-circle"></i> Basic Information
                        </div>

                        <div class="grid-3 mb-section">
                            <div>
                                <label for="formDate" class="form-label required-field">Date</label>
                                <input type="date" class="form-control" id="formDate" name="form_date" 
                                       value="<?php echo e($record['form_date']); ?>" required>
                            </div>
                            <div>
                                <label for="depTime" class="form-label required-field">Departure Time</label>
                                <input type="text" class="form-control time-input-12hr" id="depTime" name="departure_time"
                                       value="<?php echo e($record['departure_time']); ?>" placeholder="2:30 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true" required>
                            </div>
                            <div>
                                <label for="arrTime" class="form-label">Arrival Time</label>
                                <input type="text" class="form-control time-input-12hr" id="arrTime" name="arrival_time"
                                       value="<?php echo e($record['arrival_time']); ?>" placeholder="3:45 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="form-group-compact">
                            <label class="form-label">Vehicle Used</label>
                            <div class="inline-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_used" id="ambulance" value="ambulance"
                                           <?php echo $record['vehicle_used'] === 'ambulance' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ambulance">Ambulance</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_used" id="fireTruck" value="fireTruck"
                                           <?php echo $record['vehicle_used'] === 'fireTruck' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="fireTruck">Fire Truck</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_used" id="othersVehicle" value="others"
                                           <?php echo $record['vehicle_used'] === 'others' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="othersVehicle">Others</label>
                                </div>
                            </div>
                            <input type="hidden" name="vehicle_details" id="vehicleDetails" value="<?php echo e($record['vehicle_details']); ?>">
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrSceneLocation" class="form-label">Arrival at Scene - Location</label>
                                <input type="text" class="form-control" id="arrSceneLocation" name="arrival_scene_location"
                                       value="<?php echo e($record['arrival_scene_location']); ?>" placeholder="Scene location">
                            </div>
                            <div>
                                <label for="arrSceneTime" class="form-label">Arrival at Scene - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="arrSceneTime" name="arrival_scene_time"
                                       value="<?php echo e($record['arrival_scene_time']); ?>" placeholder="4:15 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="depSceneLocation" class="form-label">Departure from Scene - Location</label>
                                <input type="text" class="form-control" id="depSceneLocation" name="departure_scene_location"
                                       value="<?php echo e($record['departure_scene_location']); ?>" placeholder="Departure location">
                            </div>
                            <div>
                                <label for="depSceneTime" class="form-label">Departure from Scene - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="depSceneTime" name="departure_scene_time"
                                       value="<?php echo e($record['departure_scene_time']); ?>" placeholder="5:00 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrHospName" class="form-label">Arrival at Hospital - Name</label>
                                <input type="text" class="form-control" id="arrHospName" name="arrival_hospital_name"
                                       value="<?php echo e($record['arrival_hospital_name']); ?>" placeholder="Hospital name">
                            </div>
                            <div>
                                <label for="arrHospTime" class="form-label">Arrival at Hospital - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="arrHospTime" name="arrival_hospital_time"
                                       value="<?php echo e($record['arrival_hospital_time']); ?>" placeholder="5:30 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="depHospTime" class="form-label">Departure from Hospital - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="depHospTime" name="departure_hospital_time"
                                       value="<?php echo e($record['departure_hospital_time']); ?>" placeholder="6:00 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="driver" class="form-label">Driver</label>
                                <input type="text" class="form-control" id="driver" name="driver_name" 
                                       value="<?php echo e($record['driver_name']); ?>" placeholder="Driver name">
                            </div>
                            <div>
                                <label for="arrStation" class="form-label">Arrival at Station</label>
                                <input type="text" class="form-control time-input-12hr" id="arrStation" name="arrival_station_time"
                                       value="<?php echo e($record['arrival_station_time']); ?>" placeholder="6:30 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="form-group-compact">
                            <label class="form-label">Persons Present Upon Arrival</label>
                            <div class="checkbox-grid">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="police" name="persons_present[]" value="police"
                                           <?php echo in_array('police', $persons_present) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="police">Police</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="brgyOfficials" name="persons_present[]" value="brgyOfficials"
                                           <?php echo in_array('brgyOfficials', $persons_present) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="brgyOfficials">Barangay Officials</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="relatives" name="persons_present[]" value="relatives"
                                           <?php echo in_array('relatives', $persons_present) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="relatives">Relatives</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bystanders" name="persons_present[]" value="bystanders"
                                           <?php echo in_array('bystanders', $persons_present) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="bystanders">Bystanders</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="nonePresent" name="persons_present[]" value="none"
                                           <?php echo in_array('none', $persons_present) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="nonePresent">None</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Patient Information -->
                <div class="tab-pane fade" id="section2" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-person-fill"></i> Patient Information
                        </div>

                        <div class="grid-2 mb-section">
                            <div style="grid-column: span 2;">
                                <label for="patientName" class="form-label required-field">Patient Name</label>
                                <input type="text" class="form-control" id="patientName" name="patient_name" 
                                       value="<?php echo e($record['patient_name']); ?>" required>
                            </div>
                            <div>
                                <label for="dateOfBirth" class="form-label required-field">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth" 
                                       value="<?php echo e($record['date_of_birth']); ?>" required>
                            </div>
                            <div>
                                <label for="age" class="form-label required-field">Age</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="age" name="age"
                                           value="<?php echo e($record['age']); ?>" placeholder="Enter age" required>
                                    <select class="form-select" id="ageUnit" name="age_unit" style="max-width: 110px;">
                                        <option value="years" <?php echo ($record['age_unit'] ?? 'years') === 'years' ? 'selected' : ''; ?>>Years</option>
                                        <option value="months" <?php echo ($record['age_unit'] ?? 'years') === 'months' ? 'selected' : ''; ?>>Months</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label for="growthStatus" class="form-label">Growth Status</label>
                                <select class="form-select" id="growthStatus" name="growth_status">
                                    <option value="">Select...</option>
                                    <option value="infant" <?php echo ($record['growth_status'] ?? '') === 'infant' ? 'selected' : ''; ?>>Infant (0-1 year)</option>
                                    <option value="child" <?php echo ($record['growth_status'] ?? '') === 'child' ? 'selected' : ''; ?>>Child (2-12 years)</option>
                                    <option value="adult" <?php echo ($record['growth_status'] ?? '') === 'adult' ? 'selected' : ''; ?>>Adult (13-59 years)</option>
                                    <option value="senior" <?php echo ($record['growth_status'] ?? '') === 'senior' ? 'selected' : ''; ?>>Senior (60+ years)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label class="form-label required-field">Gender</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="male" value="male"
                                               <?php echo $record['gender'] === 'male' ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="female" value="female"
                                               <?php echo $record['gender'] === 'female' ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="female">Female</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Civil Status</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="single" value="single"
                                               <?php echo $record['civil_status'] === 'single' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="single">Single</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="married" value="married"
                                               <?php echo $record['civil_status'] === 'married' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="married">Married</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="widowed" value="widowed"
                                               <?php echo $record['civil_status'] === 'widowed' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="widowed">Widowed</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="divorced" value="divorced"
                                               <?php echo $record['civil_status'] === 'divorced' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="divorced">Divorced</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="separated" value="separated"
                                               <?php echo $record['civil_status'] === 'separated' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="separated">Separated</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address"
                                       value="<?php echo e($record['address']); ?>" placeholder="Complete address">
                            </div>
                            <div>
                                <label for="zone" class="form-label">Zone</label>
                                <input type="text" class="form-control" id="zone" name="zone"
                                       value="<?php echo e($record['zone']); ?>" placeholder="Zone/Purok">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" name="occupation"
                                       value="<?php echo e($record['occupation']); ?>" placeholder="Patient's occupation">
                            </div>
                            <div>
                                <label for="placeOfIncident" class="form-label">Place of Incident</label>
                                <input type="text" class="form-control" id="place_of_incident" name="place_of_incident"
                                       value="<?php echo e($record['place_of_incident']); ?>" placeholder="Location where incident occurred">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="zoneLandmark" class="form-label">Zone/Landmark</label>
                                <input type="text" class="form-control" id="zoneLandmark" name="zone_landmark"
                                       value="<?php echo e($record['zone_landmark']); ?>" placeholder="Nearest landmark">
                            </div>
                            <div>
                                <label for="incidentTime" class="form-label">Time of Incident</label>
                                <input type="text" class="form-control time-input-12hr" id="incidentTime" name="incident_time"
                                       value="<?php echo e($record['incident_time']); ?>" placeholder="3:00 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <div class="section-title" style="margin-top: 1.5rem;">
                            <i class="bi bi-telephone"></i> Informant Details
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="informant" class="form-label">Informant Name</label>
                                <input type="text" class="form-control" id="informant" name="informant_name"
                                       value="<?php echo e($record['informant_name']); ?>" placeholder="Name of informant">
                            </div>
                            <div>
                                <label for="informantAddress" class="form-label">Informant Address</label>
                                <input type="text" class="form-control" id="informantAddress" name="informant_address"
                                       value="<?php echo e($record['informant_address']); ?>" placeholder="Informant's address">
                            </div>
                        </div>

                        <div class="grid-3 mb-section">
                            <div>
                                <label class="form-label">Walk In / Call</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="arrival_type" id="walkIn" value="walkIn"
                                               <?php echo $record['arrival_type'] === 'walkIn' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="walkIn">Walk In</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="arrival_type" id="call" value="call"
                                               <?php echo $record['arrival_type'] === 'call' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="call">Call</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="callArrTime" class="form-label">Call/Arrival Time</label>
                                <input type="text" class="form-control time-input-12hr" id="callArrTime" name="call_arrival_time"
                                       value="<?php echo e($record['call_arrival_time']); ?>" placeholder="2:45 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="cpNumber" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="cpNumber" name="contact_number"
                                       value="<?php echo e($record['contact_number']); ?>" placeholder="Contact number">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="relationshipVictim" class="form-label">Relationship to Victim</label>
                                <input type="text" class="form-control" id="relationshipVictim" name="relationship_victim"
                                       value="<?php echo e($record['relationship_victim']); ?>" placeholder="e.g., Spouse, Parent, Sibling">
                            </div>
                            <div>
                                <label for="personalBelongings" class="form-label">Personal Belongings</label>
                                <select class="form-select" id="personalBelongings" name="personal_belongings[]" multiple size="4">
                                    <option value="wallet" <?php echo in_array('wallet', $personal_belongings) ? 'selected' : ''; ?>>Wallet</option>
                                    <option value="cellphone" <?php echo in_array('cellphone', $personal_belongings) ? 'selected' : ''; ?>>Cellphone</option>
                                    <option value="jewelry" <?php echo in_array('jewelry', $personal_belongings) ? 'selected' : ''; ?>>Jewelry</option>
                                    <option value="watch" <?php echo in_array('watch', $personal_belongings) ? 'selected' : ''; ?>>Watch</option>
                                    <option value="keys" <?php echo in_array('keys', $personal_belongings) ? 'selected' : ''; ?>>Keys</option>
                                    <option value="bag" <?php echo in_array('bag', $personal_belongings) ? 'selected' : ''; ?>>Bag</option>
                                    <option value="documents" <?php echo in_array('documents', $personal_belongings) ? 'selected' : ''; ?>>Documents/IDs</option>
                                    <option value="cash" <?php echo in_array('cash', $personal_belongings) ? 'selected' : ''; ?>>Cash</option>
                                    <option value="none" <?php echo in_array('none', $personal_belongings) ? 'selected' : ''; ?>>None</option>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple items</small>
                            </div>
                        </div>

                        <div class="mb-section">
                            <label for="otherBelongings" class="form-label">Other Belongings (specify)</label>
                            <input type="text" class="form-control" id="otherBelongings" name="other_belongings"
                                   value="<?php echo e($record['other_belongings']); ?>" placeholder="List other belongings not mentioned above">
                        </div>

                        <!-- Patient Documentation - Corporate Design -->
                        <div class="patient-documentation-card">
                            <div class="patient-doc-header">
                                <div class="patient-doc-icon">
                                    <i class="bi bi-camera-fill"></i>
                                </div>
                                <div class="patient-doc-title">
                                    <h6>Patient Documentation</h6>
                                    <span>Capture or upload patient photo for records</span>
                                </div>
                            </div>

                            <div class="patient-doc-body">
                                <?php if (!empty($record['patient_documentation'])): ?>
                                <!-- Existing Image Display -->
                                <div class="existing-patient-doc">
                                    <div class="existing-doc-label">
                                        <i class="bi bi-image-fill"></i>
                                        <span>Current Patient Photo</span>
                                    </div>
                                    <div class="preview-card">
                                        <div class="preview-image-wrapper" onclick="openExistingPatientImageModal('<?php echo e($record['patient_documentation']); ?>')">
                                            <img src="<?php echo e($record['patient_documentation']); ?>" alt="Patient Documentation">
                                            <div class="preview-overlay">
                                                <i class="bi bi-zoom-in"></i>
                                                <span>Click to enlarge</span>
                                            </div>
                                        </div>
                                        <div class="preview-actions">
                                            <span class="preview-label">
                                                <i class="bi bi-check-circle-fill"></i>
                                                Photo on file
                                            </span>
                                            <a href="<?php echo e($record['patient_documentation']); ?>" target="_blank" class="view-original-btn">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                                View Original
                                            </a>
                                        </div>
                                    </div>
                                    <div class="replace-photo-label">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span>Replace with new photo (optional)</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Upload Controls -->
                                <div class="patient-doc-controls">
                                    <button type="button" class="upload-method-btn" id="openPatientCameraBtn">
                                        <div class="upload-method-icon camera">
                                            <i class="bi bi-camera-fill"></i>
                                        </div>
                                        <div class="upload-method-text">
                                            <span class="upload-method-title">Open Camera</span>
                                            <span class="upload-method-desc">Take a photo now</span>
                                        </div>
                                    </button>

                                    <div class="upload-divider">
                                        <span>or</span>
                                    </div>

                                    <label class="upload-method-btn" for="patientFileUpload">
                                        <div class="upload-method-icon file">
                                            <i class="bi bi-cloud-arrow-up-fill"></i>
                                        </div>
                                        <div class="upload-method-text">
                                            <span class="upload-method-title">Upload File</span>
                                            <span class="upload-method-desc">JPG, PNG, GIF, WebP (max 5MB)</span>
                                        </div>
                                        <input type="file" class="hidden-file-input" id="patientFileUpload" name="patient_documentation" accept="image/jpeg,image/png,image/gif,image/webp" onchange="validatePatientFileUpload(this)">
                                    </label>
                                </div>

                                <!-- Camera Container -->
                                <div id="patientCameraContainer" class="patient-camera-container">
                                    <div class="camera-viewport">
                                        <video id="patientCameraVideo" autoplay playsinline></video>
                                    </div>
                                    <div class="camera-controls">
                                        <button type="button" class="camera-btn capture" id="capturePatientBtn" onclick="capturePatientPhoto()">
                                            <i class="bi bi-circle-fill"></i>
                                            <span>Capture</span>
                                        </button>
                                        <button type="button" class="camera-btn close-cam" id="closePatientCameraBtn" onclick="closePatientCamera()">
                                            <i class="bi bi-x-lg"></i>
                                            <span>Close</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- New Image Preview -->
                                <div id="patientPreviewContainer" class="patient-preview-container">
                                    <div class="new-photo-label">
                                        <i class="bi bi-plus-circle-fill"></i>
                                        <span>New Photo Preview</span>
                                    </div>
                                    <div class="preview-card">
                                        <div class="preview-image-wrapper" onclick="openPatientImageModal()">
                                            <img id="patientAttachmentPreview" src="" alt="Patient Documentation Preview">
                                            <div class="preview-overlay">
                                                <i class="bi bi-zoom-in"></i>
                                                <span>Click to enlarge</span>
                                            </div>
                                        </div>
                                        <div class="preview-actions">
                                            <span class="preview-label">
                                                <i class="bi bi-check-circle-fill"></i>
                                                New photo ready
                                            </span>
                                            <button type="button" class="remove-preview-btn" id="removePatientAttachmentBtn" onclick="removePatientAttachment()">
                                                <i class="bi bi-trash3"></i>
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Error Message -->
                                <div id="patientUploadError" class="patient-upload-error">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Image Preview Modal -->
                        <div id="patientImageModal" class="image-preview-modal" onclick="closePatientImageModal()">
                            <div class="modal-content-custom" onclick="event.stopPropagation()">
                                <button type="button" class="modal-close-btn" onclick="closePatientImageModal()">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <img id="patientModalImage" src="" alt="Patient Documentation">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Emergency Call & Care -->
                <div class="tab-pane fade" id="section3" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-telephone-fill"></i> Type of Emergency Call
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="medical" name="emergency_type[]" value="medical"
                                           <?php echo $record['emergency_medical'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="medical"><strong>Medical</strong></label>
                                </div>
                                <input type="text" class="form-control" id="medicalSpecify" name="medical_specify"
                                       value="<?php echo e($record['emergency_medical_details']); ?>" placeholder="Specify medical condition">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="trauma" name="emergency_type[]" value="trauma"
                                           <?php echo $record['emergency_trauma'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="trauma"><strong>Trauma</strong></label>
                                </div>
                                <input type="text" class="form-control" id="traumaSpecify" name="trauma_specify"
                                       value="<?php echo e($record['emergency_trauma_details']); ?>" placeholder="Specify trauma type">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="ob" name="emergency_type[]" value="ob"
                                           <?php echo $record['emergency_ob'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ob"><strong>OB</strong></label>
                                </div>
                                <input type="text" class="form-control" id="obSpecify" name="ob_specify"
                                       value="<?php echo e($record['emergency_ob_details']); ?>" placeholder="Specify OB condition">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="general" name="emergency_type[]" value="general"
                                           <?php echo $record['emergency_general'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="general"><strong>General</strong></label>
                                </div>
                                <input type="text" class="form-control" id="generalSpecify" name="general_specify"
                                       value="<?php echo e($record['emergency_general_details']); ?>" placeholder="Specify general condition">
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-heart-pulse-fill"></i> Care Management
                        </div>

                        <div class="form-group-compact">
                            <div class="checkbox-grid">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="immobilization" name="care_management[]" value="immobilization"
                                           <?php echo in_array('immobilization', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="immobilization">Immobilization</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cpr" name="care_management[]" value="cpr"
                                           <?php echo in_array('cpr', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="cpr">CPR</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bandaging" name="care_management[]" value="bandaging"
                                           <?php echo in_array('bandaging', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="bandaging">Bandaging</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="woundCare" name="care_management[]" value="woundCare"
                                           <?php echo in_array('woundCare', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="woundCare">Wound Care</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cCollar" name="care_management[]" value="cCollar"
                                           <?php echo in_array('cCollar', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="cCollar">C-Collar</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="aed" name="care_management[]" value="aed"
                                           <?php echo in_array('aed', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="aed">AED</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ked" name="care_management[]" value="ked"
                                           <?php echo in_array('ked', $care_management) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ked">KED</label>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="o2LPM" class="form-label">O² (LPM via)</label>
                                <input type="text" class="form-control" id="o2LPM" name="oxygen_lpm"
                                       value="<?php echo e($record['oxygen_lpm']); ?>" placeholder="Oxygen delivery method and rate">
                            </div>
                            <div>
                                <label for="othersCare" class="form-label">Others</label>
                                <input type="text" class="form-control" id="othersCare" name="other_care"
                                       value="<?php echo e($record['other_care']); ?>" placeholder="Other care management">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Vitals -->
                <div class="tab-pane fade" id="section4" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-activity"></i> Initial Vital Signs
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="initialTime" class="form-label">Time</label>
                                <input type="text" class="form-control time-input-12hr" id="initialTime" name="initial_time"
                                       value="<?php echo e($record['initial_time']); ?>" placeholder="4:00 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="initialBP" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="initialBP" name="initial_bp"
                                       value="<?php echo e($record['initial_bp']); ?>" placeholder="120/80">
                            </div>
                            <div>
                                <label for="initialTemp" class="form-label">Temp (°C)</label>
                                <input type="number" class="form-control" id="initialTemp" name="initial_temp"
                                       value="<?php echo e($record['initial_temp']); ?>" step="0.1" placeholder="36.5">
                            </div>
                            <div>
                                <label for="initialPulse" class="form-label">Pulse (BPM)</label>
                                <input type="number" class="form-control" id="initialPulse" name="initial_pulse"
                                       value="<?php echo e($record['initial_pulse']); ?>" placeholder="72">
                            </div>
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="initialResp" class="form-label">Resp. Rate</label>
                                <input type="number" class="form-control" id="initialResp" name="initial_resp"
                                       value="<?php echo e($record['initial_resp_rate']); ?>" placeholder="16">
                            </div>
                            <div>
                                <label for="initialPainScore" class="form-label">Pain Score (0-10)</label>
                                <input type="number" class="form-control" id="initialPainScore" name="initial_pain_score"
                                       value="<?php echo e($record['initial_pain_score']); ?>" min="0" max="10" placeholder="0-10">
                            </div>
                            <div>
                                <label for="initialSPO2" class="form-label">SPO2 %</label>
                                <input type="number" class="form-control" id="initialSPO2" name="initial_spo2"
                                       value="<?php echo e($record['initial_spo2']); ?>" min="0" max="100" placeholder="95-100">
                            </div>
                            <div>
                                <label class="form-label">Spinal Injury</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_spinal_injury" id="initialSpinalYes" value="yes"
                                               <?php echo $record['initial_spinal_injury'] === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialSpinalYes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_spinal_injury" id="initialSpinalNo" value="no"
                                               <?php echo $record['initial_spinal_injury'] === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialSpinalNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label class="form-label">Level of Consciousness</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialAlert" value="alert"
                                               <?php echo in_array('alert', $initial_consciousness) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialAlert">Alert</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialVerbal" value="verbal"
                                               <?php echo in_array('verbal', $initial_consciousness) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialVerbal">Verbal</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialPain" value="pain"
                                               <?php echo in_array('pain', $initial_consciousness) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialPain">Pain</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialUnconscious" value="unconscious"
                                               <?php echo in_array('unconscious', $initial_consciousness) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialUnconscious">Unconscious</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Helmet Status</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_helmet[]" id="initialHelmetAB" value="ab"
                                               <?php echo in_array('ab', $initial_helmet) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialHelmetAB">+ AB</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_helmet[]" id="initialNoHelmet" value="none"
                                               <?php echo in_array('none', $initial_helmet) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="initialNoHelmet">No Helmet</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-arrow-repeat"></i> Follow-up Vital Signs
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="followupTime" class="form-label">Time</label>
                                <input type="text" class="form-control time-input-12hr" id="followupTime" name="followup_time"
                                       value="<?php echo e($record['followup_time']); ?>" placeholder="4:30 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="followupBP" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="followupBP" name="followup_bp"
                                       value="<?php echo e($record['followup_bp']); ?>" placeholder="120/80">
                            </div>
                            <div>
                                <label for="followupTemp" class="form-label">Temp (°C)</label>
                                <input type="number" class="form-control" id="followupTemp" name="followup_temp"
                                       value="<?php echo e($record['followup_temp']); ?>" step="0.1" placeholder="36.5">
                            </div>
                            <div>
                                <label for="followupPulse" class="form-label">Pulse (BPM)</label>
                                <input type="number" class="form-control" id="followupPulse" name="followup_pulse"
                                       value="<?php echo e($record['followup_pulse']); ?>" placeholder="72">
                            </div>
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="followupResp" class="form-label">Resp. Rate</label>
                                <input type="number" class="form-control" id="followupResp" name="followup_resp"
                                       value="<?php echo e($record['followup_resp_rate']); ?>" placeholder="16">
                            </div>
                            <div>
                                <label for="followupPainScore" class="form-label">Pain Score (0-10)</label>
                                <input type="number" class="form-control" id="followupPainScore" name="followup_pain_score"
                                       value="<?php echo e($record['followup_pain_score']); ?>" min="0" max="10" placeholder="0-10">
                            </div>
                            <div>
                                <label for="followupSPO2" class="form-label">SPO2 %</label>
                                <input type="number" class="form-control" id="followupSPO2" name="followup_spo2"
                                       value="<?php echo e($record['followup_spo2']); ?>" min="0" max="100" placeholder="95-100">
                            </div>
                            <div>
                                <label class="form-label">Spinal Injury</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="followup_spinal_injury" id="followupSpinalYes" value="yes"
                                               <?php echo $record['followup_spinal_injury'] === 'yes' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="followupSpinalYes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="followup_spinal_injury" id="followupSpinalNo" value="no"
                                               <?php echo $record['followup_spinal_injury'] === 'no' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="followupSpinalNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Level of Consciousness</label>
                            <div class="inline-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupAlert" value="alert"
                                           <?php echo in_array('alert', $followup_consciousness) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="followupAlert">Alert</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupVerbal" value="verbal"
                                           <?php echo in_array('verbal', $followup_consciousness) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="followupVerbal">Verbal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupPain" value="pain"
                                           <?php echo in_array('pain', $followup_consciousness) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="followupPain">Pain</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupUnconscious" value="unconscious"
                                           <?php echo in_array('unconscious', $followup_consciousness) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="followupUnconscious">Unconscious</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Assessment -->
                <div class="tab-pane fade" id="section5" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-clipboard2-pulse"></i> Chief Complaints
                        </div>

                        <div class="form-group-compact">
                            <div class="checkbox-grid">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chestPain" name="chief_complaints[]" value="chestPain"
                                           <?php echo in_array('chestPain', $chief_complaints) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="chestPain">Chest Pain</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="headache" name="chief_complaints[]" value="headache"
                                           <?php echo in_array('headache', $chief_complaints) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="headache">Headache</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="blurredVision" name="chief_complaints[]" value="blurredVision"
                                           <?php echo in_array('blurredVision', $chief_complaints) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="blurredVision">Blurred Vision</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="difficultyBreathing" name="chief_complaints[]" value="difficultyBreathing"
                                           <?php echo in_array('difficultyBreathing', $chief_complaints) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="difficultyBreathing">Difficulty Breathing</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="dizziness" name="chief_complaints[]" value="dizziness"
                                           <?php echo in_array('dizziness', $chief_complaints) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="dizziness">Dizziness</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bodyMalaise" name="chief_complaints[]" value="bodyMalaise"
                                           <?php echo in_array('bodyMalaise', $chief_complaints) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="bodyMalaise">Body Malaise</label>
                                </div>
                            </div>
                        </div>

                        <!-- INTERACTIVE BODY DIAGRAM -->
                        <div class="body-diagram-container">
                            <div class="body-diagram-header">
                                <h6><i class="bi bi-person-bounding-box"></i> Interactive Injury Mapping</h6>
                                <small class="text-muted">Click on body diagram to mark injuries</small>

                                <!-- Marker Legend -->
                                <div class="marker-legend">
                                    <span class="legend-title">Marker Legend:</span>
                                    <span class="legend-item"><span class="legend-marker" style="background: #dc3545;">LC</span> Laceration</span>
                                    <span class="legend-item"><span class="legend-marker" style="background: #fd7e14;">FX</span> Fracture</span>
                                    <span class="legend-item"><span class="legend-marker" style="background: #ffc107; color: #333;">BN</span> Burn</span>
                                    <span class="legend-item"><span class="legend-marker" style="background: #6f42c1;">CT</span> Contusion</span>
                                    <span class="legend-item"><span class="legend-marker" style="background: #20c997;">AB</span> Abrasion</span>
                                    <span class="legend-item"><span class="legend-marker" style="background: #6c757d;">OT</span> Other</span>
                                </div>
                            </div>

                            <div class="body-diagram-content">
                                <div class="body-views">
                                    <div class="body-view">
                                        <div class="view-label">FRONT VIEW</div>
                                        <div class="body-image-container" id="frontContainer">
                                            <img src="images/body-front.png" alt="Body Front" class="body-image">
                                        </div>
                                    </div>

                                    <div class="body-view">
                                        <div class="view-label">BACK VIEW</div>
                                        <div class="body-image-container" id="backContainer">
                                            <img src="images/body-back.png" alt="Body Back" class="body-image">
                                        </div>
                                    </div>
                                </div>

                                <div class="injury-sidebar">
                                    <!-- Injury Type Selector - Redesigned -->
                                    <div class="injury-type-selector">
                                        <div class="selector-header">
                                            <i class="bi bi-palette-fill"></i>
                                            <span>Injury Classification</span>
                                        </div>
                                        <div class="injury-type-grid">
                                            <button type="button" class="injury-type-btn active" data-type="laceration" title="Open wound with irregular edges">
                                                <div class="btn-content">
                                                    <span class="color-badge" style="background: #dc3545;">
                                                        <i class="bi bi-bandaid-fill"></i>
                                                    </span>
                                                    <span class="btn-label">Laceration</span>
                                                </div>
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="fracture" title="Broken bone">
                                                <div class="btn-content">
                                                    <span class="color-badge" style="background: #fd7e14;">
                                                        <i class="bi bi-activity"></i>
                                                    </span>
                                                    <span class="btn-label">Fracture</span>
                                                </div>
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="burn" title="Thermal, chemical, or electrical burn">
                                                <div class="btn-content">
                                                    <span class="color-badge" style="background: #ffc107;">
                                                        <i class="bi bi-fire"></i>
                                                    </span>
                                                    <span class="btn-label">Burn</span>
                                                </div>
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="contusion" title="Bruising from blunt trauma">
                                                <div class="btn-content">
                                                    <span class="color-badge" style="background: #6f42c1;">
                                                        <i class="bi bi-circle-fill"></i>
                                                    </span>
                                                    <span class="btn-label">Contusion</span>
                                                </div>
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="abrasion" title="Scraped or worn away skin">
                                                <div class="btn-content">
                                                    <span class="color-badge" style="background: #20c997;">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </span>
                                                    <span class="btn-label">Abrasion</span>
                                                </div>
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="other" title="Other type of injury">
                                                <div class="btn-content">
                                                    <span class="color-badge" style="background: #6c757d;">
                                                        <i class="bi bi-three-dots"></i>
                                                    </span>
                                                    <span class="btn-label">Other</span>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Injury List Header - Redesigned -->
                                    <div class="injury-list-header">
                                        <div class="header-content">
                                            <i class="bi bi-list-check"></i>
                                            <span>Marked Injuries</span>
                                        </div>
                                        <span class="injury-badge" id="injuryCount">0</span>
                                    </div>

                                    <!-- Injury List Container -->
                                    <div id="injuryListContainer">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="bi bi-pin-map"></i>
                                            </div>
                                            <p class="empty-state-title">No injuries marked</p>
                                            <p class="empty-state-subtitle">Click on the body diagram to mark an injury location</p>
                                        </div>
                                    </div>

                                    <!-- Action Buttons - Redesigned -->
                                    <div class="diagram-actions">
                                        <button type="button" class="action-btn btn-clear" onclick="clearAllInjuries()" title="Remove all marked injuries">
                                            <i class="bi bi-trash3"></i>
                                            <span>Clear All</span>
                                        </button>
                                        <button type="button" class="action-btn btn-export" onclick="exportInjuryData()" title="Export injury data as JSON">
                                            <i class="bi bi-download"></i>
                                            <span>Export</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Parse SAMPLE data - either JSON or plain text
                        $sampleData = ['signs' => '', 'allergies' => '', 'medications' => '', 'pertinent' => '', 'last_intake' => '', 'events' => ''];
                        $rawSample = $record['fast_sample_details'] ?? '';
                        if (!empty($rawSample)) {
                            $decoded = json_decode($rawSample, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $sampleData = array_merge($sampleData, $decoded);
                            } else {
                                // Old plain text format - put in signs field
                                $sampleData['signs'] = $rawSample;
                            }
                        }
                        ?>
                        <div class="stroke-assessment-card">
                            <div class="stroke-assessment-header">
                                <div class="stroke-header-icon">
                                    <i class="bi bi-activity"></i>
                                </div>
                                <div class="stroke-header-text">
                                    <h6>Stroke Assessment</h6>
                                    <span>F.A.S.T. Protocol & S.A.M.P.L.E. History</span>
                                </div>
                            </div>
                            <div class="stroke-assessment-body">
                                <div class="fast-section">
                                    <div class="fast-section-title">
                                        <span class="fast-badge">F.A.S.T.</span>
                                        <span class="fast-subtitle">Stroke Recognition</span>
                                    </div>
                                    <div class="fast-grid">
                                        <div class="fast-item">
                                            <div class="fast-item-letter">F</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Face Drooping</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="face_drooping" id="facePos" value="positive" class="toggle-input" <?php echo $record['fast_face_drooping'] === 'positive' ? 'checked' : ''; ?>>
                                                    <label for="facePos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="face_drooping" id="faceNeg" value="negative" class="toggle-input" <?php echo $record['fast_face_drooping'] === 'negative' ? 'checked' : ''; ?>>
                                                    <label for="faceNeg" class="toggle-btn toggle-negative">(−)</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="fast-item">
                                            <div class="fast-item-letter">A</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Arm Weakness</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="arm_weakness" id="armPos" value="positive" class="toggle-input" <?php echo $record['fast_arm_weakness'] === 'positive' ? 'checked' : ''; ?>>
                                                    <label for="armPos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="arm_weakness" id="armNeg" value="negative" class="toggle-input" <?php echo $record['fast_arm_weakness'] === 'negative' ? 'checked' : ''; ?>>
                                                    <label for="armNeg" class="toggle-btn toggle-negative">(−)</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="fast-item">
                                            <div class="fast-item-letter">S</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Speech Difficulty</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="speech_difficulty" id="speechPos" value="positive" class="toggle-input" <?php echo $record['fast_speech_difficulty'] === 'positive' ? 'checked' : ''; ?>>
                                                    <label for="speechPos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="speech_difficulty" id="speechNeg" value="negative" class="toggle-input" <?php echo $record['fast_speech_difficulty'] === 'negative' ? 'checked' : ''; ?>>
                                                    <label for="speechNeg" class="toggle-btn toggle-negative">(−)</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="fast-item">
                                            <div class="fast-item-letter">T</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Time to Call</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="time_to_call" id="timePos" value="positive" class="toggle-input" <?php echo $record['fast_time_to_call'] === 'positive' ? 'checked' : ''; ?>>
                                                    <label for="timePos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="time_to_call" id="timeNeg" value="negative" class="toggle-input" <?php echo $record['fast_time_to_call'] === 'negative' ? 'checked' : ''; ?>>
                                                    <label for="timeNeg" class="toggle-btn toggle-negative">(−)</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sample-section">
                                    <div class="sample-section-title">
                                        <span class="sample-badge">S.A.M.P.L.E.</span>
                                        <span class="sample-subtitle">Patient History</span>
                                    </div>
                                    <div class="sample-table-modern">
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">S</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleSigns" name="sample_signs" placeholder="Signs & Symptoms" value="<?php echo e($sampleData['signs']); ?>">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">A</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleAllergies" name="sample_allergies" placeholder="Allergies" value="<?php echo e($sampleData['allergies']); ?>">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">M</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleMedications" name="sample_medications" placeholder="Medications" value="<?php echo e($sampleData['medications']); ?>">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">P</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="samplePertinent" name="sample_pertinent" placeholder="Pertinent History" value="<?php echo e($sampleData['pertinent']); ?>">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">L</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleLastIntake" name="sample_last_intake" placeholder="Last Oral Intake" value="<?php echo e($sampleData['last_intake']); ?>">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">E</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleEvents" name="sample_events" placeholder="Events Leading to Illness" value="<?php echo e($sampleData['events']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Hidden field to store combined SAMPLE data -->
                                    <input type="hidden" id="fastDetails" name="sample_details" value="<?php echo e($record['fast_sample_details']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="ob-section">
                            <h6><i class="bi bi-hospital-fill"></i> FOR OB Patients Only</h6>
                            <div class="grid-3" style="gap: 1rem;">
                                <div>
                                    <label for="babyDelivery" class="form-label">Baby Status</label>
                                    <input type="text" class="form-control" id="babyDelivery" name="baby_status"
                                           value="<?php echo e($record['ob_baby_status']); ?>" placeholder="e.g., Alive, Healthy">
                                </div>
                                <div>
                                    <label for="timeOfDelivery" class="form-label">Delivery Time</label>
                                    <input type="text" class="form-control time-input-12hr" id="timeOfDelivery" name="ob_delivery_time"
                                           value="<?php echo e($record['ob_delivery_time']); ?>" placeholder="11:30 PM" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                                </div>
                                <div>
                                    <label class="form-label">Placenta</label>
                                    <div class="inline-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="placenta" id="placentaIn" value="in"
                                                   <?php echo $record['ob_placenta'] === 'in' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="placentaIn">In</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="placenta" id="placentaOut" value="out"
                                                   <?php echo $record['ob_placenta'] === 'out' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="placentaOut">Out</label>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="lmp" class="form-label">LMP</label>
                                    <input type="date" class="form-control" id="lmp" name="lmp"
                                           value="<?php echo e($record['ob_lmp']); ?>">
                                </div>
                                <div>
                                    <label for="aog" class="form-label">AOG</label>
                                    <input type="text" class="form-control" id="aog" name="aog"
                                           value="<?php echo e($record['ob_aog']); ?>" placeholder="Weeks">
                                </div>
                                <div>
                                    <label for="edc" class="form-label">EDC</label>
                                    <input type="date" class="form-control" id="edc" name="edc"
                                           value="<?php echo e($record['ob_edc']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-section">
                            <label for="othersComplaint" class="form-label">Other Complaints</label>
                            <textarea class="form-control" id="othersComplaint" name="other_complaints" rows="2"><?php echo e($record['other_complaints']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 6: Team -->
                <div class="tab-pane fade" id="section6" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-people-fill"></i> Team Information
                        </div>

                        <div class="grid-3 mb-section">
                            <div>
                                <label for="teamLeader" class="form-label">Team Leader</label>
                                <input type="text" class="form-control" id="teamLeader" name="team_leader"
                                       value="<?php echo e($record['team_leader']); ?>">
                            </div>
                            <div>
                                <label for="dataRecorder" class="form-label">Data Recorder</label>
                                <input type="text" class="form-control" id="dataRecorder" name="data_recorder"
                                       value="<?php echo e($record['data_recorder']); ?>">
                            </div>
                            <div>
                                <label for="logistic" class="form-label">Logistic</label>
                                <input type="text" class="form-control" id="logistic" name="logistic"
                                       value="<?php echo e($record['logistic']); ?>">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="aider1" class="form-label">1st Aider</label>
                                <input type="text" class="form-control" id="aider1" name="aider1"
                                       value="<?php echo e($record['first_aider']); ?>" placeholder="Name">
                            </div>
                            <div>
                                <label for="aider2" class="form-label">2nd Aider</label>
                                <input type="text" class="form-control" id="aider2" name="aider2"
                                       value="<?php echo e($record['second_aider']); ?>" placeholder="Name">
                            </div>
                        </div>

                        <div class="section-title" style="margin-top: 1.5rem;">
                            <i class="bi bi-building"></i> Hospital Endorsement
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="hospital" class="form-label">Hospital Name</label>
                                <input type="text" class="form-control" id="hospital" name="hospital_name"
                                       value="<?php echo e($record['hospital_name']); ?>" placeholder="Hospital name">
                            </div>
                        </div>

                        <div class="mb-section">
                            <label for="dateTime" class="form-label">Endorsement Date & Time</label>
                            <input type="datetime-local" class="form-control" id="dateTime" name="endorsement_datetime"
                                   value="<?php echo e($record['endorsement_datetime']); ?>" style="max-width: 300px;">
                        </div>

                        <!-- Endorsement Attachment - Corporate Design -->
                        <div class="endorsement-attachment-card">
                            <div class="endorsement-doc-header">
                                <div class="endorsement-doc-icon">
                                    <i class="bi bi-file-earmark-medical-fill"></i>
                                </div>
                                <div class="endorsement-doc-title">
                                    <h6>Endorsement Attachment</h6>
                                    <span>Capture or upload hospital endorsement document</span>
                                </div>
                            </div>

                            <div class="endorsement-doc-body">
                                <?php if (!empty($record['endorsement_attachment'])): ?>
                                <!-- Existing Endorsement Display -->
                                <div class="existing-patient-doc">
                                    <div class="existing-doc-label">
                                        <i class="bi bi-image-fill"></i>
                                        <span>Current Endorsement Document</span>
                                    </div>
                                    <div class="preview-card">
                                        <div class="preview-image-wrapper" onclick="openExistingEndorsementImageModal('<?php echo e($record['endorsement_attachment']); ?>')">
                                            <img src="<?php echo e($record['endorsement_attachment']); ?>" alt="Endorsement Attachment">
                                            <div class="preview-overlay">
                                                <i class="bi bi-zoom-in"></i>
                                                <span>Click to enlarge</span>
                                            </div>
                                        </div>
                                        <div class="preview-actions">
                                            <span class="preview-label">
                                                <i class="bi bi-check-circle-fill"></i>
                                                Document on file
                                            </span>
                                            <a href="<?php echo e($record['endorsement_attachment']); ?>" target="_blank" class="view-original-btn">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                                View Original
                                            </a>
                                        </div>
                                    </div>
                                    <div class="replace-photo-label">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span>Replace with new document (optional)</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Upload Controls -->
                                <div class="endorsement-doc-controls">
                                    <button type="button" class="upload-method-btn" id="openCameraBtn">
                                        <div class="upload-method-icon camera">
                                            <i class="bi bi-camera-fill"></i>
                                        </div>
                                        <div class="upload-method-text">
                                            <span class="upload-method-title">Open Camera</span>
                                            <span class="upload-method-desc">Take a photo now</span>
                                        </div>
                                    </button>

                                    <div class="upload-divider">
                                        <span>or</span>
                                    </div>

                                    <label class="upload-method-btn" for="fileUpload">
                                        <div class="upload-method-icon file">
                                            <i class="bi bi-cloud-arrow-up-fill"></i>
                                        </div>
                                        <div class="upload-method-text">
                                            <span class="upload-method-title">Upload File</span>
                                            <span class="upload-method-desc">JPG, PNG, GIF, WebP (max 5MB)</span>
                                        </div>
                                        <input type="file" class="hidden-file-input" id="fileUpload" name="endorsement_attachment" accept="image/jpeg,image/png,image/gif,image/webp" onchange="validateFileUpload(this)">
                                    </label>
                                </div>

                                <!-- Camera Container -->
                                <div id="cameraContainer" class="endorsement-camera-container">
                                    <div class="camera-viewport">
                                        <video id="cameraVideo" autoplay playsinline></video>
                                    </div>
                                    <div class="camera-controls">
                                        <button type="button" class="camera-btn capture" id="captureBtn" onclick="capturePhoto()">
                                            <i class="bi bi-circle-fill"></i>
                                            <span>Capture</span>
                                        </button>
                                        <button type="button" class="camera-btn close-cam" id="closeCameraBtn" onclick="closeCamera()">
                                            <i class="bi bi-x-lg"></i>
                                            <span>Close</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- New Image Preview -->
                                <div id="previewContainer" class="endorsement-preview-container">
                                    <div class="new-photo-label">
                                        <i class="bi bi-plus-circle-fill"></i>
                                        <span>New Document Preview</span>
                                    </div>
                                    <div class="preview-card">
                                        <div class="preview-image-wrapper" onclick="openEndorsementImageModal()">
                                            <img id="attachmentPreview" src="" alt="Endorsement Attachment Preview">
                                            <div class="preview-overlay">
                                                <i class="bi bi-zoom-in"></i>
                                                <span>Click to enlarge</span>
                                            </div>
                                        </div>
                                        <div class="preview-actions">
                                            <span class="preview-label">
                                                <i class="bi bi-check-circle-fill"></i>
                                                New document ready
                                            </span>
                                            <button type="button" class="remove-preview-btn" id="removeAttachmentBtn" onclick="removeAttachment()">
                                                <i class="bi bi-trash3"></i>
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Error Message -->
                                <div id="uploadError" class="endorsement-upload-error">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Endorsement Image Preview Modal -->
                        <div id="endorsementImageModal" class="image-preview-modal" onclick="closeEndorsementImageModal()">
                            <div class="modal-content-custom" onclick="event.stopPropagation()">
                                <button type="button" class="modal-close-btn" onclick="closeEndorsementImageModal()">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <img id="endorsementModalImage" src="" alt="Endorsement Attachment">
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-pencil-square"></i> Team Leader Notes
                        </div>

                        <div class="mb-section">
                            <textarea class="form-control" id="teamLeaderNotes" name="team_leader_notes" rows="3" placeholder="Enter team leader notes and observations..."><?php echo e($record['team_leader_notes']); ?></textarea>
                        </div>

                    </div>
                </div>
            </div>
        </form>

        <div class="navigation-buttons">
            <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='records.php'">
                <i class="bi bi-x-lg"></i> Cancel
            </button>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" id="prevBtn" onclick="navigateTab(-1)">
                    <i class="bi bi-chevron-left"></i> Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="navigateTab(1)">
                    Next <i class="bi bi-chevron-right"></i>
                </button>
                <button type="button" class="btn btn-success" id="updateBtn" onclick="updateRecord()" style="display: none;">
                    <i class="bi bi-check2"></i> Update Record
                </button>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script src="js/prehospital-form.js?v=<?php echo asset_version(); ?>"></script>
    <!-- Custom Date Components - Month/Day/Year dropdowns -->
    <script src="js/custom-date.js?v=<?php echo asset_version(); ?>"></script>
    <script>
        // Remove loading class after page loads - CRITICAL: Always remove skeleton
        window.addEventListener('load', function() {
            setTimeout(function() {
                try {
                    document.body.classList.remove('loading');
                    console.log('Skeleton loading removed successfully');
                } catch(e) {
                    console.error('Error removing skeleton:', e);
                    // Force remove skeleton even if error
                    document.body.className = document.body.className.replace('loading', '');
                }
            }, 1000); // Wait for page to fully load before removing skeleton
        });

        // Failsafe: Remove skeleton after 3 seconds no matter what
        setTimeout(function() {
            if (document.body.classList.contains('loading')) {
                console.warn('Failsafe: Forcing skeleton removal');
                document.body.classList.remove('loading');
            }
        }, 3000);

        // Show flash messages with Notiflix
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['flash_message'])): ?>
                <?php
                $flash = $_SESSION['flash_message'];
                $type = $flash['type'];
                $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['flash_message']);
                ?>
                <?php if ($type === 'success'): ?>
                    Notiflix.Notify.success('<?php echo $message; ?>', { timeout: 3000 });
                <?php elseif ($type === 'error'): ?>
                    Notiflix.Notify.failure('<?php echo $message; ?>', { timeout: 4000 });
                <?php elseif ($type === 'warning'): ?>
                    Notiflix.Notify.warning('<?php echo $message; ?>', { timeout: 3500 });
                <?php else: ?>
                    Notiflix.Notify.info('<?php echo $message; ?>', { timeout: 3000 });
                <?php endif; ?>
            <?php endif; ?>

            // ============================================
            // UPPERCASE ALL TEXT INPUTS
            // ============================================
            const textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="url"], textarea');

            textInputs.forEach(function(input) {
                // Convert to uppercase on input
                input.addEventListener('input', function(e) {
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    this.value = this.value.toUpperCase();
                    this.setSelectionRange(start, end);
                });

                // Also convert existing values to uppercase on page load
                if (input.value) {
                    input.value = input.value.toUpperCase();
                }
            });

            console.log('Uppercase conversion initialized on ' + textInputs.length + ' text inputs');

            // ============================================
            // FLATPICKR TIME PICKER INITIALIZATION - DISABLED (Using custom dropdowns instead)
            // ============================================
            /*
            // Initialize Flatpickr for time inputs
            const timeInputs = document.querySelectorAll('input[type="time"]');

            timeInputs.forEach(function(input) {
                flatpickr(input, {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: "h:i K", // 12-hour format with AM/PM
                    time_24hr: false, // Use 12-hour format
                    minuteIncrement: 1,
                    // Mobile-friendly configuration
                    disableMobile: false,
                    // Allow manual input
                    allowInput: true,
                    // Default time
                    defaultHour: 12,
                    defaultMinute: 0,
                    // Better mobile touch experience
                    clickOpens: true,
                    // Position the picker
                    position: "auto",
                    // Custom styling for mobile
                    onReady: function(selectedDates, dateStr, instance) {
                        // Add mobile-friendly class
                        instance.calendarContainer.classList.add('flatpickr-mobile');
                    },
                    // Convert to 24-hour format for backend storage
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length > 0) {
                            const date = selectedDates[0];
                            const hours = String(date.getHours()).padStart(2, '0');
                            const minutes = String(date.getMinutes()).padStart(2, '0');
                            const time24 = hours + ':' + minutes;

                            // Store the 24-hour format in a hidden attribute for backend
                            input.setAttribute('data-time-24hr', time24);
                            // Keep the value as 24-hour format for backend compatibility
                            input.value = time24;
                        }
                    },
                    // Parse existing 24-hour values and display as 12-hour, add backdrop
                    onOpen: function(selectedDates, dateStr, instance) {
                        // Add backdrop on mobile
                        if (window.innerWidth <= 768) {
                            document.body.classList.add('flatpickr-mobile-open');
                        }

                        // Parse existing time
                        if (input.value && input.value.match(/^\d{2}:\d{2}$/)) {
                            // Parse 24-hour format
                            const [hours, minutes] = input.value.split(':');
                            const hour24 = parseInt(hours);
                            const hour12 = hour24 === 0 ? 12 : (hour24 > 12 ? hour24 - 12 : hour24);
                            const ampm = hour24 >= 12 ? 'PM' : 'AM';

                            // Update the display
                            instance.input.value = `${String(hour12).padStart(2, '0')}:${minutes} ${ampm}`;
                        }
                    },
                    // Remove backdrop when calendar closes
                    onClose: function(selectedDates, dateStr, instance) {
                        document.body.classList.remove('flatpickr-mobile-open');
                    }
                });
            });

            console.log('Flatpickr initialized on ' + timeInputs.length + ' time inputs with 12-hour AM/PM format');
            */

            /*
            // Initialize Flatpickr for datetime-local inputs (Date & Time pickers) - DISABLED
            const datetimeInputs = document.querySelectorAll('input[type="datetime-local"]');

            datetimeInputs.forEach(function(input) {
                flatpickr(input, {
                    enableTime: true,
                    dateFormat: "Y-m-d H:i", // Format for datetime-local compatibility
                    time_24hr: false, // Use 12-hour format with AM/PM
                    minuteIncrement: 1,
                    // Mobile-friendly configuration
                    disableMobile: false,
                    // Allow manual input
                    allowInput: true,
                    // Better mobile touch experience
                    clickOpens: true,
                    // Position the picker
                    position: "auto",
                    // Set default to current date and time
                    defaultDate: input.value || null,
                    // Custom styling for mobile
                    onReady: function(selectedDates, dateStr, instance) {
                        // Add mobile-friendly class
                        instance.calendarContainer.classList.add('flatpickr-mobile');

                        // Make the calendar touch-friendly on mobile
                        if (window.innerWidth <= 768) {
                            instance.calendarContainer.style.fontSize = '16px';
                        }
                    },
                    // Format value for backend
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length > 0) {
                            const date = selectedDates[0];

                            // Format as YYYY-MM-DDTHH:MM for datetime-local input
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');
                            const hours = String(date.getHours()).padStart(2, '0');
                            const minutes = String(date.getMinutes()).padStart(2, '0');

                            const datetimeValue = `${year}-${month}-${day}T${hours}:${minutes}`;
                            input.value = datetimeValue;
                        }
                    },
                    // Add backdrop on mobile when calendar opens
                    onOpen: function(selectedDates, dateStr, instance) {
                        if (window.innerWidth <= 768) {
                            document.body.classList.add('flatpickr-mobile-open');
                        }
                    },
                    // Remove backdrop when calendar closes
                    onClose: function(selectedDates, dateStr, instance) {
                        document.body.classList.remove('flatpickr-mobile-open');
                    }
                });
            });

            console.log('Flatpickr initialized on ' + datetimeInputs.length + ' datetime inputs with mobile optimization');

            // ============================================
            // FLATPICKR DATE PICKER INITIALIZATION - DISABLED
            // ============================================
            // Select all date input fields
            const dateInputs = document.querySelectorAll('input[type="date"]');

            // Initialize Flatpickr on each date input
            dateInputs.forEach(function(input) {
                flatpickr(input, {
                    dateFormat: "Y-m-d", // Format for date input compatibility
                    // Mobile-friendly configuration
                    disableMobile: false,
                    // Allow manual input
                    allowInput: true,
                    // Better mobile touch experience
                    clickOpens: true,
                    // Position the picker
                    position: "auto",
                    // Set default to current value if exists
                    defaultDate: input.value || null,
                    // Custom styling for mobile
                    onReady: function(selectedDates, dateStr, instance) {
                        // Add mobile-friendly class
                        instance.calendarContainer.classList.add('flatpickr-mobile');

                        // Make the calendar touch-friendly on mobile
                        if (window.innerWidth <= 768) {
                            instance.calendarContainer.style.fontSize = '16px';
                        }
                    },
                    // Format value for backend
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length > 0) {
                            const date = selectedDates[0];

                            // Format as YYYY-MM-DD for date input
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');

                            const dateValue = `${year}-${month}-${day}`;
                            input.value = dateValue;
                        }

                        // Trigger change event
                        const event = new Event('change', { bubbles: true });
                        input.dispatchEvent(event);
                    },
                    // Add backdrop on mobile when calendar opens
                    onOpen: function(selectedDates, dateStr, instance) {
                        if (window.innerWidth <= 768) {
                            document.body.classList.add('flatpickr-mobile-open');
                        }
                    },
                    // Remove backdrop when calendar closes
                    onClose: function(selectedDates, dateStr, instance) {
                        document.body.classList.remove('flatpickr-mobile-open');
                    }
                });
            });

            console.log('Flatpickr initialized on ' + dateInputs.length + ' date inputs with mobile optimization');
            */
        });

        // Configure Notiflix
        Notiflix.Notify.init({
            width: '320px',
            position: 'right-top',
            distance: '15px',
            timeout: 3000,
            fontSize: '15px',
            cssAnimationStyle: 'from-right',
            success: {
                background: '#28a745',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            failure: {
                background: '#dc3545',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            warning: {
                background: '#ffc107',
                textColor: '#333',
                notiflixIconColor: '#333',
            },
            info: {
                background: '#0066cc',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
        });

        Notiflix.Confirm.init({
            width: '350px',
            titleColor: '#0066cc',
            okButtonBackground: '#0066cc',
            cancelButtonBackground: '#6c757d',
            cssAnimationStyle: 'zoom',
        });

        Notiflix.Report.init({
            width: '360px',
            svgSize: '110px',
            titleFontSize: '18px',
            messageFontSize: '15px',
            buttonFontSize: '15px',
            cssAnimationStyle: 'zoom',
            success: {
                svgColor: '#28a745',
                titleColor: '#1e7e34',
                messageColor: '#333',
                buttonBackground: '#28a745',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(0,0,0,0.5)',
            },
            failure: {
                svgColor: '#dc3545',
                titleColor: '#bd2130',
                messageColor: '#333',
                buttonBackground: '#dc3545',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(0,0,0,0.5)',
            },
            warning: {
                svgColor: '#ffc107',
                titleColor: '#856404',
                messageColor: '#333',
                buttonBackground: '#ffc107',
                buttonColor: '#333',
                backOverlayColor: 'rgba(0,0,0,0.5)',
            },
            info: {
                svgColor: '#0066cc',
                titleColor: '#004d99',
                messageColor: '#333',
                buttonBackground: '#0066cc',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(0,0,0,0.5)',
            },
        });

        // Function to combine individual SAMPLE fields into hidden field
        function combineSampleFields() {
            const signs = document.getElementById('sampleSigns')?.value || '';
            const allergies = document.getElementById('sampleAllergies')?.value || '';
            const medications = document.getElementById('sampleMedications')?.value || '';
            const pertinent = document.getElementById('samplePertinent')?.value || '';
            const lastIntake = document.getElementById('sampleLastIntake')?.value || '';
            const events = document.getElementById('sampleEvents')?.value || '';

            const combined = JSON.stringify({
                signs: signs,
                allergies: allergies,
                medications: medications,
                pertinent: pertinent,
                last_intake: lastIntake,
                events: events
            });

            const hiddenField = document.getElementById('fastDetails');
            if (hiddenField) {
                hiddenField.value = combined;
            }
        }

        // Override submit function for edit mode
        function updateRecord() {
            Notiflix.Confirm.show(
                'Update Record',
                'Are you sure you want to update this record?',
                'Yes, Update',
                'Cancel',
                function okCb() {
                    // Combine SAMPLE fields before submitting
                    combineSampleFields();

                    // Show loading indicator
                    Notiflix.Loading.standard('Updating record...', {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                    });

                    // Submit the form
                    document.getElementById('editForm').submit();
                },
                function cancelCb() {
                    // Do nothing
                },
                {
                    width: '350px',
                    borderRadius: '8px',
                    titleColor: '#0066cc',
                    okButtonBackground: '#0066cc',
                    okButtonColor: '#fff',
                }
            );
        }

        // Auto-calculate age from DOB
        document.getElementById('dateOfBirth').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            document.getElementById('age').value = age;
        });
    </script>
</body>
</html>
