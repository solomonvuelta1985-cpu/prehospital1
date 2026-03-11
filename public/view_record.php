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

// Ownership check - users can only view their own records, admins can view all
if (!can_access_record($record_id)) {
    set_flash('Access denied. You can only view your own records.', 'error');
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

// Decrypt sensitive fields
decrypt_record_fields($record);

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
        /* Corporate Design System Variables */
        :root {
            --primary: #0052CC;
            --primary-hover: #0041A3;
            --primary-light: #E6F0FF;
            --accent-success: #00875A;
            --accent-warning: #FF8B00;
            --accent-danger: #DE350B;
            --accent-info: #0065FF;
            --accent-purple: #5243AA;
            --accent-teal: #00A3BF;
            --text-primary: #1a1a1a;
            --text-secondary: #333333;
            --text-muted: #555555;
            --text-light: #666666;
            --bg-white: #FFFFFF;
            --bg-light: #F4F5F7;
            --bg-subtle: #FAFBFC;
            --border-light: #DFE1E6;
            --border-medium: #C1C7D0;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 2px 4px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 4px 8px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 6px 12px rgba(0, 0, 0, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #F4F5F7;
            color: #1a1a1a;
            padding: 20px;
            line-height: 1.6;
        }

        .form-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid #DFE1E6;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background-color: #0052CC;
            padding: 24px 40px;
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
            width: 60px;
            height: auto;
            background: #FFFFFF;
            padding: 8px;
            border-radius: 6px;
        }

        .header-text h1 {
            color: #FFFFFF !important;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .header-text .form-meta {
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 13px;
            font-weight: 500;
        }

        .header-text .form-meta strong {
            color: #FFFFFF !important;
        }

        .header-right .badge-status {
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .badge-success {
            background-color: #00875A !important;
            color: #FFFFFF !important;
        }

        .badge-danger {
            background-color: #DE350B !important;
            color: #FFFFFF !important;
        }

        .badge-info {
            background-color: #0065FF !important;
            color: #FFFFFF !important;
        }

        .badge-warning {
            background-color: #FF8B00 !important;
            color: #FFFFFF !important;
        }

        .badge-purple {
            background-color: #5243AA !important;
            color: #FFFFFF !important;
        }

        .content-body {
            padding: 0;
        }

        .section-header {
            background-color: #F4F5F7;
            color: #0052CC !important;
            padding: 14px 40px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
            border-left: 4px solid #0052CC;
            border-bottom: 1px solid #DFE1E6;
        }

        .section-content {
            padding: 24px 40px;
            border-bottom: 1px solid #DFE1E6;
            background-color: #FFFFFF;
        }

        .section-content:last-child {
            border-bottom: none;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px 24px;
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
            gap: 6px;
        }

        .data-field.full-width {
            grid-column: 1 / -1;
        }

        .data-field label {
            font-size: 10px;
            font-weight: 700;
            color: #555555 !important;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .data-field .value {
            font-size: 14px;
            color: #1a1a1a !important;
            font-weight: 600;
            padding: 8px 4px;
            background-color: transparent;
            border: none;
            border-bottom: 2px solid #0052CC;
            border-radius: 0;
            min-height: 32px;
            display: flex;
            align-items: center;
        }

        .data-field .value.empty {
            color: #888888 !important;
            font-style: italic;
            border-bottom-color: #C1C7D0;
        }

        .data-field .value.multiline {
            white-space: pre-wrap;
            align-items: flex-start;
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .vital-box {
            background-color: #FFFFFF;
            border: 1px solid #DFE1E6;
            border-radius: 6px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .vital-box-header {
            background-color: #0052CC;
            padding: 12px 20px;
        }

        .vital-box h4 {
            font-size: 12px;
            font-weight: 700;
            color: #FFFFFF !important;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .vital-box-content {
            padding: 20px;
            background-color: #FAFBFC;
        }

        .vital-box .data-field {
            margin-bottom: 14px;
        }

        .vital-box .data-field:last-child {
            margin-bottom: 0;
        }

        .vital-box .data-field .value {
            background-color: transparent;
            border: none;
            border-bottom: 2px solid #0052CC;
        }

        .injuries-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .injury-card {
            background-color: #FFFFFF;
            border: 1px solid #DFE1E6;
            border-left: 4px solid #DE350B;
            border-radius: 6px;
            padding: 18px 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .injury-card-header {
            font-size: 13px;
            font-weight: 700;
            color: #1a1a1a !important;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #DFE1E6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .injury-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .injury-detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .injury-detail-item label {
            font-size: 10px;
            font-weight: 700;
            color: #555555 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .injury-detail-item .value {
            font-size: 13px;
            color: #1a1a1a !important;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            padding: 24px 40px;
            background-color: #F4F5F7;
            border-top: 1px solid #DFE1E6;
        }

        .btn {
            padding: 12px 28px;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background-color: #0052CC !important;
            color: #FFFFFF !important;
        }

        .btn-primary:hover {
            background-color: #0041A3 !important;
            color: #FFFFFF !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #6B778C !important;
            color: #FFFFFF !important;
        }

        .btn-secondary:hover {
            background-color: #5A6373 !important;
            color: #FFFFFF !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .btn-warning {
            background-color: #FF8B00 !important;
            color: #FFFFFF !important;
        }

        .btn-warning:hover {
            background-color: #E67E00 !important;
            color: #FFFFFF !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: #00875A !important;
            color: #FFFFFF !important;
        }

        .btn-danger {
            background-color: #DE350B !important;
            color: #FFFFFF !important;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 12px 0;
            border-bottom: 1px solid #DFE1E6;
        }

        .info-table td:first-child {
            font-size: 10px;
            font-weight: 700;
            color: #555555 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 200px;
        }

        .info-table td:last-child {
            font-size: 14px;
            color: #1a1a1a !important;
            font-weight: 600;
        }

        /* Print Styles */
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
                border-radius: 0;
                max-width: 100%;
                margin: 0;
            }

            .header {
                padding: 15px 20px;
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
                padding: 4px 2px;
                min-height: 20px;
                border-bottom: 1px solid #333;
            }

            .vital-signs-grid {
                gap: 10px;
            }

            .vital-box {
                border: 1px solid #DFE1E6;
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
                border: 1px solid #DFE1E6;
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

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .form-container {
                border: none;
                box-shadow: none;
                border-radius: 0;
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
                font-size: 11px;
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

        /* Force High Contrast Text - scoped to content area only */
        .content h1, .content h2, .content h3, .content h4, .content h5, .content h6 {
            color: #1a1a1a !important;
        }

        .content p, .content span, .content div, .content td, .content th, .content li {
            color: #1a1a1a;
        }

        .header h1, .header .form-meta, .header .form-meta strong {
            color: #FFFFFF !important;
        }

        /* Ensure all value text is clearly visible */
        .value, .data-field .value, .injury-detail-item .value {
            color: #1a1a1a !important;
            font-weight: 600 !important;
        }

        /* ========================================
           DESKTOP/LAPTOP COMPACT FIT
           ======================================== */
        @media (min-width: 1024px) {
            body {
                padding: 12px;
            }

            .form-container {
                max-width: 1200px;
            }

            .header {
                padding: 18px 32px;
            }

            .header .logo {
                width: 55px;
                padding: 6px;
            }

            .header-text h1 {
                font-size: 18px;
                margin-bottom: 3px;
            }

            .header-text .form-meta {
                font-size: 13px;
            }

            .header-right .badge-status {
                padding: 7px 16px;
                font-size: 11px;
            }

            .section-header {
                padding: 12px 32px;
                font-size: 12px;
                letter-spacing: 1.2px;
            }

            .section-content {
                padding: 18px 32px;
            }

            .data-grid {
                gap: 14px 22px;
            }

            .data-grid.three-column {
                grid-template-columns: repeat(4, 1fr);
            }

            .data-grid.two-column {
                grid-template-columns: repeat(3, 1fr);
            }

            .data-field {
                gap: 5px;
            }

            .data-field label {
                font-size: 10px;
            }

            .data-field .value {
                font-size: 13px;
                padding: 6px 4px;
                min-height: 30px;
                border-bottom-width: 2px;
            }

            .vital-signs-grid {
                gap: 18px;
            }

            .vital-box-header {
                padding: 11px 18px;
            }

            .vital-box h4 {
                font-size: 12px;
            }

            .vital-box-content {
                padding: 16px;
            }

            .vital-box .data-field {
                margin-bottom: 12px;
            }

            .injury-card {
                padding: 16px 18px;
            }

            .injury-card-header {
                font-size: 13px;
                margin-bottom: 12px;
                padding-bottom: 9px;
            }

            .injury-details {
                gap: 12px;
            }

            .action-buttons {
                padding: 18px 32px;
                gap: 12px;
            }

            .btn {
                padding: 11px 24px;
                font-size: 12px;
            }
        }

        /* Standard laptop (1366x768) - Extra Compact */
        @media (min-width: 1024px) and (max-height: 800px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 14px 28px;
            }

            .header .logo {
                width: 48px;
                padding: 5px;
            }

            .header-text h1 {
                font-size: 16px;
            }

            .header-text .form-meta {
                font-size: 12px;
            }

            .section-header {
                padding: 10px 28px;
                font-size: 11px;
            }

            .section-content {
                padding: 14px 28px;
            }

            .data-grid {
                gap: 10px 18px;
            }

            .data-field label {
                font-size: 9px;
                letter-spacing: 0.6px;
            }

            .data-field .value {
                font-size: 12px;
                padding: 5px 4px;
                min-height: 28px;
            }

            .vital-signs-grid {
                gap: 14px;
            }

            .vital-box-header {
                padding: 9px 14px;
            }

            .vital-box h4 {
                font-size: 11px;
            }

            .vital-box-content {
                padding: 12px;
            }

            .vital-box .data-field {
                margin-bottom: 10px;
            }

            .injury-card {
                padding: 12px 14px;
                margin-bottom: 10px;
            }

            .injury-card-header {
                font-size: 12px;
                margin-bottom: 10px;
                padding-bottom: 7px;
            }

            .action-buttons {
                padding: 14px 28px;
            }

            .btn {
                padding: 9px 20px;
                font-size: 11px;
            }
        }

        /* Large desktop (1920x1080+) */
        @media (min-width: 1600px) {
            .form-container {
                max-width: 1400px;
            }

            .data-grid.three-column {
                grid-template-columns: repeat(5, 1fr);
            }

            .data-grid.two-column {
                grid-template-columns: repeat(4, 1fr);
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
                <button data-action="print" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print
                </button>
                <a href="../api/export_pdf.php?id=<?php echo $record['id']; ?>" class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
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
                        <!-- Commented out: place_of_incident and zone_landmark hidden from view
                        <div class="data-field full-width">
                            <label>Place of Incident</label>
                            <div class="value<?php echo empty($record['place_of_incident']) ? ' empty' : ''; ?>"><?php echo e($record['place_of_incident'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Zone Landmark</label>
                            <div class="value<?php echo empty($record['zone_landmark']) ? ' empty' : ''; ?>"><?php echo e($record['zone_landmark'] ?: 'Not specified'); ?></div>
                        </div>
                        -->
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

                <!-- Emergency Type & Care Management -->
                <div class="section-header">Emergency Type & Care Management</div>
                <div class="section-content">
                    <div class="data-grid">
                        <div class="data-field full-width">
                            <label>Emergency Types</label>
                            <div class="value<?php
                                $emergency_types = [];
                                if (!empty($record['emergency_medical'])) $emergency_types[] = 'Medical';
                                if (!empty($record['emergency_trauma'])) $emergency_types[] = 'Trauma';
                                if (!empty($record['emergency_ob'])) $emergency_types[] = 'OB';
                                if (!empty($record['emergency_general'])) $emergency_types[] = 'General';
                                echo empty($emergency_types) ? ' empty' : '';
                            ?>">
                                <?php echo !empty($emergency_types) ? implode(', ', $emergency_types) : 'Not specified'; ?>
                            </div>
                        </div>
                        <?php if (!empty($record['emergency_medical_details'])): ?>
                        <div class="data-field">
                            <label>Medical Details</label>
                            <div class="value"><?php echo e($record['emergency_medical_details']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($record['emergency_trauma_details'])): ?>
                        <div class="data-field">
                            <label>Trauma Details</label>
                            <div class="value"><?php echo e($record['emergency_trauma_details']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($record['emergency_ob_details'])): ?>
                        <div class="data-field">
                            <label>OB Details</label>
                            <div class="value"><?php echo e($record['emergency_ob_details']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($record['emergency_general_details'])): ?>
                        <div class="data-field">
                            <label>General Details</label>
                            <div class="value"><?php echo e($record['emergency_general_details']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="data-field full-width">
                            <label>Care Management</label>
                            <div class="value<?php
                                $care_mgmt = $record['care_management'] ?? '';
                                $care_decoded = $care_mgmt ? json_decode($care_mgmt, true) : null;
                                $has_care = $care_decoded && is_array($care_decoded) && count($care_decoded) > 0;
                                echo !$has_care ? ' empty' : '';
                            ?>">
                                <?php
                                if ($has_care) {
                                    echo implode(', ', array_map('ucfirst', array_map('e', $care_decoded)));
                                } else {
                                    echo 'Not specified';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="data-field">
                            <label>O² (LPM via)</label>
                            <div class="value<?php echo empty($record['oxygen_lpm']) ? ' empty' : ''; ?>"><?php echo e($record['oxygen_lpm'] ?: 'Not specified'); ?></div>
                        </div>
                        <div class="data-field">
                            <label>Other Care</label>
                            <div class="value<?php echo empty($record['other_care']) ? ' empty' : ''; ?>"><?php echo e($record['other_care'] ?: 'Not specified'); ?></div>
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
                                    <div class="value<?php echo empty($record['initial_consciousness']) ? ' empty' : ''; ?>">
                                        <?php
                                        if (!empty($record['initial_consciousness'])) {
                                            $consciousness = json_decode($record['initial_consciousness'], true);
                                            if (is_array($consciousness)) {
                                                echo implode(', ', array_map('ucfirst', $consciousness));
                                            } else {
                                                echo ucfirst($record['initial_consciousness']);
                                            }
                                        } else {
                                            echo 'Not recorded';
                                        }
                                        ?>
                                    </div>
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
                                    <div class="value<?php echo empty($record['followup_consciousness']) ? ' empty' : ''; ?>">
                                        <?php
                                        if (!empty($record['followup_consciousness'])) {
                                            $consciousness = json_decode($record['followup_consciousness'], true);
                                            if (is_array($consciousness)) {
                                                echo implode(', ', array_map('ucfirst', $consciousness));
                                            } else {
                                                echo ucfirst($record['followup_consciousness']);
                                            }
                                        } else {
                                            echo 'Not recorded';
                                        }
                                        ?>
                                    </div>
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

                <!-- Incident Time (moved from patient info to emergency/care section) -->
                <div class="section-content">
                    <div class="data-grid">
                        <div class="data-field">
                            <label>Time of Incident</label>
                            <div class="value<?php echo empty($record['incident_time']) ? ' empty' : ''; ?>"><?php echo e($record['incident_time'] ?: 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Hospital & Transport Information -->
                <div class="section-header">Hospital & Transport</div>
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

                <!-- Narrative Report -->
                <?php if (!empty($record['narrative_report'])): ?>
                <div class="section-header">Narrative Report</div>
                <div class="section-content">
                    <div style="font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 0.8125rem; line-height: 1.8; color: #1f2937; white-space: pre-wrap;"><?php echo e($record['narrative_report']); ?></div>
                </div>
                <?php endif; ?>

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
                <button data-action="print" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print
                </button>
                <a href="../api/export_pdf.php?id=<?php echo $record['id']; ?>" class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
                <a href="edit_record.php?id=<?php echo $record['id']; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        // Configure Notiflix - Corporate Design Colors
        Notiflix.Notify.init({
            width: '320px',
            position: 'right-top',
            distance: '15px',
            timeout: 3000,
            fontSize: '14px',
            fontFamily: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif',
            cssAnimationStyle: 'from-right',
            borderRadius: '6px',
            success: {
                background: '#00875A',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            failure: {
                background: '#DE350B',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            warning: {
                background: '#FF8B00',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            info: {
                background: '#0052CC',
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

        // CSP-compliant event delegation
        document.addEventListener('click', function(e) {
            var el = e.target.closest('[data-action]');
            if (!el) return;
            if (el.getAttribute('data-action') === 'print') { window.print(); }
        });
    </script>
</body>
</html>
