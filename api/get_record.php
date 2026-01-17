<?php
/**
 * Get Record for Modal View
 * Returns HTML content for displaying in modal
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

// Set JSON header
header('Content-Type: application/json');

// Get record ID
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($record_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid record ID'
    ]);
    exit;
}

try {
    // Get record details
    $sql = "SELECT * FROM prehospital_forms WHERE id = ?";
    $stmt = db_query($sql, [$record_id]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode([
            'success' => false,
            'message' => 'Record not found'
        ]);
        exit;
    }

    // Get injuries for this record
    $injury_sql = "SELECT * FROM injuries WHERE form_id = ? ORDER BY injury_number";
    $injury_stmt = db_query($injury_sql, [$record_id]);
    $injuries = $injury_stmt->fetchAll();

    // Clean up date and time fields
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

    // Start building HTML
    ob_start();
    ?>
    <div class="modal-record-view">
        <style>
            .modal-record-view {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .record-header {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 6px;
                margin-bottom: 20px;
                border-left: 4px solid #1a4d8f;
            }
            .record-header h2 {
                color: #1a4d8f;
                font-size: 1.5rem;
                margin-bottom: 10px;
                font-weight: 600;
            }
            .record-header .meta {
                color: #6c757d;
                font-size: 0.9rem;
            }
            .section-title {
                background-color: #1a4d8f;
                color: white;
                padding: 10px 15px;
                margin: 20px 0 15px 0;
                font-weight: 600;
                font-size: 0.95rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .data-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }
            .data-item {
                padding: 12px;
                background-color: #f8f9fa;
                border-left: 3px solid #dee2e6;
            }
            .data-item label {
                font-size: 0.75rem;
                color: #6c757d;
                text-transform: uppercase;
                font-weight: 600;
                display: block;
                margin-bottom: 5px;
            }
            .data-item .value {
                font-size: 0.95rem;
                color: #212529;
                font-weight: 500;
            }
            .data-item .value.empty {
                color: #adb5bd;
                font-style: italic;
            }
            .vital-box {
                background-color: #f8f9fa;
                border: 2px solid #dee2e6;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 15px;
            }
            .vital-box h4 {
                color: #1a4d8f;
                font-size: 1rem;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #dee2e6;
                font-weight: 600;
            }
            .injury-card {
                background-color: #ffffff;
                border: 1px solid #dee2e6;
                border-left: 4px solid #1a4d8f;
                padding: 15px;
                margin-bottom: 15px;
                border-radius: 4px;
            }
            .injury-card-header {
                font-size: 1rem;
                font-weight: 600;
                color: #1a4d8f;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 1px solid #e9ecef;
            }
            .badge-status {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 0.75rem;
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
                background-color: #0d6efd;
                color: white;
            }
        </style>

        <!-- Header -->
        <div class="record-header">
            <h2>PRE-HOSPITAL CARE RECORD</h2>
            <div class="meta">
                <strong>Form No:</strong> <?php echo e($record['form_number']); ?> |
                <strong>Created:</strong> <?php echo date('M d, Y g:i A', strtotime($record['created_at'])); ?> |
                <strong>Status:</strong> <span class="badge-status badge-success"><?php echo ucfirst($record['status']); ?></span>
            </div>
        </div>

        <!-- Basic Information -->
        <div class="section-title">Basic Information</div>
        <div class="data-row">
            <div class="data-item">
                <label>Form Date</label>
                <div class="value"><?php echo $record['form_date'] ? date('F d, Y', strtotime($record['form_date'])) : 'N/A'; ?></div>
            </div>
            <div class="data-item">
                <label>Departure Time</label>
                <div class="value<?php echo empty($record['departure_time']) ? ' empty' : ''; ?>"><?php echo e($record['departure_time'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Arrival Time</label>
                <div class="value<?php echo empty($record['arrival_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_time'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Vehicle Used</label>
                <div class="value"><?php echo ucfirst($record['vehicle_used'] ?: 'N/A'); ?></div>
            </div>
            <div class="data-item">
                <label>Driver Name</label>
                <div class="value<?php echo empty($record['driver_name']) ? ' empty' : ''; ?>"><?php echo e($record['driver_name'] ?: 'Not specified'); ?></div>
            </div>
        </div>

        <!-- Scene Information -->
        <div class="section-title">Scene Information</div>
        <div class="data-row">
            <div class="data-item">
                <label>Arrival Scene Location</label>
                <div class="value<?php echo empty($record['arrival_scene_location']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_scene_location'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Arrival Scene Time</label>
                <div class="value<?php echo empty($record['arrival_scene_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_scene_time'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Departure Scene Location</label>
                <div class="value<?php echo empty($record['departure_scene_location']) ? ' empty' : ''; ?>"><?php echo e($record['departure_scene_location'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Departure Scene Time</label>
                <div class="value<?php echo empty($record['departure_scene_time']) ? ' empty' : ''; ?>"><?php echo e($record['departure_scene_time'] ?: 'Not specified'); ?></div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="section-title">Patient Information</div>
        <div class="data-row">
            <div class="data-item" style="grid-column: 1 / -1;">
                <label>Patient Name</label>
                <div class="value"><?php echo e($record['patient_name']); ?></div>
            </div>
            <div class="data-item">
                <label>Date of Birth</label>
                <div class="value"><?php echo $record['date_of_birth'] ? date('F d, Y', strtotime($record['date_of_birth'])) : 'N/A'; ?></div>
            </div>
            <div class="data-item">
                <label>Age</label>
                <div class="value"><?php echo e($record['age']); ?> years old</div>
            </div>
            <div class="data-item">
                <label>Gender</label>
                <div class="value"><?php echo ucfirst($record['gender']); ?></div>
            </div>
            <div class="data-item">
                <label>Civil Status</label>
                <div class="value<?php echo empty($record['civil_status']) ? ' empty' : ''; ?>"><?php echo ucfirst($record['civil_status'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Occupation</label>
                <div class="value<?php echo empty($record['occupation']) ? ' empty' : ''; ?>"><?php echo e($record['occupation'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item" style="grid-column: 1 / -1;">
                <label>Address</label>
                <div class="value<?php echo empty($record['address']) ? ' empty' : ''; ?>"><?php echo e($record['address'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Zone</label>
                <div class="value<?php echo empty($record['zone']) ? ' empty' : ''; ?>"><?php echo e($record['zone'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item" style="grid-column: 1 / -1;">
                <label>Place of Incident</label>
                <div class="value<?php echo empty($record['place_of_incident']) ? ' empty' : ''; ?>"><?php echo e($record['place_of_incident'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Zone Landmark</label>
                <div class="value<?php echo empty($record['zone_landmark']) ? ' empty' : ''; ?>"><?php echo e($record['zone_landmark'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Incident Time</label>
                <div class="value<?php echo empty($record['incident_time']) ? ' empty' : ''; ?>"><?php echo e($record['incident_time'] ?: 'Not specified'); ?></div>
            </div>
        </div>

        <!-- Vital Signs -->
        <div class="section-title">Vital Signs</div>
        <div class="row">
            <div class="col-md-6">
                <div class="vital-box">
                    <h4>Initial Assessment</h4>
                    <div class="data-item">
                        <label>Blood Pressure</label>
                        <div class="value<?php echo empty($record['initial_bp']) ? ' empty' : ''; ?>"><?php echo e($record['initial_bp'] ?: 'Not recorded'); ?></div>
                    </div>
                    <div class="data-item">
                        <label>Temperature</label>
                        <div class="value<?php echo empty($record['initial_temp']) ? ' empty' : ''; ?>"><?php echo $record['initial_temp'] ? $record['initial_temp'] . '°C' : 'Not recorded'; ?></div>
                    </div>
                    <div class="data-item">
                        <label>Pulse Rate</label>
                        <div class="value<?php echo empty($record['initial_pulse']) ? ' empty' : ''; ?>"><?php echo $record['initial_pulse'] ? $record['initial_pulse'] . ' BPM' : 'Not recorded'; ?></div>
                    </div>
                    <div class="data-item">
                        <label>SPO2</label>
                        <div class="value<?php echo empty($record['initial_spo2']) ? ' empty' : ''; ?>"><?php echo $record['initial_spo2'] ? $record['initial_spo2'] . '%' : 'Not recorded'; ?></div>
                    </div>
                    <div class="data-item">
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
            <div class="col-md-6">
                <div class="vital-box">
                    <h4>Follow-up Assessment</h4>
                    <div class="data-item">
                        <label>Blood Pressure</label>
                        <div class="value<?php echo empty($record['followup_bp']) ? ' empty' : ''; ?>"><?php echo e($record['followup_bp'] ?: 'Not recorded'); ?></div>
                    </div>
                    <div class="data-item">
                        <label>Temperature</label>
                        <div class="value<?php echo empty($record['followup_temp']) ? ' empty' : ''; ?>"><?php echo $record['followup_temp'] ? $record['followup_temp'] . '°C' : 'Not recorded'; ?></div>
                    </div>
                    <div class="data-item">
                        <label>Pulse Rate</label>
                        <div class="value<?php echo empty($record['followup_pulse']) ? ' empty' : ''; ?>"><?php echo $record['followup_pulse'] ? $record['followup_pulse'] . ' BPM' : 'Not recorded'; ?></div>
                    </div>
                    <div class="data-item">
                        <label>SPO2</label>
                        <div class="value<?php echo empty($record['followup_spo2']) ? ' empty' : ''; ?>"><?php echo $record['followup_spo2'] ? $record['followup_spo2'] . '%' : 'Not recorded'; ?></div>
                    </div>
                    <div class="data-item">
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

        <!-- Injuries -->
        <?php if (!empty($injuries)): ?>
        <div class="section-title">Injuries Marked (<?php echo count($injuries); ?>)</div>
        <?php foreach ($injuries as $injury): ?>
            <div class="injury-card">
                <div class="injury-card-header">Injury #<?php echo $injury['injury_number']; ?></div>
                <div class="data-row">
                    <div class="data-item">
                        <label>Injury Type</label>
                        <div class="value">
                            <span class="badge-status badge-danger"><?php echo ucfirst($injury['injury_type']); ?></span>
                        </div>
                    </div>
                    <div class="data-item">
                        <label>Body Location</label>
                        <div class="value">
                            <span class="badge-status badge-info"><?php echo e($injury['body_part'] ?? (ucfirst($injury['body_view']) . ' View')); ?></span>
                        </div>
                    </div>
                    <div class="data-item" style="grid-column: 1 / -1;">
                        <label>Notes</label>
                        <div class="value"><?php echo e($injury['notes'] ?: 'No additional notes'); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Hospital Information -->
        <div class="section-title">Hospital Information</div>
        <div class="data-row">
            <div class="data-item">
                <label>Arrival Hospital Name</label>
                <div class="value<?php echo empty($record['arrival_hospital_name']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_hospital_name'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Arrival Hospital Time</label>
                <div class="value<?php echo empty($record['arrival_hospital_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_hospital_time'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Departure Hospital Time</label>
                <div class="value<?php echo empty($record['departure_hospital_time']) ? ' empty' : ''; ?>"><?php echo e($record['departure_hospital_time'] ?: 'Not specified'); ?></div>
            </div>
            <div class="data-item">
                <label>Arrival Station Time</label>
                <div class="value<?php echo empty($record['arrival_station_time']) ? ' empty' : ''; ?>"><?php echo e($record['arrival_station_time'] ?: 'Not specified'); ?></div>
            </div>
        </div>

        <!-- Team Information -->
        <div class="section-title">Team Information</div>
        <div class="data-row">
            <div class="data-item">
                <label>Team Leader</label>
                <div class="value<?php echo empty($record['team_leader']) ? ' empty' : ''; ?>"><?php echo e($record['team_leader'] ?: 'Not assigned'); ?></div>
            </div>
            <div class="data-item">
                <label>Data Recorder</label>
                <div class="value<?php echo empty($record['data_recorder']) ? ' empty' : ''; ?>"><?php echo e($record['data_recorder'] ?: 'Not assigned'); ?></div>
            </div>
            <div class="data-item">
                <label>Logistic</label>
                <div class="value<?php echo empty($record['logistic']) ? ' empty' : ''; ?>"><?php echo e($record['logistic'] ?: 'Not assigned'); ?></div>
            </div>
            <div class="data-item">
                <label>1st Aider</label>
                <div class="value<?php echo empty($record['first_aider']) ? ' empty' : ''; ?>"><?php echo e($record['first_aider'] ?: 'Not assigned'); ?></div>
            </div>
            <div class="data-item">
                <label>2nd Aider</label>
                <div class="value<?php echo empty($record['second_aider']) ? ' empty' : ''; ?>"><?php echo e($record['second_aider'] ?: 'Not assigned'); ?></div>
            </div>
            <?php if ($record['team_leader_notes']): ?>
            <div class="data-item" style="grid-column: 1 / -1;">
                <label>Team Leader Notes</label>
                <div class="value"><?php echo nl2br(e($record['team_leader_notes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Record Information -->
        <div class="section-title">Record Information</div>
        <div class="data-row">
            <div class="data-item">
                <label>Created At</label>
                <div class="value"><?php echo date('F d, Y g:i A', strtotime($record['created_at'])); ?></div>
            </div>
            <div class="data-item">
                <label>Last Updated</label>
                <div class="value"><?php echo date('F d, Y g:i A', strtotime($record['updated_at'])); ?></div>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    error_log("Error fetching record: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching the record'
    ]);
}
