<?php
/**
 * View Pre-Hospital Care Record Details
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

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

// Clean up date and time fields - don't show invalid/empty values
$dateTimeFields = [
    'departure_time', 'arrival_time', 'arrival_scene_time', 'departure_scene_time',
    'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time',
    'incident_time', 'call_arrival_time', 'initial_time', 'followup_time',
    'delivery_time', 'endorsement_datetime'
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
$dateFields = ['date_of_birth', 'lmp', 'edc'];
foreach ($dateFields as $field) {
    if (isset($record[$field])) {
        // Clear date fields if they are '0000-00-00' or NULL or empty
        if ($record[$field] === '0000-00-00' || $record[$field] === null || $record[$field] === '' ||
            $record[$field] === '0000-00-00 00:00:00') {
            $record[$field] = '';
        }
    }
}

// Get current user
$current_user = get_auth_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Record - <?php echo e($record['form_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            padding: 20px;
            line-height: 1.6;
        }

        .form-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dee2e6;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .header {
            background-color: #ffffff;
            padding: 30px 40px;
            border-bottom: 3px solid #1a4d8f;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header .logo {
            width: 70px;
            height: auto;
        }

        .header-text h1 {
            color: #1a4d8f;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .header-text .form-meta {
            color: #6c757d;
            font-size: 13px;
        }

        .header-right .badge-status {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-info {
            background-color: #0d6efd;
            color: white;
        }

        .content-body {
            padding: 0;
        }

        .section-header {
            background-color: #1a4d8f;
            color: #ffffff;
            padding: 12px 40px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .section-content {
            padding: 25px 40px;
            border-bottom: 1px solid #e9ecef;
        }

        .section-content:last-child {
            border-bottom: none;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px 30px;
        }

        .data-grid.two-column {
            grid-template-columns: repeat(2, 1fr);
        }

        .data-grid.three-column {
            grid-template-columns: repeat(3, 1fr);
        }

        .data-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .data-field.full-width {
            grid-column: 1 / -1;
        }

        .data-field label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-field .value {
            font-size: 14px;
            color: #212529;
            font-weight: 500;
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-left: 3px solid #dee2e6;
            min-height: 36px;
            display: flex;
            align-items: center;
        }

        .data-field .value.empty {
            color: #adb5bd;
            font-style: italic;
        }

        .data-field .value.multiline {
            white-space: pre-wrap;
            align-items: flex-start;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .vital-box {
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            padding: 0;
            overflow: hidden;
        }

        .vital-box-header {
            background-color: #e9ecef;
            padding: 12px 20px;
            border-bottom: 2px solid #dee2e6;
        }

        .vital-box h4 {
            font-size: 13px;
            font-weight: 600;
            color: #1a4d8f;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vital-box-content {
            padding: 20px;
        }

        .vital-box .data-field {
            margin-bottom: 15px;
        }

        .vital-box .data-field:last-child {
            margin-bottom: 0;
        }

        .vital-box .data-field .value {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-left: 3px solid #1a4d8f;
        }

        .injuries-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .injury-card {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-left: 4px solid #1a4d8f;
            padding: 20px;
        }

        .injury-card-header {
            font-size: 14px;
            font-weight: 600;
            color: #1a4d8f;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .injury-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .injury-detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .injury-detail-item label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
        }

        .injury-detail-item .value {
            font-size: 13px;
            color: #212529;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            padding: 30px 40px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .btn {
            padding: 11px 24px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background-color: #1a4d8f;
            color: white;
        }

        .btn-primary:hover {
            background-color: #153d73;
            color: white;
            box-shadow: 0 2px 8px rgba(26, 77, 143, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
            box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            color: #212529;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-table td:first-child {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            width: 200px;
        }

        .info-table td:last-child {
            font-size: 14px;
            color: #212529;
            font-weight: 500;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .form-container {
                box-shadow: none;
                border: none;
                max-width: 100%;
                margin: 0;
            }

            .header {
                padding: 15px 20px;
                border-bottom: 2px solid #1a4d8f;
                page-break-after: avoid;
            }

            .header .logo {
                width: 50px;
            }

            .header-text h1 {
                font-size: 16px;
            }

            .header-text .form-meta {
                font-size: 10px;
            }

            .section-header {
                font-size: 10px;
                padding: 8px 20px;
                page-break-after: avoid;
            }

            .section-content {
                padding: 12px 20px;
                page-break-inside: avoid;
            }

            .data-grid {
                gap: 8px 15px;
            }

            .data-field label {
                font-size: 8px;
            }

            .data-field .value {
                font-size: 10px;
                padding: 4px 8px;
                min-height: 24px;
            }

            .vital-signs-grid {
                gap: 10px;
            }

            .vital-box {
                border: 1px solid #dee2e6;
            }

            .vital-box-header {
                padding: 6px 12px;
            }

            .vital-box h4 {
                font-size: 9px;
            }

            .vital-box-content {
                padding: 10px;
            }

            .injury-card {
                padding: 10px;
                border: 1px solid #dee2e6;
                page-break-inside: avoid;
            }

            .injury-card-header {
                font-size: 10px;
                margin-bottom: 8px;
                padding-bottom: 6px;
            }

            .badge-status {
                font-size: 8px;
                padding: 3px 8px;
            }

            @page {
                size: legal;
                margin: 0.3in 0.4in;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .form-container {
                border: none;
                box-shadow: none;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
                gap: 15px;
            }

            .header-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .section-header {
                padding: 10px 20px;
                font-size: 12px;
            }

            .section-content {
                padding: 20px;
            }

            .data-grid {
                grid-template-columns: 1fr !important;
            }

            .vital-signs-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                padding: 20px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="form-container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="uploads/logo.png" alt="Logo" class="logo">
                    <div class="header-text">
                        <h1>PRE-HOSPITAL CARE RECORD</h1>
                        <div class="form-meta">Form No: <strong><?php echo e($record['form_number']); ?></strong> | Created: <?php echo date('M d, Y', strtotime($record['created_at'])); ?></div>
                    </div>
                </div>
                <div class="header-right">
                    <span class="badge-status badge-success"><?php echo ucfirst($record['status']); ?></span>
                </div>
            </div>

            <!-- Action Buttons (Top) -->
            <div class="action-buttons no-print">
                <a href="records.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Records
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print
                </button>
                <a href="edit_record.php?id=<?php echo $record['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>

            <div class="content-body">
                <!-- Basic Information -->
                <div class="section-header">Basic Information</div>
                <div class="section-content">
                    <div class="data-grid three-column">
                        <div class="data-field">
                            <label>Form Date</label>
                            <div class="value"><?php echo $record['form_date'] ? date('F d, Y', strtotime($record['form_date'])) : 'N/A'; ?></div>
                        </div>
                        <div class="data-field">
                            <label>Departure Time</label>
                            <div class="value<?php echo empty($record['departure_time']) ? ' empty' : ''; ?>"><?php echo e($record['departure_time'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Arrival Time</label>
                            <div class="value<?php echo empty($record['arrival_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_time'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Vehicle Used</label>
                            <div class="value">
                                <?php
                                $vehicleDisplay = 'N/A';
                                if ($record['vehicle_used']) {
                                    if ($record['vehicle_used'] === 'ambulance' && !empty($record['vehicle_details'])) {
                                        // Try to parse JSON vehicle details
                                        $vehicleData = json_decode($record['vehicle_details'], true);
                                        if ($vehicleData && isset($vehicleData['id']) && isset($vehicleData['plate'])) {
                                            $vehicleDisplay = 'Ambulance ' . $vehicleData['id'] . ' (' . $vehicleData['plate'] . ')';
                                        } else {
                                            $vehicleDisplay = 'Ambulance';
                                        }
                                    } elseif ($record['vehicle_used'] === 'fireTruck') {
                                        $vehicleDisplay = 'Fire Truck';
                                    } elseif ($record['vehicle_used'] === 'others') {
                                        $vehicleDisplay = 'Others';
                                    } else {
                                        $vehicleDisplay = ucfirst($record['vehicle_used']);
                                    }
                                }
                                echo e($vehicleDisplay);
                                ?>
                            </div>
                        </div>
                        <div class="data-field">
                            <label>Driver Name</label>
                            <div class="value<?php echo empty($record['driver_name']) ? ' empty' : ''; ?>"><?php echo e($record['driver_name'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Scene Information -->
                <div class="section-header">Scene Information</div>
                <div class="section-content">
                    <div class="data-grid two-column">
                        <div class="data-field">
                            <label>Arrival Scene Location</label>
                            <div class="value<?php echo empty($record['arrival_scene_location']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_scene_location'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Arrival Scene Time</label>
                            <div class="value<?php echo empty($record['arrival_scene_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_scene_time'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Departure Scene Location</label>
                            <div class="value<?php echo empty($record['departure_scene_location']) ? ' empty' : ''; ?>"><?php echo e($record['departure_scene_location'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Departure Scene Time</label>
                            <div class="value<?php echo empty($record['departure_scene_time']) ? ' empty' : ''; ?>"><?php echo e($record['departure_scene_time'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Patient Information -->
                <div class="section-header">Patient Information</div>
                <div class="section-content">
                    <div class="data-grid">
                        <div class="data-field full-width">
                            <label>Patient Name</label>
                            <div class="value"><?php echo e($record['patient_name']); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Date of Birth</label>
                            <div class="value"><?php echo $record['date_of_birth'] ? date('F d, Y', strtotime($record['date_of_birth'])) : 'N/A'; ?></div>
                        </div>
                        <div class="data-field">
                            <label>Age</label>
                            <div class="value"><?php echo e($record['age']); ?> years old</div>
                        </div>
                        <div class="data-field">
                            <label>Gender</label>
                            <div class="value"><?php echo ucfirst($record['gender']); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Civil Status</label>
                            <div class="value<?php echo empty($record['civil_status']) ? ' empty' : ''; ?>"><?php echo ucfirst($record['civil_status'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Occupation</label>
                            <div class="value<?php echo empty($record['occupation']) ? ' empty' : ''; ?>"><?php echo e($record['occupation'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field full-width">
                            <label>Address</label>
                            <div class="value<?php echo empty($record['address']) ? ' empty' : ''; ?>"><?php echo e($record['address'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Zone</label>
                            <div class="value<?php echo empty($record['zone']) ? ' empty' : ''; ?>"><?php echo e($record['zone'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field full-width">
                            <label>Place of Incident</label>
                            <div class="value<?php echo empty($record['place_of_incident']) ? ' empty' : ''; ?>"><?php echo e($record['place_of_incident'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Zone Landmark</label>
                            <div class="value<?php echo empty($record['zone_landmark']) ? ' empty' : ''; ?>"><?php echo e($record['zone_landmark'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Incident Time</label>
                            <div class="value<?php echo empty($record['incident_time']) ? ' empty' : ''; ?>"><?php echo e($record['incident_time'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Informant Details -->
                <div class="section-header">Informant Details</div>
                <div class="section-content">
                    <div class="data-grid">
                        <div class="data-field">
                            <label>Informant Name</label>
                            <div class="value<?php echo empty($record['informant_name']) ? ' empty' : ''; ?>"><?php echo e($record['informant_name'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Contact Number</label>
                            <div class="value<?php echo empty($record['contact_number']) ? ' empty' : ''; ?>"><?php echo e($record['contact_number'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field full-width">
                            <label>Informant Address</label>
                            <div class="value<?php echo empty($record['informant_address']) ? ' empty' : ''; ?>"><?php echo e($record['informant_address'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Arrival Type</label>
                            <div class="value<?php echo empty($record['arrival_type']) ? ' empty' : ''; ?>"><?php echo ucfirst($record['arrival_type'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Call Arrival Time</label>
                            <div class="value<?php echo empty($record['call_arrival_time']) ? ' empty' : ''; ?>"><?php echo e($record['call_arrival_time'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field full-width">
                            <label>Relationship to Victim</label>
                            <div class="value<?php echo empty($record['relationship_victim']) ? ' empty' : ''; ?>"><?php echo e($record['relationship_victim'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field full-width">
                            <label>Persons Present Upon Arrival</label>
                            <div class="value<?php
                            $persons_present = $record['persons_present'];
                            $has_persons = false;
                            if ($persons_present) {
                                $decoded = json_decode($persons_present, true);
                                $has_persons = $decoded && is_array($decoded) && count($decoded) > 0;
                            }
                            echo !$has_persons ? ' empty' : '';
                            ?>">
                                <?php
                                if ($persons_present) {
                                    $decoded = json_decode($persons_present, true);
                                    if ($decoded && is_array($decoded)) {
                                        echo implode(', ', array_map('e', $decoded));
                                    } else {
                                        echo e($persons_present);
                                    }
                                } else {
                                    echo 'Not specified';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vital Signs -->
                <div class="section-header">Vital Signs</div>
                <div class="section-content">
                    <div class="vital-signs-grid">
                        <div class="vital-box">
                            <div class="vital-box-header">
                                <h4>Initial Assessment</h4>
                            </div>
                            <div class="vital-box-content">
                                <div class="data-field">
                                    <label>Blood Pressure</label>
                                    <div class="value<?php echo empty($record['initial_bp']) ? ' empty' : ''; ?>"><?php echo e($record['initial_bp'] ?: 'Not recorded'); ?></div>
                                </div>
                                <div class="data-field">
                                    <label>Temperature</label>
                                    <div class="value<?php echo empty($record['initial_temp']) ? ' empty' : ''; ?>"><?php echo $record['initial_temp'] ? $record['initial_temp'] . '°C' : 'Not recorded'; ?></div>
                                </div>
                                <div class="data-field">
                                    <label>Pulse Rate</label>
                                    <div class="value<?php echo empty($record['initial_pulse']) ? ' empty' : ''; ?>"><?php echo $record['initial_pulse'] ? $record['initial_pulse'] . ' BPM' : 'Not recorded'; ?></div>
                                </div>
                                <div class="data-field">
                                    <label>SPO2</label>
                                    <div class="value<?php echo empty($record['initial_spo2']) ? ' empty' : ''; ?>"><?php echo $record['initial_spo2'] ? $record['initial_spo2'] . '%' : 'Not recorded'; ?></div>
                                </div>
                                <div class="data-field">
                                    <label>Consciousness</label>
                                    <div class="value<?php echo empty($record['initial_consciousness']) ? ' empty' : ''; ?>"><?php echo ucfirst($record['initial_consciousness'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="vital-box">
                            <div class="vital-box-header">
                                <h4>Follow-up Assessment</h4>
                            </div>
                            <div class="vital-box-content">
                                <div class="data-field">
                                    <label>Blood Pressure</label>
                                    <div class="value<?php echo empty($record['followup_bp']) ? ' empty' : ''; ?>"><?php echo e($record['followup_bp'] ?: 'Not recorded'); ?></div>
                                </div>
                                <div class="data-field">
                                    <label>Temperature</label>
                                    <div class="value<?php echo empty($record['followup_temp']) ? ' empty' : ''; ?>"><?php echo $record['followup_temp'] ? $record['followup_temp'] . '°C' : 'Not recorded'; ?></div>
                                </div>
                                <div class="data-field">
                                    <label>Pulse Rate</label>
                                    <div class="value<?php echo empty($record['followup_pulse']) ? ' empty' : ''; ?>"><?php echo $record['followup_pulse'] ? $record['followup_pulse'] . ' BPM' : 'Not recorded'; ?></div>
                                </div>
                                <div class="data-field">
                                    <label>SPO2</label>
                                    <div class="value<?php echo empty($record['followup_spo2']) ? ' empty' : ''; ?>"><?php echo $record['followup_spo2'] ? $record['followup_spo2'] . '%' : 'Not recorded'; ?></div>
                                </div>
                                <div class="data-field">
                                    <label>Consciousness</label>
                                    <div class="value<?php echo empty($record['followup_consciousness']) ? ' empty' : ''; ?>"><?php echo ucfirst($record['followup_consciousness'] ?: 'Not recorded'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Injuries -->
                <?php if (!empty($injuries)): ?>
                <div class="section-header">Injuries Marked (<?php echo count($injuries); ?>)</div>
                <div class="section-content">
                    <div class="injuries-section">
                        <?php foreach ($injuries as $injury): ?>
                            <div class="injury-card">
                                <div class="injury-card-header">Injury #<?php echo $injury['injury_number']; ?></div>
                                <div class="injury-details">
                                    <div class="injury-detail-item">
                                        <label>Injury Type</label>
                                        <div class="value">
                                            <span class="badge-status badge-danger"><?php echo ucfirst($injury['injury_type']); ?></span>
                                        </div>
                                    </div>
                                    <div class="injury-detail-item">
                                        <label>Body View</label>
                                        <div class="value">
                                            <span class="badge-status badge-info"><?php echo ucfirst($injury['body_view']); ?> View</span>
                                        </div>
                                    </div>
                                    <div class="injury-detail-item" style="grid-column: 1 / -1;">
                                        <label>Notes</label>
                                        <div class="value"><?php echo e($injury['notes'] ?: 'No additional notes'); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hospital & Team Information -->
                <div class="section-header">Hospital Information</div>
                <div class="section-content">
                    <div class="data-grid">
                        <div class="data-field">
                            <label>Arrival Hospital Name</label>
                            <div class="value<?php echo empty($record['arrival_hospital_name']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_hospital_name'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Arrival Hospital Time</label>
                            <div class="value<?php echo empty($record['arrival_hospital_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_hospital_time'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Departure Hospital Time</label>
                            <div class="value<?php echo empty($record['departure_hospital_time']) ? ' empty' : ''; ?>"><?php echo e($record['departure_hospital_time'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Arrival Station Time</label>
                            <div class="value<?php echo empty($record['arrival_station_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_station_time'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Team Information -->
                <div class="section-header">Team Information</div>
                <div class="section-content">
                    <div class="data-grid">
                        <div class="data-field">
                            <label>Team Leader</label>
                            <div class="value<?php echo empty($record['team_leader']) ? ' empty' : ''; ?>"><?php echo e($record['team_leader'] ?: 'Not assigned'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Data Recorder</label>
                            <div class="value<?php echo empty($record['data_recorder']) ? ' empty' : ''; ?>"><?php echo e($record['data_recorder'] ?: 'Not assigned'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Logistic</label>
                            <div class="value<?php echo empty($record['logistic']) ? ' empty' : ''; ?>"><?php echo e($record['logistic'] ?: 'Not assigned'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>1st Aider</label>
                            <div class="value<?php echo empty($record['first_aider']) ? ' empty' : ''; ?>"><?php echo e($record['first_aider'] ?: 'Not assigned'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>2nd Aider</label>
                            <div class="value<?php echo empty($record['second_aider']) ? ' empty' : ''; ?>"><?php echo e($record['second_aider'] ?: 'Not assigned'); ?></div>
                        </div>
                        <?php if ($record['team_leader_notes']): ?>
                        <div class="data-field full-width">
                            <label>Team Leader Notes</label>
                            <div class="value multiline"><?php echo nl2br(e($record['team_leader_notes'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Record Information -->
                <div class="section-header">Record Information</div>
                <div class="section-content">
                    <div class="data-grid two-column">
                        <div class="data-field">
                            <label>Created At</label>
                            <div class="value"><?php echo date('F d, Y g:i A', strtotime($record['created_at'])); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Last Updated</label>
                            <div class="value"><?php echo date('F d, Y g:i A', strtotime($record['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (Bottom) -->
            <div class="action-buttons no-print">
                <a href="records.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Records
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print
                </button>
                <a href="edit_record.php?id=<?php echo $record['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
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
        });
    </script>
</body>
</html>
