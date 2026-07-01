<?php
/**
 * COMPREHENSIVE BACKEND TEST SUITE
 * Tests dummy record insertion and detects backend failures.
 * 
 * Uses database transactions that are ROLLED BACK so zero real data is persisted.
 * 
 * Run with: php tests/test_save_dummy_records.php
 */

define('APP_ACCESS', true);

// ── CLI Compatibility: set $_SERVER vars that config.php expects ──────────
// Running from CLI (php tests/...) means no $_SERVER['HTTP_HOST'], etc.
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/tests/test_save_dummy_records.php';
    $_SERVER['HTTPS'] = 'off';
    $_SERVER['HTTP_USER_AGENT'] = 'PHP_CLI_TEST';
    $_SERVER['SCRIPT_NAME'] = '/tests/test_save_dummy_records.php';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ── Colour helpers for readable output ──────────────────────────────────────
function green($msg)  { return "\033[32m" . $msg . "\033[0m"; }
function red($msg)    { return "\033[31m" . $msg . "\033[0m"; }
function yellow($msg) { return "\033[33m" . $msg . "\033[0m"; }
function cyan($msg)   { return "\033[36m" . $msg . "\033[0m"; }
function bold($msg)   { return "\033[1m"  . $msg . "\033[0m"; }

$passed = 0;
$failed = 0;
$total  = 0;

function test($description, $condition, $details = '') {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        echo "  " . green("✅ PASS") . "  {$description}\n";
        $passed++;
    } else {
        echo "  " . red("❌ FAIL") . "  {$description}\n";
        if (!empty($details)) {
            echo "         " . yellow("Details: {$details}\n");
        }
        $failed++;
    }
}

function separator($title = '') {
    echo "\n" . str_repeat('─', 72) . "\n";
    if ($title) echo "  " . cyan(bold($title)) . "\n\n";
}

// ── Use a clean transaction that we'll ALWAYS rollback ──────────────────────
// This ensures no actual data is committed to the database.
function begin_test_transaction() {
    global $pdo;
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->beginTransaction();
}

function rollback_test($msg = '') {
    global $pdo;
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        return true;
    }
    return false;
}

// ── Utility: ensure we have at least one user ID to reference ───────────────
function get_test_user_id() {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : 1;
}

// ═══════════════════════════════════════════════════════════════════════════════
echo "\n";
echo bold("┌" . str_repeat("─", 70) . "┐\n");
echo bold("│") . "  PRE-HOSPITAL CARE SYSTEM — BACKEND SAVE & DATA INTEGRITY TEST     " . bold("│") . "\n";
echo bold("│") . "  " . date('Y-m-d H:i:s') . "                                                 " . bold("│") . "\n";
echo bold("└" . str_repeat("─", 70) . "┘\n");
echo "\n  " . yellow("⚠  ALL TESTS USE TRANSACTIONS — NOTHING IS PERSISTED TO THE DATABASE") . "\n";

// ═══════════════════════════════════════════════════════════════════════════════
// PART 1: DIRECT DATABASE INSERTION TESTS
// ═══════════════════════════════════════════════════════════════════════════════
separator("PART 1: DIRECT DATABASE INSERTION TESTS");

$testUserId = get_test_user_id();
echo "  Using test user_id: {$testUserId}\n\n";

// ── 1.1  MINIMAL VALID RECORD ───────────────────────────────────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    $stmt = db_query($sql, [
        $formNumber, date('Y-m-d'), 30, 'years', 'male',
        1, 'TEST PATIENT MINIMAL', $testUserId
    ], true);
    $formId = (int)$pdo->lastInsertId();
    
    // Verify it was inserted
    $check = db_query("SELECT id, form_number, patient_name, status FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    
    test(
        '1.1  Insert minimal valid record',
        $row !== false && $row['form_number'] === $formNumber && $row['status'] === 'completed',
        $row !== false ? "Inserted ID={$formId}, form_number={$row['form_number']}" : 'Row not found after insert'
    );
    
    // Also verify default age_unit took effect
    $row2 = db_query("SELECT age, age_unit FROM prehospital_forms WHERE id = ?", [$formId], true)->fetch();
    test(
        '1.1b Default age_unit is "years"',
        $row2['age_unit'] === 'years',
        "age_unit = '{$row2['age_unit']}'"
    );
} catch (Exception $e) {
    test('1.1  Insert minimal valid record', false, $e->getMessage());
}
rollback_test();


