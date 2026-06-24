<?php
/**
 * Get Record for Modal View
 * Returns clean HTML content for displaying in modal
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
    // Ownership check
    if (!can_access_record($record_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);
        exit;
    }

    // Get record details
    $sql = "SELECT * FROM prehospital_forms WHERE id = ?";
    $stmt = db_query($sql, [$record_id]);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode([
            'success' => false,
            'message' => 'Record not found'
        ]);
        exit;
    }

    // Decrypt sensitive fields
    decrypt_record_fields($record);

    // Get injuries for this record
    $injury_sql = "SELECT * FROM injuries WHERE form_id = ? ORDER BY injury_number";
    $injury_stmt = db_query($injury_sql, [$record_id]);
    $injuries = $injury_stmt ? $injury_stmt->fetchAll() : [];

    // Clean up date and time fields
    $dateTimeFields = [
        'departure_time', 'arrival_time', 'arrival_scene_time', 'departure_scene_time',
        'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time',
        'incident_time', 'call_arrival_time', 'initial_time', 'followup_time',
        'ob_delivery_time', 'endorsement_datetime'
    ];

    foreach ($dateTimeFields as $field) {
        if (isset($record[$field])) {
            if ($record[$field] === '00:00:00' || $record[$field] === null || $record[$field] === '' ||
                $record[$field] === '0000-00-00 00:00:00') {
                $record[$field] = '';
            }
        }
    }

    $dateFields = ['form_date', 'date_of_birth', 'ob_lmp', 'ob_edc'];
    foreach ($dateFields as $field) {
        if (isset($record[$field])) {
            if ($record[$field] === '0000-00-00' || $record[$field] === null || $record[$field] === '' ||
                $record[$field] === '0000-00-00 00:00:00') {
                $record[$field] = '';
            }
        }
    }

    // Helper for empty values
    $val = function($field, $format = null) use ($record) {
        $v = $record[$field] ?? '';
        if (empty($v)) return '<span class="mv-empty">—</span>';
        if ($format === 'date') return date('M d, Y', strtotime($v));
        if ($format === 'datetime') return date('M d, Y g:i A', strtotime($v));
        if ($format === 'time') return $v;
        return e($v);
    };

    $status_class = ['completed'=>'completed','draft'=>'draft','archived'=>'archived'][$record['status']] ?? 'draft';

    // Emergency types
    $emergencyTypes = [];
    if ($record['emergency_medical']) {
        $d = $record['emergency_medical_details'] ? ' — ' . $record['emergency_medical_details'] : '';
        $emergencyTypes[] = 'Medical' . $d;
    }
    if ($record['emergency_trauma']) {
        $d = $record['emergency_trauma_details'] ? ' — ' . $record['emergency_trauma_details'] : '';
        $emergencyTypes[] = 'Trauma' . $d;
    }
    if ($record['emergency_ob']) {
        $d = $record['emergency_ob_details'] ? ' — ' . $record['emergency_ob_details'] : '';
        $emergencyTypes[] = 'OB' . $d;
    }
    if ($record['emergency_general']) {
        $d = $record['emergency_general_details'] ? ' — ' . $record['emergency_general_details'] : '';
        $emergencyTypes[] = 'General' . $d;
    }

    // Care items
    $careItems = [];
    if ($record['care_management']) {
        $decoded = json_decode($record['care_management'], true);
        if (is_array($decoded)) $careItems = array_map('ucfirst', $decoded);
    }
    if (!empty($record['other_care'])) $careItems[] = $record['other_care'];

    // Get creator name
    $creator_name = 'Unknown';
    if (!empty($record['created_by'])) {
        $user_stmt = db_query("SELECT full_name FROM users WHERE id = ?", [$record['created_by']]);
        if ($user_stmt) {
            $u = $user_stmt->fetch();
            if ($u) $creator_name = $u['full_name'];
        }
    }

    ob_start();
    ?>
    <div class="modal-record-view">
        <!-- Header -->
        <div class="mv-header">
            <div class="mv-header-top">
                <div>
                    <span class="mv-form-number">#<?php echo e($record['form_number']); ?></span>
                    <h2 class="mv-patient-name"><?php echo e($record['patient_name'] ?: 'Unknown Patient'); ?></h2>
                </div>
                <span class="modal-record-status <?php echo $status_class; ?>">
                    <?php if ($status_class === 'completed'): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
                    <?php if ($status_class === 'draft'): ?><i class="bi bi-pencil-fill"></i><?php endif; ?>
                    <?php echo ucfirst($record['status']); ?>
                </span>
            </div>
            <div class="mv-header-meta">
                <span><i class="bi bi-calendar3"></i> <?php echo $val('form_date', 'date'); ?></span>
                <span><i class="bi bi-person"></i> <?php echo e($creator_name); ?></span>
                <span><i class="bi bi-clock"></i> <?php echo time_ago($record['updated_at'] ?? $record['created_at']); ?></span>
            </div>
        </div>

        <!-- Basic + Scene Info -->
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-info-circle-fill"></i> Incident Details</div>
            <div class="mv-grid">
                <div class="mv-item"><span class="mv-label">Vehicle Used</span><span class="mv-value"><?php echo $record['vehicle_used'] ? ucfirst(e($record['vehicle_used'])) : '<span class="mv-empty">—</span>'; ?></span></div>
                <div class="mv-item"><span class="mv-label">Driver</span><span class="mv-value"><?php echo e($record['driver_name'] ?: '-'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Departure Time</span><span class="mv-value"><?php echo $val('departure_time', 'time'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Arrival Time</span><span class="mv-value"><?php echo $val('arrival_time', 'time'); ?></span></div>
                <div class="mv-item mv-item--full"><span class="mv-label">Place of Incident</span><span class="mv-value"><?php echo $val('place_of_incident'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Arrival Scene Time</span><span class="mv-value"><?php echo $val('arrival_scene_time', 'time'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Departure Scene Time</span><span class="mv-value"><?php echo $val('departure_scene_time', 'time'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Incident Time</span><span class="mv-value"><?php echo $val('incident_time', 'time'); ?></span></div>
            </div>
        </div>

        <!-- Patient Info -->
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-person-fill"></i> Patient Information</div>
            <div class="mv-grid">
                <div class="mv-item mv-item--full"><span class="mv-label">Full Name</span><span class="mv-value" style="font-weight:600;"><?php echo e($record['patient_name'] ?: '-'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Age</span><span class="mv-value"><?php echo e($record['age']); ?> <?php echo ($record['age_unit'] ?? 'years') === 'months' ? 'months' : 'years'; ?> old</span></div>
                <div class="mv-item"><span class="mv-label">Gender</span><span class="mv-value"><?php echo $record['gender'] ? ucfirst($record['gender']) : '-'; ?></span></div>
                <div class="mv-item"><span class="mv-label">Date of Birth</span><span class="mv-value"><?php echo $val('date_of_birth', 'date'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Civil Status</span><span class="mv-value"><?php echo $record['civil_status'] ? ucfirst(e($record['civil_status'])) : '<span class="mv-empty">—</span>'; ?></span></div>
                <div class="mv-item"><span class="mv-label">Occupation</span><span class="mv-value"><?php echo $val('occupation'); ?></span></div>
                <div class="mv-item mv-item--full"><span class="mv-label">Address</span><span class="mv-value"><?php echo $val('address'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Zone</span><span class="mv-value"><?php echo $val('zone'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Landmark</span><span class="mv-value"><?php echo $val('zone_landmark'); ?></span></div>
            </div>
        </div>

        <!-- Emergency Types + Care -->
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-clipboard2-heart-fill"></i> Clinical Assessment</div>
            <div class="mv-grid">
                <?php if ($emergencyTypes): ?>
                <div class="mv-item mv-item--full">
                    <span class="mv-label">Type of Emergency Call</span>
                    <span class="mv-value"><span class="mv-chips"><?php foreach ($emergencyTypes as $et) echo '<span class="mv-chip mv-chip--emergency">' . e($et) . '</span>'; ?></span></span>
                </div>
                <?php endif; ?>
                <?php if ($careItems): ?>
                <div class="mv-item mv-item--full">
                    <span class="mv-label">Care Management</span>
                    <span class="mv-value"><span class="mv-chips"><?php foreach ($careItems as $ci) echo '<span class="mv-chip mv-chip--care">' . e($ci) . '</span>'; ?></span></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vital Signs -->
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-heart-pulse-fill"></i> Vital Signs</div>
            <div class="mv-vitals">
                <div class="mv-vital-card">
                    <div class="mv-vital-card-title">Initial Assessment</div>
                    <div class="mv-grid">
                        <div class="mv-item"><span class="mv-label">Blood Pressure</span><span class="mv-value"><?php echo $record['initial_bp'] ? e($record['initial_bp']) : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item"><span class="mv-label">Temperature</span><span class="mv-value"><?php echo $record['initial_temp'] ? e($record['initial_temp']) . '°C' : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item"><span class="mv-label">Pulse Rate</span><span class="mv-value"><?php echo $record['initial_pulse'] ? e($record['initial_pulse']) . ' BPM' : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item"><span class="mv-label">SPO2</span><span class="mv-value"><?php echo $record['initial_spo2'] ? e($record['initial_spo2']) . '%' : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item mv-item--full"><span class="mv-label">Consciousness</span><span class="mv-value"><?php
                            if (!empty($record['initial_consciousness'])) {
                                $c = json_decode($record['initial_consciousness'], true);
                                echo is_array($c) ? implode(', ', array_map('ucfirst', $c)) : ucfirst($record['initial_consciousness']);
                            } else { echo '<span class="mv-empty">—</span>'; }
                        ?></span></div>
                    </div>
                </div>
                <div class="mv-vital-card">
                    <div class="mv-vital-card-title">Follow-up Assessment</div>
                    <div class="mv-grid">
                        <div class="mv-item"><span class="mv-label">Blood Pressure</span><span class="mv-value"><?php echo $record['followup_bp'] ? e($record['followup_bp']) : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item"><span class="mv-label">Temperature</span><span class="mv-value"><?php echo $record['followup_temp'] ? e($record['followup_temp']) . '°C' : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item"><span class="mv-label">Pulse Rate</span><span class="mv-value"><?php echo $record['followup_pulse'] ? e($record['followup_pulse']) . ' BPM' : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item"><span class="mv-label">SPO2</span><span class="mv-value"><?php echo $record['followup_spo2'] ? e($record['followup_spo2']) . '%' : '<span class="mv-empty">—</span>'; ?></span></div>
                        <div class="mv-item mv-item--full"><span class="mv-label">Consciousness</span><span class="mv-value"><?php
                            if (!empty($record['followup_consciousness'])) {
                                $c = json_decode($record['followup_consciousness'], true);
                                echo is_array($c) ? implode(', ', array_map('ucfirst', $c)) : ucfirst($record['followup_consciousness']);
                            } else { echo '<span class="mv-empty">—</span>'; }
                        ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Injuries -->
        <?php if (!empty($injuries)): ?>
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-bandaid-fill"></i> Injuries (<?php echo count($injuries); ?>)</div>
            <div class="mv-grid">
                <?php foreach ($injuries as $injury): ?>
                <div class="mv-item mv-injury-item">
                    <span class="mv-label">Injury #<?php echo $injury['injury_number']; ?></span>
                    <span class="mv-value">
                        <span class="mv-chip mv-chip--emergency"><?php echo ucfirst($injury['injury_type']); ?></span>
                        <span class="mv-chip mv-chip--info"><?php echo e($injury['body_part'] ?? (ucfirst($injury['body_view'] ?? '') . ' View')); ?></span>
                        <?php if ($injury['notes']): ?><span class="mv-injury-note"><?php echo e($injury['notes']); ?></span><?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hospital -->
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-hospital-fill"></i> Hospital & Team</div>
            <div class="mv-grid">
                <div class="mv-item mv-item--full"><span class="mv-label">Arrival Hospital</span><span class="mv-value"><?php echo $val('arrival_hospital_name'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Arrival Hospital Time</span><span class="mv-value"><?php echo $val('arrival_hospital_time', 'time'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Departure Hospital Time</span><span class="mv-value"><?php echo $val('departure_hospital_time', 'time'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Team Leader</span><span class="mv-value"><?php echo $val('team_leader'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Data Recorder</span><span class="mv-value"><?php echo $val('data_recorder'); ?></span></div>
                <div class="mv-item"><span class="mv-label">Logistic</span><span class="mv-value"><?php echo $val('logistic'); ?></span></div>
                <div class="mv-item"><span class="mv-label">1st Aider</span><span class="mv-value"><?php echo $val('first_aider'); ?></span></div>
                <div class="mv-item"><span class="mv-label">2nd Aider</span><span class="mv-value"><?php echo $val('second_aider'); ?></span></div>
                <?php if (!empty($record['team_leader_notes'])): ?>
                <div class="mv-item mv-item--full"><span class="mv-label">Team Leader Notes</span><span class="mv-value"><?php echo nl2br(e($record['team_leader_notes'])); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Narrative -->
        <?php if (!empty($record['narrative_report'])): ?>
        <div class="mv-section">
            <div class="mv-section-title"><i class="bi bi-journal-text"></i> Narrative Report</div>
            <div class="mv-narrative"><?php echo nl2br(e($record['narrative_report'])); ?></div>
        </div>
        <?php endif; ?>

        <!-- Record Meta -->
        <div class="mv-footer">
            <span>Created <?php echo $val('created_at', 'datetime'); ?></span>
            <span>&middot;</span>
            <span>Updated <?php echo time_ago($record['updated_at'] ?? $record['created_at']); ?></span>
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