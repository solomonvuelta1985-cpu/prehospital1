<?php
/**
 * Get Record for Modal View
 * Returns clean HTML content for displaying in modal
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

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
        if ($format === 'time') return date('g:i A', strtotime($v));
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
$hasPictures = !empty($record['patient_documentation']) || !empty($record['endorsement_attachment']) || !empty($record['waiver_attachment']);
$records_view = isset($_GET['context']) && $_GET['context'] === 'records';

/* Records uses a presentation-only renderer. The default renderer below is
   intentionally preserved for Drafts and any other existing callers. */
if ($records_view) {
    $records_empty = '<span class="records-modal-empty">&mdash;</span>';
    $records_value = function($field, $format = null) use ($record, $records_empty) {
        $value = $record[$field] ?? '';
        if (empty($value)) return $records_empty;
        if ($format === 'date') return date('M d, Y', strtotime($value));
        if ($format === 'datetime') return date('M d, Y g:i A', strtotime($value));
        if ($format === 'time') return date('g:i A', strtotime($value));
        return e($value);
    };
    $records_field = function($label, $value, $wide = false) {
        return '<div class="records-modal-field' . ($wide ? ' records-modal-field--wide' : '') . '">' .
            '<span class="records-modal-field-label">' . e($label) . '</span>' .
            '<div class="records-modal-field-value">' . $value . '</div></div>';
    };
    $records_chips = function($items, $class) {
        if (empty($items)) return '<span class="records-modal-empty">&mdash;</span>';
        $html = '<div class="records-modal-chips">';
        foreach ($items as $item) {
            $html .= '<span class="records-modal-chip ' . e($class) . '">' . e($item) . '</span>';
        }
        return $html . '</div>';
    };
    $status_icon = $status_class === 'completed' ? 'bi-check-circle-fill' : ($status_class === 'draft' ? 'bi-pencil-fill' : 'bi-archive-fill');
    $patient_name = $record['patient_name'] ?: 'Unknown Patient';

    ob_start();
    ?>
    <div class="records-modal-record">
        <section class="records-modal-patient-hero" aria-labelledby="recordsModalPatientName">
            <div class="records-modal-patient-avatar" aria-hidden="true"><?php echo mb_strtoupper(mb_substr($patient_name, 0, 1)); ?></div>
            <div class="records-modal-patient-main">
                <div class="records-modal-patient-meta">
                    <span class="records-modal-form-number">#<?php echo e($record['form_number']); ?></span>
                    <span class="records-modal-status records-modal-status--<?php echo $status_class; ?>"><i class="bi <?php echo $status_icon; ?>"></i> <?php echo e(ucfirst((string)$record['status'])); ?></span>
                </div>
                <h2 class="records-modal-patient-name" id="recordsModalPatientName"><?php echo e($patient_name); ?></h2>
                <div class="records-modal-patient-facts">
                    <span><i class="bi bi-calendar3"></i> <?php echo $records_value('form_date', 'date'); ?></span>
                    <span><i class="bi bi-geo-alt-fill"></i> <?php echo e($record['place_of_incident'] ?: 'Location not specified'); ?></span>
                    <span><i class="bi bi-person"></i> <?php echo e($creator_name); ?></span>
                </div>
            </div>
        </section>

        <div class="records-modal-tabs" role="tablist" aria-label="Record sections">
            <button class="records-modal-tab active" id="records-modal-tab-overview" type="button" role="tab" data-record-tab="overview" aria-controls="records-modal-panel-overview" aria-selected="true" tabindex="0"><i class="bi bi-grid-fill"></i><span>Overview</span></button>
            <button class="records-modal-tab" id="records-modal-tab-vitals" type="button" role="tab" data-record-tab="vitals" aria-controls="records-modal-panel-vitals" aria-selected="false" tabindex="-1"><i class="bi bi-heart-pulse-fill"></i><span>Vitals</span></button>
            <?php if ($hasInjuries): ?><button class="records-modal-tab" id="records-modal-tab-injuries" type="button" role="tab" data-record-tab="injuries" aria-controls="records-modal-panel-injuries" aria-selected="false" tabindex="-1"><i class="bi bi-bandaid-fill"></i><span>Injuries</span><b><?php echo count($injuries); ?></b></button><?php endif; ?>
            <?php if ($hasNarrative): ?><button class="records-modal-tab" id="records-modal-tab-narrative" type="button" role="tab" data-record-tab="narrative" aria-controls="records-modal-panel-narrative" aria-selected="false" tabindex="-1"><i class="bi bi-journal-text"></i><span>Narrative</span></button><?php endif; ?>
            <button class="records-modal-tab" id="records-modal-tab-pictures" type="button" role="tab" data-record-tab="pictures" aria-controls="records-modal-panel-pictures" aria-selected="false" tabindex="-1"><i class="bi bi-camera-fill"></i><span>Attachments</span></button>
        </div>

        <section class="records-modal-panel active" id="records-modal-panel-overview" role="tabpanel" aria-labelledby="records-modal-tab-overview" tabindex="0">
            <div class="records-modal-section-grid">
                <article class="records-modal-section-card">
                    <div class="records-modal-section-heading"><span class="records-modal-section-icon records-modal-section-icon--indigo"><i class="bi bi-truck"></i></span><div><h3>Incident & response</h3><p>Arrival, transport, and scene timeline</p></div></div>
                    <div class="records-modal-fields">
                        <?php echo $records_field('Vehicle Used', $record['vehicle_used'] ? ucfirst(e($record['vehicle_used'])) : $records_empty); ?>
                        <?php echo $records_field('Driver', $record['driver_name'] ? e($record['driver_name']) : $records_empty); ?>
                        <?php echo $records_field('Departure', $records_value('departure_time', 'time')); ?>
                        <?php echo $records_field('Arrival', $records_value('arrival_time', 'time')); ?>
                        <?php echo $records_field('Place of Incident', $records_value('place_of_incident'), true); ?>
                        <?php echo $records_field('Scene Arrival', $records_value('arrival_scene_time', 'time')); ?>
                        <?php echo $records_field('Scene Departure', $records_value('departure_scene_time', 'time')); ?>
                        <?php echo $records_field('Incident Time', $records_value('incident_time', 'time')); ?>
                    </div>
                </article>
                <article class="records-modal-section-card">
                    <div class="records-modal-section-heading"><span class="records-modal-section-icon records-modal-section-icon--teal"><i class="bi bi-person-fill"></i></span><div><h3>Patient profile</h3><p>Identity and demographic information</p></div></div>
                    <div class="records-modal-fields">
                        <?php echo $records_field('Full Name', $record['patient_name'] ? e($record['patient_name']) : $records_empty, true); ?>
                        <?php echo $records_field('Age', $record['age'] ? e($record['age']) . ' ' . (($record['age_unit'] ?? 'years') === 'months' ? 'months' : 'years') . ' old' : $records_empty); ?>
                        <?php echo $records_field('Gender', $record['gender'] ? e(ucfirst((string)$record['gender'])) : $records_empty); ?>
                        <?php echo $records_field('Date of Birth', $records_value('date_of_birth', 'date')); ?>
                        <?php echo $records_field('Civil Status', $record['civil_status'] ? ucfirst(e($record['civil_status'])) : $records_empty); ?>
                        <?php echo $records_field('Occupation', $records_value('occupation')); ?>
                        <?php echo $records_field('Address', $records_value('address'), true); ?>
                        <?php echo $records_field('Zone', $records_value('zone')); ?>
                        <?php echo $records_field('Landmark', $records_value('zone_landmark')); ?>
                    </div>
                </article>
                <?php if ($emergencyTypes || $careItems): ?>
                <article class="records-modal-section-card records-modal-section-card--wide">
                    <div class="records-modal-section-heading"><span class="records-modal-section-icon records-modal-section-icon--rose"><i class="bi bi-clipboard2-heart-fill"></i></span><div><h3>Clinical assessment</h3><p>Emergency classification and care delivered</p></div></div>
                    <div class="records-modal-fields">
                        <?php if ($emergencyTypes): ?><?php echo $records_field('Type of Emergency', $records_chips($emergencyTypes, 'records-modal-chip--emergency'), true); ?><?php endif; ?>
                        <?php if ($careItems): ?><?php echo $records_field('Care Provided', $records_chips($careItems, 'records-modal-chip--care'), true); ?><?php endif; ?>
                    </div>
                </article>
                <?php endif; ?>
                <article class="records-modal-section-card records-modal-section-card--wide">
                    <div class="records-modal-section-heading"><span class="records-modal-section-icon records-modal-section-icon--amber"><i class="bi bi-hospital-fill"></i></span><div><h3>Hospital & crew</h3><p>Handoff destination and response team</p></div></div>
                    <div class="records-modal-fields">
                        <?php echo $records_field('Arrival Hospital', $records_value('arrival_hospital_name'), true); ?>
                        <?php echo $records_field('Hospital Arrival', $records_value('arrival_hospital_time', 'time')); ?>
                        <?php echo $records_field('Hospital Departure', $records_value('departure_hospital_time', 'time')); ?>
                        <?php echo $records_field('Refusal Waiver', !empty($record['waiver_attachment']) ? '<a href="../api/serve_file.php?file=../' . e($record['waiver_attachment']) . '" target="_blank" rel="noopener">Signed document available</a>' : (!empty($record['waiver_required']) ? '<strong>Required, document missing</strong>' : 'Not applicable'), true); ?>
                        <?php echo $records_field('Team Leader', $records_value('team_leader')); ?>
                        <?php echo $records_field('Data Recorder', $records_value('data_recorder')); ?>
                        <?php echo $records_field('Logistic', $records_value('logistic')); ?>
                        <?php echo $records_field('1st Aider', $records_value('first_aider')); ?>
                        <?php echo $records_field('2nd Aider', $records_value('second_aider')); ?>
                        <?php if (!empty($record['team_leader_notes'])): ?><?php echo $records_field('Team Leader Notes', nl2br(e($record['team_leader_notes'])), true); ?><?php endif; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="records-modal-panel" id="records-modal-panel-vitals" role="tabpanel" aria-labelledby="records-modal-tab-vitals" tabindex="0">
            <div class="records-modal-vitals-compare">
                <?php foreach (['initial' => ['Initial assessment', 'records-modal-vitals-card--initial'], 'followup' => ['Follow-up assessment', 'records-modal-vitals-card--followup']] as $prefix => $vitalCard): ?>
                <article class="records-modal-vitals-card <?php echo $vitalCard[1]; ?>">
                    <div class="records-modal-vitals-heading"><span><i class="bi bi-heart-pulse-fill"></i></span><div><h3><?php echo $vitalCard[0]; ?></h3><p><?php echo $prefix === 'initial' ? 'First recorded patient status' : 'Most recent recorded patient status'; ?></p></div></div>
                    <div class="records-modal-vitals-grid">
                        <?php
                        $vitalDefinitions = [
                            ['Blood Pressure', $prefix . '_bp', 'mmHg', 'bi-droplet-fill', 'records-modal-vital--bp'],
                            ['Temperature', $prefix . '_temp', '°C', 'bi-thermometer-half', 'records-modal-vital--temp'],
                            ['Pulse Rate', $prefix . '_pulse', 'BPM', 'bi-heart-fill', 'records-modal-vital--pulse'],
                            ['SpO2', $prefix . '_spo2', '%', 'bi-lungs-fill', 'records-modal-vital--spo2']
                        ];
                        foreach ($vitalDefinitions as $vital):
                            $vitalValue = $record[$vital[1]] ?? '';
                        ?>
                        <div class="records-modal-vital-item"><span class="records-modal-vital-icon <?php echo $vital[4]; ?>"><i class="bi <?php echo $vital[3]; ?>"></i></span><div><span class="records-modal-vital-label"><?php echo $vital[0]; ?></span><strong><?php echo $vitalValue ? e($vitalValue) . ' <small>' . $vital[2] . '</small>' : $records_empty; ?></strong></div></div>
                        <?php endforeach; ?>
                        <?php $consciousness = $record[$prefix . '_consciousness'] ?? ''; if ($consciousness) { $decodedConsciousness = json_decode($consciousness, true); $consciousness = is_array($decodedConsciousness) ? implode(', ', array_map('ucfirst', $decodedConsciousness)) : ucfirst((string)$consciousness); } ?>
                        <div class="records-modal-vital-item records-modal-vital-item--wide"><span class="records-modal-vital-icon records-modal-vital--consciousness"><i class="bi bi-brain"></i></span><div><span class="records-modal-vital-label">Consciousness</span><strong><?php echo $consciousness ? e($consciousness) : $records_empty; ?></strong></div></div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($hasInjuries): ?>
        <section class="records-modal-panel" id="records-modal-panel-injuries" role="tabpanel" aria-labelledby="records-modal-tab-injuries" tabindex="0">
            <div class="records-modal-panel-heading"><span class="records-modal-panel-icon records-modal-section-icon--rose"><i class="bi bi-bandaid-fill"></i></span><div><h3>Injury mapping</h3><p><?php echo count($injuries); ?> documented injury <?php echo count($injuries) === 1 ? 'finding' : 'findings'; ?></p></div></div>
            <div class="records-modal-injury-list">
                <?php foreach ($injuries as $injury): ?>
                <article class="records-modal-injury-card"><span class="records-modal-injury-number">#<?php echo (int)$injury['injury_number']; ?></span><div><div class="records-modal-chips"><span class="records-modal-chip records-modal-chip--emergency"><?php echo ucfirst((string)$injury['injury_type']); ?></span><span class="records-modal-chip records-modal-chip--info"><?php echo e($injury['body_part'] ?? (ucfirst((string)($injury['body_view'] ?? '')) . ' View')); ?></span></div><?php if (!empty($injury['notes'])): ?><p><?php echo e($injury['notes']); ?></p><?php else: ?><p class="records-modal-empty-note">No additional notes recorded.</p><?php endif; ?></div></article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($hasNarrative): ?>
        <section class="records-modal-panel" id="records-modal-panel-narrative" role="tabpanel" aria-labelledby="records-modal-tab-narrative" tabindex="0">
            <div class="records-modal-panel-heading"><span class="records-modal-panel-icon records-modal-section-icon--indigo"><i class="bi bi-journal-text"></i></span><div><h3>Narrative report</h3><p>Clinical account recorded for this response</p></div></div>
            <article class="records-modal-narrative"><div class="records-modal-narrative-label"><i class="bi bi-quote"></i> Response narrative</div><div class="records-modal-narrative-text"><?php echo nl2br(e($record['narrative_report'])); ?></div></article>
        </section>
        <?php endif; ?>

        <section class="records-modal-panel" id="records-modal-panel-pictures" role="tabpanel" aria-labelledby="records-modal-tab-pictures" tabindex="0">
            <div class="records-modal-panel-heading"><span class="records-modal-panel-icon records-modal-section-icon--teal"><i class="bi bi-paperclip"></i></span><div><h3>Record attachments</h3><p>Patient documentation, endorsement, and waiver files</p></div></div>
            <div class="records-modal-attachment-grid">
                <?php if (!empty($record['patient_documentation'])): ?><article class="records-modal-attachment"><div class="records-modal-attachment-heading"><span><i class="bi bi-camera-fill"></i></span><div><h3>Patient documentation</h3><p>Patient image attached to the record</p></div></div><button type="button" class="records-modal-image-button" data-record-image="../api/serve_file.php?file=../<?php echo e($record['patient_documentation']); ?>" aria-label="Open patient documentation image"><img src="../api/serve_file.php?file=../<?php echo e($record['patient_documentation']); ?>" alt="Patient documentation"></button></article><?php endif; ?>
                <?php if (!empty($record['endorsement_attachment'])): ?><article class="records-modal-attachment"><div class="records-modal-attachment-heading"><span><i class="bi bi-file-earmark-check-fill"></i></span><div><h3>Endorsement attachment</h3><p>Handoff documentation attached to the record</p></div></div><button type="button" class="records-modal-image-button" data-record-image="../api/serve_file.php?file=../<?php echo e($record['endorsement_attachment']); ?>" aria-label="Open endorsement attachment image"><img src="../api/serve_file.php?file=../<?php echo e($record['endorsement_attachment']); ?>" alt="Endorsement attachment"></button></article><?php endif; ?>
                <?php if (!empty($record['waiver_attachment'])): ?><article class="records-modal-attachment"><div class="records-modal-attachment-heading"><span><i class="bi bi-shield-check"></i></span><div><h3>Signed refusal waiver</h3><p>Patient refusal document on file</p></div></div><button type="button" class="records-modal-image-button" data-record-image="../api/serve_file.php?file=../<?php echo e($record['waiver_attachment']); ?>" aria-label="Open signed refusal waiver"><img src="../api/serve_file.php?file=../<?php echo e($record['waiver_attachment']); ?>" alt="Signed refusal waiver"></button></article><?php endif; ?>
            </div>
            <?php if (!$hasPictures): ?><div class="records-modal-empty-attachments"><span><i class="bi bi-paperclip"></i></span><h3>No attachments</h3><p>No patient documentation, endorsement, or waiver images have been uploaded for this record.</p></div><?php endif; ?>
        </section>

        <footer class="records-modal-record-footer"><span><i class="bi bi-clock-history"></i> Created <?php echo $records_value('created_at', 'datetime'); ?></span><span>Updated <?php echo time_ago($record['updated_at'] ?? $record['created_at']); ?></span></footer>
    </div>
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

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
                        <?php echo e(ucfirst((string)$record['status'])); ?>
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
            <button class="mv-tab" data-mv-tab="pictures"><i class="bi bi-camera-fill"></i> Pictures</button>
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
                    <div class="mv-field"><span class="mv-field-label">Gender</span><span class="mv-field-value"><?php echo $record['gender'] ? e(ucfirst((string)$record['gender'])) : '<span class="mv-empty">—</span>'; ?></span></div>
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
                            if (!empty($record['initial_consciousness'])) { $c = json_decode($record['initial_consciousness'], true); echo is_array($c) ? e(implode(', ', array_map('ucfirst', $c))) : e(ucfirst((string)$record['initial_consciousness'])); }
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
                            if (!empty($record['followup_consciousness'])) { $c = json_decode($record['followup_consciousness'], true); echo is_array($c) ? e(implode(', ', array_map('ucfirst', $c))) : e(ucfirst((string)$record['followup_consciousness'])); }
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

        <div class="mv-tab-content" id="mv-tab-pictures">
            <?php if (!empty($record['patient_documentation'])): ?>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-camera-fill"></i> Patient Documentation</div>
                <div class="mv-pictures-wrapper">
                    <img src="../api/serve_file.php?file=../<?php echo e($record['patient_documentation']); ?>" alt="Patient Documentation" class="mv-picture-img" onclick="openModalPicture(this.src)">
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($record['endorsement_attachment'])): ?>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-file-earmark-check-fill"></i> Endorsement Attachment</div>
                <div class="mv-pictures-wrapper">
                    <img src="../api/serve_file.php?file=../<?php echo e($record['endorsement_attachment']); ?>" alt="Endorsement Attachment" class="mv-picture-img" onclick="openModalPicture(this.src)">
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($record['waiver_attachment'])): ?>
            <div class="mv-card">
                <div class="mv-card-header"><i class="bi bi-shield-check"></i> Signed Refusal Waiver</div>
                <div class="mv-pictures-wrapper">
                    <img src="../api/serve_file.php?file=../<?php echo e($record['waiver_attachment']); ?>" alt="Signed Refusal Waiver" class="mv-picture-img" onclick="openModalPicture(this.src)">
                </div>
            </div>
            <?php endif; ?>
            <?php if (empty($record['patient_documentation']) && empty($record['endorsement_attachment']) && empty($record['waiver_attachment'])): ?>
            <div class="mv-empty-state">
                <div class="mv-empty-icon"><i class="bi bi-camera"></i></div>
                <div class="mv-empty-title">No Pictures Attached</div>
                <div class="mv-empty-desc">No patient documentation, endorsement, or waiver images have been uploaded for this record.</div>
            </div>
            <?php endif; ?>
        </div>

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
