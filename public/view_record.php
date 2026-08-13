<?php
/**
 * View Pre-Hospital Care Record Details
 * Modern SaaS / Corporate Design — matches records.php aesthetic
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
        if ($record[$field] === '00:00:00' || $record[$field] === null || $record[$field] === '' ||
            $record[$field] === '0000-00-00 00:00:00') {
            $record[$field] = '';
        }
    }
}

/**
 * Format a 24-hour time (HH:MM:SS) to 12-hour format with AM/PM
 */
function format_time_12h($time) {
    if (empty($time)) return '';
    $ts = strtotime($time);
    if ($ts === false) return htmlspecialchars((string)$time);
    return date('g:i A', $ts);
}

// Clean up date-only fields
$dateFields = ['date_of_birth', 'lmp', 'edc'];
foreach ($dateFields as $field) {
    if (isset($record[$field])) {
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
    <title>View Record <?php echo e($record['form_number']); ?> - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/records-style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <style>
        /* ===== VIEW RECORD PAGE-SPECIFIC STYLES ===== */
        /* Inherits records-style.css design tokens */

        .vr-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Record Header Card */
        .vr-header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            padding: 1.75rem 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .vr-header-left {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
        }
        .vr-header-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .vr-header-info h1 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 0.25rem 0;
            letter-spacing: -0.015em;
        }
        .vr-header-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.8125rem;
            opacity: 0.9;
        }
        .vr-header-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .vr-header-meta i { font-size: 0.75rem; }

        .vr-header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.75rem;
        }
        .vr-status-badge {
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(255,255,255,0.2);
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Action Bar */
        .vr-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .vr-actions .btn-primary,
        .vr-actions .btn-ghost,
        .vr-actions .btn-danger {
            font-size: 0.8125rem;
            padding: 0.55rem 1.15rem;
        }

        /* Section Card */
        .vr-section {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xs);
            margin-bottom: 1.25rem;
            overflow: hidden;
        }
        .vr-section-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-100);
        }
        .vr-section-header i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        .vr-section-header .vr-section-count {
            margin-left: auto;
            font-size: 0.6875rem;
            color: var(--gray-500);
            font-weight: 500;
            letter-spacing: 0.03em;
        }
        .vr-section-body {
            padding: 1.25rem 1.5rem;
        }

        /* Data Grid */
        .vr-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem 1.5rem;
        }
        .vr-grid--2col { grid-template-columns: repeat(2, 1fr); }
        .vr-grid--4col { grid-template-columns: repeat(4, 1fr); }
        .vr-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-50);
        }
        .vr-item--full { grid-column: 1 / -1; }
        .vr-item:last-child { border-bottom: none; }
        .vr-label {
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .vr-value {
            font-size: 0.875rem;
            color: var(--gray-800);
            font-weight: 500;
            word-break: break-word;
        }
        .vr-value--empty {
            color: var(--gray-400);
            font-style: italic;
            font-weight: 400;
        }
        .vr-value--multiline {
            white-space: pre-wrap;
            line-height: 1.7;
        }
        .vr-value--bold {
            font-weight: 700;
            color: var(--gray-900);
        }

        /* Chips & Badges */
        .vr-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }
        .vr-chip {
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.6875rem;
            font-weight: 600;
        }
        .vr-chip--emergency { background: var(--danger-light); color: var(--danger); }
        .vr-chip--care { background: var(--primary-light); color: var(--primary); }
        .vr-chip--info { background: #eff6ff; color: #2563eb; }
        .vr-chip--purple { background: var(--purple-light); color: var(--purple); }

        /* Vital Signs Cards */
        .vr-vitals-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
        }
        .vr-vital-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
        }
        .vr-vital-card-header {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            padding: 0.75rem 1.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--purple);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #e9d5ff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .vr-vital-card-header i { font-size: 0.85rem; }
        .vr-vital-card-body {
            padding: 1rem 1.25rem;
        }
        .vr-vital-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-50);
            gap: 1rem;
        }
        .vr-vital-row:last-child { border-bottom: none; }
        .vr-vital-label {
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            flex-shrink: 0;
        }
        .vr-vital-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            text-align: right;
            font-variant-numeric: tabular-nums;
        }
        .vr-vital-value--empty {
            color: var(--gray-400);
            font-style: italic;
            font-weight: 400;
        }

        /* Injury Cards */
        .vr-injuries-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1rem;
        }
        .vr-injury-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--danger);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow-xs);
        }
        .vr-injury-card-header {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
            padding-bottom: 0.625rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .vr-injury-card-header i { color: var(--danger); }
        .vr-injury-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
        }
        .vr-injury-item {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .vr-injury-label {
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .vr-injury-value {
            font-size: 0.8125rem;
            color: var(--gray-800);
            font-weight: 500;
        }
        .vr-injury-item--full { grid-column: 1 / -1; }

        /* Narrative Block */
        .vr-narrative {
            background: #fafbfc;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            font-size: 0.875rem;
            line-height: 1.8;
            color: var(--gray-700);
            white-space: pre-wrap;
            font-family: var(--font-sans);
        }

        /* Record Meta Footer */
        .vr-footer {
            display: flex;
            justify-content: center;
            gap: 2rem;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            color: var(--gray-500);
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }
        .vr-footer span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .vr-footer i { color: var(--gray-400); font-size: 0.7rem; }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff;
                padding: 0;
                margin: 0;
                font-size: 10px;
            }
            .vr-container {
                max-width: 100%;
                margin: 0;
            }
            .vr-header {
                background: #4f46e5 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-radius: 0;
                padding: 1rem 1.5rem;
                margin-bottom: 0.5rem;
                page-break-after: avoid;
            }
            .vr-header-info h1 { font-size: 1rem; }
            .vr-header-meta { font-size: 0.65rem; }
            .vr-section {
                box-shadow: none;
                border-radius: 0;
                border: none;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 0;
                page-break-inside: avoid;
            }
            .vr-section-header {
                font-size: 0.6rem;
                padding: 0.5rem 1rem;
            }
            .vr-section-body { padding: 0.5rem 1rem; }
            .vr-grid { gap: 0.3rem 0.75rem; }
            .vr-label { font-size: 0.5rem; }
            .vr-value { font-size: 0.65rem; }
            .vr-vitals-grid { gap: 0.5rem; }
            .vr-vital-card-header { padding: 0.4rem 0.75rem; font-size: 0.6rem; }
            .vr-vital-card-body { padding: 0.5rem 0.75rem; }
            .vr-vital-label { font-size: 0.5rem; }
            .vr-vital-value { font-size: 0.65rem; }
            .vr-injury-card { padding: 0.5rem 0.75rem; }
            .vr-injury-card-header { font-size: 0.65rem; margin-bottom: 0.4rem; }
            .vr-injury-label { font-size: 0.5rem; }
            .vr-injury-value { font-size: 0.65rem; }
            .vr-narrative { font-size: 0.65rem; padding: 0.5rem 0.75rem; }
            .vr-status-badge { font-size: 0.55rem; padding: 0.15rem 0.5rem; }
            @page {
                size: legal;
                margin: 0.3in 0.4in;
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .vr-grid { grid-template-columns: repeat(2, 1fr); }
            .vr-grid--4col { grid-template-columns: repeat(2, 1fr); }
            .vr-grid--2col { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .vr-header {
                flex-direction: column;
                padding: 1.25rem;
            }
            .vr-header-right { align-items: flex-start; }
            .vr-grid { grid-template-columns: 1fr; }
            .vr-grid--4col { grid-template-columns: 1fr; }
            .vr-vitals-grid { grid-template-columns: 1fr; }
            .vr-injuries-list { grid-template-columns: 1fr; }
            .vr-actions { flex-direction: column; }
            .vr-actions .btn { width: 100%; justify-content: center; }
            .vr-footer { flex-direction: column; gap: 0.5rem; align-items: center; }
        }
    </style>
    <link href="css/view-record-redesign.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content view-record-page">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- Page Header -->
            <div class="page-header-inline no-print view-record-page-header">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon"><i class="bi bi-file-earmark-medical"></i></span>
                        Patient Care Record
                    </h1>
                    <p class="page-subtitle">
                        Review the complete pre-hospital response record.
                    </p>
                </div>
                <div class="header-actions">
                    <a href="records.php" class="btn-ghost">
                        <i class="bi bi-arrow-left"></i> Back to Records
                    </a>
                    <button type="button" class="btn-ghost" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <a href="../api/export_pdf.php?id=<?php echo $record['id']; ?>" class="btn-danger">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </a>
                    <a href="edit_record.php?id=<?php echo $record['id']; ?>" class="btn-primary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                </div>
            </div>

            <div class="vr-container">
                <!-- Record Header Card -->
                <div class="vr-header">
                    <div class="vr-header-left">
                        <div class="vr-header-icon">
                            <i class="bi bi-clipboard2-pulse"></i>
                        </div>
                        <div class="vr-header-info">
                            <span class="vr-header-kicker">Field documentation / ResQ-Link EMS</span>
                            <h1>Pre-Hospital Care Record</h1>
                            <p class="vr-header-patient"><?php echo e($record['patient_name'] ?: 'Patient not identified'); ?></p>
                            <div class="vr-header-meta">
                                <span><i class="bi bi-hash"></i> Form No: <strong><?php echo e($record['form_number']); ?></strong></span>
                                <span><i class="bi bi-calendar3"></i> <?php echo date('M d, Y', strtotime($record['created_at'])); ?></span>
                                <?php if ($record['form_date'] && $record['form_date'] !== '0000-00-00'): ?>
                                <span><i class="bi bi-journal-medical"></i> <?php echo date('M d, Y', strtotime($record['form_date'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="vr-header-right">
                        <span class="vr-status-badge">
                            <?php
                            $status_icon = $record['status'] === 'completed' ? 'bi-check-circle-fill' : ($record['status'] === 'draft' ? 'bi-pencil-fill' : 'bi-archive-fill');
                            ?>
                            <i class="bi <?php echo $status_icon; ?>"></i>
                            <?php echo ucfirst($record['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-info-circle"></i> Basic Information
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid vr-grid--4col">
                            <div class="vr-item">
                                <span class="vr-label">Form Date</span>
                                <span class="vr-value"><?php echo ($record['form_date'] && $record['form_date'] !== '0000-00-00') ? date('F d, Y', strtotime($record['form_date'])) : '<span class="vr-value--empty">N/A</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Departure Time</span>
                                <span class="vr-value<?php echo empty($record['departure_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['departure_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Arrival Time</span>
                                <span class="vr-value<?php echo empty($record['arrival_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['arrival_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Vehicle Used</span>
                                <span class="vr-value">
                                    <?php
                                    $vehicleDisplay = 'N/A';
                                    if ($record['vehicle_used']) {
                                        if ($record['vehicle_used'] === 'ambulance' && !empty($record['vehicle_details'])) {
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
                                </span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Driver Name</span>
                                <span class="vr-value<?php echo empty($record['driver_name']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['driver_name'] ?: 'Not specified'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scene Information -->
                <div class="vr-section vr-section--half">
                    <div class="vr-section-header">
                        <i class="bi bi-geo-alt"></i> Scene Information
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid vr-grid--2col">
                            <div class="vr-item">
                                <span class="vr-label">Arrival Scene Location</span>
                                <span class="vr-value<?php echo empty($record['arrival_scene_location']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['arrival_scene_location'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Arrival Scene Time</span>
                                <span class="vr-value<?php echo empty($record['arrival_scene_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['arrival_scene_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Departure Scene Location</span>
                                <span class="vr-value<?php echo empty($record['departure_scene_location']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['departure_scene_location'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Departure Scene Time</span>
                                <span class="vr-value<?php echo empty($record['departure_scene_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['departure_scene_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Information -->
                <div class="vr-section vr-section--half">
                    <div class="vr-section-header">
                        <i class="bi bi-person-badge"></i> Patient Information
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid">
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Patient Name</span>
                                <span class="vr-value vr-value--bold"><?php echo e($record['patient_name']); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Date of Birth</span>
                                <span class="vr-value"><?php echo ($record['date_of_birth'] && $record['date_of_birth'] !== '0000-00-00') ? date('F d, Y', strtotime($record['date_of_birth'])) : '<span class="vr-value--empty">N/A</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Age</span>
                                <span class="vr-value"><?php echo e($record['age']); ?> years old</span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Gender</span>
                                <span class="vr-value"><?php echo ucfirst($record['gender']); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Civil Status</span>
                                <span class="vr-value<?php echo empty($record['civil_status']) ? ' vr-value--empty' : ''; ?>"><?php echo ucfirst($record['civil_status'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Occupation</span>
                                <span class="vr-value<?php echo empty($record['occupation']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['occupation'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Address</span>
                                <span class="vr-value<?php echo empty($record['address']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['address'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Zone</span>
                                <span class="vr-value<?php echo empty($record['zone']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['zone'] ?: 'Not specified'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informant Details -->
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-person-lines-fill"></i> Informant Details
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid">
                            <div class="vr-item">
                                <span class="vr-label">Informant Name</span>
                                <span class="vr-value<?php echo empty($record['informant_name']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['informant_name'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Contact Number</span>
                                <span class="vr-value<?php echo empty($record['contact_number']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['contact_number'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Informant Address</span>
                                <span class="vr-value<?php echo empty($record['informant_address']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['informant_address'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Arrival Type</span>
                                <span class="vr-value<?php echo empty($record['arrival_type']) ? ' vr-value--empty' : ''; ?>"><?php echo ucfirst($record['arrival_type'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Call Arrival Time</span>
                                <span class="vr-value<?php echo empty($record['call_arrival_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['call_arrival_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Relationship to Victim</span>
                                <span class="vr-value<?php echo empty($record['relationship_victim']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['relationship_victim'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Persons Present Upon Arrival</span>
                                <span class="vr-value<?php
                                $persons_present = $record['persons_present'];
                                $has_persons = false;
                                if ($persons_present) {
                                    $decoded = json_decode($persons_present, true);
                                    $has_persons = $decoded && is_array($decoded) && count($decoded) > 0;
                                }
                                echo !$has_persons ? ' vr-value--empty' : '';
                                ?>">
                                    <?php
                                    if ($persons_present) {
                                        $decoded = json_decode($persons_present, true);
                                        if ($decoded && is_array($decoded)) {
                                            echo '<div class="vr-chips">';
                                            foreach ($decoded as $person) {
                                                echo '<span class="vr-chip vr-chip--info">' . e($person) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo e($persons_present);
                                        }
                                    } else {
                                        echo 'Not specified';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Type & Care Management -->
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-exclamation-triangle"></i> Emergency Type & Care Management
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid">
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Emergency Types</span>
                                <span class="vr-value<?php
                                    $emergency_types = [];
                                    if (!empty($record['emergency_medical'])) $emergency_types[] = 'Medical';
                                    if (!empty($record['emergency_trauma'])) $emergency_types[] = 'Trauma';
                                    if (!empty($record['emergency_ob'])) $emergency_types[] = 'OB';
                                    if (!empty($record['emergency_general'])) $emergency_types[] = 'General';
                                    echo empty($emergency_types) ? ' vr-value--empty' : '';
                                ?>">
                                    <?php if (!empty($emergency_types)): ?>
                                        <div class="vr-chips">
                                            <?php foreach ($emergency_types as $etype): ?>
                                                <span class="vr-chip vr-chip--emergency"><?php echo $etype; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($record['emergency_medical_details'])): ?>
                            <div class="vr-item">
                                <span class="vr-label">Medical Details</span>
                                <span class="vr-value"><?php echo e($record['emergency_medical_details']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($record['emergency_trauma_details'])): ?>
                            <div class="vr-item">
                                <span class="vr-label">Trauma Details</span>
                                <span class="vr-value"><?php echo e($record['emergency_trauma_details']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($record['emergency_ob_details'])): ?>
                            <div class="vr-item">
                                <span class="vr-label">OB Details</span>
                                <span class="vr-value"><?php echo e($record['emergency_ob_details']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($record['emergency_general_details'])): ?>
                            <div class="vr-item">
                                <span class="vr-label">General Details</span>
                                <span class="vr-value"><?php echo e($record['emergency_general_details']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Care Management</span>
                                <span class="vr-value<?php
                                    $care_mgmt = $record['care_management'] ?? '';
                                    $care_decoded = $care_mgmt ? json_decode($care_mgmt, true) : null;
                                    $has_care = $care_decoded && is_array($care_decoded) && count($care_decoded) > 0;
                                    echo !$has_care ? ' vr-value--empty' : '';
                                ?>">
                                    <?php
                                    if ($has_care) {
                                        echo '<div class="vr-chips">';
                                        foreach ($care_decoded as $care) {
                                            echo '<span class="vr-chip vr-chip--care">' . ucfirst(e($care)) . '</span>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo 'Not specified';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">O² (LPM via)</span>
                                <span class="vr-value<?php echo empty($record['oxygen_lpm']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['oxygen_lpm'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Other Care</span>
                                <span class="vr-value<?php echo empty($record['other_care']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['other_care'] ?: 'Not specified'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vital Signs -->
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-heart-pulse"></i> Vital Signs
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-vitals-grid">
                            <!-- Initial Assessment -->
                            <div class="vr-vital-card">
                                <div class="vr-vital-card-header">
                                    <i class="bi bi-clipboard2-pulse"></i> Initial Assessment
                                </div>
                                <div class="vr-vital-card-body">
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Blood Pressure</span>
                                        <span class="vr-vital-value<?php echo empty($record['initial_bp']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo e($record['initial_bp'] ?: 'Not recorded'); ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Temperature</span>
                                        <span class="vr-vital-value<?php echo empty($record['initial_temp']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo $record['initial_temp'] ? $record['initial_temp'] . '&deg;C' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Pulse Rate</span>
                                        <span class="vr-vital-value<?php echo empty($record['initial_pulse']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo $record['initial_pulse'] ? $record['initial_pulse'] . ' BPM' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">SPO2</span>
                                        <span class="vr-vital-value<?php echo empty($record['initial_spo2']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo $record['initial_spo2'] ? $record['initial_spo2'] . '%' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Consciousness</span>
                                        <span class="vr-vital-value<?php echo empty($record['initial_consciousness']) ? ' vr-vital-value--empty' : ''; ?>">
                                            <?php
                                            if (!empty($record['initial_consciousness'])) {
                                                $consciousness = json_decode($record['initial_consciousness'], true);
                                                if (is_array($consciousness)) {
                                                    echo '<div class="vr-chips">';
                                                    foreach ($consciousness as $c) {
                                                        echo '<span class="vr-chip vr-chip--care">' . ucfirst($c) . '</span>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo ucfirst($record['initial_consciousness']);
                                                }
                                            } else {
                                                echo 'Not recorded';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Follow-up Assessment -->
                            <div class="vr-vital-card">
                                <div class="vr-vital-card-header">
                                    <i class="bi bi-arrow-repeat"></i> Follow-up Assessment
                                </div>
                                <div class="vr-vital-card-body">
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Blood Pressure</span>
                                        <span class="vr-vital-value<?php echo empty($record['followup_bp']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo e($record['followup_bp'] ?: 'Not recorded'); ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Temperature</span>
                                        <span class="vr-vital-value<?php echo empty($record['followup_temp']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo $record['followup_temp'] ? $record['followup_temp'] . '&deg;C' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Pulse Rate</span>
                                        <span class="vr-vital-value<?php echo empty($record['followup_pulse']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo $record['followup_pulse'] ? $record['followup_pulse'] . ' BPM' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">SPO2</span>
                                        <span class="vr-vital-value<?php echo empty($record['followup_spo2']) ? ' vr-vital-value--empty' : ''; ?>"><?php echo $record['followup_spo2'] ? $record['followup_spo2'] . '%' : 'Not recorded'; ?></span>
                                    </div>
                                    <div class="vr-vital-row">
                                        <span class="vr-vital-label">Consciousness</span>
                                        <span class="vr-vital-value<?php echo empty($record['followup_consciousness']) ? ' vr-vital-value--empty' : ''; ?>">
                                            <?php
                                            if (!empty($record['followup_consciousness'])) {
                                                $consciousness = json_decode($record['followup_consciousness'], true);
                                                if (is_array($consciousness)) {
                                                    echo '<div class="vr-chips">';
                                                    foreach ($consciousness as $c) {
                                                        echo '<span class="vr-chip vr-chip--care">' . ucfirst($c) . '</span>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo ucfirst($record['followup_consciousness']);
                                                }
                                            } else {
                                                echo 'Not recorded';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Injuries -->
                <?php if (!empty($injuries)): ?>
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-bandaid"></i> Injuries
                        <span class="vr-section-count"><?php echo count($injuries); ?> total</span>
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-injuries-list">
                            <?php foreach ($injuries as $injury): ?>
                                <div class="vr-injury-card">
                                    <div class="vr-injury-card-header">
                                        <i class="bi bi-exclamation-diamond"></i> Injury #<?php echo $injury['injury_number']; ?>
                                    </div>
                                    <div class="vr-injury-details">
                                        <div class="vr-injury-item">
                                            <span class="vr-injury-label">Injury Type</span>
                                            <span class="vr-injury-value">
                                                <span class="vr-chip vr-chip--emergency"><?php echo ucfirst($injury['injury_type']); ?></span>
                                            </span>
                                        </div>
                                        <div class="vr-injury-item">
                                            <span class="vr-injury-label">Body View</span>
                                            <span class="vr-injury-value">
                                                <span class="vr-chip vr-chip--info"><?php echo ucfirst($injury['body_view']); ?> View</span>
                                            </span>
                                        </div>
                                        <div class="vr-injury-item vr-injury-item--full">
                                            <span class="vr-injury-label">Notes</span>
                                            <span class="vr-injury-value"><?php echo e($injury['notes'] ?: 'No additional notes'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Incident Time -->
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-clock-history"></i> Incident Time
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid">
                            <div class="vr-item">
                                <span class="vr-label">Time of Incident</span>
                                <span class="vr-value<?php echo empty($record['incident_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['incident_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hospital & Transport Information -->
                <div class="vr-section vr-section--half">
                    <div class="vr-section-header">
                        <i class="bi bi-hospital"></i> Hospital & Transport
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid vr-grid--4col">
                            <div class="vr-item">
                                <span class="vr-label">Arrival Hospital Name</span>
                                <span class="vr-value<?php echo empty($record['arrival_hospital_name']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['arrival_hospital_name'] ?: 'Not specified'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Arrival Hospital Time</span>
                                <span class="vr-value<?php echo empty($record['arrival_hospital_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['arrival_hospital_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Departure Hospital Time</span>
                                <span class="vr-value<?php echo empty($record['departure_hospital_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['departure_hospital_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Arrival Station Time</span>
                                <span class="vr-value<?php echo empty($record['arrival_station_time']) ? ' vr-value--empty' : ''; ?>"><?php echo format_time_12h($record['arrival_station_time']) ?: '<span class="vr-value--empty">Not specified</span>'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Information -->
                <div class="vr-section vr-section--half">
                    <div class="vr-section-header">
                        <i class="bi bi-people"></i> Team Information
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid">
                            <div class="vr-item">
                                <span class="vr-label">Team Leader</span>
                                <span class="vr-value<?php echo empty($record['team_leader']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['team_leader'] ?: 'Not assigned'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Data Recorder</span>
                                <span class="vr-value<?php echo empty($record['data_recorder']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['data_recorder'] ?: 'Not assigned'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">Logistic</span>
                                <span class="vr-value<?php echo empty($record['logistic']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['logistic'] ?: 'Not assigned'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">1st Aider</span>
                                <span class="vr-value<?php echo empty($record['first_aider']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['first_aider'] ?: 'Not assigned'); ?></span>
                            </div>
                            <div class="vr-item">
                                <span class="vr-label">2nd Aider</span>
                                <span class="vr-value<?php echo empty($record['second_aider']) ? ' vr-value--empty' : ''; ?>"><?php echo e($record['second_aider'] ?: 'Not assigned'); ?></span>
                            </div>
                            <?php if (!empty($record['team_leader_notes'])): ?>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label">Team Leader Notes</span>
                                <span class="vr-value vr-value--multiline"><?php echo nl2br(e($record['team_leader_notes'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($record['waiver_required']) || !empty($record['waiver_attachment'])): ?>
                <!-- Refusal Waiver -->
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-shield-check"></i> Refusal Waiver
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-grid">
                            <?php
                            $waiver_has_file = !empty($record['waiver_attachment']);
                            $waiver_is_required = !empty($record['waiver_required']);
                            $waiver_status_icon = ($waiver_is_required && $waiver_has_file) ? 'bi-check-circle-fill' : ($waiver_is_required ? 'bi-exclamation-circle-fill' : 'bi-archive-fill');
                            $waiver_status_class = ($waiver_is_required && $waiver_has_file) ? 'is-complete' : ($waiver_is_required ? 'is-missing' : 'is-inactive');
                            ?>
                            <div class="vr-item">
                                <span class="vr-label"><i class="bi bi-shield-check"></i> Waiver Status</span>
                                <span class="vr-value vr-waiver-status <?php echo $waiver_status_class; ?>">
                                    <i class="bi <?php echo $waiver_status_icon; ?>"></i>
                                    <?php if ($waiver_is_required && $waiver_has_file): ?>Signed document on file<?php elseif ($waiver_is_required): ?>Required — document missing<?php else: ?>Inactive — document retained for audit<?php endif; ?>
                                </span>
                            </div>
                            <?php if ($waiver_has_file): ?>
                            <div class="vr-item">
                                <span class="vr-label"><i class="bi bi-file-earmark-check"></i> Signed Waiver</span>
                                <span class="vr-value"><a class="vr-waiver-link" href="../api/serve_file.php?file=../<?php echo e($record['waiver_attachment']); ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open signed waiver document</a></span>
                            </div>
                            <?php endif; ?>
                            <div class="vr-item vr-item--full">
                                <span class="vr-label"><i class="bi bi-info-circle"></i> Notice</span>
                                <span class="vr-value vr-waiver-notice"><i class="bi bi-shield-lock"></i> The uploaded signed paper waiver is the authoritative refusal document.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Narrative Report -->
                <?php if (!empty($record['narrative_report'])): ?>
                <div class="vr-section vr-section--full">
                    <div class="vr-section-header">
                        <i class="bi bi-journal-text"></i> Narrative Report
                    </div>
                    <div class="vr-section-body">
                        <div class="vr-narrative"><?php echo e($record['narrative_report']); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Record Meta Footer -->
                <div class="vr-section vr-section--full">
                    <div class="vr-footer">
                        <span><i class="bi bi-calendar-plus"></i> Created: <?php echo date('F d, Y g:i A', strtotime($record['created_at'])); ?></span>
                        <span><i class="bi bi-calendar-check"></i> Last Updated: <?php echo date('F d, Y g:i A', strtotime($record['updated_at'])); ?></span>
                    </div>
                </div>
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
            fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif',
            cssAnimationStyle: 'from-right',
            borderRadius: '8px',
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
