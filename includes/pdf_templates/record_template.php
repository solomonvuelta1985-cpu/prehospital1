<?php
/**
 * PDF Template for Single Patient Record
 * Variables available: $record, $injuries, $creator_name
 */
?>
<html>
<head>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; margin: 0; padding: 20px; }
    .header { text-align: center; border-bottom: 3px solid #0d6efd; padding-bottom: 10px; margin-bottom: 15px; }
    .header h1 { margin: 0; font-size: 18px; color: #0d6efd; }
    .header h2 { margin: 5px 0 0; font-size: 13px; color: #666; font-weight: normal; }
    .header .form-num { font-size: 11px; color: #999; margin-top: 3px; }
    .section { margin-bottom: 12px; }
    .section-title { background: #0d6efd; color: #fff; padding: 5px 10px; font-size: 11px; font-weight: bold; margin-bottom: 5px; border-radius: 3px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table td, table th { padding: 4px 8px; border: 1px solid #dee2e6; font-size: 9px; }
    table th { background: #f8f9fa; font-weight: 600; text-align: left; width: 30%; }
    .injury-table th { width: auto; text-align: center; }
    .injury-table td { text-align: center; }
    .footer { text-align: center; font-size: 8px; color: #999; border-top: 1px solid #dee2e6; padding-top: 8px; margin-top: 20px; }
    .two-col { width: 48%; display: inline-block; vertical-align: top; }
</style>
</head>
<body>
    <div class="header">
        <h1>RESCUE 116 MDRRMO</h1>
        <h2>Pre-Hospital Care Report</h2>
        <div class="form-num">Form #: <?= htmlspecialchars($record['form_number'] ?? 'N/A') ?> | Date: <?= htmlspecialchars($record['form_date'] ?? '') ?></div>
    </div>

    <!-- Basic Information -->
    <div class="section">
        <div class="section-title">Basic Information</div>
        <table>
            <tr><th>Form Date</th><td><?= htmlspecialchars($record['form_date'] ?? '') ?></td><th>Form Number</th><td><?= htmlspecialchars($record['form_number'] ?? '') ?></td></tr>
            <tr><th>Departure Time</th><td><?= htmlspecialchars($record['departure_time'] ?? '') ?></td><th>Arrival Time</th><td><?= htmlspecialchars($record['arrival_time'] ?? '') ?></td></tr>
            <tr><th>Vehicle Used</th><td><?= htmlspecialchars($record['vehicle_used'] ?? '') ?></td><th>Driver</th><td><?= htmlspecialchars($record['driver'] ?? '') ?></td></tr>
            <tr><th>Arrival at Scene</th><td><?= htmlspecialchars($record['arrival_scene_time'] ?? '') ?></td><th>Departure from Scene</th><td><?= htmlspecialchars($record['departure_scene_time'] ?? '') ?></td></tr>
            <tr><th>Arrival at Hospital</th><td><?= htmlspecialchars($record['arrival_hospital_time'] ?? '') ?></td><th>Arrival at Station</th><td><?= htmlspecialchars($record['arrival_station_time'] ?? '') ?></td></tr>
        </table>
    </div>

    <!-- Patient Information -->
    <div class="section">
        <div class="section-title">Patient Information</div>
        <table>
            <tr><th>Patient Name</th><td colspan="3"><?= htmlspecialchars($record['patient_name'] ?? '') ?></td></tr>
            <tr><th>Date of Birth</th><td><?= htmlspecialchars($record['date_of_birth'] ?? '') ?></td><th>Age</th><td><?= htmlspecialchars(($record['age'] ?? '') . ' ' . ($record['age_unit'] ?? 'years')) ?></td></tr>
            <tr><th>Gender</th><td><?= htmlspecialchars(ucfirst($record['gender'] ?? '')) ?></td><th>Civil Status</th><td><?= htmlspecialchars(ucfirst($record['civil_status'] ?? '')) ?></td></tr>
            <tr><th>Address</th><td colspan="3"><?= htmlspecialchars($record['address'] ?? '') ?></td></tr>
            <tr><th>Zone</th><td><?= htmlspecialchars($record['zone'] ?? '') ?></td><th>Occupation</th><td><?= htmlspecialchars($record['occupation'] ?? '') ?></td></tr>
            <tr><th>Place of Incident</th><td colspan="3"><?= htmlspecialchars($record['place_of_incident'] ?? '') ?></td></tr>
            <tr><th>Incident Time</th><td><?= htmlspecialchars($record['incident_time'] ?? '') ?></td><th>Contact Number</th><td><?= htmlspecialchars($record['contact_number'] ?? '') ?></td></tr>
        </table>
    </div>

    <!-- Emergency Type & Care -->
    <div class="section">
        <div class="section-title">Emergency Type & Care Management</div>
        <table>
            <tr><th>Emergency Type</th><td colspan="3"><?= htmlspecialchars($record['emergency_type'] ?? '') ?></td></tr>
            <tr><th>Care Management</th><td colspan="3"><?= htmlspecialchars($record['care_management'] ?? '') ?></td></tr>
            <tr><th>O2 LPM</th><td><?= htmlspecialchars($record['o2_lpm'] ?? '') ?></td><th>Other Care</th><td><?= htmlspecialchars($record['others_care'] ?? '') ?></td></tr>
        </table>
    </div>

    <!-- Vital Signs -->
    <div class="section">
        <div class="section-title">Vital Signs</div>
        <table>
            <tr><th></th><th style="text-align:center; width:35%;">Initial</th><th style="text-align:center; width:35%;">Follow-up</th></tr>
            <tr><th>Time</th><td><?= htmlspecialchars($record['initial_time'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_time'] ?? '') ?></td></tr>
            <tr><th>Blood Pressure</th><td><?= htmlspecialchars($record['initial_bp'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_bp'] ?? '') ?></td></tr>
            <tr><th>Temperature</th><td><?= htmlspecialchars($record['initial_temp'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_temp'] ?? '') ?></td></tr>
            <tr><th>Pulse Rate</th><td><?= htmlspecialchars($record['initial_pulse'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_pulse'] ?? '') ?></td></tr>
            <tr><th>Respiratory Rate</th><td><?= htmlspecialchars($record['initial_resp'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_resp'] ?? '') ?></td></tr>
            <tr><th>SPO2</th><td><?= htmlspecialchars($record['initial_spo2'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_spo2'] ?? '') ?></td></tr>
            <tr><th>Pain Score</th><td><?= htmlspecialchars($record['initial_pain_score'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_pain_score'] ?? '') ?></td></tr>
            <tr><th>Consciousness</th><td><?= htmlspecialchars($record['initial_consciousness'] ?? '') ?></td><td><?= htmlspecialchars($record['followup_consciousness'] ?? '') ?></td></tr>
        </table>
    </div>

    <!-- Injuries -->
    <?php if (!empty($injuries)): ?>
    <div class="section">
        <div class="section-title">Injuries (<?= count($injuries) ?>)</div>
        <table class="injury-table">
            <tr><th>#</th><th>Type</th><th>Body Part</th><th>View</th><th>Notes</th></tr>
            <?php foreach ($injuries as $injury): ?>
            <tr>
                <td><?= htmlspecialchars($injury['injury_number'] ?? '') ?></td>
                <td><?= htmlspecialchars(ucfirst($injury['injury_type'] ?? '')) ?></td>
                <td><?= htmlspecialchars($injury['body_part'] ?? '') ?></td>
                <td><?= htmlspecialchars(ucfirst($injury['body_view'] ?? '')) ?></td>
                <td style="text-align:left;"><?= htmlspecialchars($injury['notes'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Assessment -->
    <div class="section">
        <div class="section-title">Assessment</div>
        <table>
            <tr><th>Chief Complaints</th><td colspan="3"><?= htmlspecialchars($record['chief_complaints'] ?? '') ?></td></tr>
            <tr><th>Other Complaints</th><td colspan="3"><?= htmlspecialchars($record['others_complaint'] ?? '') ?></td></tr>
            <?php if (!empty($record['narrative_report'])): ?>
            <tr><th>Narrative Report</th><td colspan="3"><?= htmlspecialchars($record['narrative_report'] ?? '') ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Team Information -->
    <div class="section">
        <div class="section-title">Team Information</div>
        <table>
            <tr><th>Team Leader</th><td><?= htmlspecialchars($record['team_leader'] ?? '') ?></td><th>Data Recorder</th><td><?= htmlspecialchars($record['data_recorder'] ?? '') ?></td></tr>
            <tr><th>1st Aider</th><td><?= htmlspecialchars($record['aider1'] ?? '') ?></td><th>2nd Aider</th><td><?= htmlspecialchars($record['aider2'] ?? '') ?></td></tr>
            <tr><th>Logistic</th><td><?= htmlspecialchars($record['logistic'] ?? '') ?></td><th>Driver</th><td><?= htmlspecialchars($record['driver'] ?? '') ?></td></tr>
        </table>
    </div>

    <!-- Hospital Endorsement -->
    <div class="section">
        <div class="section-title">Hospital Endorsement</div>
        <table>
            <tr><th>Hospital</th><td><?= htmlspecialchars($record['hospital'] ?? '') ?></td><th>Date/Time</th><td><?= htmlspecialchars($record['endorsement_datetime'] ?? '') ?></td></tr>
            <tr><th>Endorsed To</th><td><?= htmlspecialchars($record['endorsed_to'] ?? '') ?></td><th>Designation</th><td><?= htmlspecialchars($record['endorsement_designation'] ?? '') ?></td></tr>
        </table>
    </div>

    <div class="footer">
        Generated on <?= date('F d, Y h:i A') ?> | Pre-Hospital Care System | Created by: <?= htmlspecialchars($creator_name ?? 'Unknown') ?>
    </div>
</body>
</html>