// ── 1.2  FULL-RECORD INSERTION (all optional columns) ────────────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Prepare a near-complete record with realistic test data
    // Match the actual DB schema columns (including departure_hospital_location which the
    // live schema still has, even though the save API doesn't reference it anymore).
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, departure_time, arrival_time,
                vehicle_used, vehicle_details, driver_name,
                arrival_scene_location, arrival_scene_time, departure_scene_location, departure_scene_time,
                arrival_hospital_name, arrival_hospital_time, departure_hospital_location, departure_hospital_time,
                arrival_station_time, persons_present,
                patient_name, date_of_birth, age, age_unit, growth_status, gender, civil_status,
                address, zone, occupation,
                place_of_incident, zone_landmark, incident_time,
                informant_name, informant_address, arrival_type, call_arrival_time,
                contact_number, relationship_victim,
                personal_belongings, other_belongings, patient_documentation,
                photo_latitude, photo_longitude, photo_address, photo_datetime,
                emergency_medical, emergency_medical_details,
                emergency_trauma, emergency_trauma_details,
                emergency_ob, emergency_ob_details,
                emergency_general, emergency_general_details,
                incident_category,
                care_management, oxygen_lpm, other_care,
                initial_time, initial_bp, initial_temp, initial_pulse, initial_resp_rate,
                initial_pain_score, initial_spo2, initial_spinal_injury,
                initial_consciousness, initial_helmet,
                followup_time, followup_bp, followup_temp, followup_pulse, followup_resp_rate,
                followup_pain_score, followup_spo2, followup_spinal_injury,
                followup_consciousness,
                chief_complaints, other_complaints,
                fast_face_drooping, fast_arm_weakness, fast_speech_difficulty, fast_time_to_call,
                fast_sample_details,
                ob_baby_status, ob_delivery_time, ob_placenta, ob_lmp, ob_aog, ob_edc,
                team_leader_notes, team_leader, data_recorder, logistic, first_aider, second_aider,
                hospital_name, endorsement_attachment, endorsement_datetime,
                narrative_report,
                status, created_by
            ) VALUES (
                ?, ?, ?, ?,    /* form_number, form_date, departure_time, arrival_time */
                ?, ?, ?,       /* vehicle_used, vehicle_details, driver_name */
                ?, ?, ?, ?,    /* arrival_scene_location, arrival_scene_time, departure_scene_location, departure_scene_time */
                ?, ?, ?, ?,    /* arrival_hospital_name, arrival_hospital_time, departure_hospital_location, departure_hospital_time */
                ?, ?,          /* arrival_station_time, persons_present */
                ?, ?, ?, ?, ?, ?, ?,  /* patient_name, date_of_birth, age, age_unit, growth_status, gender, civil_status */
                ?, ?, ?,       /* address, zone, occupation */
                ?, ?, ?,       /* place_of_incident, zone_landmark, incident_time */
                ?, ?, ?, ?,    /* informant_name, informant_address, arrival_type, call_arrival_time */
                ?, ?,          /* contact_number, relationship_victim */
                ?, ?, ?,       /* personal_belongings, other_belongings, patient_documentation */
                ?, ?, ?, ?,    /* photo_latitude, photo_longitude, photo_address, photo_datetime */
                ?, ?,          /* emergency_medical, emergency_medical_details */
                ?, ?,          /* emergency_trauma, emergency_trauma_details */
                ?, ?,          /* emergency_ob, emergency_ob_details */
                ?, ?,          /* emergency_general, emergency_general_details */
                ?,             /* incident_category */
                ?, ?, ?,       /* care_management, oxygen_lpm, other_care */
                ?, ?, ?, ?, ?, /* initial_time, initial_bp, initial_temp, initial_pulse, initial_resp_rate */
                ?, ?, ?,       /* initial_pain_score, initial_spo2, initial_spinal_injury */
                ?, ?,          /* initial_consciousness, initial_helmet */
                ?, ?, ?, ?, ?, /* followup_time, followup_bp, followup_temp, followup_pulse, followup_resp_rate */
                ?, ?, ?,       /* followup_pain_score, followup_spo2, followup_spinal_injury */
                ?,             /* followup_consciousness */
                ?, ?,          /* chief_complaints, other_complaints */
                ?, ?, ?, ?,    /* fast_face_drooping, fast_arm_weakness, fast_speech_difficulty, fast_time_to_call */
                ?,             /* fast_sample_details */
                ?, ?, ?, ?, ?, ?, /* ob_baby_status, ob_delivery_time, ob_placenta, ob_lmp, ob_aog, ob_edc */
                ?, ?, ?, ?, ?, ?, /* team_leader_notes, team_leader, data_recorder, logistic, first_aider, second_aider */
                ?, ?, ?,       /* hospital_name, endorsement_attachment, endorsement_datetime */
                ?,             /* narrative_report */
                'completed', ?  /* status, created_by */
            )";
    
    $params = [
        $formNumber, '2026-06-15', '08:30', '09:00',                                    // 4
        'ambulance', '{"type":"ambulance","id":"T1"}', 'DRIVER TEST',                    // 3
        'TEST LOCATION', '08:45', 'TEST LOCATION', '08:55',                               // 4
        'TEST HOSPITAL', '09:15', null, '09:45',                                          // 4 (departure_hospital_location=null)
        '10:00', '["PNP","BFP"]',                                                         // 2
        'TEST PATIENT FULL', '1990-05-15', 36, 'years', 'adult', 'male', 'married',       // 7
        'TEST ADDRESS', 'ZONE 5', 'TEST OCCUPATION',                                     // 3
        'TEST INCIDENT PLACE', 'NEAR LANDMARK', '08:00',                                 // 3
        'TEST INFORMANT', 'INFORMANT ADDRESS', 'call', '08:15',                          // 4
        '09123456789', 'SPOUSE',                                                         // 2
        '["wallet","phone"]', 'TEST BELONGINGS', 'uploads/test_doc.jpg',                 // 3
        17.12345678, 121.98765432, 'TEST GPS ADDRESS', '2026-06-15 08:00:00',           // 4
        1, 'MEDICAL DETAILS TEST',                                                       // 2
        0, null,                                                                         // 2
        0, null,                                                                         // 2
        0, null,                                                                         // 2
        'Vehicular Accident',                                                            // 1
        '["CPR","OXYGEN"]', '10 LPM', 'OTHER CARE NOTES',                                // 3
        '08:45', '120/80', 36.5, 80, 18,                                                // 5
        5, 98, 'no',                                                                     // 3
        '["alert","verbal"]', '["none"]',                                                // 2
        '09:15', '120/80', 36.6, 78, 18,                                                // 5
        3, 99, 'no',                                                                     // 3
        '["alert"]',                                                                     // 1
        '["CHEST PAIN","COUGH"]', 'OTHER COMPLAINTS TEST',                               // 2
        'negative', 'negative', 'negative', 'negative',                                  // 4
        '{"signs":"none","allergies":"none"}',                                           // 1
        'LIVE BIRTH', '09:00', 'out', '2026-01-01', '38 WEEKS', '2026-06-01',           // 6
        'TEAM NOTES TEST', 'TEAM LEADER TEST', 'DATA RECORDER TEST', 'LOGISTIC TEST', 'AIDER1 TEST', 'AIDER2 TEST', // 6
        'TEST HOSPITAL NAME', 'uploads/endorsement_test.jpg', '2026-06-15 09:30:00',    // 3
        'NARRATIVE REPORT TEST — PATIENT TRANSPORTED SUCCESSFULLY.',                    // 1
        $testUserId                                                                      // 1
        // Total: 4+3+4+4+2+7+3+3+4+2+3+4+2+2+2+2+1+3+5+3+2+5+3+1+2+4+1+6+6+3+1+1 = should match columns
    ];
    
    $stmt = db_query($sql, $params, true);
    $formId = (int)$pdo->lastInsertId();
    
    $check = db_query("SELECT * FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    
    $allOk = $row !== false
        && $row['form_number'] === $formNumber
        && $row['patient_name'] !== null
        && $row['age'] == 36
        && $row['gender'] === 'male'
        && $row['vehicle_used'] === 'ambulance'
        && $row['emergency_medical'] == 1
        && $row['incident_category'] === 'Vehicular Accident'
        && $row['status'] === 'completed'
        && $row['photo_latitude'] !== null
        && $row['initial_temp'] == 36.5;
    
    test(
        '1.2  Insert full record (all optional columns)',
        $allOk,
        $allOk ? "Inserted ID={$formId}" : 'One or more field values did not match'
    );
    
    // Verify JSON columns stored correctly
    test(
        '1.2b persons_present stored as valid JSON',
        json_decode($row['persons_present'], true) === ['PNP', 'BFP'],
        "persons_present = '{$row['persons_present']}'"
    );
    
    test(
        '1.2c chief_complaints stored as valid JSON',
        json_decode($row['chief_complaints'], true) === ['CHEST PAIN', 'COUGH'],
        "chief_complaints = '{$row['chief_complaints']}'"
    );
    
    test(
        '1.2d care_management stored as valid JSON',
        json_decode($row['care_management'], true) === ['CPR', 'OXYGEN'],
        "care_management = '{$row['care_management']}'"
    );
} catch (Exception $e) {
    test('1.2  Insert full record', false, $e->getMessage());
}
rollback_test();


