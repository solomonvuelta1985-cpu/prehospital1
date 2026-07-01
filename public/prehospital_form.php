<?php
/**
 * Pre-Hospital Care Form - PHP Version
 * Maintains exact HTML design with PHP security features
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <link href="css/records-style.css?v=<?php echo asset_version(); ?>" rel="stylesheet">
    <link href="css/prehospital-form.css?v=<?php echo asset_version(); ?>" rel="stylesheet">
    <style>
        /* Indigo design tokens now live in css/prehospital-form.css (:root).
           Only page-specific layout/overrides remain inline below. */

        /* Sidebar Layout Compatibility - Corporate Design 2026 */
        body { overflow: auto !important; height: auto !important; }
        /* Flat full-bleed form: kill the gray gutter + inset so the form is NOT a card.
           The form fills the content area edge-to-edge on a single white surface. */
        .content { overflow-y: auto !important; overflow-x: hidden !important; padding-bottom: 0 !important; min-height: 100vh; max-height: 100vh; position: relative; background: #ffffff !important; }
        .content > .container-fluid { padding: 0 !important; max-width: 100% !important; }
        /* Header keeps comfortable side padding now that the container gutter is gone */
        .content > .container-fluid > .page-header-inline { padding: var(--space-page, 1.25rem) var(--space-page, 1.5rem) 0; margin-bottom: 1rem; }
        .form-container { overflow: visible !important; height: auto !important; max-width: 100%; margin-bottom: 0; padding-bottom: 0; background: #ffffff !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; }
        .form-body { overflow: visible !important; max-height: none !important; padding-bottom: 2rem !important; min-height: calc(100vh - 400px) !important; }
        .tab-content { overflow: visible !important; padding-bottom: 0 !important; }
        .tab-pane { padding-bottom: 0 !important; }
        .form-section { margin-bottom: 0 !important; padding-bottom: 0 !important; }

        /* Sticky Navigation with Indigo Accent */
        .navigation-buttons {
            position: sticky !important; bottom: 0 !important; left: 0; right: 0;
            background: #ffffff !important; margin-top: auto !important;
            margin-bottom: 0 !important; z-index: 1000 !important;
            border-top: 4px solid #4f46e5 !important; padding: 1.5rem 2rem !important;
            display: flex !important; justify-content: space-between !important;
            gap: 1rem !important; box-shadow: 0 -4px 16px rgba(15,23,42,0.10) !important;
            flex-shrink: 0 !important;
        }
        @supports (position: sticky) { .navigation-buttons { position: -webkit-sticky !important; position: sticky !important; } }
        @media (max-width: 768px) { body.sidebar-open .navigation-buttons { opacity: 0.25 !important; pointer-events: none !important; } }

        /* Notiflix Fix */
        div[id^="NotiflixReportWrap"] button, div[id^="NotiflixReportWrap"] button:hover, div[id^="NotiflixReportWrap"] button:focus, div[id^="NotiflixReportWrap"] button:active { text-decoration: none !important; }

        /* Indigo Navigation Tabs */
        .tabs-container { margin-bottom: 1.5rem; overflow-x: auto; overflow-y: visible; }
        .nav-tabs { border-bottom: 2px solid var(--gray-200, #e5e7eb); display: flex; flex-wrap: nowrap; gap: 0; padding: 0; position: relative; }
        .nav-item { flex: 0 0 auto; min-width: auto; position: relative; margin: 0; }
        .nav-link { background: transparent; color: #64748b; border: none; border-bottom: 3px solid transparent; padding: 0.75rem 0.875rem; font-weight: 600; font-size: 0.875rem; font-style: normal; text-align: center; position: relative; border-radius: 0; transition: all .2s ease; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 0.35rem; }
        .nav-item:not(:last-child) .nav-link::after { content: 'â€º'; position: absolute; right: -4px; font-size: 1.25rem; color: #cbd5e1; font-weight: 300; z-index: 1; }
        .nav-link:hover { color: #4f46e5; background: rgba(79,70,229,0.05); border-bottom-color: #a5b4fc; }
        .nav-link.active { color: #4f46e5; background: transparent; border-bottom-color: #4f46e5; font-weight: 700; }
        .nav-link.active::after { color: #4f46e5 !important; }
        .nav-link.completed:not(.active) { color: #059669; }
        @media (max-width: 992px) { .nav-link { font-size: .8rem; padding: .65rem .75rem; } }
        @media (max-width: 768px) { .nav-link { font-size: .75rem; padding: .6rem .65rem; gap: .25rem; } .nav-item:not(:last-child) .nav-link::after { font-size: 1.1rem; right: -3px; } }
        @media (max-width: 576px) { .tabs-container { margin-left: -.5rem; margin-right: -.5rem; padding: 0 .25rem; } .nav-link { font-size: .7rem; padding: .5rem .4rem; } .nav-item:not(:last-child) .nav-link::after { display: none; } }

        /* Mobile Touch-Friendly Inputs */
        @media (max-width: 768px) { input[type="time"], input[type="datetime-local"], input[type="date"] { font-size: 16px !important; min-height: 48px !important; padding: 12px !important; -webkit-appearance: none; appearance: none; } }
        @media (max-width: 576px) { input[type="time"], input[type="datetime-local"], input[type="date"] { font-size: 16px !important; min-height: 50px !important; } }

        /* Uppercase Inputs */
        input[type="text"]:not(.no-uppercase), input[type="email"], input[type="tel"], input[type="url"], select, #teamLeaderNotes { text-transform: uppercase !important; }
        textarea:not(#teamLeaderNotes), input[type="text"].no-uppercase { text-transform: none !important; }
        input::placeholder, textarea::placeholder { text-transform: none !important; }

        /* Vehicle Display Bar - Indigo */
        #selectedVehicleDisplay { background: linear-gradient(90deg, #eef2ff 0%, #e0e7ff 100%); border-left: 4px solid #4f46e5; border-radius: 8px; }

        /* View Summary Button - Indigo */
        .summary-button-container { padding: 1.5rem 1rem; }
        #viewSummaryBtn { padding: .55rem 1.5rem; font-size: .875rem; font-weight: 600; border-radius: 8px; border: 2px solid #4f46e5; color: #4f46e5; background: #fff; transition: all .2s ease; }
        #viewSummaryBtn i { font-size: .95rem; margin-right: .35rem; }
        #viewSummaryBtn:hover { background: #eef2ff; box-shadow: 0 2px 12px rgba(79,70,229,0.2); transform: translateY(-1px); }
        .summary-help-text { font-size: .9rem; }
        @media (max-width: 768px) { .summary-button-container { padding: 1.25rem .5rem; } #viewSummaryBtn { padding: .4rem 1.1rem; font-size: .85rem; } .summary-help-text { font-size: .85rem; padding: 0 1rem; } }
        @media (max-width: 576px) { .summary-button-container { padding: 1rem .5rem; } #viewSummaryBtn { padding: .4rem 1rem; font-size: .8rem; } .summary-help-text { font-size: .8rem; } }

        /* ===== NARRATIVE REPORT CARD - Indigo Corporate Design ===== */
        .narrative-report-card {
            background: #fff;
            border: 1px solid var(--gray-200, #e5e7eb);
            border-radius: var(--radius-lg, 16px);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(16,24,40,0.06);
            margin: 1.5rem 0;
        }
        .narrative-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #fff;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .narrative-header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .narrative-icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .narrative-header-left h5 {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.01em;
        }
        .narrative-header-left span {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
            font-weight: 400;
        }
        .narrative-header-right {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }
        .narrative-pills {
            display: flex;
            gap: 0;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 3px;
        }
        .narrative-pill {
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-family: 'Inter', sans-serif;
        }
        .narrative-pill:hover { color: #fff; background: rgba(255,255,255,0.12); }
        .narrative-pill.active {
            background: #fff;
            color: #4f46e5;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .narrative-icon-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(255,255,255,0.15);
            border: none;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .narrative-icon-btn:hover { background: rgba(255,255,255,0.25); }
        .narrative-body {
            padding: 1.5rem;
            min-height: 160px;
            background: #fafbfc;
        }
        .narrative-text {
            font-family: 'SF Mono', 'Monaco', 'Consolas', 'Courier New', monospace;
            font-size: 0.8125rem;
            line-height: 1.7;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .narrative-placeholder-modern {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            text-align: center;
            min-height: 160px;
        }
        .narrative-placeholder-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }
        .narrative-placeholder-modern p {
            font-size: 0.8125rem;
            color: #64748b;
            margin: 0 0 1rem 0;
            font-family: 'Inter', sans-serif;
        }
        .btn-generate-narrative {
            padding: 0.5rem 1.25rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 8px;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(79,70,229,0.25);
        }
        .btn-generate-narrative:hover {
            background: linear-gradient(135deg, #4338ca, #4f46e5);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.35);
        }
        .narrative-footer-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-top: 1px solid var(--gray-100, #f1f5f9);
            background: #fff;
            flex-wrap: wrap;
        }
        .narrative-footer-btn {
            padding: 0.4rem 0.9rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            background: #fff;
            border: 1px solid var(--gray-200, #e5e7eb);
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: 'Inter', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .narrative-footer-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
        }
        .narrative-footer-btn i { font-size: 0.75rem; }
        @media (max-width: 768px) {
            .narrative-header-bar { flex-direction: column; align-items: flex-start; padding: 1rem; }
            .narrative-header-right { width: 100%; }
            .narrative-pills { width: 100%; }
            .narrative-pill { flex: 1; text-align: center; }
            .narrative-body { padding: 1rem; }
            .narrative-footer-actions { padding: 0.625rem 1rem; }
            .narrative-footer-btn { flex: 1; justify-content: center; }
        }

        /* Header Actions */
        .header-actions { display: flex; gap: 0.75rem; align-items: center; }
    </style>
</head>
<body class="loading">
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- ===== PAGE HEADER ===== -->
            <div class="page-header-inline">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon"><i class="bi bi-file-medical"></i></span>
                        Pre-Hospital Care Form
                    </h1>
                    <p class="page-subtitle">Emergency Medical Services &middot; <strong>RESQ-link EMS</strong></p>
                </div>
                <div class="header-actions">
                    <a href="drafts.php" class="btn-ghost">
                        <i class="bi bi-file-earmark-text"></i> My Drafts
                    </a>
                    <button type="button" class="btn-primary" id="manualSaveBtnHeader">
                        <i class="bi bi-cloud-arrow-up-fill"></i> Save Draft
                    </button>
                </div>
            </div>

        <div class="form-container">
        <div class="progress-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.85rem; font-weight: 600; color: #4f46e5;">
                    <i class="bi bi-list-check"></i> Form Progress
                </span>
                <span id="stepIndicator" style="font-size: 0.85rem; font-weight: 500; color: #64748b;">
                    Step 1 of 7
                </span>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" id="progressBar" style="width: 14%; background: linear-gradient(135deg, #6366f1, #4f46e5);"></div>
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

                        <!-- Date & Vehicle -->
                        <div class="subsection-title">
                            <i class="bi bi-calendar-event"></i> Date & Vehicle
                        </div>

                        <!-- Date + Vehicle Used â€” side by side -->
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="formDate" class="form-label required-field">Date</label>
                                <input type="date" class="form-control" id="formDate" name="form_date" required>
                            </div>
                            <div>
                                <label class="form-label">Vehicle Used</label>
                                <div class="inline-group" style="min-height: var(--input-h); align-items: center;">
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
                            </div>
                        </div>

                        <!-- Ambulance Unit â€” own row; opens a modal picker -->
                        <div id="ambulanceDropdownContainer" class="mb-section" style="display: none; max-width: 24rem;">
                            <label class="form-label">Ambulance Unit</label>
                            <!-- Hidden select preserves the existing JS/save/resume contract:
                                 the modal writes its value and dispatches 'change'. -->
                            <select id="ambulanceSelect" tabindex="-1" aria-hidden="true" style="display:none !important;">
                                <option value="">-- Select Ambulance --</option>
                                <?php for ($__v = 1; $__v <= 18; $__v++): ?>
                                <option value="V<?php echo $__v; ?>">V<?php echo $__v; ?></option>
                                <?php endfor; ?>
                            </select>
                            <!-- Trigger: shows current pick, opens the picker modal -->
                            <button type="button" class="unit-trigger" id="ambulanceUnitTrigger" data-bs-toggle="modal" data-bs-target="#ambulanceUnitModal">
                                <span class="unit-trigger-value" id="ambulanceUnitTriggerText">Select unitâ€¦</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>

                        <input type="hidden" name="vehicle_details" id="vehicleDetails">
                        <!-- Selected Vehicle Display -->
                        <div id="selectedVehicleDisplay" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: linear-gradient(90deg, #eef2ff 0%, #e0e7ff 100%); border-left: 4px solid #4f46e5; border-radius: 8px;">
                            <strong style="color: #3730a3;">Selected Vehicle:</strong>
                            <span id="selectedVehicleText" style="color: #1e293b; margin-left: 0.5rem;"></span>
                        </div>

                        <hr class="section-divider-light">

                        <!-- Response Timeline -->
                        <div class="subsection-title">
                            <i class="bi bi-clock-history"></i> Response Timeline
                        </div>

                        <!-- Walk In / Call + Call/Arrival Time + Contact Number -->
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
                                <input type="text" class="form-control time-input-12hr" id="callArrTime" name="call_arrival_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="cpNumber" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="cpNumber" name="contact_number" placeholder="Contact number">
                            </div>
                        </div>

                        <!-- 1. Driver + Departure Time -->
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="driver" class="form-label">Driver</label>
                                <input type="text" class="form-control" id="driver" name="driver" placeholder="Driver name">
                            </div>
                            <div>
                                <label for="depTime" class="form-label required-field">Departure Time</label>
                                <input type="text" class="form-control time-input-12hr" id="depTime" name="departure_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true" required>
                            </div>
                            <!-- <div>
                                <label for="arrTime" class="form-label">Arrival Time</label>
                                <input type="text" class="form-control time-input-12hr" id="arrTime" name="arrival_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div> -->
                        </div>

                        <!-- 2. Arrival at Scene -->
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrSceneLocation" class="form-label">Arrival at Scene - Location</label>
                                <input type="text" class="form-control" id="arrSceneLocation" name="arrival_scene_location" placeholder="Scene location">
                            </div>
                            <div>
                                <label for="arrSceneTime" class="form-label">Arrival at Scene - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="arrSceneTime" name="arrival_scene_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <!-- 3. Departure at Scene -->
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="depSceneLocation" class="form-label">Departure from Scene - Location</label>
                                <input type="text" class="form-control" id="depSceneLocation" name="departure_scene_location" placeholder="Departure location">
                            </div>
                            <div>
                                <label for="depSceneTime" class="form-label">Departure from Scene - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="depSceneTime" name="departure_scene_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <!-- 4. Arrival at Hospital -->
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="arrHospName" class="form-label">Arrival at Hospital - Name</label>
                                <input type="text" class="form-control" id="arrHospName" name="arrival_hospital_name" placeholder="Hospital name">
                            </div>
                            <div>
                                <label for="arrHospTime" class="form-label">Arrival at Hospital - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="arrHospTime" name="arrival_hospital_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <!-- 5-6. Departure from Hospital + Arrival at Station -->
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="depHospTime" class="form-label">Departure from Hospital - Time</label>
                                <input type="text" class="form-control time-input-12hr" id="depHospTime" name="departure_hospital_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="arrStation" class="form-label">Arrival at Station</label>
                                <input type="text" class="form-control time-input-12hr" id="arrStation" name="arrival_station_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>

                        <hr class="section-divider-light">

                        <!-- Persons Present -->
                        <div class="subsection-title">
                            <i class="bi bi-people"></i> Scene Observation
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

                        <!-- Basic Patient Details -->
                        <div class="subsection-title">
                            <i class="bi bi-person-badge"></i> Personal Details
                        </div>
                        <!-- Patient Name â€” own row -->
                        <div class="mb-section">
                            <label for="patientName" class="form-label required-field">Patient Name</label>
                            <input type="text" class="form-control" id="patientName" name="patient_name" placeholder="Last Name, First Name, Middle Initial" required>
                        </div>

                        <!-- DOB Â· Age Â· Growth Status â€” balanced row -->
                        <div class="grid-3 mb-section">
                            <div>
                                <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth">
                            </div>
                            <div>
                                <label for="age" class="form-label required-field">Age</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="age" name="age" min="0" max="150" placeholder="Enter age" required>
                                    <select class="form-select" id="ageUnit" name="age_unit" style="max-width: 110px;">
                                        <option value="years" selected>Years</option>
                                        <option value="months">Months</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label for="growthStatus" class="form-label">Growth Status</label>
                                <select class="form-select" id="growthStatus" name="growth_status">
                                    <option value="">Select...</option>
                                    <option value="infant">Infant (0-1 year)</option>
                                    <option value="child">Child (2-12 years)</option>
                                    <option value="adult">Adult (13-59 years)</option>
                                    <option value="senior">Senior (60+ years)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="gender" class="form-label required-field">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender...</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="civilStatus" class="form-label">Civil Status</label>
                                <select class="form-select" id="civilStatus" name="civil_status">
                                    <option value="">Select Civil Status...</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="widowed">Widowed</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="separated">Separated</option>
                                </select>
                            </div>
                        </div>

                        <hr class="section-divider-light">

                        <!-- Address & Location -->
                        <div class="subsection-title">
                            <i class="bi bi-house-door"></i> Address & Location
                        </div>
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" placeholder="Complete address">
                            </div>
                            <div>
                                <label for="zone" class="form-label">Zone</label>
                                <input type="text" class="form-control" id="zone" name="zone" placeholder="Zone/Purok">
                            </div>
                        </div>

                        <hr class="section-divider-light">

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" name="occupation" placeholder="Patient's occupation">
                            </div>
                            <!-- Commented out: place_of_incident moved label was misleading
                            <div>
                                <label for="placeOfIncident" class="form-label">Type of Emergency Call</label>
                                <input type="text" class="form-control" id="placeOfIncident" name="place_of_incident" placeholder="Location where incident occurred">
                            </div>
                            -->
                        </div>

                        <!-- Commented out: zone_landmark field and incident_time moved to Emergency tab
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="zoneLandmark" class="form-label">Zone/Landmark</label>
                                <input type="text" class="form-control" id="zoneLandmark" name="zone_landmark" placeholder="Nearest landmark">
                            </div>
                            <div>
                                <label for="incidentTime" class="form-label">Time of Incident</label>
                                <input type="text" class="form-control time-input-12hr" id="incidentTime" name="incident_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                        </div>
                        -->

                        <hr class="section-divider">

                        <div class="section-title">
                            <i class="bi bi-telephone"></i> Informant Details
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="informant" class="form-label">Informant Name</label>
                                <input type="text" class="form-control" id="informant" name="informant_name" placeholder="Name of informant">
                            </div>
                            <div>
                                <label for="informantAddress" class="form-label">Informant Address</label>
                                <input type="text" class="form-control" id="informantAddress" name="informant_address" placeholder="Informant's address">
                            </div>
                        </div>


                        <hr class="section-divider-light">

                        <!-- Personal Belongings -->
                        <div class="subsection-title">
                            <i class="bi bi-bag"></i> Personal Belongings
                        </div>
                        <div class="grid-2 mb-section">
                            <div>
                                <label for="relationshipVictim" class="form-label">Relationship to Victim</label>
                                <input type="text" class="form-control" id="relationshipVictim" name="relationship_victim" placeholder="e.g., Spouse, Parent, Sibling">
                                <label for="otherBelongings" class="form-label" style="margin-top: 1rem;">Other Belongings (specify)</label>
                                <input type="text" class="form-control" id="otherBelongings" name="other_belongings" placeholder="List other belongings not mentioned above">
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

                        <hr class="section-divider">

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
                                            <span class="upload-method-desc">JPG, PNG, GIF, WebP (max 20MB, auto-compressed)</span>
                                        </div>
                                        <input type="file" class="hidden-file-input" id="patientFileUpload" name="patient_documentation" accept="image/jpeg,image/png,image/gif,image/webp">
                                    </label>
                                </div>

                                <!-- Camera Container -->
                                <div id="patientCameraContainer" class="patient-camera-container">
                                    <div class="camera-top-bar">
                                        <button type="button" class="camera-close-btn" data-action="closePatientCamera">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                        <span class="camera-top-label">Patient Photo</span>
                                        <div class="camera-top-spacer"></div>
                                    </div>
                                    <div class="camera-viewport">
                                        <video id="patientCameraVideo" autoplay playsinline></video>
                                        <div class="camera-flash-overlay" id="patientFlashOverlay"></div>
                                    </div>
                                    <div class="camera-bottom-bar">
                                        <div class="camera-bottom-spacer"></div>
                                        <button type="button" class="camera-shutter-btn" id="capturePatientBtn" data-action="capturePatientPhoto">
                                            <span class="shutter-ring"><span class="shutter-inner"></span></span>
                                        </button>
                                        <button type="button" class="camera-flip-btn" id="flipPatientCameraBtn" data-action="flipPatientCamera">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Image Preview -->
                                <div id="patientPreviewContainer" class="patient-preview-container">
                                    <div class="preview-card">
                                        <div class="preview-image-wrapper" data-action="openPatientImageModal">
                                            <img id="patientAttachmentPreview" src="" alt="Patient Documentation Preview">
                                            <div class="preview-overlay">
                                                <i class="bi bi-zoom-in"></i>
                                                <span>Click to enlarge</span>
                                            </div>
                                        </div>
                                        <div class="preview-actions">
                                            <span class="preview-label">
                                                <i class="bi bi-check-circle-fill"></i>
                                                Photo attached
                                            </span>
                                            <button type="button" class="remove-preview-btn" id="removePatientAttachmentBtn" data-action="removePatientAttachment">
                                                <i class="bi bi-trash3"></i>
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- GPS Status Indicator -->
                                <div id="gpsStatus" class="gps-status-indicator" style="display: none;"></div>

                                <!-- GPS Metadata Hidden Fields -->
                                <input type="hidden" name="photo_latitude" id="photoLatitude">
                                <input type="hidden" name="photo_longitude" id="photoLongitude">
                                <input type="hidden" name="photo_address" id="photoAddress">
                                <input type="hidden" name="photo_datetime" id="photoDatetime">

                                <!-- Error Message -->
                                <div id="patientUploadError" class="patient-upload-error">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Image Preview Modal -->
                        <div id="patientImageModal" class="image-preview-modal" data-action="closePatientImageModal">
                            <div class="modal-content-custom" data-action="stopPropagation">
                                <button type="button" class="modal-close-btn" data-action="closePatientImageModal">
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
                            <i class="bi bi-telephone-fill"></i> Type of Emergency Call <span class="required-field"></span>
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="medical" name="emergency_type[]" value="medical">
                                    <label class="form-check-label" for="medical"><strong>Medical</strong></label>
                                </div>
                                <input type="text" class="form-control" id="medicalSpecify" name="medical_specify" placeholder="Specify medical condition" list="incidentCategoryList">
                            </div>
                            <div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="trauma" name="emergency_type[]" value="trauma">
                                    <label class="form-check-label" for="trauma"><strong>Trauma</strong></label>
                                </div>
                                <input type="text" class="form-control" id="traumaSpecify" name="trauma_specify" placeholder="Specify trauma type" list="incidentCategoryList">
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
                                <input type="text" class="form-control" id="generalSpecify" name="general_specify" placeholder="Specify general condition" list="incidentCategoryList">
                            </div>
                        </div>
                        <datalist id="incidentCategoryList">
                            <?php foreach (incident_categories() as $__cat): ?>
                            <option value="<?php echo e($__cat); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <div class="mb-section" style="max-width: 24rem;">
                            <label for="incidentTime" class="form-label">Time of Incident</label>
                            <input type="text" class="form-control time-input-12hr" id="incidentTime" name="incident_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                        </div>

                        <hr class="section-divider">

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

                        <hr class="section-divider-light">

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="o2LPM" class="form-label">OÂ² (LPM via)</label>
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
                                <input type="text" class="form-control time-input-12hr" id="initialTime" name="initial_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="initialBP" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="initialBP" name="initial_bp" placeholder="120/80">
                            </div>
                            <div>
                                <label for="initialTemp" class="form-label">Temp (Â°C)</label>
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
                                <input type="number" class="form-control" id="initialPainScore" name="initial_pain_score" min="0" max="10" placeholder="0-10">
                            </div>
                            <div>
                                <label for="initialSPO2" class="form-label">SPO2 %</label>
                                <input type="number" class="form-control" id="initialSPO2" name="initial_spo2" min="0" max="100" placeholder="95-100">
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

                        <hr class="section-divider-light">

                        <div class="grid-2 mb-section">
                            <div>
                                <label class="form-label">Level of Consciousness</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialAlert" value="alert">
                                        <label class="form-check-label" for="initialAlert">Alert</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialVerbal" value="verbal">
                                        <label class="form-check-label" for="initialVerbal">Verbal</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialPain" value="pain">
                                        <label class="form-check-label" for="initialPain">Pain</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_consciousness[]" id="initialUnconscious" value="unconscious">
                                        <label class="form-check-label" for="initialUnconscious">Unconscious</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Helmet Status</label>
                                <div class="inline-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_helmet[]" id="initialHelmetAB" value="ab">
                                        <label class="form-check-label" for="initialHelmetAB">+ AB</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="initial_helmet[]" id="initialNoHelmet" value="none">
                                        <label class="form-check-label" for="initialNoHelmet">No Helmet</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="form-section">
                        <div class="section-title">
                            <i class="bi bi-arrow-repeat"></i> Follow-up Vital Signs
                        </div>

                        <div class="grid-4 mb-section">
                            <div>
                                <label for="followupTime" class="form-label">Time</label>
                                <input type="text" class="form-control time-input-12hr" id="followupTime" name="followup_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
                            </div>
                            <div>
                                <label for="followupBP" class="form-label">Blood Pressure</label>
                                <input type="text" class="form-control" id="followupBP" name="followup_bp" placeholder="120/80">
                            </div>
                            <div>
                                <label for="followupTemp" class="form-label">Temp (Â°C)</label>
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
                                <input type="number" class="form-control" id="followupPainScore" name="followup_pain_score" min="0" max="10" placeholder="0-10">
                            </div>
                            <div>
                                <label for="followupSPO2" class="form-label">SPO2 %</label>
                                <input type="number" class="form-control" id="followupSPO2" name="followup_spo2" min="0" max="100" placeholder="95-100">
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

                        <hr class="section-divider-light">

                        <div class="mb-section">
                            <label class="form-label">Level of Consciousness</label>
                            <div class="inline-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupAlert" value="alert">
                                    <label class="form-check-label" for="followupAlert">Alert</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupVerbal" value="verbal">
                                    <label class="form-check-label" for="followupVerbal">Verbal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupPain" value="pain">
                                    <label class="form-check-label" for="followupPain">Pain</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="followup_consciousness[]" id="followupUnconscious" value="unconscious">
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

                        <hr class="section-divider">

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
                                        <button type="button" class="action-btn btn-clear" data-action="clearAllInjuries" title="Remove all marked injuries">
                                            <i class="bi bi-trash3"></i>
                                            <span>Clear All</span>
                                        </button>
                                        <button type="button" class="action-btn btn-export" data-action="exportInjuryData" title="Export injury data as JSON">
                                            <i class="bi bi-download"></i>
                                            <span>Export</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="injuries_data" id="injuriesData">
                        <input type="hidden" name="narrative_report" id="narrativeReportField">

                        <hr class="section-divider">

                        <div class="stroke-assessment-card">
                    <div class="stroke-assessment-header" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);">
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
                                                    <input type="radio" name="face_drooping" id="facePos" value="positive" class="toggle-input">
                                                    <label for="facePos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="face_drooping" id="faceNeg" value="negative" class="toggle-input">
                                                    <label for="faceNeg" class="toggle-btn toggle-negative">(âˆ’)</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="fast-item">
                                            <div class="fast-item-letter">A</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Arm Weakness</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="arm_weakness" id="armPos" value="positive" class="toggle-input">
                                                    <label for="armPos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="arm_weakness" id="armNeg" value="negative" class="toggle-input">
                                                    <label for="armNeg" class="toggle-btn toggle-negative">(âˆ’)</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="fast-item">
                                            <div class="fast-item-letter">S</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Speech Difficulty</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="speech_difficulty" id="speechPos" value="positive" class="toggle-input">
                                                    <label for="speechPos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="speech_difficulty" id="speechNeg" value="negative" class="toggle-input">
                                                    <label for="speechNeg" class="toggle-btn toggle-negative">(âˆ’)</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="fast-item">
                                            <div class="fast-item-letter">T</div>
                                            <div class="fast-item-content">
                                                <span class="fast-item-label">Time to Call</span>
                                                <div class="toggle-group">
                                                    <input type="radio" name="time_to_call" id="timePos" value="positive" class="toggle-input">
                                                    <label for="timePos" class="toggle-btn toggle-positive">(+)</label>
                                                    <input type="radio" name="time_to_call" id="timeNeg" value="negative" class="toggle-input">
                                                    <label for="timeNeg" class="toggle-btn toggle-negative">(âˆ’)</label>
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
                                                <input type="text" class="form-control" id="sampleSigns" name="sample_signs" placeholder="Signs & Symptoms">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">A</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleAllergies" name="sample_allergies" placeholder="Allergies">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">M</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleMedications" name="sample_medications" placeholder="Medications">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">P</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="samplePertinent" name="sample_pertinent" placeholder="Pertinent History">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">L</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleLastIntake" name="sample_last_intake" placeholder="Last Oral Intake">
                                            </div>
                                        </div>
                                        <div class="sample-row-modern">
                                            <div class="sample-letter-modern">E</div>
                                            <div class="sample-input-modern">
                                                <input type="text" class="form-control" id="sampleEvents" name="sample_events" placeholder="Events Leading to Illness">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Hidden field to store combined SAMPLE data for backward compatibility -->
                                    <input type="hidden" id="fastDetails" name="sample_details">
                                </div>
                            </div>
                        </div>

                        <hr class="section-divider">

                        <div class="ob-section">
                            <h6><i class="bi bi-hospital-fill"></i> FOR OB Patients Only</h6>
                            <div class="grid-3" style="gap: 1rem;">
                                <div>
                                    <label for="babyDelivery" class="form-label">Baby Status</label>
                                    <input type="text" class="form-control" id="babyDelivery" name="baby_status" placeholder="e.g., Alive, Healthy">
                                </div>
                                <div>
                                    <label for="timeOfDelivery" class="form-label">Delivery Time</label>
                                    <input type="text" class="form-control time-input-12hr" id="timeOfDelivery" name="ob_delivery_time" placeholder="--:-- --" maxlength="8" pattern="^(0?[1-9]|1[0-2]):[0-5][0-9]\s?(AM|PM|am|pm)$" data-time-field="true">
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

                        <hr class="section-divider-light">

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

                        <hr class="section-divider-light">

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

                        <hr class="section-divider">

                        <div class="section-title">
                            <i class="bi bi-building"></i> Hospital Endorsement
                        </div>

                        <div class="grid-2 mb-section">
                            <div>
                                <label for="hospital" class="form-label">Hospital Name</label>
                                <input type="text" class="form-control" id="hospital" name="hospital_name" placeholder="Hospital name">
                            </div>
                            <div>
                                <label for="dateTime" class="form-label">Endorsement Date & Time</label>
                                <input type="datetime-local" class="form-control" id="dateTime" name="endorsement_datetime">
                            </div>
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
                                            <span class="upload-method-desc">JPG, PNG, GIF, WebP (max 20MB, auto-compressed)</span>
                                        </div>
                                        <input type="file" class="hidden-file-input" id="fileUpload" name="endorsement_attachment" accept="image/jpeg,image/png,image/gif,image/webp" onchange="validateFileUpload(this)">
                                    </label>
                                </div>

                                <!-- Camera Container -->
                                <div id="cameraContainer" class="endorsement-camera-container">
                                    <div class="camera-top-bar">
                                        <button type="button" class="camera-close-btn" data-action="closeCamera">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                        <span class="camera-top-label">Endorsement Document</span>
                                        <div class="camera-top-spacer"></div>
                                    </div>
                                    <div class="camera-viewport">
                                        <video id="cameraVideo" autoplay playsinline></video>
                                        <div class="camera-flash-overlay" id="endorsementFlashOverlay"></div>
                                    </div>
                                    <div class="camera-bottom-bar">
                                        <div class="camera-bottom-spacer"></div>
                                        <button type="button" class="camera-shutter-btn" id="captureBtn" data-action="capturePhoto">
                                            <span class="shutter-ring"><span class="shutter-inner"></span></span>
                                        </button>
                                        <button type="button" class="camera-flip-btn" id="flipEndorsementCameraBtn" data-action="flipEndorsementCamera">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Image Preview -->
                                <div id="previewContainer" class="endorsement-preview-container">
                                    <div class="preview-card">
                                        <div class="preview-image-wrapper" data-action="openEndorsementImageModal">
                                            <img id="attachmentPreview" src="" alt="Endorsement Attachment Preview">
                                            <div class="preview-overlay">
                                                <i class="bi bi-zoom-in"></i>
                                                <span>Click to enlarge</span>
                                            </div>
                                        </div>
                                        <div class="preview-actions">
                                            <span class="preview-label">
                                                <i class="bi bi-check-circle-fill"></i>
                                                Document attached
                                            </span>
                                            <button type="button" class="remove-preview-btn" id="removeAttachmentBtn" data-action="removeAttachment">
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
                        <div id="endorsementImageModal" class="image-preview-modal" data-action="closeEndorsementImageModal">
                            <div class="modal-content-custom" data-action="stopPropagation">
                                <button type="button" class="modal-close-btn" data-action="closeEndorsementImageModal">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <img id="endorsementModalImage" src="" alt="Endorsement Attachment">
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

                        <!-- View Summary Button -->
                        <div class="text-center summary-button-container">
                            <button type="button" class="btn btn-primary" id="viewSummaryBtn" data-action="openSummaryModal">
                                <i class="bi bi-list-check"></i>
                                <span>View Form Summary</span>
                            </button>
                            <p class="text-muted mt-3 summary-help-text">
                                <i class="bi bi-info-circle"></i>
                                <span class="d-none d-sm-inline">Click to view a comprehensive summary of all entered data</span>
                                <span class="d-inline d-sm-none">View all entered data</span>
                            </p>
                        </div>

                        <!-- NARRATIVE REPORT CARD - Modern Corporate Design -->
                        <div class="narrative-report-card">
                            <div class="narrative-header-bar">
                                <div class="narrative-header-left">
                                    <div class="narrative-icon-circle">
                                        <i class="bi bi-file-text-fill"></i>
                                    </div>
                                    <div>
                                        <h5>Narrative Report</h5>
                                        <span>Auto-generated from form data</span>
                                    </div>
                                </div>
                                <div class="narrative-header-right">
                                    <div class="narrative-pills">
                                        <button type="button" class="narrative-pill active" data-format="professional" data-action="switchNarrativeFormat" data-arg="professional">
                                            Professional
                                        </button>
                                        <button type="button" class="narrative-pill" data-format="concise" data-action="switchNarrativeFormat" data-arg="concise">
                                            Concise
                                        </button>
                                    </div>
                                    <button type="button" class="narrative-icon-btn" data-action="generateNarrative" title="Regenerate Report">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="narrative-body" id="narrativeContent">
                                <div class="narrative-placeholder-modern">
                                    <div class="narrative-placeholder-icon">
                                        <i class="bi bi-file-earmark-medical"></i>
                                    </div>
                                    <p>Narrative report will be generated automatically as you complete each tab.</p>
                                    <button type="button" class="btn-generate-narrative" data-action="generateNarrative">
                                        <i class="bi bi-stars me-2"></i>Generate Now
                                    </button>
                                </div>
                            </div>
                            <div class="narrative-footer-actions">
                                <button type="button" class="narrative-footer-btn" data-action="copyNarrativeToClipboard">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                                <button type="button" class="narrative-footer-btn" data-action="printNarrative">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                                <button type="button" class="narrative-footer-btn" data-action="exportNarrativeAsText">
                                    <i class="bi bi-download"></i> Download .TXT
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </form>

        <!-- Form Summary Modal -->
        <div class="modal fade" id="formSummaryModal" tabindex="-1" aria-labelledby="formSummaryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-clipboard2-check-fill"></i><span>Pre-Hospital Care Form Summary</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="summaryModalBody">
                        <div style="text-align: center; padding: 3rem; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 12px;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-action="copySummaryToClipboard">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-action="printSummary">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                            <i class="bi bi-check2"></i> Done
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="navigation-buttons">
            <button type="button" class="btn btn-outline-primary" id="prevBtn" data-action="navigateTab" data-arg="-1">
                <i class="bi bi-chevron-left"></i> Previous
            </button>
            <button type="button" class="btn btn-primary" id="nextBtn" data-action="navigateTab" data-arg="1">
                Next <i class="bi bi-chevron-right"></i>
            </button>
            <button type="button" class="btn btn-success" id="submitBtn" style="display: none;" data-action="submitForm">
                <i class="bi bi-check2"></i> Save Form
            </button>
        </div>
    </div>

    <!-- Fire Truck Selection Modal -->
    <!-- Ambulance Unit Picker Modal -->
    <div class="modal fade" id="ambulanceUnitModal" tabindex="-1" aria-labelledby="ambulanceUnitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ambulanceUnitModalLabel">
                        <i class="bi bi-truck-front-fill me-2"></i>Select Ambulance Unit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="unit-grid" id="ambulanceUnitGrid" role="radiogroup" aria-label="Ambulance Unit">
                        <?php for ($__v = 1; $__v <= 18; $__v++): ?>
                        <button type="button" class="unit-btn" data-unit="V<?php echo $__v; ?>" data-bs-dismiss="modal" role="radio" aria-checked="false">V<?php echo $__v; ?></button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="fireTruckModal" tabindex="-1" aria-labelledby="fireTruckModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fireTruckModalLabel">
                        <i class="bi bi-truck me-2"></i>Select Fire Truck Type
                    </h5>
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
    <!-- JS Modules -->
    <script src="js/modules/utils.js?v=<?php echo asset_version(); ?>"></script>
    <script src="js/modules/injury-tracker.js?v=<?php echo asset_version(); ?>"></script>
    <script src="js/modules/camera.js?v=<?php echo asset_version(); ?>"></script>
    <script src="js/modules/form-tabs.js?v=<?php echo asset_version(); ?>"></script>
    <script src="js/modules/validation.js?v=<?php echo asset_version(); ?>"></script>
    <script src="js/prehospital-form.js?v=<?php echo asset_version(); ?>"></script>
    <!-- Custom Date Components - Month/Day/Year dropdowns -->
    <script src="js/custom-date.js?v=<?php echo asset_version(); ?>"></script>
    <!-- Material Design Time Picker Modal -->
    <script src="js/time-picker-modal.js?v=<?php echo asset_version(); ?>"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        // ============================================================
        // SKELETON REMOVAL â€” runs FIRST, before anything that can throw.
        // If a CDN script (e.g. Notiflix) fails to load, the code below
        // must still clear the loading skeleton so labels/text are never
        // left greyed out. Runs immediately + on DOM/load + a 1.2s safety.
        // ============================================================
        (function clearSkeleton() {
            var off = function () { document.body.classList.remove('loading'); };
            off(); // body already exists (script is at end of <body>)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', off);
            }
            window.addEventListener('load', off);
            setTimeout(off, 1200);
        })();

        // Configure Notiflix â€” wrapped so a CDN load failure can't halt the page
        try {
        Notiflix.Notify.init({
            width: '320px',
            position: 'right-top',
            distance: '15px',
            timeout: 3000,
            fontSize: '15px',
            cssAnimationStyle: 'from-right',
            success: {
                background: '#059669',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            failure: {
                background: '#dc2626',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            warning: {
                background: '#d97706',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            info: {
                background: '#4f46e5',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
        });

        Notiflix.Confirm.init({
            width: '350px',
            titleColor: '#3730a3',
            okButtonBackground: '#4f46e5',
            cancelButtonBackground: '#64748b',
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
                svgColor: '#059669',
                titleColor: '#065f46',
                messageColor: '#1e293b',
                buttonBackground: '#059669',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(15,23,42,0.5)',
            },
            failure: {
                svgColor: '#dc2626',
                titleColor: '#991b1b',
                messageColor: '#1e293b',
                buttonBackground: '#dc2626',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(15,23,42,0.5)',
            },
            warning: {
                svgColor: '#d97706',
                titleColor: '#92400e',
                messageColor: '#1e293b',
                buttonBackground: '#d97706',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(15,23,42,0.5)',
            },
            info: {
                svgColor: '#6366f1',
                titleColor: '#3730a3',
                messageColor: '#1e293b',
                buttonBackground: '#4f46e5',
                buttonColor: '#fff',
                backOverlayColor: 'rgba(15,23,42,0.5)',
            },
        });
        } catch (e) {
            // Notiflix unavailable (e.g. CDN blocked) â€” non-fatal, keep going.
            console.warn('Notiflix init skipped:', e);
        }

        // ============================================
        // FLATPICKR TIME PICKER INITIALIZATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // ============================================
            // UPPERCASE ALL TEXT INPUTS
            // ============================================
            const textInputs = document.querySelectorAll('input[type="text"]:not(.no-uppercase), input[type="email"], input[type="tel"], input[type="url"]');

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

            // Handle select dropdowns - convert selected values to uppercase display
            const selects = document.querySelectorAll('select');
            selects.forEach(function(select) {
                select.addEventListener('change', function() {
                    // The visual display is handled by CSS
                });
            });

            console.log('Uppercase conversion initialized on ' + textInputs.length + ' text inputs');

        }); // End of DOMContentLoaded event listener

        // ============================================
        // AUTOSAVE FUNCTIONALITY
        // ============================================
        let autosaveTimer = null;
        let currentDraftId = null;
        let lastSaveTime = null;
        let isFormDirty = false;
        let autosaveEnabled = false; // Prevent autosave on page load

        // Reset form to a completely fresh state (no draft association)
        function resetFormCompletely(reason) {
            currentDraftId = null;
            document.getElementById('draftIdField').value = '';

            const form = document.getElementById('preHospitalForm');
            if (form) {
                form.reset();
                // Also reset custom date/datetime dropdowns (Month/Day/Year selects)
                form.querySelectorAll('.custom-date-input select').forEach(function(sel) {
                    sel.selectedIndex = 0;
                });
            }

            // Reset autosave state
            isFormDirty = false;
            autosaveEnabled = false;
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
                autosaveTimer = null;
            }
            console.log('Form reset (' + reason + '): starting fresh, no draft association');
        }

        // Check URL for draft_id parameter
        const urlParams = new URLSearchParams(window.location.search);
        const resumeDraftId = urlParams.get('draft_id');
        if (resumeDraftId) {
            currentDraftId = resumeDraftId;
            // Set the draft_id in the hidden field immediately
            document.getElementById('draftIdField').value = resumeDraftId;
            console.log('Draft ID from URL:', resumeDraftId);
            console.log('Hidden field set to:', document.getElementById('draftIdField').value);
            loadDraft(resumeDraftId);
        } else {
            // No draft_id in URL â€” ensure we start completely fresh
            resetFormCompletely('no draft_id in URL');
        }

        // Handle bfcache restoration (back/forward navigation)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was restored from bfcache â€” check if we should be on a fresh form
                const freshParams = new URLSearchParams(window.location.search);
                if (!freshParams.get('draft_id')) {
                    resetFormCompletely('bfcache restore without draft_id');
                }
            }
        });

        // Function to combine individual SAMPLE fields into hidden field
        function combineSampleFields() {
            const signs = document.getElementById('sampleSigns')?.value || '';
            const allergies = document.getElementById('sampleAllergies')?.value || '';
            const medications = document.getElementById('sampleMedications')?.value || '';
            const pertinent = document.getElementById('samplePertinent')?.value || '';
            const lastIntake = document.getElementById('sampleLastIntake')?.value || '';
            const events = document.getElementById('sampleEvents')?.value || '';

            // Combine with delimiter for storage
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

        // Function to populate individual SAMPLE fields from combined data
        function populateSampleFields(combinedData) {
            if (!combinedData) return;

            try {
                const data = JSON.parse(combinedData);
                if (document.getElementById('sampleSigns')) document.getElementById('sampleSigns').value = data.signs || '';
                if (document.getElementById('sampleAllergies')) document.getElementById('sampleAllergies').value = data.allergies || '';
                if (document.getElementById('sampleMedications')) document.getElementById('sampleMedications').value = data.medications || '';
                if (document.getElementById('samplePertinent')) document.getElementById('samplePertinent').value = data.pertinent || '';
                if (document.getElementById('sampleLastIntake')) document.getElementById('sampleLastIntake').value = data.last_intake || '';
                if (document.getElementById('sampleEvents')) document.getElementById('sampleEvents').value = data.events || '';
            } catch (e) {
                // If not JSON, it might be old plain text format - put it in signs field
                if (document.getElementById('sampleSigns')) {
                    document.getElementById('sampleSigns').value = combinedData;
                }
            }
        }

        // Function to collect all form data
        function collectFormData() {
            // Combine SAMPLE fields before collecting
            combineSampleFields();

            const form = document.getElementById('preHospitalForm');
            const data = {};

            // Collect ALL inputs regardless of visibility (including hidden tabs)
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                const name = input.name;
                if (!name) return; // Skip inputs without names

                // Handle checkboxes and radio buttons
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) {
                        if (name.endsWith('[]')) {
                            const cleanKey = name.slice(0, -2);
                            if (!data[cleanKey]) {
                                data[cleanKey] = [];
                            }
                            data[cleanKey].push(input.value);
                        } else {
                            data[name] = input.value;
                        }
                    }
                }
                // Handle file inputs
                else if (input.type === 'file') {
                    if (input.files && input.files.length > 0) {
                        data[name] = input.files[0].name;
                    }
                }
                // Handle all other inputs
                else {
                    if (name.endsWith('[]')) {
                        const cleanKey = name.slice(0, -2);
                        if (!data[cleanKey]) {
                            data[cleanKey] = [];
                        }
                        data[cleanKey].push(input.value);
                    } else {
                        data[name] = input.value;
                    }
                }
            });

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

            // Log which sections have data
            const sections = ['section1', 'section2', 'section3', 'section4', 'section5', 'section6', 'section7'];
            sections.forEach(sectionId => {
                const sectionInputs = document.querySelectorAll(`#${sectionId} input, #${sectionId} select, #${sectionId} textarea`);
                let sectionHasData = false;
                sectionInputs.forEach(input => {
                    if (input.name && data[input.name] && data[input.name] !== '') {
                        sectionHasData = true;
                    }
                });
                if (sectionHasData) {
                    console.log(`âœ“ ${sectionId} has data`);
                }
            });

            // Show saving indicator
            Notiflix.Loading.circle('Saving draft...', {
                svgColor: '#4f46e5',
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
                    console.log('âœ“ DRAFT SAVED SUCCESSFULLY');
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
                svgColor: '#4f46e5',
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
                        // Check if draft was already completed/submitted
                        if (result.message && result.message.includes('Draft not found')) {
                            // Draft was already submitted - silently clear the URL parameter and draft ID
                            console.log('Draft was already submitted, starting fresh form');

                            // Clear the draft ID from hidden field and global variable
                            currentDraftId = null;
                            document.getElementById('draftIdField').value = '';

                            // Clear URL parameter
                            const url = new URL(window.location);
                            url.searchParams.delete('draft_id');
                            window.history.replaceState({}, '', url);

                            // Don't show error, just start with fresh form
                            return;
                        }

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

        // Function to map database column names to form field names
        function mapDatabaseToFormField(dbColumn) {
            const fieldMap = {
                'driver_name': 'driver',
                'departure_hospital_location': 'depHospLocation',
                'initial_resp_rate': 'initial_resp',
                'followup_resp_rate': 'followup_resp',
                'fast_face_drooping': 'face_drooping',
                'fast_arm_weakness': 'arm_weakness',
                'fast_speech_difficulty': 'speech_difficulty',
                'fast_time_to_call': 'time_to_call',
                'fast_sample_details': 'fastDetails',
                'ob_baby_status': 'babyDelivery',
                'ob_placenta': 'placenta',
                'ob_lmp': 'lmp',
                'ob_aog': 'aog',
                'ob_edc': 'edc',
                'first_aider': 'aider1',
                'second_aider': 'aider2'
            };

            return fieldMap[dbColumn] || dbColumn;
        }

        // Function to populate form with draft data
        function populateForm(data) {
            console.log('Populating form with data:', data);

            // Handle emergency types (stored as separate boolean columns in DB)
            const emergencyTypes = [];
            if (data.emergency_medical == 1) {
                emergencyTypes.push('medical');
                const medicalInput = document.querySelector('[name="medical_specify"]');
                if (medicalInput && data.emergency_medical_details) {
                    medicalInput.value = data.emergency_medical_details;
                }
            }
            if (data.emergency_trauma == 1) {
                emergencyTypes.push('trauma');
                const traumaInput = document.querySelector('[name="trauma_specify"]');
                if (traumaInput && data.emergency_trauma_details) {
                    traumaInput.value = data.emergency_trauma_details;
                }
            }
            if (data.emergency_ob == 1) {
                emergencyTypes.push('ob');
                const obInput = document.querySelector('[name="ob_specify"]');
                if (obInput && data.emergency_ob_details) {
                    obInput.value = data.emergency_ob_details;
                }
            }
            if (data.emergency_general == 1) {
                emergencyTypes.push('general');
                const generalInput = document.querySelector('[name="general_specify"]');
                if (generalInput && data.emergency_general_details) {
                    generalInput.value = data.emergency_general_details;
                }
            }

            // Check emergency type checkboxes
            emergencyTypes.forEach(type => {
                const checkbox = document.querySelector(`[name="emergency_type[]"][value="${type}"]`);
                if (checkbox) checkbox.checked = true;
            });

            // Text inputs and other fields
            for (let key in data) {
                // Skip non-field columns and already-handled emergency fields
                if (['id', 'form_number', 'created_by', 'created_at', 'updated_at', 'status',
                     'endorsement_attachment', 'patient_documentation', 'waiver_patient_signature',
                     'waiver_witness_signature', 'emergency_medical', 'emergency_medical_details',
                     'emergency_trauma', 'emergency_trauma_details', 'emergency_ob', 'emergency_ob_details',
                     'emergency_general', 'emergency_general_details', 'received_by', 'endorsement'].includes(key)) {
                    continue;
                }

                // Map database column name to form field name
                let fieldName = mapDatabaseToFormField(key);
                let input = document.querySelector(`[name="${fieldName}"]`);

                // If not found, try without underscore conversions or special cases
                if (!input) {
                    // Try array notation for multi-select fields
                    input = document.querySelector(`[name="${fieldName}[]"]`);
                }

                // If still not found with mapped name, try original database column name
                if (!input) {
                    fieldName = key;
                    input = document.querySelector(`[name="${fieldName}"]`);
                    if (!input) {
                        input = document.querySelector(`[name="${fieldName}[]"]`);
                    }
                }

                if (input) {
                    if (input.type === 'radio') {
                        const radio = document.querySelector(`[name="${fieldName}"][value="${data[key]}"]`);
                        if (radio) radio.checked = true;
                    } else if (input.type === 'checkbox') {
                        // Handle JSON arrays
                        try {
                            const values = JSON.parse(data[key]);
                            if (Array.isArray(values)) {
                                values.forEach(val => {
                                    const checkbox = document.querySelector(`[name="${fieldName}[]"][value="${val}"]`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            }
                        } catch (e) {
                            // Single checkbox
                            input.checked = !!data[key];
                        }
                    } else if (input.tagName === 'SELECT' && input.multiple) {
                        // Handle multi-select dropdowns
                        try {
                            const values = JSON.parse(data[key]);
                            if (Array.isArray(values)) {
                                Array.from(input.options).forEach(option => {
                                    if (values.includes(option.value)) {
                                        option.selected = true;
                                    }
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing multi-select data for', key, e);
                        }
                    } else {
                        // Handle time fields - don't populate if value is 00:00:00 or null
                        if (input.type === 'time') {
                            const timeValue = data[key];
                            // Only set value if it's not null, empty, or 00:00:00
                            if (timeValue &&
                                timeValue !== '00:00:00' &&
                                timeValue !== '0000-00-00 00:00:00' &&
                                timeValue !== 'null' &&
                                timeValue.trim() !== '') {
                                input.value = timeValue;
                            } else {
                                // Explicitly clear the field
                                input.value = '';
                            }
                        } else if (input.type === 'date' || input.type === 'datetime-local' ||
                                   (input.name && (input.name.includes('date') || input.name === 'lmp' || input.name === 'edc' || input.name === 'form_date'))) {
                            // Check by name too since Flatpickr changes type="date" to type="text"
                            const dateValue = data[key];
                            // Only set value if it's not null, empty, or 0000-00-00
                            if (dateValue &&
                                dateValue !== '0000-00-00' &&
                                dateValue !== '0000-00-00 00:00:00' &&
                                dateValue !== 'null' &&
                                dateValue.trim() !== '') {
                                // Use Flatpickr API if available for proper date handling
                                if (input._flatpickr) {
                                    input._flatpickr.setDate(dateValue, true);
                                } else {
                                    input.value = dateValue;
                                }
                            } else {
                                // Explicitly clear the field
                                if (input._flatpickr) {
                                    input._flatpickr.clear();
                                } else {
                                    input.value = '';
                                }
                            }
                        } else {
                            // Regular input - set value or empty string
                            input.value = data[key] || '';
                        }
                    }
                } else {
                    console.log('Field not found for database column:', key);
                }
            }

            // Populate individual SAMPLE fields from combined data
            if (data.fast_sample_details || data.sample_details) {
                populateSampleFields(data.fast_sample_details || data.sample_details);
            }

            console.log('Form population complete');
        }

        // ============================================
        // SECTION PERSISTENCE - Remember current section on refresh ONLY
        // ============================================

        // Set a flag when the page is being refreshed (not navigated away from)
        let wasRefreshed = sessionStorage.getItem('pageRefreshed') === 'true';

        // Mark that we're on the prehospital form page
        sessionStorage.setItem('onPrehospitalForm', 'true');

        // Save current section to localStorage
        function saveCurrentSection() {
            const activeTab = document.querySelector('.nav-link.active');
            if (activeTab) {
                const sectionId = activeTab.getAttribute('data-bs-target');
                localStorage.setItem('currentSection', sectionId);
                localStorage.setItem('sectionTimestamp', Date.now().toString());
                console.log('Current section saved:', sectionId);
            }
        }

        // Restore section ONLY if it was a refresh (not navigation)
        function restoreCurrentSection() {
            const savedSection = localStorage.getItem('currentSection');
            const sectionTimestamp = localStorage.getItem('sectionTimestamp');

            // Only restore if:
            // 1. This was a page refresh (not a new navigation)
            // 2. The timestamp is recent (within last 5 seconds - indicates refresh, not new visit)
            const timeSinceLastSave = Date.now() - parseInt(sectionTimestamp || '0');
            const wasRecentlySaved = timeSinceLastSave < 5000; // 5 seconds

            console.log('Restore check:', {
                wasRefreshed,
                savedSection,
                timeSinceLastSave,
                wasRecentlySaved
            });

            if (wasRefreshed && wasRecentlySaved && savedSection && savedSection !== '#section1') {
                console.log('Restoring section after refresh:', savedSection);

                // Find and activate the tab
                const tab = document.querySelector(`[data-bs-target="${savedSection}"]`);
                if (tab) {
                    const bsTab = new bootstrap.Tab(tab);
                    bsTab.show();
                }
            } else {
                console.log('Starting fresh at Section 1 (new navigation)');
                // Clear the saved section since this is a new navigation
                localStorage.removeItem('currentSection');
                localStorage.removeItem('sectionTimestamp');
            }

            // Reset the refresh flag
            sessionStorage.removeItem('pageRefreshed');
        }

        // Add listeners to all tabs to save current section
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-link[data-bs-toggle="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function() {
                    saveCurrentSection();
                });
            });

            // Restore section after a short delay to ensure form is loaded
            setTimeout(() => {
                restoreCurrentSection();
            }, 500);
        });

        // Detect page refresh vs navigation
        window.addEventListener('beforeunload', function() {
            // Mark that the user might be refreshing
            sessionStorage.setItem('pageRefreshed', 'true');

            // This will be reset if they navigate to a different page
            setTimeout(() => {
                sessionStorage.removeItem('pageRefreshed');
            }, 100);
        });

        // Clear the "on prehospital form" flag when leaving the page
        window.addEventListener('pagehide', function() {
            sessionStorage.removeItem('onPrehospitalForm');
        });

        // Clear section memory when form is submitted or cleared
        function clearSectionMemory() {
            localStorage.removeItem('currentSection');
            localStorage.removeItem('sectionTimestamp');
            sessionStorage.removeItem('pageRefreshed');
            console.log('Section memory cleared');
        }

        // Initialize autosave listeners after page loads
        function initializeAutosave() {
            const formInputs = document.querySelectorAll('#preHospitalForm input, #preHospitalForm select, #preHospitalForm textarea');
            console.log('Initializing autosave for', formInputs.length, 'form fields');

            // Group inputs by tab for debugging
            const inputsByTab = {};
            formInputs.forEach(input => {
                const section = input.closest('.tab-pane');
                if (section) {
                    const sectionId = section.id;
                    if (!inputsByTab[sectionId]) {
                        inputsByTab[sectionId] = 0;
                    }
                    inputsByTab[sectionId]++;
                }
            });
            console.log('Inputs per section:', inputsByTab);

            formInputs.forEach(input => {
                // Skip CSRF token and draft_id fields
                if (input.name === 'csrf_token' || input.name === 'draft_id') {
                    return;
                }

                // Skip elements that already have autosave listeners (prevents double-firing on re-init)
                if (input.dataset.autosaveInit) {
                    return;
                }
                input.dataset.autosaveInit = '1';

                input.addEventListener('change', () => {
                    const section = input.closest('.tab-pane');
                    const sectionName = section ? section.id : 'unknown';
                    console.log('Field changed:', input.name, 'in section:', sectionName);

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

                    // Set new timer (OPTIMIZED: autosave after 15 seconds of inactivity to reduce server load)
                    autosaveTimer = setTimeout(() => {
                        performAutosave();
                    }, 15000);
                });

                // Also trigger on input for text fields (more responsive)
                if (input.type === 'text' || input.type === 'textarea' || input.tagName === 'TEXTAREA') {
                    input.addEventListener('input', () => {
                        const section = input.closest('.tab-pane');
                        const sectionName = section ? section.id : 'unknown';
                        console.log('Field input:', input.name, 'in section:', sectionName);

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
                        }, 20000); // OPTIMIZED: 20 seconds for continuous typing to reduce overhead
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

        // Re-initialize autosave after page load to ensure all dynamic elements are captured.
        // The data-autosave-init attribute prevents double-attaching listeners to elements
        // that were already initialized above.
        window.addEventListener('load', function() {
            setTimeout(function() {
                console.log('Re-initializing autosave for custom datetime elements...');
                initializeAutosave();
            }, 800);
        });

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
        function setupSidebarInterception() {
            const sidebarLinks = document.querySelectorAll('.sidebar a[href], #sidebar a[href]');
            console.log('Found sidebar links:', sidebarLinks.length);

            sidebarLinks.forEach(link => {
                // Skip the prehospital_form.php link itself
                if (link.href.includes('prehospital_form.php')) {
                    console.log('Skipping prehospital_form.php link');
                    return;
                }

                link.addEventListener('click', function(e) {
                    console.log('Sidebar link clicked:', this.href);
                    console.log('isFormDirty:', isFormDirty);
                    console.log('hasFormData():', hasFormData());

                    // Check if form has unsaved data
                    if (isFormDirty && hasFormData()) {
                        console.log('Preventing navigation and showing prompt');
                        e.preventDefault();
                        e.stopPropagation();
                        const targetUrl = this.href;

                        Notiflix.Confirm.show(
                            'Save Your Progress?',
                            'You have an unfinished record. Would you like to save it and continue later from "My Drafts", or discard all changes?',
                            'Save & Continue Later',
                            'Discard Changes',
                            function() {
                                // Save draft and navigate
                                isFormDirty = true;
                                fetch('../api/autosave_draft.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify(collectFormData())
                                })
                                .then(response => response.json())
                                .then(result => {
                                    if (result.success) {
                                        Notiflix.Notify.success('Draft saved! You can resume editing from "My Drafts"', {
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
                                // Discard - delete draft if exists and navigate
                                if (currentDraftId) {
                                    fetch('../api/delete_record.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/json'},
                                        body: JSON.stringify({ id: currentDraftId })
                                    })
                                    .then(() => {
                                        Notiflix.Notify.warning('Changes discarded', {
                                            timeout: 2000
                                        });
                                        setTimeout(() => {
                                            window.location.href = targetUrl;
                                        }, 500);
                                    })
                                    .catch(() => {
                                        window.location.href = targetUrl;
                                    });
                                } else {
                                    Notiflix.Notify.warning('Changes discarded', {
                                        timeout: 2000
                                    });
                                    setTimeout(() => {
                                        window.location.href = targetUrl;
                                    }, 500);
                                }
                            },
                            {
                                width: '450px',
                                titleColor: '#4f46e5',
                                okButtonBackground: '#28a745',
                                cancelButtonBackground: '#dc3545',
                            }
                        );
                    } else {
                        console.log('Allowing navigation - no unsaved data');
                    }
                }, true); // Use capture phase to ensure we catch it first
            });
        }

        // Call setup function when DOM is ready AND after a delay
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up sidebar interception...');
            // Delay to ensure sidebar is fully loaded
            setTimeout(setupSidebarInterception, 1000);
        });

        // Also try to set it up immediately if DOM is already loaded
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            console.log('DOM already loaded, setting up sidebar interception...');
            setTimeout(setupSidebarInterception, 1000);
        }

        // Manual save button - positioned in top-right, below navbar (Corporate Design)
        const manualSaveBtn = document.createElement('button');
        manualSaveBtn.type = 'button';
        manualSaveBtn.className = 'btn save-draft-btn-corporate';
        manualSaveBtn.innerHTML = `
            <span class="save-draft-icon">
                <i class="bi bi-cloud-arrow-up-fill"></i>
            </span>
            <span class="save-draft-text">Save Draft</span>
        `;
        manualSaveBtn.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1001;
            box-shadow: 0 4px 16px rgba(30, 58, 95, 0.25);
            background: linear-gradient(135deg, #3730a3 0%, #4f46e5 100%);
            color: #ffffff;
            border: none;
            padding: 0;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 0;
            overflow: hidden;
            cursor: pointer;
        `;

        // Style the icon container
        const iconSpan = manualSaveBtn.querySelector('.save-draft-icon');
        iconSpan.style.cssText = `
            background: rgba(255, 255, 255, 0.15);
            padding: 0.625rem 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        `;

        // Style the text
        const textSpan = manualSaveBtn.querySelector('.save-draft-text');
        textSpan.style.cssText = `
            padding: 0.625rem 1rem 0.625rem 0.75rem;
            letter-spacing: 0.3px;
        `;

        // Add hover effect
        manualSaveBtn.addEventListener('mouseenter', () => {
            manualSaveBtn.style.background = 'linear-gradient(135deg, #4f46e5 0%, #6366f1 100%)';
            manualSaveBtn.style.transform = 'translateY(-2px)';
            manualSaveBtn.style.boxShadow = '0 6px 20px rgba(30, 58, 95, 0.35)';
        });

        manualSaveBtn.addEventListener('mouseleave', () => {
            manualSaveBtn.style.background = 'linear-gradient(135deg, #3730a3 0%, #4f46e5 100%)';
            manualSaveBtn.style.transform = 'translateY(0)';
            manualSaveBtn.style.boxShadow = '0 4px 16px rgba(30, 58, 95, 0.25)';
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

        // ============================================
        // NARRATIVE REPORT GENERATION
        // ============================================

        let currentNarrativeFormat = 'professional';

        // Switch narrative format
        function switchNarrativeFormat(format) {
            currentNarrativeFormat = format;

            // Update button states
            document.querySelectorAll('.format-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.format === format) {
                    btn.classList.add('active');
                }
            });

            // Regenerate narrative with new format
            generateNarrative();
        }

        // Main narrative generation function
        function generateNarrative() {
            console.log('Generating narrative report in', currentNarrativeFormat, 'format');

            // Collect form data
            const formData = collectNarrativeData();

            // Generate narrative based on format
            let narrative = '';
            if (currentNarrativeFormat === 'professional') {
                narrative = generateProfessionalNarrative(formData);
            } else {
                narrative = generateConciseNarrative(formData);
            }

            // Display narrative
            const narrativeContent = document.getElementById('narrativeContent');
            narrativeContent.innerHTML = '<div class="narrative-text">' + narrative + '</div>';

            console.log('Narrative generated successfully');
        }

        // Collect all form data for narrative
        function collectNarrativeData() {
            const getFieldValue = (name) => {
                const field = document.querySelector(`[name="${name}"]`);
                return field ? field.value : '';
            };

            const getRadioValue = (name) => {
                const field = document.querySelector(`[name="${name}"]:checked`);
                return field ? field.value : '';
            };

            const getSelectValue = (id) => {
                const field = document.getElementById(id);
                return field ? field.value : '';
            };

            const getCheckboxValues = (name) => {
                const fields = document.querySelectorAll(`[name="${name}"]:checked`);
                return Array.from(fields).map(f => f.value);
            };

            const getSelectMultipleValues = (name) => {
                const field = document.querySelector(`[name="${name}"]`);
                if (!field) return [];
                return Array.from(field.selectedOptions).map(o => o.value);
            };

            return {
                // Basic Info
                formDate: getFieldValue('form_date'),
                departureTime: getFieldValue('departure_time'),
                // arrivalTime: getFieldValue('arrival_time'),
                vehicleUsed: getRadioValue('vehicle_used'),

                // Patient Info
                patientName: getFieldValue('patient_name'),
                age: getFieldValue('age'),
                gender: getSelectValue('gender'),
                address: getFieldValue('address'),
                zone: getFieldValue('zone'),

                // Incident Details
                placeOfIncident: getFieldValue('place_of_incident'),
                zoneLandmark: getFieldValue('zone_landmark'),
                incidentTime: getFieldValue('incident_time'),
                callArrivalTime: getFieldValue('call_arrival_time'),

                // Emergency Type
                emergencyType: getCheckboxValues('emergency_type[]'),
                medicalSpecify: getFieldValue('medical_specify'),
                traumaSpecify: getFieldValue('trauma_specify'),
                obSpecify: getFieldValue('ob_specify'),
                generalSpecify: getFieldValue('general_specify'),

                // Assessment
                helmetStatus: getCheckboxValues('initial_helmet[]'),
                consciousness: getCheckboxValues('initial_consciousness[]'),
                chiefComplaints: getCheckboxValues('chief_complaints[]'),
                otherComplaints: getFieldValue('other_complaints'),

                // Vitals
                initialBP: getFieldValue('initial_bp'),
                initialTemp: getFieldValue('initial_temp'),
                initialPulse: getFieldValue('initial_pulse'),
                initialResp: getFieldValue('initial_resp'),
                initialSPO2: getFieldValue('initial_spo2'),

                // Follow-up Vitals
                followupBP: getFieldValue('followup_bp'),
                followupTemp: getFieldValue('followup_temp'),
                followupPulse: getFieldValue('followup_pulse'),
                followupResp: getFieldValue('followup_resp'),
                followupSPO2: getFieldValue('followup_spo2'),
                followupConsciousness: getCheckboxValues('followup_consciousness[]'),

                // Injuries
                injuriesData: getFieldValue('injuries_data'),

                // Care Management
                careManagement: getCheckboxValues('care_management[]'),
                oxygenLPM: getFieldValue('oxygen_lpm'),
                otherCare: getFieldValue('other_care'),

                // Team
                teamLeader: getFieldValue('team_leader'),
                teamLeaderNotes: getFieldValue('team_leader_notes'),

                // Hospital
                hospitalName: getFieldValue('hospital_name') || getFieldValue('arrival_hospital_name'),
                endorsement: getFieldValue('endorsement')
            };
        }

        // Generate professional format narrative
        function generateProfessionalNarrative(data) {
            let narrative = '';

            // Header
            const teamName = data.teamLeader ? data.teamLeader.toUpperCase() : 'RESCUE TEAM';
            narrative += `RESCUE 116 EMS Team ${teamName}\n\n`;

            // Incident Details
            narrative += `Name/Nature of Incident / Mechanism of Injury:\n`;
            const emergencyDetails = formatEmergencyType(data);
            narrative += `${emergencyDetails}\n\n`;

            narrative += `Place of Incident: ${data.placeOfIncident || 'Not specified'}`;
            if (data.zoneLandmark) {
                narrative += ` - ${data.zoneLandmark}`;
            }
            narrative += `\n`;

            narrative += `Date of Incident: ${formatDate(data.formDate)}\n`;
            narrative += `Time of Response: ${formatTime(data.departureTime || data.callArrivalTime)}\n\n`;

            // Patient Information
            narrative += `Patient Information:\n`;
            const gender = data.gender === 'male' ? 'Male' : data.gender === 'female' ? 'Female' : 'Not specified';
            narrative += `${gender}, ${data.age || 'unknown'} years old\n`;
            if (data.address) {
                narrative += `Resident of ${data.address}`;
                if (data.zone) {
                    narrative += `, ${data.zone}`;
                }
                narrative += `\n`;
            }
            narrative += `\n`;

            // Assessment
            narrative += `Assessment:\n`;
            narrative += formatAssessmentSection(data);
            narrative += `\n`;

            // Management
            narrative += `Management Provided:\n`;
            narrative += formatManagementSection(data);
            narrative += `\n`;

            // Endorsement
            const hospital = data.hospitalName || data.endorsement || 'the receiving medical facility';
            narrative += `The patient was transported and formally endorsed to ${hospital} for further evaluation, diagnostic procedures, and definitive medical management.\n\n`;

            // Footer
            narrative += `For emergencies, please contact 0966-206-8444.`;

            return narrative;
        }

        // Generate concise format narrative
        function generateConciseNarrative(data) {
            let narrative = '';

            // Header
            const teamName = data.teamLeader ? data.teamLeader.toUpperCase() : 'TEAM';
            narrative += `EMS TEAM ${teamName}\n\n`;

            // Incident summary
            const emergencyDetails = formatEmergencyType(data);
            narrative += `Name/Nature of Incident/Mechanism of Injury: ${emergencyDetails}\n`;
            narrative += `Place of Incident: ${data.placeOfIncident || 'Not specified'}`;
            if (data.zoneLandmark) {
                narrative += ` ${data.zoneLandmark}`;
            }
            narrative += `\n`;
            narrative += `Date of Incident: ${formatDate(data.formDate)}\n`;
            narrative += `Time of Response: ${formatTime(data.departureTime || data.callArrivalTime)}\n`;

            // Patient
            const gender = data.gender === 'male' ? 'MALE' : data.gender === 'female' ? 'FEMALE' : 'PATIENT';
            narrative += `PATIENT : ${gender} ${data.age || 'unknown'} YEARS OLD`;
            if (data.address) {
                narrative += ` FROM ${data.address.toUpperCase()}`;
            }
            narrative += `\n\n`;

            // Assessment
            narrative += `Assessment:\n`;
            narrative += formatConciseAssessment(data);
            narrative += `\n`;

            // Management
            narrative += `Management:\n`;
            narrative += formatConciseManagement(data);
            narrative += `\n`;

            // Endorsement
            const hospital = data.hospitalName || data.endorsement || 'hospital';
            narrative += `\nTHE PATIENT WAS TRANSPORTED AND ENDORSED TO ${hospital.toUpperCase()} FOR FURTHER EVALUATIONS AND MANAGEMENT.\n\n`;

            // Footer
            narrative += `For emergencies, please call 0966-206-8444`;

            return narrative;
        }

        // Format emergency type
        function formatEmergencyType(data) {
            const types = [];

            if (data.emergencyType.includes('trauma')) {
                const detail = data.traumaSpecify || 'Unspecified';
                types.push(`Trauma (${detail})`);
            }
            if (data.emergencyType.includes('medical')) {
                const detail = data.medicalSpecify || 'Unspecified';
                types.push(`Medical (${detail})`);
            }
            if (data.emergencyType.includes('ob')) {
                const detail = data.obSpecify || 'Unspecified';
                types.push(`OB (${detail})`);
            }
            if (data.emergencyType.includes('general')) {
                const detail = data.generalSpecify || 'Unspecified';
                types.push(`General (${detail})`);
            }

            return types.length > 0 ? types.join(', ') : 'Emergency Response';
        }

        // Format assessment section (Professional)
        function formatAssessmentSection(data) {
            let assessment = '';

            // Consciousness
            const consciousness = formatConsciousness(data.consciousness);
            assessment += `Upon arrival at the scene, the patient was found ${consciousness}. `;

            // Helmet status (handle array from checkbox)
            if (data.helmetStatus && Array.isArray(data.helmetStatus) && data.helmetStatus.length > 0) {
                if (data.helmetStatus.includes('none')) {
                    assessment += `The patient was noted to be without a helmet at the time of the incident. `;
                } else if (data.helmetStatus.includes('ab')) {
                    assessment += `Helmet was present and properly worn. `;
                }
            }

            assessment += `\n\nPhysical examination revealed:\n`;

            // Injuries
            const injuries = parseInjuries(data.injuriesData);
            if (injuries.length > 0) {
                injuries.forEach(injury => {
                    assessment += `- ${injury.type} on ${injury.location}\n`;
                });
            }

            // Chief complaints
            if (data.chiefComplaints.length > 0) {
                data.chiefComplaints.forEach(complaint => {
                    assessment += `- ${formatComplaint(complaint)}\n`;
                });
            }

            if (data.otherComplaints) {
                assessment += `- ${data.otherComplaints}\n`;
            }

            // Vitals summary
            if (data.initialBP || data.initialTemp || data.initialPulse) {
                assessment += `\nInitial Vital Signs:\n`;
                if (data.initialBP) assessment += `- Blood Pressure: ${data.initialBP}\n`;
                if (data.initialTemp) assessment += `- Temperature: ${data.initialTemp}Â°C\n`;
                if (data.initialPulse) assessment += `- Pulse: ${data.initialPulse} BPM\n`;
                if (data.initialResp) assessment += `- Respiratory Rate: ${data.initialResp}\n`;
                if (data.initialSPO2) assessment += `- SPO2: ${data.initialSPO2}%\n`;
            }

            // Follow-up Assessment
            if (data.followupBP || data.followupTemp || data.followupPulse || (data.followupConsciousness && data.followupConsciousness.length > 0)) {
                assessment += `\nFollow-up Assessment:\n`;

                // Follow-up consciousness
                if (data.followupConsciousness && data.followupConsciousness.length > 0) {
                    const followupConsciousness = formatConsciousness(data.followupConsciousness);
                    assessment += `Patient condition: ${followupConsciousness}. `;
                }

                // Follow-up vitals
                if (data.followupBP || data.followupTemp || data.followupPulse) {
                    assessment += `\n`;
                    if (data.followupBP) assessment += `- Blood Pressure: ${data.followupBP}\n`;
                    if (data.followupTemp) assessment += `- Temperature: ${data.followupTemp}Â°C\n`;
                    if (data.followupPulse) assessment += `- Pulse: ${data.followupPulse} BPM\n`;
                    if (data.followupResp) assessment += `- Respiratory Rate: ${data.followupResp}\n`;
                    if (data.followupSPO2) assessment += `- SPO2: ${data.followupSPO2}%\n`;
                }
            }

            return assessment;
        }

        // Format management section (Professional)
        function formatManagementSection(data) {
            let management = '';

            // Standard procedures
            management += `- Initial assessment conducted\n`;
            management += `- Vital signs taken and documented\n`;

            // Care management actions
            if (data.careManagement.length > 0) {
                data.careManagement.forEach(action => {
                    management += `- ${formatCareAction(action)}\n`;
                });
            }

            // Oxygen
            if (data.oxygenLPM) {
                management += `- Oxygen administered: ${data.oxygenLPM}\n`;
            }

            // Other care
            if (data.otherCare) {
                management += `- ${data.otherCare}\n`;
            }

            // Standard transport
            management += `- Patient positioned in a position of comfort during transport\n`;

            return management;
        }

        // Format concise assessment
        function formatConciseAssessment(data) {
            let assessment = '';

            // Helmet (handle array from checkbox)
            if (Array.isArray(data.helmetStatus) && data.helmetStatus.includes('none')) {
                assessment += `-NO HELMET\n`;
            }

            // Consciousness
            const consciousness = formatConsciousness(data.consciousness).toUpperCase();
            assessment += `-${consciousness}\n`;

            // Injuries
            const injuries = parseInjuries(data.injuriesData);
            if (injuries.length > 0) {
                injuries.forEach(injury => {
                    assessment += `- ${injury.type.toUpperCase()} ON ${injury.location.toUpperCase()}\n`;
                });
            }

            // Complaints
            if (data.chiefComplaints.length > 0) {
                data.chiefComplaints.forEach(complaint => {
                    assessment += `- ${formatComplaint(complaint).toUpperCase()}\n`;
                });
            }

            return assessment;
        }

        // Format concise management
        function formatConciseManagement(data) {
            let management = '';

            // Care actions
            if (data.careManagement.length > 0) {
                data.careManagement.forEach(action => {
                    management += `-${formatCareAction(action).toUpperCase()}\n`;
                });
            }

            // Standard items
            management += `- POSITIONED PATIENT INTO COMFORT WHILE ON TRANSPORT\n`;
            management += `-INITIAL ASSESSMENT DONE\n`;
            management += `-VITAL SIGNS TAKEN AND RECORDED\n`;

            return management;
        }

        // Helper: Format consciousness level
        function formatConsciousness(level) {
            const map = {
                'alert': 'awake and coherent',
                'verbal': 'responsive to verbal stimuli',
                'pain': 'responsive to pain stimuli only',
                'unconscious': 'unresponsive'
            };

            // Handle array of consciousness levels (from checkbox)
            if (Array.isArray(level)) {
                if (level.length === 0) return 'under assessment';
                const formatted = level.map(l => map[l] || l).join(', ');
                return formatted;
            }

            // Handle single value (legacy support)
            return map[level] || 'under assessment';
        }

        // Helper: Format chief complaint
        function formatComplaint(complaint) {
            const map = {
                'chestPain': 'Chest Pain',
                'headache': 'Headache',
                'blurredVision': 'Blurred Vision',
                'difficultyBreathing': 'Difficulty Breathing',
                'dizziness': 'Dizziness',
                'bodyMalaise': 'Body Malaise'
            };
            return map[complaint] || complaint;
        }

        // Helper: Format care action
        function formatCareAction(action) {
            const map = {
                'immobilization': 'Immobilization provided',
                'cpr': 'CPR administered',
                'bandaging': 'Wound care and bandaging done',
                'woundCare': 'Appropriate wound care administered',
                'cCollar': 'C-Collar applied',
                'aed': 'AED utilized',
                'ked': 'KED device applied'
            };
            return map[action] || action;
        }

        // Helper: Parse injuries from JSON
        function parseInjuries(injuriesJson) {
            if (!injuriesJson) return [];

            try {
                const injuries = JSON.parse(injuriesJson);
                return injuries.map(injury => ({
                    type: injury.type || 'injury',
                    location: injury.bodyPart || injury.location || (injury.view === 'front' ? 'Front' : 'Back') || 'unspecified area'
                }));
            } catch (e) {
                return [];
            }
        }

        // Helper: Format date
        function formatDate(dateStr) {
            if (!dateStr) return 'Not specified';

            try {
                const date = new Date(dateStr);
                const months = ['January', 'February', 'March', 'April', 'May', 'June',
                               'July', 'August', 'September', 'October', 'November', 'December'];
                return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
            } catch (e) {
                return dateStr;
            }
        }

        // Helper: Format time
        function formatTime(timeStr) {
            if (!timeStr) return 'Not specified';

            try {
                const [hours, minutes] = timeStr.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour % 12 || 12;
                return `${displayHour}:${minutes} ${ampm}`;
            } catch (e) {
                return timeStr;
            }
        }

        // Copy narrative to clipboard
        function copyNarrativeToClipboard() {
            const narrativeElement = document.getElementById('narrativeContent');
            const narrativeText = narrativeElement.innerText;

            if (!narrativeText || narrativeText.includes('Narrative report will be generated')) {
                Notiflix.Notify.warning('Please generate the narrative report first', {
                    timeout: 3000
                });
                return;
            }

            // Use modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(narrativeText)
                    .then(() => {
                        Notiflix.Notify.success('Narrative copied to clipboard!', {
                            timeout: 2500,
                            position: 'right-top'
                        });
                    })
                    .catch(err => {
                        console.error('Clipboard error:', err);
                        fallbackCopy(narrativeText);
                    });
            } else {
                fallbackCopy(narrativeText);
            }
        }

        // Fallback copy method
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                Notiflix.Notify.success('Narrative copied to clipboard!', {
                    timeout: 2500
                });
            } catch (err) {
                Notiflix.Notify.failure('Failed to copy. Please copy manually.', {
                    timeout: 3000
                });
            }

            document.body.removeChild(textarea);
        }

        // Print narrative only
        function printNarrative() {
            const narrativeElement = document.getElementById('narrativeContent');
            const narrativeText = narrativeElement.innerText;

            if (!narrativeText || narrativeText.includes('Narrative report will be generated')) {
                Notiflix.Notify.warning('Please generate the narrative report first', {
                    timeout: 3000
                });
                return;
            }

            // Create print window
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Narrative Report - EMS</title>
                    <style>
                        body {
                            font-family: 'Courier New', monospace;
                            font-size: 12pt;
                            line-height: 1.6;
                            margin: 2cm;
                            white-space: pre-wrap;
                        }
                        @media print {
                            body { margin: 1.5cm; }
                        }
                    </style>
                </head>
                <body>${narrativeText}</body>
                </html>
            `);
            printWindow.document.close();

            // Wait for content to load then print
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }

        // Export narrative as text file
        function exportNarrativeAsText() {
            const narrativeElement = document.getElementById('narrativeContent');
            const narrativeText = narrativeElement.innerText;

            if (!narrativeText || narrativeText.includes('Narrative report will be generated')) {
                Notiflix.Notify.warning('Please generate the narrative report first', {
                    timeout: 3000
                });
                return;
            }

            // Create blob and download
            const blob = new Blob([narrativeText], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');

            // Generate filename with date
            const now = new Date();
            const dateStr = now.toISOString().split('T')[0];
            a.href = url;
            a.download = `EMS_Narrative_Report_${dateStr}.txt`;

            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            Notiflix.Notify.success('Narrative report downloaded!', {
                timeout: 2500
            });
        }

        // Auto-generate narrative when entering Sections 5, 6, or 7
        ['tab5', 'tab6', 'tab7'].forEach(function(tabId) {
            document.getElementById(tabId).addEventListener('click', function() {
                setTimeout(function() {
                    const narrativeContent = document.getElementById('narrativeContent');
                    if (narrativeContent && narrativeContent.querySelector('.narrative-placeholder')) {
                        generateNarrative();
                    }
                }, 300);
            });
        });
    // CSP-compliant event delegation for data-action attributes
    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-action]');
        if (!el) return;
        var action = el.getAttribute('data-action');
        var arg = el.getAttribute('data-arg');
        switch (action) {
            case 'stopPropagation': e.stopPropagation(); return;
            case 'closePatientCamera': closePatientCamera(); break;
            case 'capturePatientPhoto': capturePatientPhoto(); break;
            case 'flipPatientCamera': flipPatientCamera(); break;
            case 'openPatientImageModal': openPatientImageModal(); break;
            case 'removePatientAttachment': removePatientAttachment(); break;
            case 'closePatientImageModal': closePatientImageModal(); break;
            case 'clearAllInjuries': clearAllInjuries(); break;
            case 'exportInjuryData': exportInjuryData(); break;
            case 'closeCamera': closeCamera(); break;
            case 'capturePhoto': capturePhoto(); break;
            case 'flipEndorsementCamera': flipEndorsementCamera(); break;
            case 'openEndorsementImageModal': openEndorsementImageModal(); break;
            case 'removeAttachment': removeAttachment(); break;
            case 'closeEndorsementImageModal': closeEndorsementImageModal(); break;
            case 'openSummaryModal': openSummaryModal(); break;
            case 'switchNarrativeFormat': switchNarrativeFormat(arg); break;
            case 'generateNarrative': generateNarrative(); break;
            case 'copyNarrativeToClipboard': copyNarrativeToClipboard(); break;
            case 'printNarrative': printNarrative(); break;
            case 'exportNarrativeAsText': exportNarrativeAsText(); break;
            case 'copySummaryToClipboard': copySummaryToClipboard(); break;
            case 'printSummary': printSummary(); break;
            case 'navigateTab': navigateTab(parseInt(arg)); break;
            case 'submitForm': submitForm(); break;
        }
    });

    // ============================================================
    // AMBULANCE UNIT BUTTON GRID
    // Buttons drive the hidden #ambulanceSelect and dispatch a native
    // 'change' event, so all existing vehicle/save/draft-resume logic
    // (in prehospital-form.js) keeps working with zero changes.
    // ============================================================
    (function initAmbulanceUnitGrid() {
        var grid = document.getElementById('ambulanceUnitGrid');
        var select = document.getElementById('ambulanceSelect');
        var triggerText = document.getElementById('ambulanceUnitTriggerText');
        if (!grid || !select) return;

        var buttons = grid.querySelectorAll('.unit-btn');

        function paintActive(value) {
            buttons.forEach(function (b) {
                var on = b.getAttribute('data-unit') === value && value !== '';
                b.classList.toggle('active', on);
                b.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            // Reflect the choice on the modal trigger.
            if (triggerText) {
                triggerText.textContent = value ? value : 'Select unitâ€¦';
            }
            var trigger = document.getElementById('ambulanceUnitTrigger');
            if (trigger) trigger.classList.toggle('has-value', !!value);
        }

        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('.unit-btn');
            if (!btn) return;
            var unit = btn.getAttribute('data-unit');
            select.value = unit;
            paintActive(unit);
            // Notify existing listeners (sets vehicleDetails + display).
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Keep the grid + trigger in sync if the select is changed elsewhere
        // (e.g. draft resume, or the Ambulance radio clearing it).
        select.addEventListener('change', function () {
            paintActive(select.value);
        });

        // The vehicle radios clear #ambulanceSelect WITHOUT firing 'change',
        // so repaint on the next tick after any vehicle radio is clicked.
        document.querySelectorAll('input[name="vehicle_used"]').forEach(function (r) {
            r.addEventListener('click', function () {
                setTimeout(function () { paintActive(select.value); }, 0);
            });
        });

        // Selecting the Ambulance radio opens the unit picker modal directly
        // (no intermediate trigger click). Runs after the module handler has
        // revealed #ambulanceDropdownContainer.
        var ambulanceRadio = document.getElementById('ambulance');
        var modalEl = document.getElementById('ambulanceUnitModal');
        if (ambulanceRadio && modalEl && window.bootstrap) {
            ambulanceRadio.addEventListener('click', function () {
                setTimeout(function () {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }, 0);
            });
        }

        // Initial paint (covers a resumed draft that pre-set the value).
        paintActive(select.value);
    })();
    </script>
</body>
</html>
