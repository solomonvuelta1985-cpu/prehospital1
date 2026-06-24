<?php
/**
 * Reports & Analytics — Modern SaaS Redesign
 * Comprehensive reporting with clinical detail breakdowns
 * Part of RESQ-link Pre-Hospital Care System
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

// Get current user
$current_user = get_auth_user();
$user_id = $current_user['id'];
$is_admin = is_admin();

// ===== FILTER PARAMETERS =====
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01', strtotime('-2 months'));
$date_to   = isset($_GET['date_to'])   ? $_GET['date_to']   : date('Y-m-d');
$status_filter = isset($_GET['status'])  ? $_GET['status']  : 'all';
$user_filter   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$compare_mode  = isset($_GET['compare']) && $_GET['compare'] === '1';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-01', strtotime('-2 months'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');

// ===== BUILD WHERE CLAUSE (Sargable — no function on column) =====
$where = ["1=1"];
$params = [];

if ($date_from) {
    $where[] = "pf.form_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where[] = "pf.form_date < ? + INTERVAL 1 DAY";
    $params[] = $date_to;
}
if ($status_filter !== 'all') {
    $where[] = "pf.status = ?";
    $params[] = $status_filter;
}
if ($user_filter > 0) {
    $where[] = "pf.created_by = ?";
    $params[] = $user_filter;
} elseif (!$is_admin) {
    $where[] = "pf.created_by = ?";
    $params[] = $user_id;
}

$where_clause = implode(' AND ', $where);

// ===== COMPARISON PERIOD (if enabled) =====
$prev_where = ["1=1"];
$prev_params = [];
$prev_date_from = null;
$prev_date_to = null;

if ($compare_mode) {
    $diff = strtotime($date_to) - strtotime($date_from);
    $prev_date_to = $date_from;
    $prev_date_from = date('Y-m-d', strtotime($date_from) - $diff - 86400);

    $prev_where[] = "pf.form_date >= ?";
    $prev_params[] = $prev_date_from;
    $prev_where[] = "pf.form_date < ? + INTERVAL 1 DAY";
    $prev_params[] = $prev_date_to;
    if ($status_filter !== 'all') {
        $prev_where[] = "pf.status = ?";
        $prev_params[] = $status_filter;
    }
    if ($user_filter > 0) {
        $prev_where[] = "pf.created_by = ?";
        $prev_params[] = $user_filter;
    } elseif (!$is_admin) {
        $prev_where[] = "pf.created_by = ?";
        $prev_params[] = $user_id;
    }
    $prev_where_clause = implode(' AND ', $prev_where);
}

// ===== SUMMARY STATISTICS =====
$summary_sql = "
    SELECT
        COUNT(*) as total_forms,
        SUM(CASE WHEN pf.status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
        SUM(CASE WHEN pf.status = 'draft' THEN 1 ELSE 0 END) as draft_forms,
        SUM(CASE WHEN pf.status = 'archived' THEN 1 ELSE 0 END) as archived_forms,
        SUM(CASE WHEN pf.form_date >= CURDATE() THEN 1 ELSE 0 END) as today_forms,
        SUM(CASE WHEN YEARWEEK(pf.form_date, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as week_forms
    FROM prehospital_forms pf
    WHERE $where_clause
";
$summary_stmt = db_query($summary_sql, $params);
$summary = $summary_stmt ? $summary_stmt->fetch() : false;
if (!$summary) {
    error_log("Reports: Summary query failed or returned no rows for user {$user_id}");
    $summary = ['total_forms' => 0, 'completed_forms' => 0, 'draft_forms' => 0, 'archived_forms' => 0, 'today_forms' => 0, 'week_forms' => 0];
}

// ===== PREVIOUS PERIOD SUMMARY (if compare mode) =====
$prev_summary = null;
if ($compare_mode) {
    $prev_summary_sql = "
        SELECT
            COUNT(*) as total_forms,
            SUM(CASE WHEN pf.status = 'completed' THEN 1 ELSE 0 END) as completed_forms
        FROM prehospital_forms pf
        WHERE $prev_where_clause
    ";
    $prev_stmt = db_query($prev_summary_sql, $prev_params);
    $prev_summary = $prev_stmt ? $prev_stmt->fetch() : false;
    if (!$prev_summary) {
        $prev_summary = ['total_forms' => 0, 'completed_forms' => 0];
    }
}

// ===== EMERGENCY TYPE BREAKDOWN =====
$emergency_sql = "
    SELECT
        SUM(CASE WHEN pf.emergency_medical = 1 THEN 1 ELSE 0 END) as medical,
        SUM(CASE WHEN pf.emergency_trauma = 1 THEN 1 ELSE 0 END) as trauma,
        SUM(CASE WHEN pf.emergency_ob = 1 THEN 1 ELSE 0 END) as obstetric,
        SUM(CASE WHEN pf.emergency_general = 1 THEN 1 ELSE 0 END) as general
    FROM prehospital_forms pf
    WHERE $where_clause
";
$emergency_stmt = db_query($emergency_sql, $params);
$emergency_data = $emergency_stmt ? $emergency_stmt->fetch() : false;
if (!$emergency_data) {
    error_log("Reports: Emergency type query failed for user {$user_id}");
    $emergency_data = ['medical' => 0, 'trauma' => 0, 'obstetric' => 0, 'general' => 0];
}

// ===== VEHICULAR ACCIDENT (VA) — derived from trauma notes, respects filters =====
$va_sql = "SELECT emergency_trauma_details FROM prehospital_forms pf WHERE $where_clause AND pf.emergency_trauma = 1 AND emergency_trauma_details IS NOT NULL AND TRIM(emergency_trauma_details) <> ''";
$va_stmt = db_query($va_sql, $params);
$emergency_va = 0;
if ($va_stmt) {
    foreach ($va_stmt->fetchAll() as $row) {
        if (is_vehicular_accident($row['emergency_trauma_details'])) $emergency_va++;
    }
}
$emergency_trauma_nonva = max(0, (int)$emergency_data['trauma'] - $emergency_va);

// ===== CONSOLIDATED RUN (incident categories, classified from notes; respects filters) =====
$cr_sql = "SELECT emergency_medical, emergency_trauma, emergency_ob, emergency_general,
                  emergency_medical_details, emergency_trauma_details, emergency_ob_details, emergency_general_details
           FROM prehospital_forms pf WHERE $where_clause";
$cr_stmt = db_query($cr_sql, $params);
$cr = consolidated_run_counts($cr_stmt ? $cr_stmt->fetchAll() : []);
$cr_total = array_sum($cr['categories']);

// ===== VEHICLE USAGE =====
$vehicle_sql = "
    SELECT vehicle_used, COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause
    GROUP BY vehicle_used
    ORDER BY count DESC
";
$vehicle_stmt = db_query($vehicle_sql, $params);
$vehicle_data = $vehicle_stmt ? $vehicle_stmt->fetchAll() : [];

// ===== VEHICLE USED PANEL (clean enum + best-effort V-number list) =====
$vu_map = ['ambulance' => 'Ambulance', 'fireTruck' => 'Fire Truck', 'others' => 'Others'];
$vu_color_map = ['Ambulance' => '#4f46e5', 'Fire Truck' => '#dc2626', 'Others' => '#64748b'];
$rpt_vehicle_rows = [];
foreach ($vehicle_data as $vrow) {
    if ($vrow['vehicle_used'] === null || $vrow['vehicle_used'] === '') continue;
    $label = $vu_map[$vrow['vehicle_used']] ?? ucfirst((string)$vrow['vehicle_used']);
    $rpt_vehicle_rows[$label] = ($rpt_vehicle_rows[$label] ?? 0) + (int)$vrow['count'];
}
arsort($rpt_vehicle_rows);
$rpt_vehicle_labels = array_keys($rpt_vehicle_rows);
$rpt_vehicle_counts = array_values($rpt_vehicle_rows);
$rpt_vehicle_colors = array_map(function ($l) use ($vu_color_map) { return $vu_color_map[$l] ?? '#94a3b8'; }, $rpt_vehicle_labels);
$rpt_vehicle_total = array_sum($rpt_vehicle_counts);

// Ambulance unit (V-number) best-effort extraction from mangled vehicle_details
$amb_sql = "SELECT vehicle_details FROM prehospital_forms pf WHERE $where_clause AND pf.vehicle_used = 'ambulance' AND vehicle_details IS NOT NULL AND TRIM(vehicle_details) <> ''";
$amb_stmt = db_query($amb_sql, $params);
$rpt_amb_units = [];
if ($amb_stmt) {
    foreach ($amb_stmt->fetchAll() as $row) {
        $decoded = html_entity_decode(html_entity_decode(html_entity_decode((string)$row['vehicle_details'], ENT_QUOTES), ENT_QUOTES), ENT_QUOTES);
        if (preg_match('/\bV(\d{1,2})\b/i', $decoded, $m)) {
            $unit = 'V' . (int)$m[1];
            $rpt_amb_units[$unit] = ($rpt_amb_units[$unit] ?? 0) + 1;
        }
    }
    uksort($rpt_amb_units, function ($a, $b) { return ((int)substr($a, 1)) <=> ((int)substr($b, 1)); });
}

// ===== PATIENT DEMOGRAPHICS =====
$age_sql = "
    SELECT
        CASE
            WHEN age < 18 THEN '0-17'
            WHEN age BETWEEN 18 AND 30 THEN '18-30'
            WHEN age BETWEEN 31 AND 50 THEN '31-50'
            WHEN age BETWEEN 51 AND 70 THEN '51-70'
            WHEN age >= 71 THEN '71+'
            ELSE 'Unknown'
        END as age_group,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND age IS NOT NULL
    GROUP BY age_group
    ORDER BY FIELD(age_group, '0-17', '18-30', '31-50', '51-70', '71+', 'Unknown')
";
$age_stmt = db_query($age_sql, $params);
$age_data = $age_stmt ? $age_stmt->fetchAll() : [];

$gender_sql = "
    SELECT gender, COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND gender IS NOT NULL AND gender != ''
    GROUP BY gender
";
$gender_stmt = db_query($gender_sql, $params);
$gender_data = $gender_stmt ? $gender_stmt->fetchAll() : [];

// ===== INJURY STATISTICS =====
$injury_sql = "
    SELECT i.injury_type, COUNT(DISTINCT pf.id) as count
    FROM injuries i
    JOIN prehospital_forms pf ON i.form_id = pf.id
    WHERE $where_clause
    GROUP BY i.injury_type
    ORDER BY count DESC
    LIMIT 12
";
$injury_stmt = db_query($injury_sql, $params);
$injury_data = $injury_stmt ? $injury_stmt->fetchAll() : [];

// ===== TOP HOSPITALS =====
$hospital_sql = "
    SELECT arrival_hospital_name, COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND arrival_hospital_name IS NOT NULL AND arrival_hospital_name != ''
    GROUP BY arrival_hospital_name
    ORDER BY count DESC
    LIMIT 10
";
$hospital_stmt = db_query($hospital_sql, $params);
$hospital_data = $hospital_stmt ? $hospital_stmt->fetchAll() : [];

// ===== DAILY TRENDS =====
$trend_sql = "
    SELECT
        DATE(form_date) as date,
        COUNT(*) as count,
        SUM(CASE WHEN emergency_medical = 1 THEN 1 ELSE 0 END) as medical,
        SUM(CASE WHEN emergency_trauma = 1 THEN 1 ELSE 0 END) as trauma,
        SUM(CASE WHEN emergency_ob = 1 THEN 1 ELSE 0 END) as obstetric,
        SUM(CASE WHEN emergency_general = 1 THEN 1 ELSE 0 END) as general
    FROM prehospital_forms pf
    WHERE $where_clause
    GROUP BY DATE(form_date)
    ORDER BY DATE(form_date)
";
$trend_stmt = db_query($trend_sql, $params);
$trend_data = $trend_stmt ? $trend_stmt->fetchAll() : [];

// ===== PEAK HOURS (Time of Day) =====
$hour_sql = "
    SELECT HOUR(departure_time) as hour_of_day, COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND departure_time IS NOT NULL
    GROUP BY HOUR(departure_time)
    ORDER BY hour_of_day
";
$hour_stmt = db_query($hour_sql, $params);
$hour_data = $hour_stmt ? $hour_stmt->fetchAll() : [];

// ===== MEDICAL CHIEF COMPLAINTS BREAKDOWN =====
$medical_complaints = [];
$medical_details = [];
$medical_vitals = ['bp' => [], 'temp' => [], 'pulse' => [], 'resp' => [], 'spo2' => []];

$med_sql = "
    SELECT chief_complaints, emergency_medical_details,
           initial_bp, initial_temp, initial_pulse, initial_resp_rate, initial_spo2
    FROM prehospital_forms pf
    WHERE $where_clause AND emergency_medical = 1
";
$med_stmt = db_query($med_sql, $params);
if ($med_stmt) {
    $med_rows = $med_stmt->fetchAll();
    $complaint_counts = [];
    foreach ($med_rows as $row) {
        // Aggregate chief complaints (JSON array)
        $complaints = json_decode($row['chief_complaints'], true);
        if (is_array($complaints)) {
            foreach ($complaints as $c) {
                $complaint_counts[$c] = ($complaint_counts[$c] ?? 0) + 1;
            }
        }
        // Aggregate specified details
        if (!empty($row['emergency_medical_details'])) {
            $detail = trim($row['emergency_medical_details']);
            $medical_details[$detail] = ($medical_details[$detail] ?? 0) + 1;
        }
        // Aggregate vitals
        if ($row['initial_bp']) $medical_vitals['bp'][] = $row['initial_bp'];
        if ($row['initial_temp']) $medical_vitals['temp'][] = floatval($row['initial_temp']);
        if ($row['initial_pulse']) $medical_vitals['pulse'][] = intval($row['initial_pulse']);
        if ($row['initial_resp_rate']) $medical_vitals['resp'][] = intval($row['initial_resp_rate']);
        if ($row['initial_spo2']) $medical_vitals['spo2'][] = intval($row['initial_spo2']);
    }
    arsort($complaint_counts);
    $medical_complaints = array_slice($complaint_counts, 0, 10);
    arsort($medical_details);
    $medical_details = array_slice($medical_details, 0, 10);
}

// ===== TRAUMA DETAILS =====
$trauma_injuries = [];
$trauma_sql = "
    SELECT i.injury_type, i.body_part, COUNT(DISTINCT pf.id) as count
    FROM injuries i
    JOIN prehospital_forms pf ON i.form_id = pf.id
    WHERE $where_clause AND pf.emergency_trauma = 1
    GROUP BY i.injury_type, i.body_part
    ORDER BY count DESC
    LIMIT 15
";
$trauma_stmt = db_query($trauma_sql, $params);
if ($trauma_stmt) {
    $trauma_injuries = $trauma_stmt->fetchAll();
}

// ===== OB DETAILS =====
$ob_details = [];
$ob_specify_counts = [];
$ob_sql = "
    SELECT emergency_ob_details, ob_lmp, ob_edc, ob_delivery_time
    FROM prehospital_forms pf
    WHERE $where_clause AND emergency_ob = 1
";
$ob_stmt = db_query($ob_sql, $params);
if ($ob_stmt) {
    $ob_rows = $ob_stmt->fetchAll();
    $ob_total = count($ob_rows);
    $delivery_count = 0;
    foreach ($ob_rows as $row) {
        if (!empty($row['emergency_ob_details'])) {
            $detail = trim($row['emergency_ob_details']);
            $ob_specify_counts[$detail] = ($ob_specify_counts[$detail] ?? 0) + 1;
        }
        if (!empty($row['ob_delivery_time'])) {
            $delivery_count++;
        }
    }
    arsort($ob_specify_counts);
    $ob_details = [
        'total' => $ob_total,
        'deliveries' => $delivery_count,
        'conditions' => array_slice($ob_specify_counts, 0, 10),
    ];
}

// ===== USER PERFORMANCE (Admin Only) =====
$user_performance = [];
if ($is_admin) {
    $user_perf_sql = "
        SELECT
            u.full_name, u.username,
            COUNT(pf.id) as total_forms,
            SUM(CASE WHEN pf.status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
            ROUND(SUM(CASE WHEN pf.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(pf.id), 0), 1) as completion_rate
        FROM users u
        LEFT JOIN prehospital_forms pf ON u.id = pf.created_by AND $where_clause
        WHERE u.role IN ('admin', 'user')
        GROUP BY u.id, u.full_name, u.username
        HAVING total_forms > 0
        ORDER BY total_forms DESC
        LIMIT 10
    ";
    $user_perf_stmt = db_query($user_perf_sql, $params);
    $user_performance = $user_perf_stmt ? $user_perf_stmt->fetchAll() : [];

    // All users for filter dropdown
    $users_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('admin', 'user') ORDER BY full_name");
    $all_users = $users_stmt->fetchAll();
}

// ===== BARANGAY BREAKDOWN (normalized address; respects filters) =====
$rpt_barangay_rows = [];
$rpt_barangay_labels = [];
$rpt_barangay_data = [];
$bgy_sql = "SELECT address FROM prehospital_forms pf WHERE $where_clause AND address IS NOT NULL AND TRIM(address) <> ''";
$bgy_stmt = db_query($bgy_sql, $params);
if ($bgy_stmt) {
    $bgy_counts = [];
    foreach ($bgy_stmt->fetchAll() as $row) {
        $name = normalize_barangay($row['address']);
        if ($name === 'UNSPECIFIED') continue;
        $bgy_counts[$name] = ($bgy_counts[$name] ?? 0) + 1;
    }
    arsort($bgy_counts);
    foreach (array_slice($bgy_counts, 0, 8, true) as $name => $cnt) {
        $title = ucwords(strtolower($name));
        $rpt_barangay_rows[] = ['name' => $title, 'count' => $cnt];
        $rpt_barangay_labels[] = mb_strimwidth($title, 0, 22, '...');
        $rpt_barangay_data[] = (int)$cnt;
    }
}

// ===== RESPONSE-TIME METRICS (exclude NULL / 00:00:00 / implausible) =====
// Legs (minutes): dispatch->scene, scene->hospital, total turnaround.
// TIMEDIFF on TIME columns; guard against negatives and >12h outliers.
$rt_sql = "
    SELECT
        AVG(CASE WHEN departure_time > '00:00:00' AND arrival_scene_time > '00:00:00'
                  AND TIME_TO_SEC(TIMEDIFF(arrival_scene_time, departure_time)) BETWEEN 1 AND 43200
                 THEN TIME_TO_SEC(TIMEDIFF(arrival_scene_time, departure_time)) END) AS dispatch_to_scene,
        AVG(CASE WHEN departure_scene_time > '00:00:00' AND arrival_hospital_time > '00:00:00'
                  AND TIME_TO_SEC(TIMEDIFF(arrival_hospital_time, departure_scene_time)) BETWEEN 1 AND 43200
                 THEN TIME_TO_SEC(TIMEDIFF(arrival_hospital_time, departure_scene_time)) END) AS scene_to_hospital,
        AVG(CASE WHEN departure_time > '00:00:00' AND arrival_station_time > '00:00:00'
                  AND TIME_TO_SEC(TIMEDIFF(arrival_station_time, departure_time)) BETWEEN 1 AND 86400
                 THEN TIME_TO_SEC(TIMEDIFF(arrival_station_time, departure_time)) END) AS total_turnaround,
        SUM(CASE WHEN departure_time > '00:00:00' AND arrival_scene_time > '00:00:00'
                  AND TIME_TO_SEC(TIMEDIFF(arrival_scene_time, departure_time)) BETWEEN 1 AND 43200
                 THEN 1 ELSE 0 END) AS rt_n
    FROM prehospital_forms pf
    WHERE $where_clause
";
$rt_stmt = db_query($rt_sql, $params);
$rt = $rt_stmt ? $rt_stmt->fetch() : false;
$response_times = [
    'dispatch_to_scene' => $rt && $rt['dispatch_to_scene'] !== null ? round($rt['dispatch_to_scene'] / 60) : null,
    'scene_to_hospital' => $rt && $rt['scene_to_hospital'] !== null ? round($rt['scene_to_hospital'] / 60) : null,
    'total_turnaround'  => $rt && $rt['total_turnaround']  !== null ? round($rt['total_turnaround'] / 60)  : null,
    'n' => $rt ? (int)$rt['rt_n'] : 0,
];

// ===== OUTCOME / DISPOSITION (inferred from existing fields) =====
$outcome_sql = "
    SELECT
        SUM(CASE WHEN arrival_hospital_name IS NOT NULL AND TRIM(arrival_hospital_name) <> '' THEN 1 ELSE 0 END) AS transported,
        SUM(CASE WHEN (arrival_hospital_name IS NULL OR TRIM(arrival_hospital_name) = '')
                  AND waiver_patient_signature IS NOT NULL AND TRIM(waiver_patient_signature) <> '' THEN 1 ELSE 0 END) AS refused,
        COUNT(*) AS total
    FROM prehospital_forms pf
    WHERE $where_clause
";
$outcome_stmt = db_query($outcome_sql, $params);
$oc = $outcome_stmt ? $outcome_stmt->fetch() : false;
$oc_transported = $oc ? (int)$oc['transported'] : 0;
$oc_refused = $oc ? (int)$oc['refused'] : 0;
$oc_total = $oc ? (int)$oc['total'] : 0;
$oc_other = max(0, $oc_total - $oc_transported - $oc_refused);
$outcome_rows = [
    ['Transported', $oc_transported, '#059669'],
    ['Refused / Waiver', $oc_refused, '#d97706'],
    ['Other / Unknown', $oc_other, '#94a3b8'],
];

// ===== PREVIOUS-PERIOD DAILY TREND (for compare overlay) =====
$prev_trend_data = [];
if ($compare_mode) {
    $prev_trend_sql = "
        SELECT DATE(form_date) as date, COUNT(*) as count
        FROM prehospital_forms pf
        WHERE $prev_where_clause
        GROUP BY DATE(form_date)
        ORDER BY DATE(form_date)
    ";
    $prev_trend_stmt = db_query($prev_trend_sql, $prev_params);
    $prev_trend_data = $prev_trend_stmt ? $prev_trend_stmt->fetchAll() : [];
}

// ===== TREND CALCULATIONS (for stat card deltas) =====
function calc_trend($current, $previous) {
    if ($previous == 0) return ['direction' => 'neutral', 'pct' => null];
    $pct = round(($current - $previous) / $previous * 100, 1);
    if ($pct > 0) return ['direction' => 'up', 'pct' => abs($pct)];
    if ($pct < 0) return ['direction' => 'down', 'pct' => abs($pct)];
    return ['direction' => 'neutral', 'pct' => 0];
}

$total_trend = null;
$completed_trend = null;
$completion_rate = $summary['total_forms'] > 0 ? round(($summary['completed_forms'] / $summary['total_forms']) * 100, 1) : 0;

if ($prev_summary) {
    $total_trend = calc_trend($summary['total_forms'], $prev_summary['total_forms']);
    $completed_trend = calc_trend($summary['completed_forms'], $prev_summary['completed_forms']);
}

// Average daily forms
$days_in_range = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400 + 1);
$avg_daily = round($summary['total_forms'] / $days_in_range, 1);

// Hero greeting
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$first_name = trim(explode(' ', trim($current_user['full_name'] ?? ($current_user['username'] ?? 'User')))[0]);
$current_date = date('l, F j, Y');

// Emergency type rows for the donut legend list (label, value, color)
// Trauma split into Vehicular Accident (derived) + other Trauma so slices still sum.
$rpt_type_rows = [
    ['Medical',             (int)$emergency_data['medical'],   '#4f46e5'],
    ['Trauma (non-VA)',     $emergency_trauma_nonva,           '#dc2626'],
    ['Vehicular Accident',  $emergency_va,                     '#f59e0b'],
    ['OB/GYN',              (int)$emergency_data['obstetric'], '#7c3aed'],
    ['General',             (int)$emergency_data['general'],   '#d97706'],
];
$rpt_type_total = array_sum(array_column($rpt_type_rows, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics — Pre-Hospital Care System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/reports-style.css?v=<?php echo md5_file(__DIR__ . '/css/reports-style.css'); ?>" rel="stylesheet">
</head>
<body class="rpt-reports-page">
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid rpt-wrap">
            <!-- ===== BRANDED EMS HERO ===== -->
            <div class="rpt-hero">
                <div class="rpt-hero-brand">
                    <div class="rpt-hero-logo"><i class="bi bi-bar-chart-fill"></i></div>
                    <div>
                        <h1 class="rpt-hero-title">REPORTS &amp; ANALYTICS</h1>
                        <p class="rpt-hero-sub">Pre-Hospital EMS &middot; Clinical Intelligence</p>
                        <p class="rpt-hero-meta"><?php echo $greeting; ?>, <?php echo e($first_name); ?> &nbsp;&bull;&nbsp; <?php echo $current_date; ?></p>
                    </div>
                </div>
                <div class="rpt-hero-right">
                    <button class="rpt-compare-toggle<?php echo $compare_mode ? ' active' : ''; ?>" id="rptCompareToggle" aria-label="Toggle period comparison">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Compare Periods</span>
                    </button>
                </div>
            </div>

            <?php show_flash(); ?>

            <!-- ===== FILTER BAR ===== -->
            <div class="rpt-filter-bar">
                <form method="GET" action="reports.php" id="rptFilterForm">
                    <!-- Date Preset Pills -->
                    <div class="rpt-preset-pills">
                        <button type="button" class="rpt-preset-pill" data-range="today">Today</button>
                        <button type="button" class="rpt-preset-pill" data-range="week">This Week</button>
                        <button type="button" class="rpt-preset-pill" data-range="month">This Month</button>
                        <button type="button" class="rpt-preset-pill" data-range="lastMonth">Last Month</button>
                        <button type="button" class="rpt-preset-pill" data-range="quarter">Last 3 Months</button>
                        <button type="button" class="rpt-preset-pill active" data-range="custom">Custom</button>
                    </div>

                    <div class="rpt-filter-row" style="margin-top: 0.75rem;">
                        <div class="rpt-filter-group">
                            <label class="rpt-filter-label" for="date_from">From</label>
                            <input type="date" class="rpt-filter-input" id="date_from" name="date_from" value="<?php echo e($date_from); ?>">
                        </div>
                        <div class="rpt-filter-group">
                            <label class="rpt-filter-label" for="date_to">To</label>
                            <input type="date" class="rpt-filter-input" id="date_to" name="date_to" value="<?php echo e($date_to); ?>">
                        </div>
                        <div class="rpt-filter-group">
                            <label class="rpt-filter-label" for="status">Status</label>
                            <select class="rpt-filter-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <?php if ($is_admin): ?>
                        <div class="rpt-filter-group">
                            <label class="rpt-filter-label" for="user_id">User</label>
                            <select class="rpt-filter-select" id="user_id" name="user_id">
                                <option value="0">All Users</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="rpt-filter-group" style="justify-content: flex-end;">
                            <label class="rpt-filter-label">&nbsp;</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="submit" class="rpt-btn-apply">
                                    <i class="bi bi-funnel"></i> Apply
                                </button>
                                <a href="reports.php" class="rpt-btn-reset" data-action="resetFilters">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Active Filter Chips -->
                    <?php if ($status_filter !== 'all' || $user_filter > 0): ?>
                    <div class="rpt-filter-chips">
                        <?php if ($status_filter !== 'all'): ?>
                        <span class="rpt-filter-chip">
                            Status: <?php echo e(ucfirst($status_filter)); ?>
                            <button type="button" class="rpt-filter-chip-dismiss" data-filter-param="status" aria-label="Remove status filter">&times;</button>
                        </span>
                        <?php endif; ?>
                        <?php if ($user_filter > 0 && $is_admin): ?>
                        <span class="rpt-filter-chip">
                            User: <?php 
                                $filtered_user = array_filter($all_users, fn($u) => $u['id'] == $user_filter);
                                echo e(($filtered_user ? reset($filtered_user)['full_name'] : 'Unknown'));
                            ?>
                            <button type="button" class="rpt-filter-chip-dismiss" data-filter-param="user_id" aria-label="Remove user filter">&times;</button>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ===== 6 KPI STAT CARDS ===== -->
            <div class="rpt-stats-grid">
                <!-- Total Forms -->
                <div class="rpt-stat-card accent-indigo rpt-animate-in">
                    <div class="rpt-stat-body">
                        <div class="rpt-stat-label">Total Forms</div>
                        <div class="rpt-stat-value"><?php echo number_format((int)$summary['total_forms']); ?></div>
                        <div class="rpt-stat-sub"><?php echo e($date_from); ?> — <?php echo e($date_to); ?></div>
                        <?php if ($total_trend): ?>
                        <span class="rpt-stat-trend rpt-stat-trend-inline <?php echo $total_trend['direction']; ?>">
                            <i class="bi bi-arrow-<?php echo $total_trend['direction'] === 'up' ? 'up' : ($total_trend['direction'] === 'down' ? 'down' : 'dash'); ?>"></i>
                            <?php echo $total_trend['pct']; ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="rpt-stat-icon indigo"><i class="bi bi-file-earmark-text"></i></div>
                </div>

                <!-- Completed -->
                <div class="rpt-stat-card accent-emerald rpt-animate-in">
                    <div class="rpt-stat-body">
                        <div class="rpt-stat-label">Completed</div>
                        <div class="rpt-stat-value"><?php echo number_format((int)$summary['completed_forms']); ?></div>
                        <div class="rpt-stat-sub"><?php echo $completion_rate; ?>% completion rate</div>
                        <?php if ($completed_trend): ?>
                        <span class="rpt-stat-trend rpt-stat-trend-inline <?php echo $completed_trend['direction']; ?>">
                            <i class="bi bi-arrow-<?php echo $completed_trend['direction'] === 'up' ? 'up' : ($completed_trend['direction'] === 'down' ? 'down' : 'dash'); ?>"></i>
                            <?php echo $completed_trend['pct']; ?>%
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="rpt-stat-icon emerald"><i class="bi bi-check-circle"></i></div>
                </div>

                <!-- In Progress -->
                <div class="rpt-stat-card accent-amber rpt-animate-in">
                    <div class="rpt-stat-body">
                        <div class="rpt-stat-label">In Progress</div>
                        <div class="rpt-stat-value"><?php echo number_format((int)$summary['draft_forms']); ?></div>
                        <div class="rpt-stat-sub">Draft forms awaiting completion</div>
                    </div>
                    <div class="rpt-stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
                </div>

                <!-- This Week -->
                <div class="rpt-stat-card accent-purple rpt-animate-in">
                    <div class="rpt-stat-body">
                        <div class="rpt-stat-label">This Week</div>
                        <div class="rpt-stat-value"><?php echo number_format((int)$summary['week_forms']); ?></div>
                        <div class="rpt-stat-sub">Forms created this calendar week</div>
                    </div>
                    <div class="rpt-stat-icon purple"><i class="bi bi-calendar-week"></i></div>
                </div>

                <!-- Completion Rate -->
                <div class="rpt-stat-card accent-teal rpt-animate-in">
                    <div class="rpt-stat-body">
                        <div class="rpt-stat-label">Completion Rate</div>
                        <div class="rpt-stat-value"><?php echo $completion_rate; ?>%</div>
                        <div class="rpt-stat-sub"><?php echo number_format((int)$summary['completed_forms']); ?> of <?php echo number_format((int)$summary['total_forms']); ?></div>
                    </div>
                    <div class="rpt-stat-icon teal"><i class="bi bi-graph-up-arrow"></i></div>
                </div>

                <!-- Avg Daily -->
                <div class="rpt-stat-card accent-rose rpt-animate-in">
                    <div class="rpt-stat-body">
                        <div class="rpt-stat-label">Avg Daily</div>
                        <div class="rpt-stat-value"><?php echo $avg_daily; ?></div>
                        <div class="rpt-stat-sub">Forms per day (<?php echo (int)$days_in_range; ?> days)</div>
                    </div>
                    <div class="rpt-stat-icon rose"><i class="bi bi-speedometer2"></i></div>
                </div>
            </div>

            <hr class="rpt-section-divider">
            <h2 class="rpt-band-title"><span class="rpt-icon-dot"></span> Analytics Overview</h2>

            <!-- ===== MAIN TREND CHART (Full Width) ===== -->
            <div class="rpt-section">
                <div class="rpt-section-header">
                    <h2 class="rpt-section-title">
                        <i class="bi bi-graph-up"></i> Forms Over Time
                    </h2>
                    <span class="rpt-section-badge"><?php echo count($trend_data); ?> days</span>
                </div>
                <div class="rpt-section-body">
                    <div class="rpt-chart-container chart-lg">
                        <canvas id="rptTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Emergency Types (donut + legend) beside Status donut -->
            <div class="rpt-grid-2-1">
                <!-- Emergency Type Breakdown -->
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-activity"></i> Emergency Types
                        </h2>
                        <span class="rpt-section-badge"><?php echo number_format($rpt_type_total); ?> total</span>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-donut-legend">
                            <div class="rpt-chart-container chart-md">
                                <canvas id="rptEmergencyChart"></canvas>
                            </div>
                            <ul class="rpt-legend-list">
                                <?php foreach ($rpt_type_rows as $tr):
                                    $pct = $rpt_type_total > 0 ? round(($tr[1] / $rpt_type_total) * 100, 1) : 0;
                                ?>
                                <li>
                                    <span class="rpt-legend-swatch" style="background: <?php echo $tr[2]; ?>"></span>
                                    <span class="rpt-legend-name"><?php echo $tr[0]; ?></span>
                                    <span class="rpt-legend-val"><?php echo number_format($tr[1]); ?></span>
                                    <span class="rpt-legend-pct"><?php echo $pct; ?>%</span>
                                </li>
                                <?php endforeach; ?>
                                <li class="rpt-legend-total"><span>Total</span><span><?php echo number_format($rpt_type_total); ?></span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Status Distribution -->
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-pie-chart"></i> Status Distribution
                        </h2>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-md">
                            <canvas id="rptStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Peak Hours (full width) -->
            <div class="rpt-section">
                <div class="rpt-section-header">
                    <h2 class="rpt-section-title">
                        <i class="bi bi-clock"></i> Peak Hours (Time of Day)
                    </h2>
                </div>
                <div class="rpt-section-body">
                    <div class="rpt-chart-container chart-md">
                        <canvas id="rptPeakHoursChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Response time + Outcomes -->
            <div class="rpt-grid-2-1">
                <!-- Response-time metrics -->
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-stopwatch"></i> Average Response Times
                        </h2>
                        <span class="rpt-section-badge">over <?php echo number_format($response_times['n']); ?> records</span>
                    </div>
                    <div class="rpt-section-body">
                        <?php if ($response_times['n'] > 0): ?>
                        <div class="rpt-vital-grid" style="grid-template-columns: repeat(3, 1fr);">
                            <div class="rpt-vital-tile">
                                <div class="rpt-vital-tile-label">Dispatch → Scene</div>
                                <div class="rpt-vital-tile-value"><?php echo $response_times['dispatch_to_scene'] !== null ? $response_times['dispatch_to_scene'] . ' <small>min</small>' : '—'; ?></div>
                            </div>
                            <div class="rpt-vital-tile">
                                <div class="rpt-vital-tile-label">Scene → Hospital</div>
                                <div class="rpt-vital-tile-value"><?php echo $response_times['scene_to_hospital'] !== null ? $response_times['scene_to_hospital'] . ' <small>min</small>' : '—'; ?></div>
                            </div>
                            <div class="rpt-vital-tile">
                                <div class="rpt-vital-tile-label">Total Turnaround</div>
                                <div class="rpt-vital-tile-value"><?php echo $response_times['total_turnaround'] !== null ? $response_times['total_turnaround'] . ' <small>min</small>' : '—'; ?></div>
                            </div>
                        </div>
                        <p style="margin:0.85rem 0 0;font-size:0.7rem;color:var(--rpt-gray-400);">Averages exclude records with missing or placeholder times.</p>
                        <?php else: ?>
                        <div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-stopwatch"></i></div><div class="rpt-empty-title">No timing data</div><div class="rpt-empty-desc">Response-time averages appear once records include valid dispatch/arrival times.</div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Outcome / disposition -->
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-clipboard2-check"></i> Outcomes
                        </h2>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-sm">
                            <canvas id="rptOutcomeChart"></canvas>
                        </div>
                        <ul class="rpt-legend-list" style="margin-top:0.75rem;">
                            <?php foreach ($outcome_rows as $orow):
                                $opct = $oc_total > 0 ? round(($orow[1] / $oc_total) * 100, 1) : 0; ?>
                            <li>
                                <span class="rpt-legend-swatch" style="background: <?php echo $orow[2]; ?>"></span>
                                <span class="rpt-legend-name"><?php echo $orow[0]; ?></span>
                                <span class="rpt-legend-val"><?php echo number_format($orow[1]); ?></span>
                                <span class="rpt-legend-pct"><?php echo $opct; ?>%</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ===== CONSOLIDATED RUN (incident categories) ===== -->
            <hr class="rpt-section-divider">
            <h2 class="rpt-band-title"><span class="rpt-icon-dot"></span> Consolidated Run</h2>
            <div class="rpt-grid-2-1">
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-clipboard2-data"></i> Incident Categories</h2>
                        <span class="rpt-section-badge"><?php echo number_format($cr_total); ?> classified</span>
                    </div>
                    <div class="rpt-section-body">
                        <?php if (!empty($cr['categories'])): $cr_max = max($cr['categories']); ?>
                        <ul class="rpt-bar-list">
                            <?php foreach ($cr['categories'] as $cat => $cnt): $w = $cr_max > 0 ? round(($cnt / $cr_max) * 100) : 0; ?>
                            <li class="rpt-bar-row">
                                <div class="rpt-bar-row-top">
                                    <span class="rpt-bar-row-label"><?php echo e($cat); ?></span>
                                    <span class="rpt-bar-row-val"><?php echo number_format($cnt); ?></span>
                                </div>
                                <div class="rpt-bar-track"><div class="rpt-bar-fill" style="width: <?php echo $w; ?>%"></div></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <p style="margin:0.85rem 0 0;font-size:0.7rem;color:var(--rpt-gray-400);">Categorized from incident notes — counts may differ from free-text spelling variants.</p>
                        <?php else: ?>
                        <div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-clipboard2-x"></i></div><div class="rpt-empty-title">No categorized incidents</div><div class="rpt-empty-desc">No records in this period matched a known incident category.</div></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-collection"></i> By Emergency Type</h2>
                    </div>
                    <div class="rpt-section-body">
                        <ul class="rpt-rank-list">
                            <?php $cp = 0; foreach ($cr['parents'] as $pl => $pc): $cp++; $rc = $cp <= 3 ? 'r'.$cp : ''; ?>
                            <li>
                                <span class="rpt-rank-badge <?php echo $rc; ?>"><i class="bi bi-dot"></i></span>
                                <span class="rpt-rank-name"><?php echo e($pl); ?></span>
                                <span class="rpt-rank-count"><?php echo number_format($pc); ?></span>
                            </li>
                            <?php endforeach; ?>
                            <li>
                                <span class="rpt-rank-badge" style="background:var(--rpt-gray-200);color:var(--rpt-gray-500);"><i class="bi bi-question"></i></span>
                                <span class="rpt-rank-name">Uncategorized</span>
                                <span class="rpt-rank-count"><?php echo number_format($cr['uncategorized']); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ===== VEHICLE USED ===== -->
            <?php if (!empty($rpt_vehicle_labels)): ?>
            <div class="rpt-grid-1-1-1">
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-truck"></i> Vehicle Used</h2>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-sm">
                            <canvas id="rptVehicleUsedChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-list-ul"></i> Vehicle Breakdown</h2>
                        <span class="rpt-section-badge"><?php echo number_format($rpt_vehicle_total); ?> total</span>
                    </div>
                    <div class="rpt-section-body">
                        <ul class="rpt-legend-list">
                            <?php foreach ($rpt_vehicle_labels as $i => $vlabel):
                                $vpct = $rpt_vehicle_total > 0 ? round(($rpt_vehicle_counts[$i] / $rpt_vehicle_total) * 100, 1) : 0; ?>
                            <li>
                                <span class="rpt-legend-swatch" style="background: <?php echo $rpt_vehicle_colors[$i]; ?>"></span>
                                <span class="rpt-legend-name"><?php echo e($vlabel); ?></span>
                                <span class="rpt-legend-val"><?php echo number_format($rpt_vehicle_counts[$i]); ?></span>
                                <span class="rpt-legend-pct"><?php echo $vpct; ?>%</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-123"></i> Ambulance Units</h2>
                    </div>
                    <div class="rpt-section-body">
                        <?php if (!empty($rpt_amb_units)): ?>
                        <ul class="rpt-rank-list">
                            <?php $vi = 0; foreach ($rpt_amb_units as $unit => $cnt): $vi++; $rc = $vi <= 3 ? 'r'.$vi : ''; ?>
                            <li>
                                <span class="rpt-rank-badge <?php echo $rc; ?>"><?php echo e($unit); ?></span>
                                <span class="rpt-rank-name">Ambulance <?php echo e($unit); ?></span>
                                <span class="rpt-rank-count"><?php echo number_format($cnt); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p style="color:#94a3b8;font-size:0.8125rem;">No unit IDs recorded (vehicle detail data unavailable).</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== CLINICAL DETAIL PANELS ===== -->
            <?php if ((int)$emergency_data['medical'] > 0 || ((int)$emergency_data['trauma'] > 0 && !empty($trauma_injuries)) || (int)$emergency_data['obstetric'] > 0): ?>
            <hr class="rpt-section-divider">
            <h2 class="rpt-band-title"><span class="rpt-icon-dot"></span> Clinical Detail Breakdown</h2>
            <?php endif; ?>

            <?php if ((int)$emergency_data['medical'] > 0): ?>
            <!-- Medical Clinical Detail -->
            <div class="rpt-clinical-panel open">
                <button type="button" class="rpt-clinical-toggle" aria-expanded="true">
                    <span class="rpt-clinical-toggle-icon medical"><i class="bi bi-heart-pulse-fill"></i></span>
                    <span class="rpt-clinical-toggle-main">
                        <span class="rpt-clinical-toggle-title">Medical Cases</span>
                        <span class="rpt-clinical-toggle-desc">Chief complaints, conditions &amp; vital sign averages</span>
                    </span>
                    <span class="rpt-clinical-toggle-count"><?php echo number_format((int)$emergency_data['medical']); ?> cases</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="rpt-clinical-body">
                    <div class="rpt-clinical-grid">
                        <!-- Chief Complaints -->
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-list-check"></i> Top Chief Complaints</div>
                            <?php if (!empty($medical_complaints)): $cc_max = max($medical_complaints); ?>
                            <ul class="rpt-bar-list">
                                <?php foreach ($medical_complaints as $complaint => $cnt): $w = $cc_max > 0 ? round(($cnt / $cc_max) * 100) : 0; ?>
                                <li class="rpt-bar-row">
                                    <div class="rpt-bar-row-top">
                                        <span class="rpt-bar-row-label"><?php echo e(formatComplaintLabel($complaint)); ?></span>
                                        <span class="rpt-bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="rpt-bar-track"><div class="rpt-bar-fill green" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p style="color: #94a3b8; font-size: 0.8125rem;">No chief complaint data recorded.</p>
                            <?php endif; ?>
                        </div>
                        <!-- Specified Conditions -->
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-clipboard2-pulse"></i> Specified Conditions</div>
                            <?php if (!empty($medical_details)): $md_max = max($medical_details); ?>
                            <ul class="rpt-bar-list">
                                <?php foreach ($medical_details as $detail => $cnt): $w = $md_max > 0 ? round(($cnt / $md_max) * 100) : 0; ?>
                                <li class="rpt-bar-row">
                                    <div class="rpt-bar-row-top">
                                        <span class="rpt-bar-row-label"><?php echo e($detail); ?></span>
                                        <span class="rpt-bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="rpt-bar-track"><div class="rpt-bar-fill green" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p style="color: #94a3b8; font-size: 0.8125rem;">No specified condition details recorded.</p>
                            <?php endif; ?>
                        </div>
                        <!-- Vital Signs Summary -->
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-activity"></i> Vital Signs (Averages)</div>
                            <div class="rpt-vital-grid">
                                <div class="rpt-vital-tile">
                                    <div class="rpt-vital-tile-label">Systolic BP</div>
                                    <div class="rpt-vital-tile-value"><?php
                                        if (!empty($medical_vitals['bp'])) {
                                            $bps = [];
                                            foreach ($medical_vitals['bp'] as $bp) { $parts = explode('/', $bp); $bps[] = intval($parts[0]); }
                                            echo round(array_sum($bps) / count($bps)) . ' <small>mmHg</small>';
                                        } else { echo '—'; }
                                    ?></div>
                                </div>
                                <div class="rpt-vital-tile">
                                    <div class="rpt-vital-tile-label">Temperature</div>
                                    <div class="rpt-vital-tile-value"><?php echo !empty($medical_vitals['temp']) ? round(array_sum($medical_vitals['temp']) / count($medical_vitals['temp']), 1) . ' <small>°C</small>' : '—'; ?></div>
                                </div>
                                <div class="rpt-vital-tile">
                                    <div class="rpt-vital-tile-label">Pulse Rate</div>
                                    <div class="rpt-vital-tile-value"><?php echo !empty($medical_vitals['pulse']) ? round(array_sum($medical_vitals['pulse']) / count($medical_vitals['pulse'])) . ' <small>BPM</small>' : '—'; ?></div>
                                </div>
                                <div class="rpt-vital-tile">
                                    <div class="rpt-vital-tile-label">SpO₂</div>
                                    <div class="rpt-vital-tile-value"><?php echo !empty($medical_vitals['spo2']) ? round(array_sum($medical_vitals['spo2']) / count($medical_vitals['spo2'])) . ' <small>%</small>' : '—'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ((int)$emergency_data['trauma'] > 0 && !empty($trauma_injuries)): ?>
            <!-- Trauma Clinical Detail -->
            <?php
            // Pre-aggregate injury types & body parts for the bars
            $rpt_injury_types = [];
            foreach ($trauma_injuries as $inj) {
                $t = $inj['injury_type'] ?: 'Unspecified';
                $rpt_injury_types[$t] = ($rpt_injury_types[$t] ?? 0) + (int)$inj['count'];
            }
            arsort($rpt_injury_types);
            $rpt_injury_types = array_slice($rpt_injury_types, 0, 10, true);
            $rpt_it_max = !empty($rpt_injury_types) ? max($rpt_injury_types) : 0;

            $rpt_body_parts = [];
            foreach ($trauma_injuries as $inj) {
                if (!empty($inj['body_part'])) { $rpt_body_parts[$inj['body_part']] = ($rpt_body_parts[$inj['body_part']] ?? 0) + (int)$inj['count']; }
            }
            arsort($rpt_body_parts);
            $rpt_body_parts = array_slice($rpt_body_parts, 0, 10, true);
            $rpt_bp_max = !empty($rpt_body_parts) ? max($rpt_body_parts) : 0;
            ?>
            <div class="rpt-clinical-panel">
                <button type="button" class="rpt-clinical-toggle" aria-expanded="false">
                    <span class="rpt-clinical-toggle-icon trauma"><i class="bi bi-bandaid-fill"></i></span>
                    <span class="rpt-clinical-toggle-main">
                        <span class="rpt-clinical-toggle-title">Trauma Cases</span>
                        <span class="rpt-clinical-toggle-desc">Injury types &amp; body parts affected</span>
                    </span>
                    <span class="rpt-clinical-toggle-count"><?php echo number_format((int)$emergency_data['trauma']); ?> cases</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="rpt-clinical-body">
                    <div class="rpt-clinical-grid">
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-exclamation-triangle"></i> Injury Types</div>
                            <?php if (!empty($rpt_injury_types)): ?>
                            <ul class="rpt-bar-list">
                                <?php foreach ($rpt_injury_types as $type => $cnt): $w = $rpt_it_max > 0 ? round(($cnt / $rpt_it_max) * 100) : 0; ?>
                                <li class="rpt-bar-row">
                                    <div class="rpt-bar-row-top">
                                        <span class="rpt-bar-row-label"><?php echo e($type); ?></span>
                                        <span class="rpt-bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="rpt-bar-track"><div class="rpt-bar-fill rose" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p style="color:#94a3b8;font-size:0.8125rem;">No injury type data recorded.</p><?php endif; ?>
                        </div>
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-person-bounding-box"></i> Body Parts Affected</div>
                            <?php if (!empty($rpt_body_parts)): ?>
                            <ul class="rpt-bar-list">
                                <?php foreach ($rpt_body_parts as $part => $cnt): $w = $rpt_bp_max > 0 ? round(($cnt / $rpt_bp_max) * 100) : 0; ?>
                                <li class="rpt-bar-row">
                                    <div class="rpt-bar-row-top">
                                        <span class="rpt-bar-row-label"><?php echo e($part); ?></span>
                                        <span class="rpt-bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="rpt-bar-track"><div class="rpt-bar-fill rose" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p style="color:#94a3b8;font-size:0.8125rem;">No body part data recorded.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ((int)$emergency_data['obstetric'] > 0): ?>
            <!-- OB Clinical Detail -->
            <div class="rpt-clinical-panel">
                <button type="button" class="rpt-clinical-toggle" aria-expanded="false">
                    <span class="rpt-clinical-toggle-icon ob"><i class="bi bi-gender-female"></i></span>
                    <span class="rpt-clinical-toggle-main">
                        <span class="rpt-clinical-toggle-title">Obstetric Cases</span>
                        <span class="rpt-clinical-toggle-desc">Field deliveries &amp; specified OB conditions</span>
                    </span>
                    <span class="rpt-clinical-toggle-count"><?php echo number_format((int)$emergency_data['obstetric']); ?> cases</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="rpt-clinical-body">
                    <div class="rpt-clinical-grid">
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-clipboard2-data"></i> Quick Stats</div>
                            <div class="rpt-vital-grid">
                                <div class="rpt-vital-tile">
                                    <div class="rpt-vital-tile-label">Total OB Cases</div>
                                    <div class="rpt-vital-tile-value"><?php echo number_format($ob_details['total']); ?></div>
                                </div>
                                <div class="rpt-vital-tile">
                                    <div class="rpt-vital-tile-label">Field Deliveries</div>
                                    <div class="rpt-vital-tile-value"><?php echo number_format($ob_details['deliveries']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($ob_details['conditions'])): $ob_max = max($ob_details['conditions']); ?>
                        <div class="rpt-clinical-card">
                            <div class="rpt-clinical-subtitle"><i class="bi bi-list-check"></i> Specified OB Conditions</div>
                            <ul class="rpt-bar-list">
                                <?php foreach ($ob_details['conditions'] as $condition => $cnt): $w = $ob_max > 0 ? round(($cnt / $ob_max) * 100) : 0; ?>
                                <li class="rpt-bar-row">
                                    <div class="rpt-bar-row-top">
                                        <span class="rpt-bar-row-label"><?php echo e($condition); ?></span>
                                        <span class="rpt-bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="rpt-bar-track"><div class="rpt-bar-fill purple" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <hr class="rpt-section-divider">
            <h2 class="rpt-band-title"><span class="rpt-icon-dot"></span> Hospitals &amp; Injuries</h2>

            <!-- ===== RECORDS BY BARANGAY ===== -->
            <?php if (!empty($rpt_barangay_data)): ?>
            <div class="rpt-grid-2-1">
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-geo-alt"></i> Records by Barangay</h2>
                        <span class="rpt-section-badge">Top <?php echo count($rpt_barangay_rows); ?></span>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-md">
                            <canvas id="rptBarangayChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title"><i class="bi bi-list-ol"></i> Top Areas</h2>
                    </div>
                    <div class="rpt-section-body">
                        <ul class="rpt-rank-list">
                            <?php foreach ($rpt_barangay_rows as $i => $b): $rank = $i + 1; $rc = $rank <= 3 ? 'r'.$rank : ''; ?>
                            <li>
                                <span class="rpt-rank-badge <?php echo $rc; ?>"><?php echo $rank; ?></span>
                                <span class="rpt-rank-name"><?php echo e($b['name']); ?></span>
                                <span class="rpt-rank-count"><?php echo number_format((int)$b['count']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== TWO-COLUMN DATA TABLES ===== -->
            <div class="rpt-grid-2">
                <!-- Top Hospitals -->
                <?php if (!empty($hospital_data)): ?>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-hospital"></i> Top Hospitals
                        </h2>
                        <span class="rpt-section-badge">Top <?php echo count($hospital_data); ?></span>
                    </div>
                    <div class="rpt-section-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="rpt-data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Hospital</th>
                                        <th style="text-align:right;">Forms</th>
                                        <th style="text-align:right;">Share</th>
                                        <th style="width:120px;">Distribution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    foreach ($hospital_data as $hospital): 
                                        $pct = $summary['total_forms'] > 0 ? ($hospital['count'] / $summary['total_forms']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td class="rpt-cell-rank">
                                            <?php if ($rank === 1): ?>&#129351;
                                            <?php elseif ($rank === 2): ?>&#129352;
                                            <?php elseif ($rank === 3): ?>&#129353;
                                            <?php else: echo $rank; endif; ?>
                                        </td>
                                        <td class="rpt-cell-name"><?php echo e($hospital['arrival_hospital_name']); ?></td>
                                        <td class="rpt-cell-number"><?php echo number_format($hospital['count']); ?></td>
                                        <td class="rpt-cell-number"><?php echo number_format($pct, 1); ?>%</td>
                                        <td>
                                            <div class="rpt-progress-wrap">
                                                <div class="rpt-progress-fill" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $rank++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Injury Types Table -->
                <?php if (!empty($injury_data)): ?>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-heart-pulse"></i> Injury Types
                        </h2>
                        <span class="rpt-section-badge">Top <?php echo count($injury_data); ?></span>
                    </div>
                    <div class="rpt-section-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="rpt-data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Injury Type</th>
                                        <th style="text-align:right;">Cases</th>
                                        <th style="width:120px;">Distribution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    $max_injury = max(array_column($injury_data, 'count'));
                                    foreach ($injury_data as $injury): 
                                        $bar_pct = $max_injury > 0 ? ($injury['count'] / $max_injury) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td class="rpt-cell-rank"><?php echo $rank; ?></td>
                                        <td class="rpt-cell-name"><?php echo e($injury['injury_type'] ?: 'Unspecified'); ?></td>
                                        <td class="rpt-cell-number"><?php echo number_format($injury['count']); ?></td>
                                        <td>
                                            <div class="rpt-progress-wrap">
                                                <div class="rpt-progress-fill emerald" style="width: <?php echo $bar_pct; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $rank++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <hr class="rpt-section-divider">
            <h2 class="rpt-band-title"><span class="rpt-icon-dot"></span> Patient Demographics</h2>

            <!-- ===== DEMOGRAPHICS (Three Column) ===== -->
            <div class="rpt-grid-3">
                <!-- Age Distribution -->
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-people"></i> Age Groups
                        </h2>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-md">
                            <canvas id="rptAgeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Gender Split -->
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-gender-ambiguous"></i> Gender Split
                        </h2>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-md">
                            <canvas id="rptGenderChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Usage -->
                <?php if (!empty($vehicle_data)): ?>
                <div class="rpt-section">
                    <div class="rpt-section-header">
                        <h2 class="rpt-section-title">
                            <i class="bi bi-truck"></i> Vehicle Usage
                        </h2>
                    </div>
                    <div class="rpt-section-body">
                        <div class="rpt-chart-container chart-md">
                            <canvas id="rptVehicleChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===== INJURY TYPES CHART (Full Width) ===== -->
            <?php if (!empty($injury_data)): ?>
            <div class="rpt-section">
                <div class="rpt-section-header">
                    <h2 class="rpt-section-title">
                        <i class="bi bi-clipboard2-pulse"></i> Injury Type Distribution
                    </h2>
                    <span class="rpt-section-badge"><?php echo count($injury_data); ?> types</span>
                </div>
                <div class="rpt-section-body">
                    <div class="rpt-chart-container chart-md">
                        <canvas id="rptInjuryChart"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== TEAM PERFORMANCE (Admin Only) ===== -->
            <?php if ($is_admin && !empty($user_performance)): ?>
            <div class="rpt-section">
                <div class="rpt-section-header">
                    <h2 class="rpt-section-title">
                        <i class="bi bi-people-fill"></i> Team Performance
                    </h2>
                    <span class="rpt-section-badge">Top <?php echo count($user_performance); ?></span>
                </div>
                <div class="rpt-section-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="rpt-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Team Member</th>
                                    <th style="text-align:right;">Total</th>
                                    <th style="text-align:right;">Completed</th>
                                    <th style="text-align:right;">Rate</th>
                                    <th style="width:140px;">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($user_performance as $user): ?>
                                <tr>
                                    <td class="rpt-cell-rank">
                                        <?php if ($rank === 1): ?>&#129351;
                                        <?php elseif ($rank === 2): ?>&#129352;
                                        <?php elseif ($rank === 3): ?>&#129353;
                                        <?php else: echo $rank; endif; ?>
                                    </td>
                                    <td class="rpt-cell-name"><?php echo e($user['full_name']); ?></td>
                                    <td class="rpt-cell-number"><?php echo number_format($user['total_forms']); ?></td>
                                    <td class="rpt-cell-number"><?php echo number_format($user['completed_forms']); ?></td>
                                    <td class="rpt-cell-number">
                                        <span class="rpt-badge <?php echo $user['completion_rate'] >= 80 ? 'success' : ($user['completion_rate'] >= 50 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($user['completion_rate'], 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div class="rpt-progress-wrap">
                                            <div class="rpt-progress-fill <?php echo $user['completion_rate'] >= 80 ? 'emerald' : ($user['completion_rate'] >= 50 ? 'amber' : 'purple'); ?>" style="width: <?php echo $user['completion_rate']; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== EXPORT SECTION ===== -->
            <div class="rpt-section">
                <div class="rpt-section-header">
                    <h2 class="rpt-section-title">
                        <i class="bi bi-download"></i> Export
                    </h2>
                    <span class="rpt-export-label"><?php echo e(date('M j, Y', strtotime($date_from))); ?> — <?php echo e(date('M j, Y', strtotime($date_to))); ?></span>
                </div>
                <div class="rpt-section-body">
                    <div class="rpt-export-group">
                        <button class="rpt-btn-export" data-action="exportToCSV">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Download CSV
                        </button>
                        <button class="rpt-btn-export" data-action="exportToPDF">
                            <i class="bi bi-printer"></i> Print / Save as PDF
                        </button>
                    </div>
                    <p style="margin:0.75rem 0 0;font-size:0.72rem;color:var(--rpt-gray-400);">
                        CSV exports the filtered records. "Print / Save as PDF" opens your browser's print dialog — choose <strong>Save as PDF</strong> as the destination.
                    </p>
                </div>
            </div>

            <!-- ===== DARK FOOTER BAND ===== -->
            <div class="rpt-footer">
              <div class="rpt-footer-inner">
                <div class="rpt-footer-top">
                    <div class="rpt-footer-brand">
                        <div class="rpt-footer-logo"><i class="bi bi-heart-pulse-fill"></i></div>
                        <div>
                            <div class="rpt-footer-name">RESCUE 116-link</div>
                            <div class="rpt-footer-tag">Pre-Hospital Emergency Care System</div>
                        </div>
                    </div>
                </div>
                <div class="rpt-footer-stats">
                    <div class="rpt-footer-stat">
                        <div class="rpt-footer-stat-icon green"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div>
                            <div class="rpt-footer-stat-label">Records (Filtered)</div>
                            <div class="rpt-footer-stat-value"><?php echo number_format((int)$summary['total_forms']); ?></div>
                            <div class="rpt-footer-stat-sub"><?php echo e(date('M j', strtotime($date_from))); ?> — <?php echo e(date('M j', strtotime($date_to))); ?></div>
                        </div>
                    </div>
                    <div class="rpt-footer-stat">
                        <div class="rpt-footer-stat-icon blue"><i class="bi bi-hospital-fill"></i></div>
                        <div>
                            <div class="rpt-footer-stat-label">Hospital Network</div>
                            <div class="rpt-footer-stat-value"><?php echo number_format(count($hospital_data)); ?></div>
                            <div class="rpt-footer-stat-sub">Destination facilities</div>
                        </div>
                    </div>
                    <div class="rpt-footer-stat">
                        <div class="rpt-footer-stat-icon red"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <div class="rpt-footer-stat-label">Emergency Hotline</div>
                            <div class="rpt-footer-stat-value">RESCUE 116</div>
                            <div class="rpt-footer-stat-sub">0967 379 7967</div>
                        </div>
                    </div>
                    <div class="rpt-footer-stat">
                        <div class="rpt-footer-stat-icon amber"><i class="bi bi-heart-pulse-fill"></i></div>
                        <div>
                            <div class="rpt-footer-stat-label">Our Mission</div>
                            <div class="rpt-footer-stat-value" style="font-size:0.82rem;line-height:1.3;">Magkasama sa Bilis na Tugon</div>
                            <div class="rpt-footer-stat-sub">Ligtas na Bayan</div>
                        </div>
                    </div>
                </div>
                <div class="rpt-footer-bottom">
                    <span>&copy; <?php echo date('Y'); ?> RESCUE 116-link &middot; Pre-Hospital Care System. All rights reserved.</span>
                    <span class="pill-tag">EMERGENCY 24/7</span>
                </div>
              </div>
            </div>
        </div><!-- /container-fluid -->
    </div><!-- /content -->

    <!-- ===== SCRIPTS ===== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>" src="js/reports-charts.js?v=<?php echo md5_file(__DIR__ . '/js/reports-charts.js'); ?>"></script>

    <!-- Data bridge: pass PHP data to JS -->
    <script nonce="<?php echo CSP_NONCE; ?>">
        window.__rptTrendData = <?php echo json_encode($trend_data); ?>;
        window.__rptCompleted = <?php echo (int)($summary['completed_forms'] ?? 0); ?>;
        window.__rptDraft = <?php echo (int)($summary['draft_forms'] ?? 0); ?>;
        window.__rptArchived = <?php echo (int)($summary['archived_forms'] ?? 0); ?>;
        window.__rptMedical = <?php echo (int)($emergency_data['medical'] ?? 0); ?>;
        window.__rptTrauma = <?php echo (int)($emergency_data['trauma'] ?? 0); ?>;
        window.__rptObstetric = <?php echo (int)($emergency_data['obstetric'] ?? 0); ?>;
        window.__rptGeneral = <?php echo (int)($emergency_data['general'] ?? 0); ?>;
        window.__rptAgeData = <?php echo json_encode($age_data); ?>;
        window.__rptGenderData = <?php echo json_encode($gender_data); ?>;
        window.__rptVehicleData = <?php echo json_encode($vehicle_data); ?>;
        window.__rptInjuryData = <?php echo json_encode($injury_data); ?>;
        window.__rptHourData = <?php echo json_encode($hour_data); ?>;
        window.__rptBarangayLabels = <?php echo json_encode($rpt_barangay_labels); ?>;
        window.__rptBarangayData = <?php echo json_encode($rpt_barangay_data); ?>;
        window.__rptOutcomeLabels = <?php echo json_encode(array_column($outcome_rows, 0)); ?>;
        window.__rptOutcomeData = <?php echo json_encode(array_map('intval', array_column($outcome_rows, 1))); ?>;
        window.__rptOutcomeColors = <?php echo json_encode(array_column($outcome_rows, 2)); ?>;
        window.__rptPrevTrendData = <?php echo json_encode($prev_trend_data); ?>;
        window.__rptTypeLabels = <?php echo json_encode(array_column($rpt_type_rows, 0)); ?>;
        window.__rptTypeData = <?php echo json_encode(array_map('intval', array_column($rpt_type_rows, 1))); ?>;
        window.__rptTypeColors = <?php echo json_encode(array_column($rpt_type_rows, 2)); ?>;
        window.__rptVehicleUsedLabels = <?php echo json_encode($rpt_vehicle_labels); ?>;
        window.__rptVehicleUsedData = <?php echo json_encode(array_map('intval', $rpt_vehicle_counts)); ?>;
        window.__rptVehicleUsedColors = <?php echo json_encode($rpt_vehicle_colors); ?>;
    </script>
</body>
</html>
<?php
/**
 * Format complaint key to human-readable label
 */
function formatComplaintLabel($key) {
    $map = [
        'chestPain' => 'Chest Pain',
        'headache' => 'Headache',
        'blurredVision' => 'Blurred Vision',
        'difficultyBreathing' => 'Difficulty Breathing',
        'dizziness' => 'Dizziness',
        'bodyMalaise' => 'Body Malaise',
    ];
    return $map[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
}