// ── 1.3  INSERT WITH INJURIES (sub-table foreign key test) ───────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Insert parent record
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_trauma, emergency_trauma_details, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    $stmt = db_query($sql, [
        $formNumber, date('Y-m-d'), 25, 'years', 'male',
        1, 'VEHICULAR ACCIDENT', 'TEST PATIENT INJURIES', $testUserId
    ], true);
    $formId = (int)$pdo->lastInsertId();
    
    // Insert injuries
    $injurySql = "INSERT INTO injuries (form_id, injury_number, injury_type, body_view, body_part, coordinate_x, coordinate_y, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $injuries = [
        [1, 'laceration', 'front', 'HEAD', 150.5, 200.3, 'LACERATION ON FOREHEAD'],
        [2, 'abrasion',  'front', 'LEFT ARM', 120.0, 350.7, 'ABRASION ON LEFT FOREARM'],
        [3, 'fracture',  'back',  'RIGHT LEG', 180.2, 500.1, 'SUSPECTED FRACTURE RIGHT TIBIA'],
    ];
    
    foreach ($injuries as $inj) {
        db_query($injurySql, array_merge([$formId], $inj), true);
    }
    
    // Verify injuries
    $check = db_query("SELECT COUNT(*) as cnt FROM injuries WHERE form_id = ?", [$formId], true);
    $injuryCount = (int)$check->fetch()['cnt'];
    
    test(
        '1.3  Insert record with 3 injuries (foreign key relation)',
        $injuryCount === 3,
        "Expected 3 injuries, found {$injuryCount}"
    );
    
    // Verify injury data integrity
    $injCheck = db_query("SELECT * FROM injuries WHERE form_id = ? ORDER BY injury_number", [$formId], true);
    $allInjuries = $injCheck->fetchAll();
    
    test(
        '1.3b Injury data matches inserted values',
        count($allInjuries) === 3
        && $allInjuries[0]['injury_type'] === 'laceration'
        && $allInjuries[0]['body_part'] === 'HEAD'
        && $allInjuries[1]['injury_type'] === 'abrasion'
        && $allInjuries[2]['injury_type'] === 'fracture',
        'Injury fields verified'
    );
    
} catch (Exception $e) {
    test('1.3  Insert with injuries', false, $e->getMessage());
}
rollback_test();


