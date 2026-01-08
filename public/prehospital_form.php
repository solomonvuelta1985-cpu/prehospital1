<?php
/**
 * Pre-Hospital Care Form - PHP Version
 * Maintains exact HTML design with PHP security features
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Require authentication
require_login();

// Generate CSRF token
$csrf_token = generate_token();

// Get current user
$current_user = get_auth_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Hospital Care Form (1x.2025)</title>
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
            max-height: 100vh;
            position: relative;
        }

        .form-container {
            overflow: visible !important;
            height: auto !important;
            max-width: 100%;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-body {
            overflow: visible !important;
            max-height: none !important;
            padding-bottom: 2rem !important;
            min-height: calc(100vh - 400px) !important;
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
            padding: 1.5rem 2rem !important;
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

        /* Ensure sticky works in all browsers */
        @supports (position: sticky) {
            .navigation-buttons {
                position: -webkit-sticky !important;
                position: sticky !important;
            }
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
    </style>
</head>
<body class="loading">
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="form-container">
        <div class="form-header">
            <h1><i class="bi bi-file-medical"></i> PRE-HOSPITAL CARE FORM</h1>
            <p class="subtitle" style="margin-left: 2.15rem;">Emergency Medical Services</p>
        </div>

        <div class="progress-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.85rem; font-weight: 600; color: #0066cc;">
                    <i class="bi bi-list-check"></i> Form Progress
                </span>
                <span id="stepIndicator" style="font-size: 0.85rem; font-weight: 500; color: #6c757d;">
                    Step 1 of 7
                </span>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" id="progressBar" style="width: 14%"></div>
            </div>
        </div>

        <div class="tabs-container">
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
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab7" data-bs-toggle="tab" data-bs-target="#section7" type="button" role="tab">
                        Complete
                    </button>
                </li>
            </ul>
        </div>

        <?php show_flash(); ?>

        <form id="preHospitalForm" class="form-body" method="POST" action="../api/save_prehospital_form.php">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <!-- Draft ID (populated when resuming a draft) -->
            <input type="hidden" name="draft_id" id="draftIdField" value="">

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
                                <input type="date" class="form-control" id="formDate" name="form_date" required>
                            </div>
                            <div>
                                <label for="depTime" class="form-label required-field">Departure Time</label>
                                <input type="time" class="form-control" id="depTime" name="departure_time" required>
                            </div>
                            <div>
                                <label for="arrTime" class="form-label">Arrival Time</label>
                                <input type="time" class="form-control" id="arrTime" name="arrival_time">
                            </div>
                        </div>

                        <div class="form-group-compact">
                            <label class="form-label">Vehicle Used</label>
                            <div class="inline-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_used" id="ambulance" value="ambulance">
                                    <label class="form-check-label" for="ambulance">Ambulance</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_used" id="fireTruck" value="fireTruck">
                                    <label class="form-check-label" for="fireTruck">Fire Truck</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="vehicle_used" id="othersVehicle" value="others">
                                    <label class="form-check-label" for="othersVehicle">Others</label>
                                </div>
                            </div>
                            <input type="hidden" name="vehicle_details" id="vehicleDetails">
                        </div>
                        
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrSceneLocation" class="form-label">Arrival at Scene - Location</label>
                                <input type="text" class="form-control" id="arrSceneLocation" name="arrival_scene_location" placeholder="Scene location">
                            </div>
                            <div>
                                <label for="arrSceneTime" class="form-label">Arrival at Scene - Time</label>
                                <input type="time" class="form-control" id="arrSceneTime" name="arrival_scene_time">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="depSceneLocation" class="form-label">Departure from Scene - Location</label>
                                <input type="text" class="form-control" id="depSceneLocation" name="departure_scene_location" placeholder="Departure location">
                            </div>
                            <div>
                                <label for="depSceneTime" class="form-label">Departure from Scene - Time</label>
                                <input type="time" class="form-control" id="depSceneTime" name="departure_scene_time">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrHospName" class="form-label">Arrival at Hospital - Name</label>
                                <input type="text" class="form-control" id="arrHospName" name="arrival_hospital_name" placeholder="Hospital name">
                            </div>
                            <div>
                                <label for="arrHospTime" class="form-label">Arrival at Hospital - Time</label>
                                <input type="time" class="form-control" id="arrHospTime" name="arrival_hospital_time">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="depHospLocation" class="form-label">Departure from Hospital - Location</label>
                                <input type="text" class="form-control" id="depHospLocation" name="departure_hospital_location" placeholder="Departure location">
                            </div>
                            <div>
                                <label for="depHospTime" class="form-label">Departure from Hospital - Time</label>
                                <input type="time" class="form-control" id="depHospTime" name="departure_hospital_time">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrStation" class="form-label">Arrival at Station</label>
                                <input type="time" class="form-control" id="arrStation" name="arrival_station_time">
                            </div>
                            <div>
                                <label for="driver" class="form-label">Driver</label>
                                <input type="text" class="form-control" id="driver" name="driver" placeholder="Driver name">
                            </div>
                        </div>

                        <div class="form-group-compact">
                            <label class="form-label">Persons Present Upon Arrival</label>
                            <div class="checkbox-grid">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="police" name="persons_present[]" value="police">
                                    <label class="form-check-label" for="police">Police</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="brgyOfficials" name="persons_present[]" value="brgyOfficials">
                                    <label class="form-check-label" for="brgyOfficials">Barangay Officials</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="relatives" name="persons_present[]" value="relatives">
                                    <label class="form-check-label" for="relatives">Relatives</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bystanders" name="persons_present[]" value="bystanders">
                                    <label class="form-check-label" for="bystanders">Bystanders</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="nonePresent" name="persons_present[]" value="none">
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
                                <input type="text" class="form-control" id="patientName" name="patient_name" placeholder="Last Name, First Name, Middle Initial" required>
                            </div>
                            <div>
                                <label for="dateOfBirth" class="form-label required-field">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth" required>
                            </div>
                            <div>
                                <label for="age" class="form-label required-field">Age</label>
                                <input type="number" class="form-control" id="age" name="age" min="0" max="150" required>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label class="form-label required-field">Gender</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="male" value="male" required>
                                        <label class="form-check-label" for="male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="gender" id="female" value="female" required>
                                        <label class="form-check-label" for="female">Female</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Civil Status</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="single" value="single">
                                        <label class="form-check-label" for="single">Single</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="civil_status" id="married" value="married">
                                        <label class="form-check-label" for="married">Married</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" placeholder="Complete address">
                            </div>
                            <div>
                                <label for="zone" class="form-label">Zone</label>
                                <input type="text" class="form-control" id="zone" name="zone">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" name="occupation">
                            </div>
                            <div>
                                <label for="placeOfIncident" class="form-label">Place of Incident</label>
                                <input type="text" class="form-control" id="placeOfIncident" name="place_of_incident">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="zoneLandmark" class="form-label">Zone/Landmark</label>
                                <input type="text" class="form-control" id="zoneLandmark" name="zone_landmark">
                            </div>
                            <div>
                                <label for="incidentTime" class="form-label">Time of Incident</label>
                                <input type="time" class="form-control" id="incidentTime" name="incident_time">
                            </div>
                        </div>

                        <div class="section-title" style="margin-top: 1.5rem;">
                            <i class="bi bi-telephone"></i> Informant Details
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="informant" class="form-label">Informant Name</label>
                                <input type="text" class="form-control" id="informant" name="informant_name" placeholder="Name of informant">
                            </div>
                            <div>
                                <label for="informantAddress" class="form-label">Informant Address</label>
                                <input type="text" class="form-control" id="informantAddress" name="informant_address">
                            </div>
                        </div>

                        <div class="grid-3 mb-section">
                            <div>
                                <label class="form-label">Walk In / Call</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="arrival_type" id="walkIn" value="walkIn">
                                        <label class="form-check-label" for="walkIn">Walk In</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="arrival_type" id="call" value="call">
                                        <label class="form-check-label" for="call">Call</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="callArrTime" class="form-label">Call/Arrival Time</label>
                                <input type="time" class="form-control" id="callArrTime" name="call_arrival_time">
                            </div>
                            <div>
                                <label for="cpNumber" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="cpNumber" name="contact_number" placeholder="Contact number">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="relationshipVictim" class="form-label">Relationship to Victim</label>
                                <input type="text" class="form-control" id="relationshipVictim" name="relationship_victim">
                            </div>
                            <div>
                                <label for="personalBelongings" class="form-label">Personal Belongings</label>
                                <select class="form-select" id="personalBelongings" name="personal_belongings[]" multiple size="4">
                                    <option value="wallet">Wallet</option>
                                    <option value="cellphone">Cellphone</option>
                                    <option value="jewelry">Jewelry</option>
                                    <option value="watch">Watch</option>
                                    <option value="keys">Keys</option>
                                    <option value="bag">Bag</option>
                                    <option value="documents">Documents/IDs</option>
                                    <option value="cash">Cash</option>
                                    <option value="none">None</option>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple items</small>
                            </div>
                        </div>
                        
                        <div class="mb-section">
                            <label for="otherBelongings" class="form-label">Other Belongings (specify)</label>
                            <input type="text" class="form-control" id="otherBelongings" name="other_belongings" placeholder="List other belongings not mentioned above">
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
                                    <input class="form-check-input" type="checkbox" id="medical" name="emergency_type[]" value="medical">
                                    <label class="form-check-label" for="medical"><strong>Medical</strong></label>
                                </div>
                                <input type="text" class="form-control" id="medicalSpecify" name="medical_specify" placeholder="Specify medical condition">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="trauma" name="emergency_type[]" value="trauma">
                                    <label class="form-check-label" for="trauma"><strong>Trauma</strong></label>
                                </div>
                                <input type="text" class="form-control" id="traumaSpecify" name="trauma_specify" placeholder="Specify trauma type">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="ob" name="emergency_type[]" value="ob">
                                    <label class="form-check-label" for="ob"><strong>OB</strong></label>
                                </div>
                                <input type="text" class="form-control" id="obSpecify" name="ob_specify" placeholder="Specify OB condition">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="general" name="emergency_type[]" value="general">
                                    <label class="form-check-label" for="general"><strong>General</strong></label>
                                </div>
                                <input type="text" class="form-control" id="generalSpecify" name="general_specify" placeholder="Specify general condition">
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-heart-pulse-fill"></i> Care Management
                        </div>

                        <div class="form-group-compact">
                            <div class="checkbox-grid">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="immobilization" name="care_management[]" value="immobilization">
                                    <label class="form-check-label" for="immobilization">Immobilization</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cpr" name="care_management[]" value="cpr">
                                    <label class="form-check-label" for="cpr">CPR</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bandaging" name="care_management[]" value="bandaging">
                                    <label class="form-check-label" for="bandaging">Bandaging</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="woundCare" name="care_management[]" value="woundCare">
                                    <label class="form-check-label" for="woundCare">Wound Care</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cCollar" name="care_management[]" value="cCollar">
                                    <label class="form-check-label" for="cCollar">C-Collar</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="aed" name="care_management[]" value="aed">
                                    <label class="form-check-label" for="aed">AED</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ked" name="care_management[]" value="ked">
                                    <label class="form-check-label" for="ked">KED</label>
                                </div>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="o2LPM" class="form-label">O² (LPM via)</label>
                                <input type="text" class="form-control" id="o2LPM" name="oxygen_lpm" placeholder="Oxygen delivery method and rate">
                            </div>
                            <div>
                                <label for="othersCare" class="form-label">Others</label>
                                <input type="text" class="form-control" id="othersCare" name="other_care" placeholder="Other care management">
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
                                <input type="time" class="form-control" id="initialTime" name="initial_time">
                            </div>
                            <div>
                                <label for="initialBP" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="initialBP" name="initial_bp" placeholder="120/80">
                            </div>
                            <div>
                                <label for="initialTemp" class="form-label">Temp (°C)</label>
                                <input type="number" class="form-control" id="initialTemp" name="initial_temp" step="0.1" placeholder="36.5">
                            </div>
                            <div>
                                <label for="initialPulse" class="form-label">Pulse (BPM)</label>
                                <input type="number" class="form-control" id="initialPulse" name="initial_pulse" placeholder="72">
                            </div>
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="initialResp" class="form-label">Resp. Rate</label>
                                <input type="number" class="form-control" id="initialResp" name="initial_resp" placeholder="16">
                            </div>
                            <div>
                                <label for="initialPainScore" class="form-label">Pain Score (0-10)</label>
                                <input type="number" class="form-control" id="initialPainScore" name="initial_pain_score" min="0" max="10">
                            </div>
                            <div>
                                <label for="initialSPO2" class="form-label">SPO2 %</label>
                                <input type="number" class="form-control" id="initialSPO2" name="initial_spo2" min="0" max="100">
                            </div>
                            <div>
                                <label class="form-label">Spinal Injury</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_spinal_injury" id="initialSpinalYes" value="yes">
                                        <label class="form-check-label" for="initialSpinalYes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_spinal_injury" id="initialSpinalNo" value="no">
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
                                        <input class="form-check-input" type="radio" name="initial_consciousness" id="initialAlert" value="alert">
                                        <label class="form-check-label" for="initialAlert">Alert</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_consciousness" id="initialVerbal" value="verbal">
                                        <label class="form-check-label" for="initialVerbal">Verbal</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_consciousness" id="initialPain" value="pain">
                                        <label class="form-check-label" for="initialPain">Pain</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_consciousness" id="initialUnconscious" value="unconscious">
                                        <label class="form-check-label" for="initialUnconscious">Unconscious</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Helmet Status</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_helmet" id="initialHelmetAB" value="ab">
                                        <label class="form-check-label" for="initialHelmetAB">+ AB</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="initial_helmet" id="initialNoHelmet" value="none">
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
                                <input type="time" class="form-control" id="followupTime" name="followup_time">
                            </div>
                            <div>
                                <label for="followupBP" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="followupBP" name="followup_bp" placeholder="120/80">
                            </div>
                            <div>
                                <label for="followupTemp" class="form-label">Temp (°C)</label>
                                <input type="number" class="form-control" id="followupTemp" name="followup_temp" step="0.1" placeholder="36.5">
                            </div>
                            <div>
                                <label for="followupPulse" class="form-label">Pulse (BPM)</label>
                                <input type="number" class="form-control" id="followupPulse" name="followup_pulse" placeholder="72">
                            </div>
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="followupResp" class="form-label">Resp. Rate</label>
                                <input type="number" class="form-control" id="followupResp" name="followup_resp" placeholder="16">
                            </div>
                            <div>
                                <label for="followupPainScore" class="form-label">Pain Score (0-10)</label>
                                <input type="number" class="form-control" id="followupPainScore" name="followup_pain_score" min="0" max="10">
                            </div>
                            <div>
                                <label for="followupSPO2" class="form-label">SPO2 %</label>
                                <input type="number" class="form-control" id="followupSPO2" name="followup_spo2" min="0" max="100">
                            </div>
                            <div>
                                <label class="form-label">Spinal Injury</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="followup_spinal_injury" id="followupSpinalYes" value="yes">
                                        <label class="form-check-label" for="followupSpinalYes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="followup_spinal_injury" id="followupSpinalNo" value="no">
                                        <label class="form-check-label" for="followupSpinalNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Level of Consciousness</label>
                            <div class="inline-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="followup_consciousness" id="followupAlert" value="alert">
                                    <label class="form-check-label" for="followupAlert">Alert</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="followup_consciousness" id="followupVerbal" value="verbal">
                                    <label class="form-check-label" for="followupVerbal">Verbal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="followup_consciousness" id="followupPain" value="pain">
                                    <label class="form-check-label" for="followupPain">Pain</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="followup_consciousness" id="followupUnconscious" value="unconscious">
                                    <label class="form-check-label" for="followupUnconscious">Unconscious</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Assessment + Body Diagram -->
                <div class="tab-pane fade" id="section5" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-clipboard2-pulse"></i> Chief Complaints
                        </div>

                        <div class="form-group-compact">
                            <div class="checkbox-grid">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chestPain" name="chief_complaints[]" value="chestPain">
                                    <label class="form-check-label" for="chestPain">Chest Pain</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="headache" name="chief_complaints[]" value="headache">
                                    <label class="form-check-label" for="headache">Headache</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="blurredVision" name="chief_complaints[]" value="blurredVision">
                                    <label class="form-check-label" for="blurredVision">Blurred Vision</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="difficultyBreathing" name="chief_complaints[]" value="difficultyBreathing">
                                    <label class="form-check-label" for="difficultyBreathing">Difficulty Breathing</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="dizziness" name="chief_complaints[]" value="dizziness">
                                    <label class="form-check-label" for="dizziness">Dizziness</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bodyMalaise" name="chief_complaints[]" value="bodyMalaise">
                                    <label class="form-check-label" for="bodyMalaise">Body Malaise</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-section">
                            <label for="othersComplaint" class="form-label">Other Complaints</label>
                            <textarea class="form-control" id="othersComplaint" name="other_complaints" rows="2" placeholder="Describe other complaints"></textarea>
                        </div>

                        <!-- INTERACTIVE BODY DIAGRAM -->
                        <div class="body-diagram-container">
                            <div class="body-diagram-header">
                                <h6><i class="bi bi-person-bounding-box"></i> Interactive Injury Mapping</h6>
                                <small class="text-muted">Click on body diagram to mark injuries</small>
                            </div>
                            
                            <div class="body-diagram-content">
                                <div class="body-views">
                                    <div class="body-view">
                                        <div class="view-label">FRONT VIEW</div>
                                        <div class="body-image-container" id="frontContainer">
                                            <img src="../public/images/body-front.png" alt="Body Front" class="body-image">
                                        </div>
                                    </div>
                                    
                                    <div class="body-view">
                                        <div class="view-label">BACK VIEW</div>
                                        <div class="body-image-container" id="backContainer">
                                            <img src="../public/images/body-back.png" alt="Body Back" class="body-image">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="injury-sidebar">
                                    <div class="injury-type-selector">
                                        <label>Select Injury Type:</label>
                                        <div class="injury-type-grid">
                                            <button type="button" class="injury-type-btn active" data-type="laceration">
                                                <span class="color-indicator" style="background: #dc3545;"></span>
                                                Laceration
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="fracture">
                                                <span class="color-indicator" style="background: #fd7e14;"></span>
                                                Fracture
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="burn">
                                                <span class="color-indicator" style="background: #ffc107;"></span>
                                                Burn
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="contusion">
                                                <span class="color-indicator" style="background: #6f42c1;"></span>
                                                Contusion
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="abrasion">
                                                <span class="color-indicator" style="background: #20c997;"></span>
                                                Abrasion
                                            </button>
                                            <button type="button" class="injury-type-btn" data-type="other">
                                                <span class="color-indicator" style="background: #6c757d;"></span>
                                                Other
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="injury-list-header">
                                        Injuries Marked (<span id="injuryCount">0</span>)
                                    </div>
                                    <div id="injuryListContainer">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📍</div>
                                            <p>No injuries marked yet.<br>Click on body to add.</p>
                                        </div>
                                    </div>
                                    
                                    <div class="diagram-actions">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllInjuries()">Clear All</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportInjuryData()">Export</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="injuries_data" id="injuriesData">

                        <div class="fast-assessment">
                            <h6><i class="bi bi-exclamation-triangle-fill"></i> FOR Stroke Victim - F.A.S.T. Assessment</h6>
                            <div class="grid-2" style="gap: 1rem;">
                                <div class="grid-2" style="gap: 0.75rem;">
                                    <div>
                                        <label class="form-label">Face Drooping</label>
                                        <div class="inline-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="face_drooping" id="facePos" value="positive">
                                                <label class="form-check-label" for="facePos">(+)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="face_drooping" id="faceNeg" value="negative">
                                                <label class="form-check-label" for="faceNeg">(++)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Arm Weakness</label>
                                        <div class="inline-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="arm_weakness" id="armPos" value="positive">
                                                <label class="form-check-label" for="armPos">(+)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="arm_weakness" id="armNeg" value="negative">
                                                <label class="form-check-label" for="armNeg">(++)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Speech Difficulty</label>
                                        <div class="inline-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="speech_difficulty" id="speechPos" value="positive">
                                                <label class="form-check-label" for="speechPos">(+)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="speech_difficulty" id="speechNeg" value="negative">
                                                <label class="form-check-label" for="speechNeg">(++)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="form-label">Time to Call</label>
                                        <div class="inline-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="time_to_call" id="timePos" value="positive">
                                                <label class="form-check-label" for="timePos">(+)</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="time_to_call" id="timeNeg" value="negative">
                                                <label class="form-check-label" for="timeNeg">(++)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="fastDetails" class="form-label">S.A.M.P.L.E.</label>
                                    <textarea class="form-control" id="fastDetails" name="sample_details" rows="5" placeholder="Signs/Symptoms, Allergies, Medications, Pertinent history, Last oral intake, Events"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="ob-section">
                            <h6><i class="bi bi-hospital-fill"></i> FOR OB Patients Only</h6>
                            <div class="grid-3" style="gap: 1rem;">
                                <div>
                                    <label for="babyDelivery" class="form-label">Baby Status</label>
                                    <input type="text" class="form-control" id="babyDelivery" name="baby_status">
                                </div>
                                <div>
                                    <label for="timeOfDelivery" class="form-label">Delivery Time</label>
                                    <input type="time" class="form-control" id="timeOfDelivery" name="ob_delivery_time">
                                </div>
                                <div>
                                    <label class="form-label">Placenta</label>
                                    <div class="inline-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="placenta" id="placentaIn" value="in">
                                            <label class="form-check-label" for="placentaIn">In</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="placenta" id="placentaOut" value="out">
                                            <label class="form-check-label" for="placentaOut">Out</label>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label for="lmp" class="form-label">LMP</label>
                                    <input type="date" class="form-control" id="lmp" name="lmp">
                                </div>
                                <div>
                                    <label for="aog" class="form-label">AOG</label>
                                    <input type="text" class="form-control" id="aog" name="aog" placeholder="Weeks">
                                </div>
                                <div>
                                    <label for="edc" class="form-label">EDC</label>
                                    <input type="date" class="form-control" id="edc" name="edc">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 6: Team & Notes -->
                <div class="tab-pane fade" id="section6" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-pencil-square"></i> Team Leader Notes
                        </div>

                        <div class="mb-section">
                            <textarea class="form-control" id="teamLeaderNotes" name="team_leader_notes" rows="3" placeholder="Enter team leader notes and observations..."></textarea>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-people-fill"></i> Team Information
                        </div>

                        <div class="grid-3 mb-section">
                            <div>
                                <label for="teamLeader" class="form-label">Team Leader</label>
                                <input type="text" class="form-control" id="teamLeader" name="team_leader" placeholder="Name">
                            </div>
                            <div>
                                <label for="dataRecorder" class="form-label">Data Recorder</label>
                                <input type="text" class="form-control" id="dataRecorder" name="data_recorder" placeholder="Name">
                            </div>
                            <div>
                                <label for="logistic" class="form-label">Logistic</label>
                                <input type="text" class="form-control" id="logistic" name="logistic" placeholder="Name">
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="aider1" class="form-label">1st Aider</label>
                                <input type="text" class="form-control" id="aider1" name="aider1" placeholder="Name">
                            </div>
                            <div>
                                <label for="aider2" class="form-label">2nd Aider</label>
                                <input type="text" class="form-control" id="aider2" name="aider2" placeholder="Name">
                            </div>
                        </div>

                        <div class="section-title">
                            <i class="bi bi-building"></i> Hospital Endorsement
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="endorsement" class="form-label">Endorsement</label>
                                <input type="text" class="form-control" id="endorsement" name="endorsement" placeholder="Facility">
                            </div>
                            <div>
                                <label for="hospital" class="form-label">Hospital Name</label>
                                <input type="text" class="form-control" id="hospital" name="hospital_name" placeholder="Hospital name">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div>
                                <label class="form-label">ENDORSEMENT ATTACHMENT</label>
                                <div class="attachment-section">
                                    <div class="attachment-controls">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="openCameraBtn">
                                            <i class="bi bi-camera"></i> Open Camera
                                        </button>
                                        <input type="file" class="form-control form-control-sm" id="fileUpload" name="endorsement_attachment" accept="image/jpeg,image/png,image/gif,image/webp" style="display: inline-block; width: auto;" onchange="validateFileUpload(this)">
                                        <small class="text-muted">Max file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP</small>
                                    </div>
                                    <div id="cameraContainer" style="display: none; margin-top: 10px;">
                                        <video id="cameraVideo" autoplay playsinline style="width: 100%; max-width: 300px;"></video>
                                        <br>
                                        <button type="button" class="btn btn-success btn-sm" id="captureBtn" onclick="capturePhoto()">Capture Photo</button>
                                        <button type="button" class="btn btn-secondary btn-sm" id="closeCameraBtn" onclick="closeCamera()">Close Camera</button>
                                    </div>
                                    <div id="previewContainer" style="margin-top: 10px;">
                                        <img id="attachmentPreview" src="" alt="Attachment Preview" style="max-width: 200px; display: none;">
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="removeAttachmentBtn" style="display: none;" onclick="removeAttachment()">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </div>
                                    <div id="uploadError" class="text-danger" style="display: none;"></div>
                                </div>
                            </div>
                            <div>
                                <label for="dateTime" class="form-label">Date & Time</label>
                                <input type="datetime-local" class="form-control" id="dateTime" name="endorsement_datetime">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 7: Complete -->
                <div class="tab-pane fade" id="section7" role="tabpanel">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-check-circle"></i> Form Summary
                        </div>

                        <div class="summary-container" id="formSummary">
                            <!-- Summary will be populated by JavaScript -->
                        </div>

                        <div class="alert alert-success" style="margin-top: 1.5rem;">
                            <h5 class="alert-heading"><i class="bi bi-check-circle"></i> Ready to Submit</h5>
                            <p class="mb-3">Review all information above before submitting. Navigate back using tabs to make changes.</p>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-primary" onclick="printForm()">
                                    <i class="bi bi-printer"></i> Print Form
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="clearForm()">
                                    <i class="bi bi-arrow-clockwise"></i> Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="navigation-buttons">
            <button type="button" class="btn btn-outline-primary" id="prevBtn" onclick="navigateTab(-1)">
                <i class="bi bi-chevron-left"></i> Previous
            </button>
            <button type="button" class="btn btn-primary" id="nextBtn" onclick="navigateTab(1)">
                Next <i class="bi bi-chevron-right"></i>
            </button>
            <button type="button" class="btn btn-success" id="submitBtn" style="display: none;" onclick="submitForm()">
                <i class="bi bi-check2"></i> Save Form
            </button>
        </div>
    </div>

    <!-- Ambulance Selection Modal -->
    <div class="modal fade" id="ambulanceModal" tabindex="-1" aria-labelledby="ambulanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ambulanceModalLabel">Select Ambulance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please select an ambulance from the list below:</p>
                    <div id="ambulanceList">
                        <!-- Ambulance options will be generated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAmbulance">Confirm Selection</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Fire Truck Selection Modal -->
    <div class="modal fade" id="fireTruckModal" tabindex="-1" aria-labelledby="fireTruckModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fireTruckModalLabel">Select Fire Truck Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please select a fire truck type:</p>
                    <div class="vehicle-option" data-type="penetrator">
                        <div class="vehicle-name">Penetrator</div>
                        <div class="vehicle-details">Specialized for rescue operations and penetration</div>
                    </div>
                    <div class="vehicle-option" data-type="tanker">
                        <div class="vehicle-name">Tanker</div>
                        <div class="vehicle-details">Equipped with large water tank for fire suppression</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmFireTruck">Confirm Selection</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script src="js/prehospital-form.js"></script>
    <script>
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

        // Log initial loading state
        console.log('Page loading started. Body has loading class:', document.body.classList.contains('loading'));

        // Remove skeleton loading once page is fully loaded
        window.addEventListener('load', function() {
            console.log('Page loaded. Skeleton will hide in 3 seconds...');
            setTimeout(function() {
                document.body.classList.remove('loading');
                console.log('Loading class removed. Skeleton hidden.');
            }, 3000); // Extended delay to see skeleton effect
        });

        // ============================================
        // AUTOSAVE FUNCTIONALITY
        // ============================================
        let autosaveTimer = null;
        let currentDraftId = null;
        let lastSaveTime = null;
        let isFormDirty = false;
        let autosaveEnabled = false; // Prevent autosave on page load

        // Check URL for draft_id parameter
        const urlParams = new URLSearchParams(window.location.search);
        const resumeDraftId = urlParams.get('draft_id');
        if (resumeDraftId) {
            currentDraftId = resumeDraftId;
            loadDraft(resumeDraftId);
        }

        // Function to collect all form data
        function collectFormData() {
            const form = document.getElementById('preHospitalForm');
            const formData = new FormData(form);
            const data = {};

            // Convert FormData to plain object
            for (let [key, value] of formData.entries()) {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    if (!data[cleanKey]) {
                        data[cleanKey] = [];
                    }
                    data[cleanKey].push(value);
                } else {
                    data[key] = value;
                }
            }

            // Add draft_id if exists
            if (currentDraftId) {
                data.draft_id = currentDraftId;
            }

            return data;
        }

        // Function to check if form has meaningful data
        function hasFormData() {
            const data = collectFormData();

            // Check if any non-hidden, non-csrf fields have values
            for (let key in data) {
                if (key === 'csrf_token' || key === 'draft_id') continue;

                const value = data[key];
                // Check if value exists and is not empty
                if (value && value !== '' && value !== '[]' && value.length > 0) {
                    // If it's an array, check if it has elements
                    if (Array.isArray(value) && value.length > 0) {
                        return true;
                    }
                    // If it's a non-empty string
                    if (typeof value === 'string' && value.trim() !== '') {
                        return true;
                    }
                }
            }
            return false;
        }

        // Function to perform autosave
        function performAutosave() {
            if (!autosaveEnabled) {
                console.log('Autosave skipped: autosave not yet enabled (waiting for user interaction)');
                return;
            }

            if (!isFormDirty) {
                console.log('Autosave skipped: form not dirty');
                return;
            }

            // Check if form has any actual data
            if (!hasFormData()) {
                console.log('Autosave skipped: no meaningful data entered yet');
                isFormDirty = false; // Reset dirty flag
                return;
            }

            console.log('Performing autosave...');
            const data = collectFormData();
            console.log('Form data collected:', data);

            // Show saving indicator
            Notiflix.Loading.circle('Saving draft...', {
                svgColor: '#0066cc',
                backgroundColor: 'rgba(0,0,0,0.8)',
            });

            fetch('../api/autosave_draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('Autosave response status:', response.status);
                return response.json();
            })
            .then(result => {
                console.log('Autosave result:', result);
                console.log('Draft ID received from server:', result.draft_id);
                console.log('Form number:', result.form_number);
                Notiflix.Loading.remove();

                if (result.success) {
                    // Always update currentDraftId if we get one back
                    if (result.draft_id) {
                        const oldDraftId = currentDraftId;
                        if (!currentDraftId || currentDraftId !== result.draft_id) {
                            currentDraftId = result.draft_id;
                            // Update URL without reload
                            const newUrl = window.location.pathname + '?draft_id=' + currentDraftId;
                            window.history.replaceState({}, '', newUrl);
                            // Also update the hidden field
                            document.getElementById('draftIdField').value = currentDraftId;
                            console.log('Draft ID UPDATED: from', oldDraftId, 'to', currentDraftId);
                            console.log('Form number:', result.form_number);
                            console.log('URL updated to:', newUrl);
                            console.log('Hidden field value:', document.getElementById('draftIdField').value);
                        } else {
                            console.log('Draft ID unchanged:', currentDraftId);
                        }
                    } else {
                        console.error('ERROR: No draft_id in response!');
                    }

                    lastSaveTime = new Date();
                    isFormDirty = false;

                    // Show subtle success toast with draft link
                    const message = 'Draft saved at ' + result.timestamp + ' (ID: ' + result.draft_id + ')';
                    Notiflix.Notify.success(message, {
                        timeout: 3000,
                        position: 'right-bottom',
                        distance: '15px',
                        fontSize: '13px',
                    });

                    // Log for debugging
                    console.log('✓ DRAFT SAVED SUCCESSFULLY');
                    console.log('  Draft ID:', result.draft_id);
                    console.log('  Form Number:', result.form_number);
                    console.log('  View at: drafts.php');
                } else {
                    console.error('Autosave failed:', result.message);
                    Notiflix.Notify.failure('Failed to save draft: ' + result.message, {
                        timeout: 3000,
                    });
                }
            })
            .catch(error => {
                Notiflix.Loading.remove();
                console.error('Autosave error:', error);
                Notiflix.Notify.warning('Auto-save failed. Your progress is not saved.', {
                    timeout: 4000,
                });
            });
        }

        // Function to load draft data
        function loadDraft(draftId) {
            Notiflix.Loading.circle('Loading draft...', {
                svgColor: '#0066cc',
            });

            fetch(`../api/get_draft.php?id=${draftId}`)
                .then(response => response.json())
                .then(result => {
                    Notiflix.Loading.remove();

                    if (result.success && result.data) {
                        populateForm(result.data);
                        // Set the draft_id in the hidden field so it updates on submit
                        document.getElementById('draftIdField').value = draftId;
                        Notiflix.Report.success(
                            'Draft Loaded',
                            'Your previous work has been restored. Continue where you left off!',
                            'Continue Editing'
                        );
                    } else {
                        Notiflix.Report.failure(
                            'Load Failed',
                            result.message || 'Could not load draft',
                            'Okay'
                        );
                    }
                })
                .catch(error => {
                    Notiflix.Loading.remove();
                    console.error('Load draft error:', error);
                    Notiflix.Report.failure(
                        'Error',
                        'Failed to load draft data',
                        'Okay'
                    );
                });
        }

        // Function to populate form with draft data
        function populateForm(data) {
            // Text inputs
            for (let key in data) {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) {
                    if (input.type === 'radio') {
                        const radio = document.querySelector(`[name="${key}"][value="${data[key]}"]`);
                        if (radio) radio.checked = true;
                    } else if (input.type === 'checkbox') {
                        // Handle JSON arrays
                        try {
                            const values = JSON.parse(data[key]);
                            if (Array.isArray(values)) {
                                values.forEach(val => {
                                    const checkbox = document.querySelector(`[name="${key}[]"][value="${val}"]`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            }
                        } catch (e) {
                            // Single checkbox
                            input.checked = !!data[key];
                        }
                    } else {
                        // Handle time fields - don't populate if value is 00:00:00 or null
                        if (input.type === 'time') {
                            const timeValue = data[key];
                            // Only set value if it's not null, empty, or 00:00:00
                            if (timeValue && timeValue !== '00:00:00' && timeValue !== '0000-00-00 00:00:00') {
                                input.value = timeValue;
                            }
                        } else if (input.type === 'date' || input.type === 'datetime-local') {
                            const dateValue = data[key];
                            // Only set value if it's not null, empty, or 0000-00-00
                            if (dateValue && dateValue !== '0000-00-00' && dateValue !== '0000-00-00 00:00:00') {
                                input.value = dateValue;
                            }
                        } else {
                            // Regular input - set value or empty string
                            input.value = data[key] || '';
                        }
                    }
                }
            }
        }

        // Initialize autosave listeners after page loads
        function initializeAutosave() {
            const formInputs = document.querySelectorAll('#preHospitalForm input, #preHospitalForm select, #preHospitalForm textarea');
            console.log('Initializing autosave for', formInputs.length, 'form fields');

            formInputs.forEach(input => {
                // Skip CSRF token and draft_id fields
                if (input.name === 'csrf_token' || input.name === 'draft_id') {
                    return;
                }

                input.addEventListener('change', () => {
                    console.log('Field changed:', input.name);

                    // Enable autosave after first user interaction
                    if (!autosaveEnabled) {
                        autosaveEnabled = true;
                        console.log('Autosave enabled after first user interaction');
                    }

                    isFormDirty = true;

                    // Clear existing timer
                    if (autosaveTimer) {
                        clearTimeout(autosaveTimer);
                    }

                    // Set new timer (autosave after 3 seconds of inactivity)
                    autosaveTimer = setTimeout(() => {
                        performAutosave();
                    }, 3000);
                });

                // Also trigger on input for text fields (more responsive)
                if (input.type === 'text' || input.type === 'textarea' || input.tagName === 'TEXTAREA') {
                    input.addEventListener('input', () => {
                        console.log('Field input:', input.name);

                        // Enable autosave after first user interaction
                        if (!autosaveEnabled) {
                            autosaveEnabled = true;
                            console.log('Autosave enabled after first user interaction');
                        }

                        isFormDirty = true;

                        if (autosaveTimer) {
                            clearTimeout(autosaveTimer);
                        }

                        autosaveTimer = setTimeout(() => {
                            performAutosave();
                        }, 5000); // Longer delay for typing
                    });
                }
            });
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeAutosave);
        } else {
            // DOM already loaded
            initializeAutosave();
        }

        // Save before leaving page
        window.addEventListener('beforeunload', (e) => {
            if (isFormDirty && hasFormData()) {
                e.preventDefault();
                e.returnValue = 'You have unsaved work in progress. Your data has been auto-saved as a draft.';
                // Perform synchronous save
                performAutosave();
            }
        });

        // Intercept sidebar links to show draft continuation prompt
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar a[href]');

            sidebarLinks.forEach(link => {
                // Skip the prehospital_form.php link itself
                if (link.href.includes('prehospital_form.php')) {
                    return;
                }

                link.addEventListener('click', function(e) {
                    // Check if form has unsaved data
                    if (isFormDirty && hasFormData()) {
                        e.preventDefault();
                        const targetUrl = this.href;

                        Notiflix.Confirm.show(
                            'Continue or Discard Draft?',
                            'You have unsaved work in progress. What would you like to do?',
                            'Continue Later',
                            'Discard & Leave',
                            function() {
                                // Continue Later - save draft and navigate
                                isFormDirty = true;
                                fetch('../api/autosave_draft.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify(collectFormData())
                                })
                                .then(response => response.json())
                                .then(result => {
                                    if (result.success) {
                                        Notiflix.Notify.success('Draft saved! You can resume from "My Drafts"', {
                                            timeout: 3000
                                        });
                                        setTimeout(() => {
                                            window.location.href = targetUrl;
                                        }, 1000);
                                    } else {
                                        window.location.href = targetUrl;
                                    }
                                })
                                .catch(() => {
                                    window.location.href = targetUrl;
                                });
                            },
                            function() {
                                // Discard - just navigate away
                                Notiflix.Notify.warning('Draft discarded', {
                                    timeout: 2000
                                });
                                setTimeout(() => {
                                    window.location.href = targetUrl;
                                }, 500);
                            },
                            {
                                width: '400px',
                                titleColor: '#0066cc',
                                okButtonBackground: '#28a745',
                                cancelButtonBackground: '#dc3545',
                            }
                        );
                    }
                });
            });
        });

        // Manual save button - positioned in top-right, below navbar
        const manualSaveBtn = document.createElement('button');
        manualSaveBtn.type = 'button';
        manualSaveBtn.className = 'btn btn-sm btn-outline-secondary';
        manualSaveBtn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill" style="font-size: 1.1rem; margin-right: 6px;"></i> Save Draft Now';
        manualSaveBtn.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: white;
            color: #0066cc;
            border: 2px solid #0066cc;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        `;

        // Add hover effect
        manualSaveBtn.addEventListener('mouseenter', () => {
            manualSaveBtn.style.background = '#0066cc';
            manualSaveBtn.style.color = 'white';
            manualSaveBtn.style.transform = 'translateY(-2px)';
            manualSaveBtn.style.boxShadow = '0 6px 16px rgba(0,102,204,0.3)';
        });

        manualSaveBtn.addEventListener('mouseleave', () => {
            manualSaveBtn.style.background = 'white';
            manualSaveBtn.style.color = '#0066cc';
            manualSaveBtn.style.transform = 'translateY(0)';
            manualSaveBtn.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        });

        manualSaveBtn.onclick = () => {
            // Enable autosave when manually saving
            if (!autosaveEnabled) {
                autosaveEnabled = true;
                console.log('Autosave enabled by manual save button');
            }
            isFormDirty = true;
            performAutosave();
        };
        document.body.appendChild(manualSaveBtn);
    </script>
</body>
</html>
