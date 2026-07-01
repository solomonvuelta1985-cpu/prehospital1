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
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit;
}

try {
    if (!can_access_record($record_id)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $sql = "SELECT * FROM prehospital_forms WHERE id = ?";
    $stmt = db_query($sql, [$record_id]);
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Database error']); exit; }
    $record = $stmt->fetch();

    if (!$record) { echo json_encode(['success' => false, 'message' => 'Record not found']); exit; }

    decrypt_record_fields($record);

    $injury_sql = "SELECT * FROM injuries WHERE form_id = ? ORDER BY injury_number";
    $injury_stmt = db_query($injury_sql, [$record_id]);
    $injuries = $injury_stmt ? $injury_stmt->fetchAll() : [];

    $dateTimeFields = ['departure_time','arrival_time','arrival_scene_time','departure_scene_time','arrival_hospital_time','departure_hospital_time','arrival_station_time','incident_time','call_arrival_time','initial_time','followup_time','ob_delivery_time','endorsement_datetime'];
    foreach ($dateTimeFields as $field) {
        if (isset($record[$field]) && ($record[$field] === '00:00:00' || $record[$field] === null || $record[$field] === '' || $record[$field] === '0000-00-00 00:00:00')) $record[$field] = '';
    }
    $dateFields = ['form_date','date_of_birth','ob_lmp','ob_edc'];
    foreach ($dateFields as $field) {
        if (isset($record[$field]) && ($record[$field] === '0000-00-00' || $record[$field] === null || $record[$field] === '' || $record[$field] === '0000-00-00 00:00:00')) $record[$field] = '';
    }

    $val = function($field, $format = null) use ($record) {
        $v = $record[$field] ?? '';
        if (empty($v)) return '<span class="mv-empty">—</span>';
        if ($format === 'date') return date('M d, Y', strtotime($v));
        if ($format === 'datetime') return date('M d, Y g:i A', strtotime($v));
        if ($format === 'time') return $v;
        return e($v);
    };

    $status_class = ['completed'=>'completed','draft'=>'draft','archived'=>'archived'][$record['status']] ?? 'draft';

    $emergencyTypes = [];
    if ($record['emergency_medical']) $emergencyTypes[] = 'Medical' . ($record['emergency_medical_details'] ? ' — ' . $record['emergency_medical_details'] : '');
    if ($record['emergency_trauma']) $emergencyTypes[] = 'Trauma' . ($record['emergency_trauma_details'] ? ' — ' . $record['emergency_trauma_details'] : '');
    if ($record['emergency_ob']) $emergencyTypes[] = 'OB' . ($record['emergency_ob_details'] ? ' — ' . $record['emergency_ob_details'] : '');
    if ($record['emergency_general']) $emergencyTypes[] = 'General' . ($record['emergency_general_details'] ? ' — ' . $record['emergency_general_details'] : '');

    $careItems = [];
    if ($record['care_management']) {
        $decoded = json_decode($record['care_management'], true);
        if (is_array($decoded)) $careItems = array_map('ucfirst', $decoded);
    }
    if (!empty($record['other_care'])) $careItems[] = $record['other_care'];

    $creator_name = 'Unknown';
    if (!empty($record['created_by'])) {
        $user_stmt = db_query("SELECT full_name FROM users WHERE id = ?", [$record['created_by']]);
        if ($user_stmt) { $u = $user_stmt->fetch(); if ($u) $creator_name = $u['full_name']; }
    }

    $hasInjuries = !empty($injuries);
    $hasNarrative = !empty($record['narrative_report']);

    ob_start();
    ?>
    <div class="modal-record-view">
        <div class="mv-header">
            <div class="mv-header-avatar"><span class="mv-avatar-text"><?php echo mb_strtoupper(mb_substr($record['patient_name'] ?: '?', 0, 1)); ?></span></div>
            <div class="mv-header-info">
                <div class="mv-header-topline">
                    <span class="mv-form-number">#<?php echo e($record['form_number']); ?></span>
                    <span class="modal-record-status <?php echo $status_class; ?>">
                        <?php if ($status_class === 'completed'): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
                        <?php if ($status_class === 'draft'): ?><i class="bi bi-pencil-fill"></i><?php endif; ?>
                        <?php echo ucfirst($record['status']); ?>
                    </span>
                </div>
                <h2 class="mv-patient-name"><?php echo e($record['patient_name'] ?: 'Unknown Patient'); ?></h2>
                <div class="mv-header-meta">
                    <span><i class="bi bi-geo-alt-fill"></i> <?php echo e($record['place_of_incident'] ?: '—'); ?></span>
                    <span><i class="bi bi-calendar3"></i> <?php echo $val('form_date', 'date'); ?></span>
                    <span><i class="bi bi-person"></i> <?php echo e($creator_name); ?></span>
                </div>
            </div>
            <div class="mv-header-actions">
                <button type="button" class="mv-btn-icon" onclick="window.open('view_record.php?id=<?php echo $record_id; ?>','_blank')"><i class="bi bi-arrows-fullscreen" title="Full Details"></i></button>
                <button type="button" class="mv-btn-icon mv-btn-icon--edit" onclick="window.location.href='edit_record.php?id=<?php echo $record_id; ?>'"><i class="bi bi-pencil-fill" title="Edit"></i></button>
            </div>
        </div>

        <div class="mv-tabs">
            <button class="mv-tab active" data-mv-tab="overview"><i class="bi bi-grid-fill"></i> Overview</button>
            <button class="mv-tab" data-mv-tab="vitals"><i class="bi bi-heart-pulse-fill"></i> Vitals</button>
            <?php if ($hasInjuries): ?>
            <button class="mv-tab" data-mv-tab="injuries"><i class="bi bi-bandaid-fill"></i> Injuries <span class="mv-tab-badge"><?php echo count($injuries); ?></span></button>
            <?php endif; ?>
            <?php if ($hasNarrative): ?>
            <button class="mv-tab" data-mv-tab="narrative"><i class="bi bi-journal-text"></i> Narrative</button>
            <?php endif; ?>
        </div>

        <div class="mv-tab-content active" id="mv-tab-overview">
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-truck"></i> Incident Details</div>
                <div class="mv-card-grid">
                    <div class="mv-field"><span class="mv-field-label">Vehicle Used</span><span class="mv-field-value"><?php echo $record['vehicle_used'] ? ucfirst(e($record['vehicle_used'])) : '<span class="mv-empty">—</span>'; ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Driver</span><span class="mv-field-value"><?php echo e($record['driver_name'] ?: '<span class="mv-empty">—</span>'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Departure</span><span class="mv-field-value"><?php echo $val('departure_time', 'time'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Arrival</span><span class="mv-field-value"><?php echo $val('arrival_time', 'time'); ?></span></div>
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Place of Incident</span><span class="mv-field-value"><?php echo $val('place_of_incident'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Scene Arrival</span><span class="mv-field-value"><?php echo $val('arrival_scene_time', 'time'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Scene Departure</span><span class="mv-field-value"><?php echo $val('departure_scene_time', 'time'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Incident Time</span><span class="mv-field-value"><?php echo $val('incident_time', 'time'); ?></span></div>
                </div>
            </div>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-person-fill"></i> Patient Information</div>
                <div class="mv-card-grid">
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Full Name</span><span class="mv-field-value" style="font-weight:600;"><?php echo e($record['patient_name'] ?: '-'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Age</span><span class="mv-field-value"><?php echo e($record['age']); ?> <?php echo ($record['age_unit'] ?? 'years') === 'months' ? 'months' : 'years'; ?> old</span></div>
                    <div class="mv-field"><span class="mv-field-label">Gender</span><span class="mv-field-value"><?php echo $record['gender'] ? ucfirst((string)$record['gender']) : '<span class="mv-empty">—</span>'; ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Date of Birth</span><span class="mv-field-value"><?php echo $val('date_of_birth', 'date'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Civil Status</span><span class="mv-field-value"><?php echo $record['civil_status'] ? ucfirst(e($record['civil_status'])) : '<span class="mv-empty">—</span>'; ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Occupation</span><span class="mv-field-value"><?php echo $val('occupation'); ?></span></div>
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Address</span><span class="mv-field-value"><?php echo $val('address'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Zone</span><span class="mv-field-value"><?php echo $val('zone'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Landmark</span><span class="mv-field-value"><?php echo $val('zone_landmark'); ?></span></div>
                </div>
            </div>
            <?php if ($emergencyTypes || $careItems): ?>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-clipboard2-heart-fill"></i> Clinical Assessment</div>
                <div class="mv-card-grid">
                    <?php if ($emergencyTypes): ?>
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Type of Emergency</span><span class="mv-field-value"><span class="mv-chips"><?php foreach ($emergencyTypes as $et) echo '<span class="mv-chip mv-chip--emergency">'.e($et).'</span>'; ?></span></span></div>
                    <?php endif; ?>
                    <?php if ($careItems): ?>
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Care Provided</span><span class="mv-field-value"><span class="mv-chips"><?php foreach ($careItems as $ci) echo '<span class="mv-chip mv-chip--care">'.e($ci).'</span>'; ?></span></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-hospital-fill"></i> Hospital & Team</div>
                <div class="mv-card-grid">
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Arrival Hospital</span><span class="mv-field-value"><?php echo $val('arrival_hospital_name'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Arrival Hospital</span><span class="mv-field-value"><?php echo $val('arrival_hospital_time', 'time'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Departure Hospital</span><span class="mv-field-value"><?php echo $val('departure_hospital_time', 'time'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Team Leader</span><span class="mv-field-value"><?php echo $val('team_leader'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Data Recorder</span><span class="mv-field-value"><?php echo $val('data_recorder'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">Logistic</span><span class="mv-field-value"><?php echo $val('logistic'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">1st Aider</span><span class="mv-field-value"><?php echo $val('first_aider'); ?></span></div>
                    <div class="mv-field"><span class="mv-field-label">2nd Aider</span><span class="mv-field-value"><?php echo $val('second_aider'); ?></span></div>
                    <?php if (!empty($record['team_leader_notes'])): ?>
                    <div class="mv-field mv-field--full"><span class="mv-field-label">Team Leader Notes</span><span class="mv-field-value"><?php echo nl2br(e($record['team_leader_notes'])); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mv-tab-content" id="mv-tab-vitals">
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-heart-pulse-fill"></i> Initial Assessment</div>
                <div class="mv-vitals-grid-new">
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--bp"><i class="bi bi-droplet-fill"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Blood Pressure</span><span class="mv-vital-value"><?php echo $record['initial_bp'] ? e($record['initial_bp']).' <small>mmHg</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--temp"><i class="bi bi-thermometer-half"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Temperature</span><span class="mv-vital-value"><?php echo $record['initial_temp'] ? e($record['initial_temp']).' <small>°C</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--pulse"><i class="bi bi-heart-fill"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Pulse Rate</span><span class="mv-vital-value"><?php echo $record['initial_pulse'] ? e($record['initial_pulse']).' <small>BPM</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--spo2"><i class="bi bi-lungs-fill"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">SPO2</span><span class="mv-vital-value"><?php echo $record['initial_spo2'] ? e($record['initial_spo2']).' <small>%</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item mv-vital-item--full">
                        <div class="mv-vital-icon mv-vital-icon--consciousness"><i class="bi bi-brain"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Consciousness</span><span class="mv-vital-value"><?php
                            if (!empty($record['initial_consciousness'])) { $c = json_decode($record['initial_consciousness'], true); echo is_array($c) ? implode(', ', array_map('ucfirst', $c)) : ucfirst((string)$record['initial_consciousness']); }
                            else echo '<span class="mv-empty">—</span>';
                        ?></span></div>
                    </div>
                </div>
            </div>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-heart-pulse-fill"></i> Follow-up Assessment</div>
                <div class="mv-vitals-grid-new">
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--bp"><i class="bi bi-droplet-fill"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Blood Pressure</span><span class="mv-vital-value"><?php echo $record['followup_bp'] ? e($record['followup_bp']).' <small>mmHg</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--temp"><i class="bi bi-thermometer-half"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Temperature</span><span class="mv-vital-value"><?php echo $record['followup_temp'] ? e($record['followup_temp']).' <small>°C</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--pulse"><i class="bi bi-heart-fill"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Pulse Rate</span><span class="mv-vital-value"><?php echo $record['followup_pulse'] ? e($record['followup_pulse']).' <small>BPM</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item">
                        <div class="mv-vital-icon mv-vital-icon--spo2"><i class="bi bi-lungs-fill"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">SPO2</span><span class="mv-vital-value"><?php echo $record['followup_spo2'] ? e($record['followup_spo2']).' <small>%</small>' : '<span class="mv-empty">—</span>'; ?></span></div>
                    </div>
                    <div class="mv-vital-item mv-vital-item--full">
                        <div class="mv-vital-icon mv-vital-icon--consciousness"><i class="bi bi-brain"></i></div>
                        <div class="mv-vital-info"><span class="mv-vital-label">Consciousness</span><span class="mv-vital-value"><?php
                            if (!empty($record['followup_consciousness'])) { $c = json_decode($record['followup_consciousness'], true); echo is_array($c) ? implode(', ', array_map('ucfirst', $c)) : ucfirst((string)$record['followup_consciousness']); }
                            else echo '<span class="mv-empty">—</span>';
                        ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($hasInjuries): ?>
        <div class="mv-tab-content" id="mv-tab-injuries">
            <?php foreach ($injuries as $injury): ?>
            <div class="mv-injury-card">
                <div class="mv-injury-card-number">#<?php echo $injury['injury_number']; ?></div>
                <div class="mv-injury-card-body">
                    <div class="mv-injury-card-chips">
                        <span class="mv-chip mv-chip--emergency"><?php echo ucfirst((string)$injury['injury_type']); ?></span>
                        <span class="mv-chip mv-chip--info"><?php echo e($injury['body_part'] ?? (ucfirst((string)($injury['body_view'] ?? '')) . ' View')); ?></span>
                    </div>
                    <?php if (!empty($injury['notes'])): ?>
                    <p class="mv-injury-card-note"><?php echo e($injury['notes']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($hasNarrative): ?>
        <div class="mv-tab-content" id="mv-tab-narrative">
            <div class="mv-narrative"><?php echo nl2br(e($record['narrative_report'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="mv-footer">
            <span><i class="bi bi-clock-history"></i> Created <?php echo $val('created_at', 'datetime'); ?></span>
            <span>&middot;</span>
            <span>Updated <?php echo time_ago($record['updated_at'] ?? $record['created_at']); ?></span>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    error_log("Error fetching record: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching the record']);
}