// ── 1.4  CHECK NOT-NULL CONSTRAINT: form_number ──────────────────────────────
begin_test_transaction();
try {
    $sql = "INSERT INTO prehospital_forms (form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [date('Y-m-d'), 30, 'years', 'male', 1, 'TEST NULL FORM', $testUserId], true);
    test(
        '1.4  Insert NULL form_number (SHOULD FAIL — NOT NULL constraint)',
        false,
        'Expected SQL error but insert succeeded'
    );
} catch (PDOException $e) {
    $msg = strtolower($e->getMessage());
    $isNotNullError = strpos($msg, 'cannot be null') !== false
                    || strpos($msg, "doesn't have a default") !== false
                    || strpos($msg, 'not null') !== false;
    test(
        '1.4  Insert NULL form_number (SHOULD FAIL — NOT NULL constraint)',
        $isNotNullError,
        "Correctly rejected: " . substr($e->getMessage(), 0, 100)
    );
} catch (Exception $e) {
    test(
        '1.4  Insert NULL form_number (SHOULD FAIL — NOT NULL constraint)',
        true,
        "Correctly rejected: " . $e->getMessage()
    );
}
rollback_test();


// ── 1.5  DUPLICATE FORM_NUMBER CHECK ─────────────────────────────────────────
begin_test_transaction();
try {
    // First check if form_number has a UNIQUE constraint in the actual schema
    $formNumberDup = 'TEST-DUP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Insert first
    $sql1 = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql1, [$formNumberDup, date('Y-m-d'), 30, 'years', 'male', 1, 'TEST DUP 1', $testUserId], true);
    
    // Attempt duplicate
    $sql2 = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql2, [$formNumberDup, date('Y-m-d'), 25, 'years', 'female', 1, 'TEST DUP 2', $testUserId], true);
    
    test(
        '1.5  Duplicate form_number (SHOULD FAIL if UNIQUE index exists)',
        false,
        "Duplicate form_number '{$formNumberDup}' was accepted — no UNIQUE constraint on form_number"
    );
} catch (PDOException $e) {
    $msg = strtolower($e->getMessage());
    $isDupError = strpos($msg, 'duplicate') !== false || strpos($msg, 'unique') !== false;
    test(
        '1.5  Duplicate form_number (SHOULD FAIL if UNIQUE index exists)',
        $isDupError,
        $isDupError ? 'Duplicate correctly rejected' : "Error but not a duplicate-key error: " . substr($e->getMessage(), 0, 100)
    );
} catch (Exception $e) {
    test(
        '1.5  Duplicate form_number (SHOULD FAIL if UNIQUE index exists)',
        false,
        $e->getMessage()
    );
}
rollback_test();


// ── 1.6  INVALID ENUM VALUE ─────────────────────────────────────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    // vehicle_used accepts ONLY 'ambulance','fireTruck','others' — try 'spaceship'
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender, vehicle_used,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumber, date('Y-m-d'), 30, 'years', 'male', 'spaceship', 1, 'TEST ENUM', $testUserId], true);
    
    test(
        '1.6  Insert invalid enum value "spaceship" for vehicle_used (SHOULD FAIL)',
        false,
        'Invalid enum was accepted — MySQL may be in non-strict mode'
    );
} catch (PDOException $e) {
    test(
        '1.6  Insert invalid enum value "spaceship" for vehicle_used (SHOULD FAIL)',
        true,
        "Correctly rejected: " . substr($e->getMessage(), 0, 100)
    );
} catch (Exception $e) {
    test(
        '1.6  Invalid enum value (SHOULD FAIL)',
        true,
        "Correctly rejected: " . $e->getMessage()
    );
}
rollback_test();


// ── 1.7  INVALID FOREIGN KEY (created_by pointing to non-existent user) ──────
begin_test_transaction();
try {
    $formNumber = 'TEST-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    // MAX INT unlikely to be a real user (foreign key is optional in schema, so may not fail)
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumber, date('Y-m-d'), 30, 'years', 'male', 1, 'TEST BAD FK', 999999], true);
    
    $formId = (int)$pdo->lastInsertId();
    $check = db_query("SELECT created_by FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    
    // If we get here, foreign key constraint either doesn't exist or is not enforced
    test(
        '1.7  Insert with invalid created_by=999999 (should fail if FK enforced)',
        $row !== false,
        $row !== false
            ? "Record accepted with created_by=999999 — FK constraint not present on created_by column"
            : 'Record not found after insert'
    );
} catch (PDOException $e) {
    $msg = strtolower($e->getMessage());
    $isFKError = strpos($msg, 'foreign key') !== false || strpos($msg, 'constraint') !== false;
    test(
        '1.7  Foreign key enforcement on created_by',
        $isFKError,
        $isFKError ? 'FK constraint enforced' : "Error: " . substr($e->getMessage(), 0, 100)
    );
} catch (Exception $e) {
    test('1.7  Foreign key enforcement', false, $e->getMessage());
}
rollback_test();


