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
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            padding: 20px;
        }

        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #003d7a;
            position: relative;
        }

        .header .logo {
            width: 80px;
            height: auto;
            margin-bottom: 10px;
        }

        .header h1 {
            color: #003d7a;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .section-header {
            background-color: #e8e8e8;
            padding: 8px 12px;
            border-left: 4px solid #003d7a;
            font-weight: 600;
            font-size: 15px;
            color: #003d7a;
            margin-top: 20px;
            margin-bottom: 15px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            flex-basis: 100%;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 700;
            color: #555;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-group .value {
            font-size: 14px;
            color: #333;
            padding: 0;
            line-height: 1.5;
        }

        .form-group .value.multiline {
            white-space: pre-wrap;
        }

        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
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
            background-color: #17a2b8;
            color: white;
        }

        .injury-item {
            background-color: #f9f9f9;
            border-left: 4px solid #003d7a;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 3px;
        }

        .injury-item .injury-number {
            font-weight: 700;
            color: #003d7a;
            margin-bottom: 5px;
        }

        .injury-item .injury-detail {
            font-size: 13px;
            margin-bottom: 3px;
        }

        .injury-item .injury-detail strong {
            color: #555;
            min-width: 80px;
            display: inline-block;
        }

        .vital-signs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        .vital-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }

        .vital-box h4 {
            font-size: 14px;
            font-weight: 700;
            color: #003d7a;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #003d7a;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e8e8e8;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #003d7a;
            color: white;
        }

        .btn-primary:hover {
            background-color: #002a56;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            color: #333;
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
                padding: 0.3in 0.4in;
                max-width: 100%;
                margin: 0;
            }

            .action-buttons {
                display: none !important;
            }

            .header {
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 3px solid #003d7a;
                text-align: center;
            }

            .header .logo {
                width: 60px;
                height: auto;
                margin-bottom: 5px;
            }

            .header h1 {
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 3px;
            }

            .header p {
                font-size: 11px;
                margin: 2px 0;
            }

            .section-header {
                font-size: 12px;
                font-weight: 700;
                padding: 5px 10px;
                margin-top: 10px;
                margin-bottom: 6px;
                page-break-after: avoid;
                background-color: #d0d0d0;
                border-left: 4px solid #003d7a;
            }

            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 5px 15px;
                margin-bottom: 4px;
                page-break-inside: avoid;
            }

            .form-group {
                display: flex;
                flex-direction: row;
                align-items: baseline;
                gap: 5px;
            }

            .form-group label {
                font-size: 8px;
                font-weight: 600;
                margin-bottom: 0;
                color: #555;
                flex-shrink: 0;
                min-width: 80px;
            }

            .form-group label::after {
                content: ':';
                margin-left: 2px;
            }

            .form-group .value {
                font-size: 10px;
                font-weight: 700;
                color: #000;
                padding: 0;
                line-height: 1.3;
                flex: 1;
            }

            .form-group.full-width {
                grid-column: 1 / -1;
                flex-direction: column;
                align-items: flex-start;
            }

            .form-group.full-width label::after {
                content: '';
            }

            .form-group.full-width .value {
                margin-top: 2px;
            }

            .form-group .value.multiline {
                white-space: pre-wrap;
            }

            .vital-signs-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 4px;
            }

            .vital-box {
                padding: 5px;
                border: 1px solid #ccc;
                page-break-inside: avoid;
            }

            .vital-box h4 {
                font-size: 9px;
                font-weight: 700;
                margin-bottom: 3px;
                padding-bottom: 2px;
                border-bottom: 1px solid #003d7a;
            }

            .vital-box .form-group {
                margin-bottom: 2px;
                display: flex;
                flex-direction: row;
                align-items: baseline;
                gap: 4px;
            }

            .vital-box .form-group label {
                font-size: 7px;
                font-weight: 600;
                flex-shrink: 0;
                min-width: 55px;
                color: #555;
            }

            .vital-box .form-group label::after {
                content: ':';
                margin-left: 1px;
            }

            .vital-box .form-group .value {
                font-size: 9px;
                font-weight: 700;
                padding: 0;
                line-height: 1.3;
            }

            .injury-item {
                padding: 4px 6px;
                margin-bottom: 3px;
                page-break-inside: avoid;
                border-left: 3px solid #003d7a;
                background-color: #f5f5f5;
            }

            .injury-item .injury-number {
                font-size: 9px;
                font-weight: 700;
                margin-bottom: 2px;
            }

            .injury-item .injury-detail {
                font-size: 8px;
                margin-bottom: 1px;
                display: inline;
                margin-right: 8px;
            }

            .injury-item .injury-detail strong {
                font-size: 8px;
                font-weight: 600;
                color: #555;
            }

            .badge-status {
                font-size: 7px;
                padding: 2px 5px;
                border-radius: 2px;
                font-weight: 700;
            }

            /* Compact specific sections */
            .form-row:has(.form-group.full-width) {
                margin-bottom: 3px;
            }

            /* Legal size paper - single page optimization */
            @page {
                size: legal; /* 8.5 x 13 inches */
                margin: 0.25in 0.35in;
            }

            /* Force single page */
            html, body {
                height: auto;
                overflow: visible;
            }
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }

            .vital-signs-grid {
                grid-template-columns: 1fr;
            }

            .form-group {
                min-width: 100%;
            }

            .action-buttons {
                flex-direction: column;
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
            <img src="uploads/logo.png" alt="Logo" class="logo">
            <h1>PRE-HOSPITAL CARE FORM</h1>
            <p>Form Number: <?php echo e($record['form_number']); ?></p>
            <p>Status: <span class="badge-status badge-success"><?php echo ucfirst($record['status']); ?></span></p>
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

        <!-- Basic Information -->
        <div class="section-header">BASIC INFORMATION</div>
        <div class="form-row">
            <div class="form-group">
                <label>Form Date</label>
                <div class="value"><?php echo date('F d, Y', strtotime($record['form_date'])); ?></div>
            </div>
            <div class="form-group">
                <label>Departure Time</label>
                <div class="value"><?php echo e($record['departure_time'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Arrival Time</label>
                <div class="value"><?php echo e($record['arrival_time'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Vehicle Used</label>
                <div class="value"><?php echo ucfirst($record['vehicle_used'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Driver Name</label>
                <div class="value"><?php echo e($record['driver_name'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <!-- Scene Information -->
        <div class="section-header">SCENE INFORMATION</div>
        <div class="form-row">
            <div class="form-group">
                <label>Arrival Scene Location</label>
                <div class="value"><?php echo e($record['arrival_scene_location'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Arrival Scene Time</label>
                <div class="value"><?php echo e($record['arrival_scene_time'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Departure Scene Location</label>
                <div class="value"><?php echo e($record['departure_scene_location'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Departure Scene Time</label>
                <div class="value"><?php echo e($record['departure_scene_time'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="section-header">PATIENT INFORMATION</div>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Patient Name</label>
                <div class="value"><?php echo e($record['patient_name']); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Date of Birth</label>
                <div class="value"><?php echo date('F d, Y', strtotime($record['date_of_birth'])); ?></div>
            </div>
            <div class="form-group">
                <label>Age</label>
                <div class="value"><?php echo e($record['age']); ?> years old</div>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <div class="value"><?php echo ucfirst($record['gender']); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Civil Status</label>
                <div class="value"><?php echo ucfirst($record['civil_status'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Occupation</label>
                <div class="value"><?php echo e($record['occupation'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Address</label>
                <div class="value"><?php echo e($record['address'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Zone</label>
                <div class="value"><?php echo e($record['zone'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Place of Incident</label>
                <div class="value"><?php echo e($record['place_of_incident'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Zone Landmark</label>
                <div class="value"><?php echo e($record['zone_landmark'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Incident Time</label>
                <div class="value"><?php echo e($record['incident_time'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <!-- Informant Details -->
        <div class="section-header">INFORMANT DETAILS</div>
        <div class="form-row">
            <div class="form-group">
                <label>Informant Name</label>
                <div class="value"><?php echo e($record['informant_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <div class="value"><?php echo e($record['contact_number'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Informant Address</label>
                <div class="value"><?php echo e($record['informant_address'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Arrival Type</label>
                <div class="value"><?php echo ucfirst($record['arrival_type'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Call Arrival Time</label>
                <div class="value"><?php echo e($record['call_arrival_time'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Relationship to Victim</label>
                <div class="value"><?php echo e($record['relationship_victim'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Persons Present Upon Arrival</label>
                <div class="value">
                    <?php
                    $persons_present = $record['persons_present'];
                    if ($persons_present) {
                        $decoded = json_decode($persons_present, true);
                        if ($decoded && is_array($decoded)) {
                            echo implode(', ', array_map('e', $decoded));
                        } else {
                            echo e($persons_present);
                        }
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Vital Signs -->
        <div class="section-header">VITAL SIGNS</div>
        <div class="vital-signs-grid">
            <div class="vital-box">
                <h4>Initial Assessment</h4>
                <div class="form-group">
                    <label>Blood Pressure</label>
                    <div class="value"><?php echo e($record['initial_bp'] ?: 'N/A'); ?></div>
                </div>
                <div class="form-group">
                    <label>Temperature</label>
                    <div class="value"><?php echo $record['initial_temp'] ? $record['initial_temp'] . '°C' : 'N/A'; ?></div>
                </div>
                <div class="form-group">
                    <label>Pulse</label>
                    <div class="value"><?php echo $record['initial_pulse'] ? $record['initial_pulse'] . ' BPM' : 'N/A'; ?></div>
                </div>
                <div class="form-group">
                    <label>SPO2</label>
                    <div class="value"><?php echo $record['initial_spo2'] ? $record['initial_spo2'] . '%' : 'N/A'; ?></div>
                </div>
                <div class="form-group">
                    <label>Consciousness</label>
                    <div class="value"><?php echo ucfirst($record['initial_consciousness'] ?: 'N/A'); ?></div>
                </div>
            </div>

            <div class="vital-box">
                <h4>Follow-up Assessment</h4>
                <div class="form-group">
                    <label>Blood Pressure</label>
                    <div class="value"><?php echo e($record['followup_bp'] ?: 'N/A'); ?></div>
                </div>
                <div class="form-group">
                    <label>Temperature</label>
                    <div class="value"><?php echo $record['followup_temp'] ? $record['followup_temp'] . '°C' : 'N/A'; ?></div>
                </div>
                <div class="form-group">
                    <label>Pulse</label>
                    <div class="value"><?php echo $record['followup_pulse'] ? $record['followup_pulse'] . ' BPM' : 'N/A'; ?></div>
                </div>
                <div class="form-group">
                    <label>SPO2</label>
                    <div class="value"><?php echo $record['followup_spo2'] ? $record['followup_spo2'] . '%' : 'N/A'; ?></div>
                </div>
                <div class="form-group">
                    <label>Consciousness</label>
                    <div class="value"><?php echo ucfirst($record['followup_consciousness'] ?: 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <!-- Injuries -->
        <?php if (!empty($injuries)): ?>
        <div class="section-header">INJURIES MARKED (<?php echo count($injuries); ?>)</div>
        <?php foreach ($injuries as $injury): ?>
            <div class="injury-item">
                <div class="injury-number">Injury #<?php echo $injury['injury_number']; ?></div>
                <div class="injury-detail">
                    <strong>Type:</strong>
                    <span class="badge-status badge-danger"><?php echo ucfirst($injury['injury_type']); ?></span>
                </div>
                <div class="injury-detail">
                    <strong>View:</strong>
                    <span class="badge-status badge-info"><?php echo ucfirst($injury['body_view']); ?> View</span>
                </div>
                <div class="injury-detail">
                    <strong>Notes:</strong> <?php echo e($injury['notes'] ?: 'N/A'); ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Hospital & Team Information -->
        <div class="section-header">HOSPITAL & TEAM INFORMATION</div>
        <div class="form-row">
            <div class="form-group">
                <label>Arrival Hospital Name</label>
                <div class="value"><?php echo e($record['arrival_hospital_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Arrival Hospital Time</label>
                <div class="value"><?php echo e($record['arrival_hospital_time'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Departure Hospital Location</label>
                <div class="value"><?php echo e($record['departure_hospital_location'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Departure Hospital Time</label>
                <div class="value"><?php echo e($record['departure_hospital_time'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Arrival Station Time</label>
                <div class="value"><?php echo e($record['arrival_station_time'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Team Leader</label>
                <div class="value"><?php echo e($record['team_leader'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>Data Recorder</label>
                <div class="value"><?php echo e($record['data_recorder'] ?: 'N/A'); ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Logistic</label>
                <div class="value"><?php echo e($record['logistic'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>1st Aider</label>
                <div class="value"><?php echo e($record['first_aider'] ?: 'N/A'); ?></div>
            </div>
            <div class="form-group">
                <label>2nd Aider</label>
                <div class="value"><?php echo e($record['second_aider'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <?php if ($record['team_leader_notes']): ?>
        <div class="form-row">
            <div class="form-group full-width">
                <label>Team Leader Notes</label>
                <div class="value multiline"><?php echo nl2br(e($record['team_leader_notes'])); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Record Information -->
        <div class="section-header">RECORD INFORMATION</div>
        <div class="form-row">
            <div class="form-group">
                <label>Created At</label>
                <div class="value"><?php echo date('F d, Y g:i A', strtotime($record['created_at'])); ?></div>
            </div>
            <div class="form-group">
                <label>Last Updated</label>
                <div class="value"><?php echo date('F d, Y g:i A', strtotime($record['updated_at'])); ?></div>
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