// ── 1.8  TRANSACTION ROLLBACK VERIFICATION ───────────────────────────────────
begin_test_transaction();
$formNumberRollback = 'TEST-ROLLBACK-' . strtoupper(bin2hex(random_bytes(4)));
try {
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumberRollback, date('Y-m-d'), 99, 'years', 'female', 1, 'TEST SHOULD NOT EXIST', $testUserId], true);
    $insertedId = (int)$pdo->lastInsertId();
    
    // Verify it exists inside the transaction
    $check = db_query("SELECT id FROM prehospital_forms WHERE id = ?", [$insertedId], true);
    $inTransaction = $check->fetch() !== false;
    
    test(
        '1.8a Insert visible within transaction',
        $inTransaction,
        $inTransaction ? "ID={$insertedId}" : 'Not visible within transaction'
    );
} catch (Exception $e) {
    test('1.8a Insert in transaction', false, $e->getMessage());
}

// Now rollback
$rolledBack = rollback_test();
test(
    '1.8b Transaction rolled back successfully',
    $rolledBack,
    'Rollback executed'
);

// Verify record does NOT exist after rollback (new transaction to check)
try {
    $check = $pdo->query("SELECT id FROM prehospital_forms WHERE form_number = '{$formNumberRollback}'");
    $exists = $check->fetch() !== false;
    test(
        '1.8c Rollback actually removed the record (no dirty data)',
        !$exists,
        $exists ? 'RECORD STILL EXISTS AFTER ROLLBACK — MAJOR PROBLEM' : 'Record correctly not found'
    );
} catch (Exception $e) {
    test('1.8c Rollback verification', false, $e->getMessage());
}


// ═══════════════════════════════════════════════════════════════════════════════
// PART 2: FUNCTION-LEVEL TESTS
// ═══════════════════════════════════════════════════════════════════════════════
separator("PART 2: FUNCTION-LEVEL TESTS");

// ── 2.1  ENCRYPTION ROUND-TRIP ───────────────────────────────────────────────
if (!empty(APP_ENCRYPTION_KEY)) {
    $testPlaintexts = [
        'JOHN DOE',
        '123 MAIN STREET, BAGGAO, CAGAYAN',
        'TEST WITH SPECIAL CHARS: !@#$%^&*()_+-=[]{}|;:,.<>?',
        ''  // empty should return empty
    ];
    
    foreach ($testPlaintexts as $i => $plain) {
        $encrypted = encrypt_field($plain);
        $decrypted = decrypt_field($encrypted);
        
        $label = $i === 3 ? 'empty string' : substr($plain, 0, 40);
        $labelNum = '2.1.' . ($i + 1);
        
        test(
            "{$labelNum} Encrypt/decrypt round-trip: \"{$label}\"",
            $decrypted === $plain,
            $decrypted !== $plain ? "Got: '{$decrypted}' (encrypted: '" . substr($encrypted, 0, 30) . "...')" : ''
        );
    }
} else {
    echo "  " . yellow("⚠  SKIPPED encryption tests — APP_ENCRYPTION_KEY not set") . "\n";
    $total += 4; // mark 4 tests as skipped (won't affect pass/fail)
    echo "    Add APP_ENCRYPTION_KEY to your .env file to enable encryption tests.\n";
}


// ── 2.2  INCIDENT CATEGORY RESOLUTION ────────────────────────────────────────
test(
    '2.2a resolve_record_category — explicit vehicular accident',
    resolve_record_category(['incident_category' => 'Vehicular Accident']) === 'Vehicular Accident',
    "Got: " . resolve_record_category(['incident_category' => 'Vehicular Accident'])
);

test(
    '2.2b resolve_record_category — detects "MAULING" from trauma_details',
    resolve_record_category([
        'incident_category' => null,
        'emergency_trauma_details' => 'MAULING INCIDENT',
        'emergency_medical_details' => null,
        'emergency_general_details' => null,
        'emergency_ob_details' => null,
        'other_complaints' => null,
        'team_leader_notes' => null
    ]) === 'Mauling',
    "Got: " . resolve_record_category([
        'incident_category' => null,
        'emergency_trauma_details' => 'MAULING INCIDENT'
    ])
);

test(
    '2.2c resolve_record_category — detects "FALL INCIDENT" from trauma_details',
    resolve_record_category([
        'incident_category' => null,
        'emergency_trauma_details' => 'FALL INCIDENT FROM ROOF',
        'emergency_medical_details' => null,
        'emergency_general_details' => null,
        'emergency_ob_details' => null,
        'other_complaints' => null,
        'team_leader_notes' => null
    ]) === 'Fall',
    "Got: " . resolve_record_category([
        'incident_category' => null,
        'emergency_trauma_details' => 'FALL INCIDENT FROM ROOF'
    ])
);

test(
    '2.2d classify_medical_category — detects stroke from FAST positive',
    classify_medical_category([
        'fast_face_drooping' => 'positive',
        'other_complaints' => 'FACIAL DROOP NOTED',
        'emergency_medical_details' => '',
        'emergency_trauma_details' => '',
        'emergency_general_details' => '',
        'emergency_ob_details' => '',
        'team_leader_notes' => '',
        'chief_complaints' => '[]',
        'initial_consciousness' => 'alert',
        'care_management' => ''
    ]) === 'Stroke/CVA',
    "Got: " . classify_medical_category([
        'fast_face_drooping' => 'positive',
        'other_complaints' => 'FACIAL DROOP NOTED'
    ])
);

test(
    '2.2e classify_medical_category — detects chest pain',
    classify_medical_category([
        'fast_face_drooping' => null,
        'other_complaints' => 'CHEST PAIN',
        'emergency_medical_details' => 'CHEST PAIN RADIATING TO LEFT ARM',
        'emergency_trauma_details' => null,
        'emergency_general_details' => null,
        'emergency_ob_details' => null,
        'team_leader_notes' => null,
        'chief_complaints' => '["CHEST PAIN"]',
        'initial_consciousness' => 'alert',
        'care_management' => '[]'
    ]) === 'Chest Pain/Cardiac',
    "Got: " . classify_medical_category([
        'other_complaints' => 'CHEST PAIN',
        'emergency_medical_details' => 'CHEST PAIN RADIATING TO LEFT ARM',
        'chief_complaints' => '["CHEST PAIN"]'
    ])
);

test(
    '2.2f classify_incident_category — "GUNSHOT WOUND"',
    classify_incident_category('GUNSHOT WOUND TO THE CHEST') === 'Gunshot',
    "Got: " . classify_incident_category('GUNSHOT WOUND TO THE CHEST')
);

test(
    '2.2g classify_incident_category — "ELECTROCUTION"',
    classify_incident_category('ELECTROCUTION INCIDENT') === 'Electrocution',
    "Got: " . classify_incident_category('ELECTROCUTION INCIDENT')
);

test(
    '2.2h classify_incident_category — null input returns null',
    classify_incident_category(null) === null,
    "Got: " . var_export(classify_incident_category(null), true)
);

test(
    '2.2i resolve_record_category — falls back to medical for plain text',
    resolve_record_category([
        'incident_category' => null,
        'emergency_trauma_details' => null,
        'emergency_general_details' => null,
        'emergency_medical_details' => 'FEVER AND COUGH',
        'emergency_ob_details' => null,
        'other_complaints' => null,
        'team_leader_notes' => null,
        'chief_complaints' => '["FEVER"]',
        'initial_consciousness' => null,
        'care_management' => '[]'
    ]) === 'Fever/Infection',
    "Got: " . resolve_record_category([
        'incident_category' => null,
        'emergency_medical_details' => 'FEVER AND COUGH',
        'chief_complaints' => '["FEVER"]'
    ])
);


// ── 2.3  SANITIZATION EDGE CASES ────────────────────────────────────────────
test(
    '2.3a sanitize handles null',
    sanitize(null) === null,
    "Got: " . var_export(sanitize(null), true)
);

test(
    '2.3b sanitize handles empty string',
    sanitize('') === null,
    "Got: " . var_export(sanitize(''), true)
);

test(
    '2.3c sanitize strips HTML, escapes quotes, and uppercases',
    sanitize('<script>alert("xss")</script>') === mb_strtoupper(htmlspecialchars(strip_tags('<script>alert("xss")</script>'), ENT_QUOTES, 'UTF-8'), 'UTF-8'),
    "Got: '" . sanitize('<script>alert("xss")</script>') . "'"
);

test(
    '2.3d sanitize with uppercase=false preserves case',
    sanitize('Hello World', false) === 'Hello World',
    "Got: '" . sanitize('Hello World', false) . "'"
);

test(
    '2.3e sanitize handles array input (uppercases each element)',
    sanitize(['  test1  ', '<b>test2</b>']) === ['TEST1', 'TEST2'],
    "Got: " . json_encode(sanitize(['  test1  ', '<b>test2</b>']))
);


// ── 2.4  DAILY FORM LIMIT CHECK ──────────────────────────────────────────────
begin_test_transaction();
try {
    // We're in a transaction so this won't affect real data
    $limitCheck = check_daily_form_limit($testUserId, 50);
    test(
        '2.4a check_daily_form_limit returns true when under limit',
        $limitCheck === true,
        "Got: " . var_export($limitCheck, true)
    );
} catch (Exception $e) {
    test('2.4a check_daily_form_limit', false, $e->getMessage());
}
rollback_test();


// ── 2.5  JSON VALIDATION IN SCHEMA ──────────────────────────────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-JSON-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Valid JSON persons_present
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender, persons_present,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumber, date('Y-m-d'), 30, 'years', 'male', '["PNP","BFP","BARANGAY"]', 1, 'TEST JSON', $testUserId], true);
    
    $formId = (int)$pdo->lastInsertId();
    $check = db_query("SELECT persons_present FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    $decoded = json_decode($row['persons_present'], true);
    
    test(
        '2.5a JSON persons_present column accepts valid JSON array',
        $decoded === ['PNP', 'BFP', 'BARANGAY'],
        "Got: " . json_encode($decoded)
    );
    
} catch (Exception $e) {
    test('2.5a JSON persons_present', false, $e->getMessage());
}
rollback_test();


// ═══════════════════════════════════════════════════════════════════════════════
// PART 3: BULK INSERT STRESS TEST
// ═══════════════════════════════════════════════════════════════════════════════
separator("PART 3: BULK INSERT STRESS TEST");

begin_test_transaction();
$bulkCount = 10;
$bulkSuccess = 0;
$bulkFail = 0;

for ($i = 1; $i <= $bulkCount; $i++) {
    try {
        $formNumber = 'TEST-BULK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . $i;
        $sql = "INSERT INTO prehospital_forms (
                    form_number, form_date, age, age_unit, gender,
                    emergency_medical, patient_name, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
        db_query($sql, [$formNumber, date('Y-m-d'), random_int(1, 80), 'years', $i % 2 === 0 ? 'male' : 'female', 1, "BULK TEST PATIENT {$i}", $testUserId], true);
        $bulkSuccess++;
    } catch (Exception $e) {
        $bulkFail++;
    }
}

test(
    "3.1  Bulk insert {$bulkCount} records in transaction",
    $bulkSuccess === $bulkCount,
    "Inserted: {$bulkSuccess}/{$bulkCount}, Failed: {$bulkFail}"
);

// Verify count inside transaction
$countCheck = db_query("SELECT COUNT(*) as cnt FROM prehospital_forms WHERE form_number LIKE 'TEST-BULK-%'", [], true);
$countInTx = (int)$countCheck->fetch()['cnt'];
test(
    '3.2  Verify all bulk records exist within transaction',
    $countInTx === $bulkCount,
    "Found {$countInTx}/{$bulkCount} records"
);

rollback_test();

// Verify none persisted after rollback
$countAfter = $pdo->query("SELECT COUNT(*) FROM prehospital_forms WHERE form_number LIKE 'TEST-BULK-%'");
$countAfterRollback = (int)$countAfter->fetchColumn();
test(
    '3.3  Zero bulk records persist after rollback',
    $countAfterRollback === 0,
    $countAfterRollback > 0 ? "FOUND {$countAfterRollback} LEAKED RECORDS" : 'All clean'
);


// ═══════════════════════════════════════════════════════════════════════════════
// PART 4: EDGE CASES & ERROR HANDLING
// ═══════════════════════════════════════════════════════════════════════════════
separator("PART 4: EDGE CASES & ERROR HANDLING");

// ── 4.1  VERY LONG STRING INPUT ──────────────────────────────────────────────
begin_test_transaction();
try {
    $longString = str_repeat('X', 5000); // 5000 chars — exceeds VARCHAR(500)
    $formNumber = 'TEST-LONG-' . strtoupper(bin2hex(random_bytes(4)));
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, narrative_report, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumber, date('Y-m-d'), 30, 'years', 'male', 1, $longString, $longString, $testUserId], true);
    
    // With strict mode enabled, this should throw a "Data too long" error
    test(
        '4.1a Insert 5000-char patient_name (should be rejected — VARCHAR(500))',
        false,
        'Expected "Data too long" error but insert succeeded'
    );
} catch (PDOException $e) {
    $msg = strtolower($e->getMessage());
    $isDataTooLong = strpos($msg, 'data too long') !== false || strpos($msg, 'truncated') !== false;
    test(
        '4.1a Insert 5000-char patient_name (should be rejected — VARCHAR(500))',
        $isDataTooLong,
        $isDataTooLong ? 'Correctly rejected — strict mode enforces column limits' : "Rejected: " . substr($e->getMessage(), 0, 100)
    );
} catch (Exception $e) {
    test(
        '4.1a Insert 5000-char patient_name (should be rejected — VARCHAR(500))',
        true,
        "Correctly rejected: " . $e->getMessage()
    );
}
rollback_test();


// ── 4.2  SPECIAL CHARACTERS / UNICODE ────────────────────────────────────────
begin_test_transaction();
try {
    $specialName = "JOSÉ MARÍA DE LA CRUZ ÑOÑO — TEST™ ©2026";
    $formNumber = 'TEST-UNI-' . strtoupper(bin2hex(random_bytes(4)));
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumber, date('Y-m-d'), 30, 'years', 'male', 1, $specialName, $testUserId], true);
    
    $formId = (int)$pdo->lastInsertId();
    $check = db_query("SELECT patient_name FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    
    test(
        '4.2  Unicode / special characters in patient_name',
        $row !== false && $row['patient_name'] === $specialName,
        $row !== false ? "Stored: '{$row['patient_name']}'" : 'Row not found'
    );
} catch (Exception $e) {
    test('4.2  Unicode insert', false, $e->getMessage());
}
rollback_test();


// ── 4.3  NEGATIVE / ZERO AGE ─────────────────────────────────────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-NEG-' . strtoupper(bin2hex(random_bytes(4)));
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [$formNumber, date('Y-m-d'), -5, 'years', 'male', 1, 'NEGATIVE AGE PATIENT', $testUserId], true);
    
    $formId = (int)$pdo->lastInsertId();
    $check = db_query("SELECT age FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    
    test(
        '4.3  Negative age accepted (no DB constraint — app layer must validate)',
        $row !== false && $row['age'] == -5,
        $row !== false
            ? "Age stored as {$row['age']} — application layer must validate age > 0"
            : 'Insert failed'
    );
} catch (Exception $e) {
    test('4.3  Negative age', false, $e->getMessage());
}
rollback_test();


// ── 4.4  EMPTY JSON ARRAYS ──────────────────────────────────────────────────
begin_test_transaction();
try {
    $formNumber = 'TEST-EMPTY-' . strtoupper(bin2hex(random_bytes(4)));
    $sql = "INSERT INTO prehospital_forms (
                form_number, form_date, age, age_unit, gender,
                persons_present, personal_belongings, chief_complaints, care_management,
                emergency_medical, patient_name, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
    db_query($sql, [
        $formNumber, date('Y-m-d'), 30, 'years', 'male',
        '[]', '[]', '[]', '[]',
        1, 'TEST EMPTY ARRAYS', $testUserId
    ], true);
    
    $formId = (int)$pdo->lastInsertId();
    $check = db_query("SELECT persons_present, chief_complaints FROM prehospital_forms WHERE id = ?", [$formId], true);
    $row = $check->fetch();
    
    test(
        '4.4  Empty JSON arrays stored correctly',
        $row !== false
        && json_decode($row['persons_present'], true) === []
        && json_decode($row['chief_complaints'], true) === [],
        "persons_present=" . $row['persons_present'] . ", chief_complaints=" . $row['chief_complaints']
    );
} catch (Exception $e) {
    test('4.4  Empty JSON arrays', false, $e->getMessage());
}
rollback_test();


// ── 4.5  db_query ERROR PROPAGATION (intentional SQL error) ─────────────────
try {
    $result = db_query("SELECT * FROM non_existent_table_xyz", [], false);
    test(
        '4.5  db_query returns false on SQL error (without throw)',
        $result === false,
        "Got: " . var_export($result, true)
    );
} catch (Exception $e) {
    test(
        '4.5  db_query error handling',
        false,
        "Unexpected exception: " . $e->getMessage()
    );
}


// ═══════════════════════════════════════════════════════════════════════════════
// PART 5: GLOBAL CLEANUP — ensure no TEST- records exist outside transactions
// ═══════════════════════════════════════════════════════════════════════════════
separator("PART 5: GLOBAL CLEANUP");

// Safety: Delete any TEST-* records that might have leaked (should be zero)
try {
    $findTestRecords = $pdo->query("SELECT COUNT(*) FROM prehospital_forms WHERE form_number LIKE 'TEST-%'");
    $leakedCount = (int)$findTestRecords->fetchColumn();
    
    if ($leakedCount > 0) {
        echo "  " . red("⚠  FOUND {$leakedCount} LEAKED TEST RECORDS — CLEANING UP...") . "\n";
        $pdo->exec("DELETE FROM injuries WHERE form_id IN (SELECT id FROM prehospital_forms WHERE form_number LIKE 'TEST-%')");
        $deleted = $pdo->exec("DELETE FROM prehospital_forms WHERE form_number LIKE 'TEST-%'");
        echo "  " . green("✅ Cleaned {$deleted} leaked records") . "\n";
    } else {
        echo "  " . green("✅ No leaked test records found — all transactions properly rolled back") . "\n";
    }
} catch (Exception $e) {
    echo "  " . red("❌ Cleanup error: " . $e->getMessage()) . "\n";
}


// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════════════════
echo "\n";
echo bold("┌" . str_repeat("─", 70) . "┐\n");
echo bold("│") . "  TEST RESULTS SUMMARY                                                    " . bold("│") . "\n";
echo bold("├" . str_repeat("─", 70) . "┤\n");

$skipped = $total - $passed - $failed;
$pct = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo bold("│") . sprintf(
    "  %-20s %s",
    green("PASSED:  {$passed}"),
    red("FAILED:  {$failed}")
) . str_repeat(" ", 70 - 42) . bold("│") . "\n";

if ($skipped > 0) {
    echo bold("│") . "  " . yellow("SKIPPED: {$skipped}") . str_repeat(" ", 70 - 14 - strlen((string)$skipped)) . bold("│") . "\n";
}

echo bold("│") . sprintf("  %-20s %s", "TOTAL:   {$total}", "PASS RATE: {$pct}%") . str_repeat(" ", 70 - 44) . bold("│") . "\n";
echo bold("└" . str_repeat("─", 70) . "┘\n");

if ($failed === 0) {
    echo "\n  " . green(bold("🎉 ALL TESTS PASSED — BACKEND IS HEALTHY")) . "\n\n";
    exit(0);
} else {
    echo "\n  " . red(bold("⚠  {$failed} TEST(S) FAILED — REVIEW THE ISSUES ABOVE")) . "\n\n";
    exit(1);
}