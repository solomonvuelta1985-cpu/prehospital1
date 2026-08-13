<?php
/**
 * Admin Dashboard
 * System-wide overview and analytics for administrators
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();

$current_user = get_auth_user();
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');
$prev_week_start = date('Y-m-d', strtotime('monday last week'));
$prev_month_start = date('Y-m-01', strtotime('-1 month'));

// Batched statistics
$stats_stmt = db_query("
    SELECT
        COUNT(*) as total_forms,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_forms,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_forms,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_count,
        SUM(CASE WHEN form_date >= ? THEN 1 ELSE 0 END) as week_forms,
        SUM(CASE WHEN form_date >= ? THEN 1 ELSE 0 END) as month_forms
    FROM prehospital_forms
", [$week_start, $month_start]);
$stats = $stats_stmt->fetch();
$total_forms = (int)$stats['total_forms'];
$today_forms = (int)$stats['today_forms'];
$draft_forms = (int)$stats['draft_forms'];
$completed_forms = (int)$stats['completed_forms'];
$archived_count = (int)$stats['archived_count'];
$week_forms = (int)$stats['week_forms'];
$month_forms = (int)$stats['month_forms'];

// Previous period for trend comparison
$prev_week_stmt = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE form_date >= ? AND form_date < ?", [$prev_week_start, $week_start]);
$prev_week_forms = (int)$prev_week_stmt->fetch()['count'];

$prev_month_stmt = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE form_date >= ? AND form_date < ?", [$prev_month_start, $month_start]);
$prev_month_forms = (int)$prev_month_stmt->fetch()['count'];

// Active users count
$active_users_stmt = db_query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$active_users = (int)$active_users_stmt->fetch()['count'];

// Recent forms
$recent_forms_stmt = db_query("SELECT * FROM form_summary ORDER BY created_at DESC LIMIT 10");
$recent_forms = $recent_forms_stmt->fetchAll();
// Decrypt sensitive fields for each recent form
foreach ($recent_forms as &$rf) { decrypt_record_fields($rf); }
unset($rf);

// Recent activity (last 10 audit-like entries)
$activity_stmt = db_query("
    SELECT pf.id, pf.form_number, pf.patient_name, pf.status, pf.created_at, pf.updated_at,
           u.full_name as created_by_name
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    ORDER BY COALESCE(pf.updated_at, pf.created_at) DESC
    LIMIT 8
");
$recent_activity = $activity_stmt ? $activity_stmt->fetchAll() : [];
// Decrypt sensitive fields for each activity entry
foreach ($recent_activity as &$ra) { decrypt_record_fields($ra); }
unset($ra);

// Weekly data for bar chart
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));
$daily_stats_stmt = db_query("
    SELECT DATE(form_date) as date, COUNT(*) as count
    FROM prehospital_forms
    WHERE form_date >= ? AND form_date <= CURDATE()
    GROUP BY DATE(form_date)
    ORDER BY DATE(form_date) ASC
", [$seven_days_ago]);
$daily_counts = [];
while ($row = $daily_stats_stmt->fetch()) {
    $daily_counts[$row['date']] = (int)$row['count'];
}

$last_7_days = [];
$last_7_days_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime("-$i days"));
    $last_7_days[] = $label;
    $last_7_days_data[] = $daily_counts[$date] ?? 0;
}

// Monthly data for line chart
$twelve_months_ago = date('Y-m-01', strtotime('-11 months'));
$monthly_stats_stmt = db_query("
    SELECT DATE_FORMAT(form_date, '%Y-%m') as month, COUNT(*) as count
    FROM prehospital_forms
    WHERE form_date >= ?
    GROUP BY DATE_FORMAT(form_date, '%Y-%m')
    ORDER BY month ASC
", [$twelve_months_ago]);
$monthly_counts = [];
while ($row = $monthly_stats_stmt->fetch()) {
    $monthly_counts[$row['month']] = (int)$row['count'];
}

$monthly_labels = [];
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $monthly_labels[] = $label;
    $monthly_data[] = $monthly_counts[$month] ?? 0;
}

// Emergency type breakdown
$emergency_stmt = db_query("
    SELECT
        SUM(CASE WHEN emergency_medical = 1 THEN 1 ELSE 0 END) as medical,
        SUM(CASE WHEN emergency_trauma = 1 THEN 1 ELSE 0 END) as trauma,
        SUM(CASE WHEN emergency_ob = 1 THEN 1 ELSE 0 END) as ob,
        SUM(CASE WHEN emergency_general = 1 THEN 1 ELSE 0 END) as general_emergency
    FROM prehospital_forms
");
$emergency = $emergency_stmt->fetch();
$emergency_medical = (int)$emergency['medical'];
$emergency_trauma = (int)$emergency['trauma'];
$emergency_ob = (int)$emergency['ob'];
$emergency_general = (int)$emergency['general_emergency'];

// Vehicular Accident (VA) — derived from trauma free-text notes (no structured field)
$va_stmt = db_query("SELECT emergency_trauma_details FROM prehospital_forms WHERE emergency_trauma = 1 AND emergency_trauma_details IS NOT NULL AND TRIM(emergency_trauma_details) <> ''");
$emergency_va = 0;
if ($va_stmt) {
    foreach ($va_stmt->fetchAll() as $row) {
        if (is_vehicular_accident($row['emergency_trauma_details'])) $emergency_va++;
    }
}
$emergency_trauma_nonva = max(0, $emergency_trauma - $emergency_va);

// Consolidated Run (incident categories — uses the saved incident_category when
// present, else resolves from the complaint/narrative + FAST/consciousness/care
// signals via consolidated_run_counts() -> resolve_record_category()).
$cr_stmt = db_query("SELECT emergency_medical, emergency_trauma, emergency_ob, emergency_general,
                            emergency_medical_details, emergency_trauma_details, emergency_ob_details, emergency_general_details,
                            incident_category, other_complaints, team_leader_notes, chief_complaints,
                            initial_consciousness, fast_face_drooping, fast_arm_weakness, fast_speech_difficulty, fast_time_to_call,
                            care_management
                     FROM prehospital_forms");
$cr = consolidated_run_counts($cr_stmt ? $cr_stmt->fetchAll() : []);
$cr_total = array_sum($cr['categories']);

// Vehicle used breakdown (clean enum: ambulance / fireTruck / others)
$vehicle_stmt = db_query("SELECT vehicle_used, COUNT(*) as count FROM prehospital_forms WHERE vehicle_used IS NOT NULL GROUP BY vehicle_used");
$vehicle_map = ['ambulance' => 'Ambulance', 'fireTruck' => 'Fire Truck', 'others' => 'Others'];
$vehicle_colors_map = ['Ambulance' => '#4f46e5', 'Fire Truck' => '#dc2626', 'Others' => '#64748b'];
$vehicle_rows = [];
if ($vehicle_stmt) {
    foreach ($vehicle_stmt->fetchAll() as $row) {
        $label = $vehicle_map[$row['vehicle_used']] ?? ucfirst((string)$row['vehicle_used']);
        $vehicle_rows[$label] = ($vehicle_rows[$label] ?? 0) + (int)$row['count'];
    }
    arsort($vehicle_rows);
}
$vehicle_total = array_sum($vehicle_rows);
$vehicle_labels = array_keys($vehicle_rows);
$vehicle_data = array_values($vehicle_rows);
$vehicle_colors = array_map(function ($l) use ($vehicle_colors_map) { return $vehicle_colors_map[$l] ?? '#94a3b8'; }, $vehicle_labels);

// Ambulance unit (V-number) best-effort extraction from the mangled vehicle_details field
$amb_stmt = db_query("SELECT vehicle_details FROM prehospital_forms WHERE vehicle_used = 'ambulance' AND vehicle_details IS NOT NULL AND TRIM(vehicle_details) <> ''");
$amb_units = [];
if ($amb_stmt) {
    foreach ($amb_stmt->fetchAll() as $row) {
        // vehicle_details is multiply HTML-encoded & may be truncated; decode then regex a V-number.
        $decoded = html_entity_decode(html_entity_decode(html_entity_decode((string)$row['vehicle_details'], ENT_QUOTES), ENT_QUOTES), ENT_QUOTES);
        if (preg_match('/\bV(\d{1,2})\b/i', $decoded, $m)) {
            $unit = 'V' . (int)$m[1];
            $amb_units[$unit] = ($amb_units[$unit] ?? 0) + 1;
        }
    }
    // natural-ish sort by V number
    uksort($amb_units, function ($a, $b) { return ((int)substr($a, 1)) <=> ((int)substr($b, 1)); });
}

// Top hospitals breakdown
$hospitals_stmt = db_query("
    SELECT COALESCE(NULLIF(arrival_hospital_name, ''), 'Other/Unknown') as hospital,
           COUNT(*) as count
    FROM prehospital_forms
    GROUP BY hospital
    ORDER BY count DESC
    LIMIT 6
");
$top_hospitals = $hospitals_stmt ? $hospitals_stmt->fetchAll() : [];
$hospital_labels = [];
$hospital_data = [];
$hospital_colors = [];
$palette = ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe', '#e0e7ff'];
$idx = 0;
foreach ($top_hospitals as $h) {
    $hospital_labels[] = mb_strimwidth($h['hospital'], 0, 22, '...');
    $hospital_data[] = (int)$h['count'];
    $hospital_colors[] = $palette[$idx % count($palette)];
    $idx++;
}

// Top barangays breakdown (honest analog to the mockup's barangay map).
// address holds the locality/barangay but is free-text and dirty, so we
// normalize each value (see normalize_barangay) and aggregate in PHP.
$bgy_stmt = db_query("SELECT address FROM prehospital_forms WHERE address IS NOT NULL AND TRIM(address) <> ''");
$bgy_counts = [];
if ($bgy_stmt) {
    foreach ($bgy_stmt->fetchAll() as $row) {
        $name = normalize_barangay($row['address']);
        if ($name === 'UNSPECIFIED') continue;
        $bgy_counts[$name] = ($bgy_counts[$name] ?? 0) + 1;
    }
    arsort($bgy_counts);
}
$zone_rows = [];   // [['zone' => name, 'count' => n], ...] for the ranked list
$zone_labels = []; // chart labels
$zone_data = [];   // chart data
foreach (array_slice($bgy_counts, 0, 6, true) as $name => $cnt) {
    $title = ucwords(strtolower($name));
    $zone_rows[] = ['zone' => $title, 'count' => $cnt];
    $zone_labels[] = mb_strimwidth($title, 0, 22, '...');
    $zone_data[] = (int)$cnt;
}

// ===== CLINICAL DETAIL QUERIES =====
// Medical Chief Complaints Breakdown
$medical_complaints_dash = [];
$medical_details_dash = [];
$medical_vitals_dash = ['bp' => [], 'temp' => [], 'pulse' => [], 'spo2' => []];

$med_dash_sql = "
    SELECT chief_complaints, emergency_medical_details,
           initial_bp, initial_temp, initial_pulse, initial_spo2
    FROM prehospital_forms
    WHERE emergency_medical = 1
";
$med_dash_stmt = db_query($med_dash_sql);
if ($med_dash_stmt) {
    $med_rows = $med_dash_stmt->fetchAll();
    $cc_counts = [];
    foreach ($med_rows as $row) {
        $complaints = json_decode($row['chief_complaints'], true);
        if (is_array($complaints)) {
            foreach ($complaints as $c) {
                $cc_counts[$c] = ($cc_counts[$c] ?? 0) + 1;
            }
        }
        if (!empty($row['emergency_medical_details'])) {
            $detail = trim($row['emergency_medical_details']);
            $medical_details_dash[$detail] = ($medical_details_dash[$detail] ?? 0) + 1;
        }
        if ($row['initial_bp']) $medical_vitals_dash['bp'][] = $row['initial_bp'];
        if ($row['initial_temp']) $medical_vitals_dash['temp'][] = floatval($row['initial_temp']);
        if ($row['initial_pulse']) $medical_vitals_dash['pulse'][] = intval($row['initial_pulse']);
        if ($row['initial_spo2']) $medical_vitals_dash['spo2'][] = intval($row['initial_spo2']);
    }
    arsort($cc_counts);
    $medical_complaints_dash = array_slice($cc_counts, 0, 8);
    arsort($medical_details_dash);
    $medical_details_dash = array_slice($medical_details_dash, 0, 8);
}

// Trauma Injury Detail
$trauma_injuries_dash = [];
$trauma_dash_sql = "
    SELECT i.injury_type, i.body_part, COUNT(DISTINCT pf.id) as count
    FROM injuries i
    JOIN prehospital_forms pf ON i.form_id = pf.id
    WHERE pf.emergency_trauma = 1
    GROUP BY i.injury_type, i.body_part
    ORDER BY count DESC
    LIMIT 10
";
$trauma_dash_stmt = db_query($trauma_dash_sql);
if ($trauma_dash_stmt) {
    $trauma_injuries_dash = $trauma_dash_stmt->fetchAll();
}

// Calculate trend percentages
$week_trend = $prev_week_forms > 0 ? round((($week_forms - $prev_week_forms) / $prev_week_forms) * 100) : 0;
$month_trend = $prev_month_forms > 0 ? round((($month_forms - $prev_month_forms) / $prev_month_forms) * 100) : 0;

// Completion rate
$completion_rate = $total_forms > 0 ? round(($completed_forms / $total_forms) * 100) : 0;

// Hospital network size (distinct destination facilities on record)
$hospital_network_stmt = db_query("
    SELECT COUNT(DISTINCT arrival_hospital_name) as c
    FROM prehospital_forms
    WHERE arrival_hospital_name IS NOT NULL AND TRIM(arrival_hospital_name) <> ''
");
$hospital_network = $hospital_network_stmt ? (int)$hospital_network_stmt->fetch()['c'] : 0;

// Total emergency-typed records (for the type donut center total)
$emergency_total = $emergency_medical + $emergency_trauma + $emergency_ob + $emergency_general;

// Time-aware greeting
$hour = (int)date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
$first_name = trim(explode(' ', trim($current_user['full_name']))[0]);

// Format current date
$current_date = date('l, F j, Y');
$current_time = date('g:i A');

// Emergency type rows for the donut legend list (label, value, color)
// Records by Type — Trauma split into Vehicular Accident (derived) + other Trauma so slices still sum.
$type_rows = [
    ['Medical',             $emergency_medical,       '#4f46e5'],
    ['Trauma (non-VA)',     $emergency_trauma_nonva,  '#dc2626'],
    ['Vehicular Accident',  $emergency_va,            '#f59e0b'],
    ['OB/GYN',              $emergency_ob,            '#7c3aed'],
    ['General',             $emergency_general,       '#d97706'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --success: #059669;
            --success-light: #ecfdf5;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --purple: #7c3aed;
            --purple-light: #f5f3ff;
            --teal: #0d9488;
            --teal-light: #f0fdfa;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow-xs: 0 1px 2px rgba(16,24,40,0.04);
            --shadow-sm: 0 1px 3px rgba(16,24,40,0.06), 0 1px 2px rgba(16,24,40,0.04);
            --shadow-md: 0 4px 10px -2px rgba(16,24,40,0.08), 0 2px 4px -2px rgba(16,24,40,0.04);
            --shadow-lg: 0 12px 22px -6px rgba(16,24,40,0.12);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--gray-50);
            font-family: var(--font-sans);
            color: var(--gray-800);
            line-height: 1.5;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ===== BRANDED TOP BAR ===== */
        .ems-topbar {
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-xs);
            flex-wrap: wrap;
        }
        .ems-brand { display: flex; align-items: center; gap: 0.9rem; }
        .ems-logo {
            width: 50px; height: 50px; border-radius: 14px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(37,99,235,0.35);
        }
        .ems-brand-title {
            font-size: 1.4rem; font-weight: 900; color: #1e40af;
            letter-spacing: -0.02em; line-height: 1; margin: 0;
        }
        .ems-brand-sub {
            font-size: 0.72rem; font-weight: 800; color: var(--danger);
            letter-spacing: 0.08em; text-transform: uppercase; margin: 0.15rem 0 0;
        }
        .ems-brand-meta {
            font-size: 0.72rem; color: var(--gray-500); font-weight: 500; margin: 0.1rem 0 0;
        }
        .ems-topbar-right { display: flex; align-items: center; gap: 1rem; }
        .ems-datechip {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: var(--gray-50); border: 1px solid var(--gray-200);
            border-radius: 999px; padding: 0.45rem 0.9rem;
            font-size: 0.78rem; font-weight: 600; color: var(--gray-700);
            white-space: nowrap;
        }
        .ems-datechip i { color: var(--primary); }
        .ems-iconbtn {
            position: relative; width: 40px; height: 40px; border-radius: 50%;
            background: var(--gray-50); border: 1px solid var(--gray-200);
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-600); font-size: 1.05rem; text-decoration: none;
            transition: all 0.18s ease;
        }
        .ems-iconbtn:hover { background: var(--primary-light); color: var(--primary); border-color: #c7d2fe; }
        .ems-iconbtn .dot-badge {
            position: absolute; top: -2px; right: -2px; min-width: 17px; height: 17px;
            padding: 0 4px; border-radius: 999px; background: var(--danger); color: #fff;
            font-size: 0.6rem; font-weight: 800; display: flex; align-items: center;
            justify-content: center; border: 2px solid #fff;
        }
        .ems-user { display: flex; align-items: center; gap: 0.6rem; }
        .ems-user-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
        }
        .ems-user-name { font-size: 0.85rem; font-weight: 700; color: var(--gray-900); line-height: 1.1; }
        .ems-user-role { font-size: 0.7rem; color: var(--gray-500); }

        /* ===== SECTION DIVIDER ===== */
        .section-divider {
            border: none;
            height: 1px;
            background: linear-gradient(90deg, var(--gray-200), var(--gray-100) 60%, transparent);
            margin: 1.75rem 0 1.5rem;
        }

        /* ===== SECTION TITLES ===== */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 0 0 1rem; }
        .section-title {
            font-size: 1.05rem; font-weight: 800; color: var(--gray-900); margin: 0;
            letter-spacing: -0.015em; display: flex; align-items: center; gap: 0.5rem;
        }
        .section-title .icon-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--primary); }
        .link-more {
            font-size: 0.78rem; font-weight: 700; color: var(--primary); text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.3rem;
        }
        .link-more:hover { color: var(--primary-hover); }

        /* ===== KPI CARDS (pastel + solid circular icon) ===== */
        .kpi-card {
            border-radius: var(--radius-lg);
            padding: 1.3rem 1.4rem;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid transparent;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .kpi-label {
            font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.07em; color: var(--gray-500); margin-bottom: 0.35rem;
        }
        .kpi-value {
            font-size: 2.1rem; font-weight: 900; line-height: 1;
            letter-spacing: -0.03em; color: var(--gray-900); font-variant-numeric: tabular-nums;
        }
        .kpi-foot { font-size: 0.7rem; font-weight: 600; margin-top: 0.4rem; display: flex; align-items: center; gap: 0.3rem; }
        .kpi-foot.up { color: var(--success); }
        .kpi-foot.down { color: var(--danger); }
        .kpi-foot.muted { color: var(--gray-400); }
        .kpi-icon {
            width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.55rem; color: #fff;
        }
        /* pastel backgrounds + solid icons */
        .kpi-card.c-blue   { background: #eff4ff; border-color: #dbe6ff; }
        .kpi-card.c-blue   .kpi-icon { background: #2563eb; box-shadow: 0 6px 14px rgba(37,99,235,0.35); }
        .kpi-card.c-red    { background: #fef2f2; border-color: #fddcdc; }
        .kpi-card.c-red    .kpi-icon { background: #dc2626; box-shadow: 0 6px 14px rgba(220,38,38,0.30); }
        .kpi-card.c-green  { background: #ecfdf5; border-color: #c8f1dd; }
        .kpi-card.c-green  .kpi-icon { background: #059669; box-shadow: 0 6px 14px rgba(5,150,105,0.30); }
        .kpi-card.c-purple { background: #f5f3ff; border-color: #e4dcff; }
        .kpi-card.c-purple .kpi-icon { background: #7c3aed; box-shadow: 0 6px 14px rgba(124,58,237,0.30); }
        .kpi-card.c-amber  { background: #fffbeb; border-color: #fdeec1; }
        .kpi-card.c-amber  .kpi-icon { background: #d97706; box-shadow: 0 6px 14px rgba(217,119,6,0.30); }
        .kpi-card.c-teal   { background: #f0fdfa; border-color: #cbf2ec; }
        .kpi-card.c-teal   .kpi-icon { background: #0d9488; box-shadow: 0 6px 14px rgba(13,148,136,0.30); }

        /* ===== CARD / PANEL ===== */
        .card-panel {
            background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xs); height: 100%; overflow: hidden;
        }
        .card-head {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 1.1rem 1.35rem; border-bottom: 1px solid var(--gray-100);
        }
        .card-title { font-size: 0.95rem; font-weight: 800; color: var(--gray-900); margin: 0; letter-spacing: -0.01em; }
        .card-sub { font-size: 0.7rem; color: var(--gray-400); font-weight: 500; margin-top: 0.1rem; }
        .card-badge {
            font-size: 0.68rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 999px;
            background: var(--primary-light); color: var(--primary); white-space: nowrap;
        }
        .card-body-p { padding: 1.25rem 1.35rem; }
        .chart-box { position: relative; height: 260px; }
        .chart-box.short { height: 240px; }

        /* ===== DONUT LEGEND LIST (mockup right column) ===== */
        .legend-list { list-style: none; margin: 0; padding: 0; }
        .legend-list li {
            display: flex; align-items: center; gap: 0.7rem; padding: 0.7rem 0;
            border-bottom: 1px solid var(--gray-50); font-size: 0.85rem; color: var(--gray-700);
        }
        .legend-list li:last-child { border-bottom: none; }
        .legend-swatch { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
        .legend-name { flex: 1; font-weight: 600; }
        .legend-val { font-weight: 700; color: var(--gray-900); font-variant-numeric: tabular-nums; }
        .legend-pct { font-size: 0.72rem; color: var(--gray-400); font-weight: 600; min-width: 46px; text-align: right; }
        .legend-total {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 0.6rem; padding-top: 0.8rem; border-top: 2px solid var(--gray-100);
            font-weight: 800; color: var(--gray-900);
        }

        /* ===== RANKED LIST (barangay) ===== */
        .rank-list { list-style: none; margin: 0; padding: 0; }
        .rank-list li {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0;
            border-bottom: 1px solid var(--gray-50); font-size: 0.85rem; color: var(--gray-700);
        }
        .rank-list li:last-child { border-bottom: none; }
        .rank-badge {
            width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 800; background: var(--primary-light); color: var(--primary);
        }
        .rank-badge.r1 { background: #2563eb; color: #fff; }
        .rank-badge.r2 { background: var(--purple); color: #fff; }
        .rank-badge.r3 { background: var(--teal); color: #fff; }
        .rank-name { flex: 1; min-width: 0; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rank-count { font-weight: 800; color: var(--gray-900); font-variant-numeric: tabular-nums; }

        /* ===== RECENT TABLE ===== */
        .rtable { width: 100%; font-size: 0.82rem; border-collapse: collapse; }
        .rtable thead th {
            font-size: 0.66rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--gray-400); padding: 0.7rem 1rem; text-align: left; border-bottom: 1px solid var(--gray-100);
            white-space: nowrap;
        }
        .rtable tbody td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-50); color: var(--gray-700); vertical-align: middle; }
        .rtable tbody tr:last-child td { border-bottom: none; }
        .rtable tbody tr:hover { background: var(--gray-50); }
        .dot-type { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 0.45rem; }
        .pill {
            padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.66rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.03em; display: inline-flex; align-items: center; gap: 0.25rem;
        }
        .pill.completed { background: var(--success-light); color: var(--success); }
        .pill.draft { background: var(--gray-100); color: var(--gray-600); }
        .pill.archived { background: var(--purple-light); color: var(--purple); }
        .pill.vehicle { background: var(--primary-light); color: var(--primary); text-transform: none; letter-spacing: 0; }
        .pill.injury { background: var(--danger-light); color: var(--danger); text-transform: none; letter-spacing: 0; }
        .mini-avatar {
            width: 30px; height: 30px; border-radius: 50%; background: var(--primary); color: #fff;
            display: inline-flex; align-items: center; justify-content: center; font-size: 0.66rem;
            font-weight: 800; margin-right: 0.5rem;
        }
        .mini-avatar.p { background: var(--purple); } .mini-avatar.t { background: var(--teal); } .mini-avatar.r { background: var(--danger); }
        .btn-view {
            padding: 0.3rem 0.65rem; font-size: 0.72rem; font-weight: 700; background: #fff;
            border: 1px solid var(--gray-200); border-radius: 8px; color: var(--gray-700); text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.25rem; transition: all 0.15s ease;
        }
        .btn-view:hover { background: var(--primary-light); border-color: #c7d2fe; color: var(--primary); }
        .relt { font-size: 0.66rem; color: var(--gray-400); font-weight: 600; }

        /* ===== ACTIVITY FEED ===== */
        .feed { padding: 0.4rem 0; }
        .feed-item { display: flex; gap: 0.8rem; padding: 0.7rem 1.35rem; transition: background 0.12s ease; }
        .feed-item:hover { background: var(--gray-50); }
        .feed-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 0.3rem; flex-shrink: 0; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--gray-200); }
        .feed-dot.created { background: var(--primary); } .feed-dot.completed { background: var(--success); } .feed-dot.draft { background: var(--gray-400); }
        .feed-text { font-size: 0.8rem; color: var(--gray-700); font-weight: 500; line-height: 1.4; }
        .feed-text strong { color: var(--gray-900); font-weight: 700; }
        .feed-time { font-size: 0.66rem; color: var(--gray-400); margin-top: 0.1rem; font-weight: 600; }

        /* ===== FOOTER INFO TILES ===== */
        .info-tile {
            background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-lg);
            padding: 1.1rem 1.2rem; height: 100%; display: flex; align-items: center; gap: 0.85rem;
            box-shadow: var(--shadow-xs);
        }
        .info-tile-icon {
            width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .info-tile-icon.blue { background: var(--primary-light); color: var(--primary); }
        .info-tile-icon.green { background: var(--success-light); color: var(--success); }
        .info-tile-icon.red { background: var(--danger-light); color: var(--danger); }
        .info-tile-icon.teal { background: var(--teal-light); color: var(--teal); }
        .info-tile-label { font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gray-400); }
        .info-tile-value { font-size: 1.1rem; font-weight: 800; color: var(--gray-900); line-height: 1.15; }
        .info-tile-sub { font-size: 0.7rem; color: var(--gray-500); }
        .info-tile.brand { background: linear-gradient(135deg,#2563eb,#1e40af); border: none; color: #fff; }
        .info-tile.brand .bi { font-size: 1.7rem; }
        .info-tile.brand .tagline { font-size: 0.82rem; font-weight: 800; letter-spacing: 0.01em; line-height: 1.25; }

        /* ===== DARK FOOTER BAND ===== */
        /* Full-bleed: break out of the .container-fluid (.75rem side padding)
           and the py-4 (1.5rem) bottom padding so the dark band reaches the
           left, right, and bottom edges of the content area. */
        .dash-footer {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #cbd5e1;
            margin-left: -0.75rem;
            margin-right: -0.75rem;
            margin-bottom: -1.5rem;
        }
        /* inner wrapper keeps content aligned with the rest of the page */
        .dash-footer-inner {
            padding: 1.75rem 2.25rem;
            max-width: 1600px;
            margin: 0 auto;
        }
        .dash-footer-top {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1.25rem; flex-wrap: wrap;
            padding-bottom: 1.1rem; margin-bottom: 1.1rem;
            border-bottom: 1px solid rgba(148,163,184,0.18);
        }
        .dash-footer-brand { display: flex; align-items: center; gap: 0.85rem; }
        .dash-footer-logo {
            width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
            background: linear-gradient(135deg,#6366f1,#4f46e5); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            box-shadow: 0 4px 12px rgba(79,70,229,0.4);
        }
        .dash-footer-name { font-size: 1.05rem; font-weight: 800; color: #f1f5f9; letter-spacing: -0.01em; }
        .dash-footer-tag { font-size: 0.72rem; color: #94a3b8; margin-top: 0.1rem; }
        .dash-footer-contact { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .dash-footer-item { display: flex; align-items: center; gap: 0.6rem; }
        .dash-footer-item-icon {
            width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.05rem; color: #fff;
        }
        .dash-footer-item-icon.red { background: rgba(220,38,38,0.2); color: #f87171; }
        .dash-footer-item-icon.blue { background: rgba(59,130,246,0.2); color: #60a5fa; }
        .dash-footer-item-label { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .dash-footer-item-value { font-size: 0.92rem; font-weight: 700; color: #f1f5f9; }
        .dash-footer-bottom {
            display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;
            font-size: 0.74rem; color: #64748b;
        }
        .dash-footer-bottom .pill-tag {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.02em; color: #cbd5e1;
            background: rgba(148,163,184,0.12); padding: 0.3rem 0.7rem; border-radius: 999px;
        }
        /* 4-stat cards inside the footer (replaces the redundant light tiles) */
        .dash-footer-stats {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
            margin: 1.25rem 0;
        }
        .dash-footer-stat {
            display: flex; align-items: center; gap: 0.75rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }
        .dash-footer-stat:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(148,163,184,0.35);
            transform: translateY(-2px);
        }
        .dash-footer-stat-icon {
            width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
        }
        .dash-footer-stat-icon.green { background: rgba(5,150,105,0.2); color: #34d399; }
        .dash-footer-stat-icon.blue  { background: rgba(59,130,246,0.2); color: #60a5fa; }
        .dash-footer-stat-icon.red   { background: rgba(220,38,38,0.2); color: #f87171; }
        .dash-footer-stat-icon.amber { background: rgba(217,119,6,0.2); color: #fbbf24; }
        .dash-footer-stat-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .dash-footer-stat-value { font-size: 1.15rem; font-weight: 800; color: #f1f5f9; line-height: 1.15; }
        .dash-footer-stat-sub { font-size: 0.66rem; color: #94a3b8; }
        @media (max-width: 767px) { .dash-footer-stats { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 575px) {
            .dash-footer-top, .dash-footer-contact { flex-direction: column; align-items: flex-start; gap: 0.9rem; }
            .dash-footer-stats { grid-template-columns: 1fr; gap: 0.9rem; }
        }

        /* Refined system footer */
        .dash-footer {
            background: linear-gradient(135deg, #111c32 0%, #0a1222 100%);
            border-top: 1px solid rgba(129, 140, 248, 0.32);
        }
        .dash-footer-inner { padding: 2rem clamp(1.25rem, 3vw, 3rem) 1.1rem; }
        .dash-footer-top { padding-bottom: 1.35rem; margin-bottom: 1.25rem; border-color: rgba(148, 163, 184, 0.16); }
        .dash-footer-kicker { margin-bottom: 0.15rem; color: #818cf8; font-size: 0.62rem; font-weight: 850; letter-spacing: 0.14em; text-transform: uppercase; }
        .dash-footer-name { font-size: 1.2rem; letter-spacing: -0.025em; }
        .dash-footer-tag { color: #9aa9bf; }
        .dash-footer-status { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 0.7rem; border: 1px solid rgba(52, 211, 153, 0.24); border-radius: 999px; color: #a7f3d0; background: rgba(16, 185, 129, 0.1); font-size: 0.68rem; font-weight: 800; }
        .dash-footer-status-dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.12); }
        .dash-footer-stats { gap: 0.8rem; margin: 0 0 1.45rem; }
        .dash-footer-stat { min-height: 5.4rem; padding: 0.9rem 1rem; border-color: rgba(148, 163, 184, 0.16); border-radius: 14px; background: rgba(255, 255, 255, 0.045); }
        .dash-footer-stat:hover { transform: translateY(-3px); background: rgba(99, 102, 241, 0.1); border-color: rgba(129, 140, 248, 0.38); }
        .dash-footer-stat-icon { width: 2.55rem; height: 2.55rem; border-radius: 12px; }
        .dash-footer-stat-label { color: #8190a7; font-size: 0.58rem; letter-spacing: 0.09em; }
        .dash-footer-stat-value { font-size: 1.05rem; }
        .dash-footer-bottom { padding-top: 1rem; border-top: 1px solid rgba(148, 163, 184, 0.12); color: #8190a7; }
        .dash-footer-bottom-meta { display: inline-flex; align-items: center; gap: 0.8rem; }
        .dash-footer-separator { padding: 0 0.45rem; color: #475569; }
        .dash-footer-bottom .pill-tag { display: inline-flex; align-items: center; gap: 0.35rem; color: #c7d2fe; background: rgba(99, 102, 241, 0.16); }
        @media (max-width: 575px) {
            .dash-footer-inner { padding: 1.5rem 1rem 1rem; }
            .dash-footer-top { gap: 1rem; }
            .dash-footer-status { align-self: flex-start; }
            .dash-footer-bottom, .dash-footer-bottom-meta { align-items: flex-start; flex-direction: column; gap: 0.55rem; }
            .dash-footer-separator { display: none; }
        }

        /* Footer v2: compact service rail, intentionally different from the
           previous large four-card footer composition. */
        .dash-footer {
            position: relative;
            margin-top: 2rem;
            background: #0b1427;
            border-top: 0;
            overflow: hidden;
        }
        .dash-footer::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, #6366f1 0%, #22d3ee 48%, #34d399 100%);
        }
        .dash-footer::after {
            content: '';
            position: absolute;
            width: 28rem;
            height: 28rem;
            right: -13rem;
            top: -17rem;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.13);
            pointer-events: none;
        }
        .dash-footer-inner { position: relative; z-index: 1; padding: 1.5rem clamp(1.25rem, 3vw, 3rem) 0.95rem; }
        .dash-footer-top { align-items: center; padding-bottom: 1.15rem; margin-bottom: 0.95rem; border-bottom-color: rgba(148, 163, 184, 0.13); }
        .dash-footer-brand { gap: 0.75rem; }
        .dash-footer-logo { width: 2.8rem; height: 2.8rem; border-radius: 14px; font-size: 1.25rem; background: linear-gradient(145deg, #6366f1, #4338ca); box-shadow: 0 8px 22px rgba(79, 70, 229, 0.35); }
        .dash-footer-kicker { font-size: 0.58rem; letter-spacing: 0.18em; }
        .dash-footer-name { font-size: 1.05rem; }
        .dash-footer-tag { font-size: 0.68rem; }
        .dash-footer-status { padding: 0.36rem 0.65rem; color: #99f6e4; background: rgba(20, 184, 166, 0.08); border-color: rgba(45, 212, 191, 0.25); font-size: 0.63rem; }
        .dash-footer-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0; margin: 0 0 1.1rem; padding: 0.3rem 0; border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 16px; background: rgba(255, 255, 255, 0.035); }
        .dash-footer-stat { min-height: 4.25rem; padding: 0.72rem 1rem; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
        .dash-footer-stat + .dash-footer-stat { border-left: 1px solid rgba(148, 163, 184, 0.15); }
        .dash-footer-stat:hover { transform: none; background: rgba(99, 102, 241, 0.09); border-color: transparent; }
        .dash-footer-stat-icon { width: 2.25rem; height: 2.25rem; border-radius: 10px; font-size: 1rem; }
        .dash-footer-stat-label { font-size: 0.54rem; }
        .dash-footer-stat-value { font-size: 0.98rem; }
        .dash-footer-stat-sub { font-size: 0.61rem; }
        .dash-footer-bottom { padding-top: 0.75rem; font-size: 0.66rem; }
        .dash-footer-bottom-meta { gap: 0.65rem; }
        .dash-footer-bottom .pill-tag { padding: 0.3rem 0.6rem; font-size: 0.61rem; }
        @media (max-width: 767px) {
            .dash-footer-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dash-footer-stat:nth-child(3) { border-left: 0; border-top: 1px solid rgba(148, 163, 184, 0.15); }
            .dash-footer-stat:nth-child(4) { border-top: 1px solid rgba(148, 163, 184, 0.15); }
        }
        @media (max-width: 575px) {
            .dash-footer-inner { padding: 1.35rem 1rem 0.85rem; }
            .dash-footer-top { align-items: flex-start; }
            .dash-footer-status { margin-top: 0.1rem; }
            .dash-footer-stats { grid-template-columns: 1fr; }
            .dash-footer-stat + .dash-footer-stat,
            .dash-footer-stat:nth-child(3),
            .dash-footer-stat:nth-child(4) { border-left: 0; border-top: 1px solid rgba(148, 163, 184, 0.15); }
        }

        /* Footer v3: full composition replacement */
        .dash-footer { display: none !important; }
        .dash-footer-v3 {
            position: relative;
            margin: 2rem -0.75rem -1.5rem;
            padding: 1.5rem clamp(1rem, 3vw, 2.5rem) 1.25rem;
            background: #f4f7fb;
            border-top: 1px solid #dbe5f0;
            color: #17233b;
        }
        .dash-footer-v3-inner { max-width: 1500px; margin: 0 auto; }
        .dash-footer-v3-identity {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.5rem 1.7rem;
            border-radius: 20px;
            color: #fff;
            background: linear-gradient(115deg, #312e81 0%, #4f46e5 52%, #2563eb 100%);
            box-shadow: 0 12px 26px rgba(49, 46, 129, 0.22);
        }
        .dash-footer-v3-brand { display: flex; align-items: center; gap: 0.9rem; min-width: 0; }
        .dash-footer-v3-mark { display: grid; place-items: center; width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; border: 1px solid rgba(255,255,255,.3); border-radius: 16px; background: rgba(255,255,255,.16); font-size: 1.45rem; }
        .dash-footer-v3-eyebrow { display: block; margin-bottom: 0.15rem; color: #c7d2fe; font-size: 0.6rem; font-weight: 850; letter-spacing: 0.16em; }
        .dash-footer-v3-brand h2 { margin: 0; color: #fff; font-size: 1.3rem; font-weight: 850; letter-spacing: -0.03em; }
        .dash-footer-v3-brand p { margin: 0.2rem 0 0; color: #dbeafe; font-size: 0.73rem; }
        .dash-footer-v3-hotline { display: grid; gap: 0.1rem; min-width: 12rem; padding: 0.8rem 1rem; border: 1px solid rgba(255,255,255,.23); border-radius: 13px; background: rgba(15,23,42,.18); }
        .dash-footer-v3-hotline span { color: #dbeafe; font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
        .dash-footer-v3-hotline i { margin-right: 0.25rem; color: #fda4af; }
        .dash-footer-v3-hotline strong { color: #fff; font-size: 1rem; }
        .dash-footer-v3-hotline small { color: #bfdbfe; font-size: 0.65rem; }
        .dash-footer-v3-information { display: grid; grid-template-columns: minmax(14rem, 0.85fr) minmax(0, 1.7fr); margin-top: 0.9rem; border: 1px solid #dbe5f0; border-radius: 16px; background: #fff; box-shadow: 0 5px 16px rgba(30, 41, 59, 0.05); overflow: hidden; }
        .dash-footer-v3-mission { display: grid; align-content: center; gap: 0.2rem; padding: 1.1rem 1.35rem; border-right: 1px solid #e5ebf3; }
        .dash-footer-v3-mission > span { color: #6366f1; font-size: 0.58rem; font-weight: 850; letter-spacing: 0.13em; }
        .dash-footer-v3-mission strong { color: #1e293b; font-size: 0.9rem; line-height: 1.25; }
        .dash-footer-v3-mission small { color: #64748b; font-size: 0.68rem; }
        .dash-footer-v3-metrics { display: grid; grid-template-columns: repeat(3, 1fr); }
        .dash-footer-v3-metrics > div { display: grid; grid-template-columns: auto 1fr; align-content: center; column-gap: 0.65rem; padding: 1rem 1.15rem; }
        .dash-footer-v3-metrics > div + div { border-left: 1px solid #e5ebf3; }
        .dash-footer-v3-metrics i { grid-row: span 2; align-self: center; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 9px; color: #4f46e5; background: #eef2ff; }
        .dash-footer-v3-metrics strong { color: #17233b; font-size: 1rem; line-height: 1.05; }
        .dash-footer-v3-metrics small { color: #64748b; font-size: 0.65rem; }
        .dash-footer-v3-legal { display: flex; justify-content: space-between; gap: 1rem; padding: 1rem 0.15rem 0; color: #64748b; font-size: 0.66rem; }
        @media (max-width: 700px) {
            .dash-footer-v3 { margin-left: -0.5rem; margin-right: -0.5rem; padding: 1rem 0.75rem 0.85rem; }
            .dash-footer-v3-identity { align-items: stretch; flex-direction: column; padding: 1.2rem; }
            .dash-footer-v3-hotline { width: 100%; }
            .dash-footer-v3-information { grid-template-columns: 1fr; }
            .dash-footer-v3-mission { border-right: 0; border-bottom: 1px solid #e5ebf3; }
            .dash-footer-v3-metrics > div { padding: 0.85rem 0.7rem; }
            .dash-footer-v3-metrics strong { font-size: 0.88rem; }
        }
        @media (max-width: 430px) {
            .dash-footer-v3-metrics { grid-template-columns: 1fr; }
            .dash-footer-v3-metrics > div + div { border-left: 0; border-top: 1px solid #e5ebf3; }
            .dash-footer-v3-legal { align-items: flex-start; flex-direction: column; gap: 0.35rem; }
        }

        /* Footer v4: minimal operational footer */
        .dash-footer-v3, .dash-footer { display: none !important; }
        .dash-footer-v4 { position: relative; display: block; overflow: hidden; margin: 2rem -0.75rem -1.5rem; border-top: 0; background: #f4f7fb; color: #cbd5e1; }
        .dash-footer-v4-banner { display: none !important; }
        .dash-footer-v4-inner { position: relative; z-index: 1; max-width: none; margin: 0; padding: 1.25rem clamp(1.25rem, 3vw, 2.75rem) 0.8rem; background: #101a2d; }
        .dash-footer-v4-inner, .dash-footer-v4-details, .dash-footer-v4-brand, .dash-footer-v4-bottom { display: flex; align-items: center; }
        .dash-footer-v4-inner { flex-wrap: wrap; justify-content: space-between; gap: 1rem 2rem; }
        .dash-footer-v4-brand { gap: 0.7rem; min-width: 14rem; }
        .dash-footer-v4-mark { display: grid; place-items: center; width: 2.45rem; height: 2.45rem; flex: 0 0 2.45rem; border-radius: 11px; background: #4f46e5; color: #fff; font-size: 1.1rem; box-shadow: 0 5px 14px rgba(79,70,229,.28); }
        .dash-footer-v4-brand strong { display: block; color: #f8fafc; font-size: 0.95rem; letter-spacing: -0.01em; }
        .dash-footer-v4-brand span { display: block; margin-top: 0.12rem; color: #8fa1bb; font-size: 0.65rem; }
        .dash-footer-v4-details { flex: 1 1 auto; justify-content: flex-end; gap: 0; }
        .dash-footer-v4-details > div { display: grid; gap: 0.15rem; padding: 0 1rem; border-left: 1px solid rgba(148,163,184,.2); }
        .dash-footer-v4-details > div:first-child { border-left: 0; }
        .dash-footer-v4-details span { color: #8292aa; font-size: 0.6rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
        .dash-footer-v4-details strong { color: #f1f5f9; font-size: 0.75rem; white-space: nowrap; }
        .dash-footer-v4-details strong i { margin-right: .25rem; color: #fb7185; }
        .dash-footer-v4-status { display: inline-flex !important; align-items: center; gap: .35rem; color: #86efac; }
        .dash-footer-v4-status i { color: #34d399; font-size: .8rem; }
        .dash-footer-v4-bottom { width: 100%; justify-content: space-between; gap: 1rem; padding-top: .75rem; border-top: 1px solid rgba(148,163,184,.14); color: #71829b; font-size: .62rem; }
        @media (max-width: 760px) {
            .dash-footer-v4 { margin-left: -.5rem; margin-right: -.5rem; }
            .dash-footer-v4-details { width: 100%; justify-content: flex-start; margin-top: .25rem; }
            .dash-footer-v4-details > div { padding-left: .75rem; padding-right: .75rem; }
        }
        @media (max-width: 560px) {
            .dash-footer-v4-inner { align-items: flex-start; flex-direction: column; padding: 1.15rem 1rem .8rem; }
            .dash-footer-v4-details { align-items: stretch; flex-direction: column; gap: .65rem; }
            .dash-footer-v4-details > div, .dash-footer-v4-details > div:first-child { padding: 0; border-left: 0; }
            .dash-footer-v4-bottom { align-items: flex-start; flex-direction: column; gap: .3rem; }
        }

        /* ===== CLINICAL PANELS (card-system redesign) ===== */
        .clinical-panel {
            background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius-lg);
            margin-bottom: 1rem; overflow: hidden; box-shadow: var(--shadow-xs);
        }
        .clinical-toggle {
            width: 100%; padding: 1rem 1.35rem; background: #fff; border: none; cursor: pointer;
            display: flex; align-items: center; gap: 0.85rem; font-family: var(--font-sans);
            text-align: left; transition: background 0.15s ease;
        }
        .clinical-toggle:hover { background: var(--gray-50); }
        .clinical-toggle-icon {
            width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #fff;
        }
        .clinical-toggle-icon.medical { background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 4px 10px rgba(5,150,105,0.3); }
        .clinical-toggle-icon.trauma  { background: linear-gradient(135deg,#f43f5e,#e11d48); box-shadow: 0 4px 10px rgba(225,29,72,0.3); }
        .clinical-toggle-main { flex: 1; min-width: 0; }
        .clinical-toggle-title { font-size: 0.92rem; font-weight: 800; color: var(--gray-900); letter-spacing: -0.01em; }
        .clinical-toggle-desc { font-size: 0.72rem; color: var(--gray-500); font-weight: 500; margin-top: 0.1rem; }
        .clinical-toggle-count {
            font-size: 0.7rem; font-weight: 800; padding: 0.3rem 0.7rem; border-radius: 999px;
            background: var(--gray-100); color: var(--gray-700); white-space: nowrap;
        }
        .clinical-chevron { color: var(--gray-400); font-size: 0.8rem; transition: transform 0.25s ease; margin-left: 0.25rem; }
        .clinical-panel.open .clinical-chevron { transform: rotate(180deg); }
        .clinical-body { display: none; padding: 1.35rem; border-top: 1px solid var(--gray-100); background: var(--gray-50); }
        .clinical-panel.open .clinical-body { display: block; }
        .clinical-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap: 1rem; }

        /* ===== COLLAPSIBLE CARD TOGGLE (Consolidated Run cards) ===== */
        .card-collapse-toggle {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            background: var(--gray-50); border: 1px solid var(--gray-200);
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-500); font-size: 0.85rem; cursor: pointer;
            transition: all 0.2s ease; padding: 0; line-height: 1;
        }
        .card-collapse-toggle:hover { background: var(--primary-light); color: var(--primary); border-color: #c7d2fe; }
        .card-collapse-toggle .chevron-icon { transition: transform 0.25s ease; display: inline-block; }
        .card-panel.expanded .card-collapse-toggle .chevron-icon { transform: rotate(180deg); }
        .collapse-hidden { display: none; }
        .collapsible-hint {
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.5rem 0 0.15rem; font-size: 0.7rem; font-weight: 600;
            color: var(--gray-400); border-top: 1px dashed var(--gray-200);
            margin-top: 0.4rem; cursor: pointer;
        }
        .collapsible-hint:hover { color: var(--primary); }
        .collapsible-hint .hint-dots { letter-spacing: 0.15em; }
        .card-panel.expanded .collapsible-hint { display: none; }

        /* sub-card inside the panel */
        .clinical-card { background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 1.1rem 1.2rem; }
        .clinical-subtitle {
            font-size: 0.64rem; font-weight: 800; color: var(--gray-400); text-transform: uppercase;
            letter-spacing: 0.06em; margin-bottom: 0.9rem; display: flex; align-items: center; gap: 0.4rem;
        }
        .clinical-subtitle i { color: var(--primary); font-size: 0.85rem; }

        /* progress-bar list rows */
        .bar-list { list-style: none; padding: 0; margin: 0; }
        .bar-row { margin-bottom: 0.85rem; }
        .bar-row:last-child { margin-bottom: 0; }
        .bar-row-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.3rem; }
        .bar-row-label { font-size: 0.8rem; font-weight: 600; color: var(--gray-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 0.5rem; }
        .bar-row-val { font-size: 0.8rem; font-weight: 800; color: var(--gray-900); font-variant-numeric: tabular-nums; flex-shrink: 0; }
        .bar-track { height: 7px; background: var(--gray-100); border-radius: 999px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#6366f1,#4f46e5); }
        .bar-fill.green { background: linear-gradient(90deg,#34d399,#059669); }
        .bar-fill.rose  { background: linear-gradient(90deg,#fb7185,#e11d48); }

        /* vital-signs stat tiles */
        .vital-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.7rem; }
        .vital-tile { background: var(--gray-50); border: 1px solid var(--gray-100); border-radius: 10px; padding: 0.7rem 0.8rem; }
        .vital-tile-label { font-size: 0.62rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.04em; }
        .vital-tile-value { font-size: 1.05rem; font-weight: 800; color: var(--gray-900); margin-top: 0.15rem; }
        .vital-tile-value small { font-size: 0.7rem; font-weight: 600; color: var(--gray-400); }

        .clinical-no-data { color: var(--gray-400); font-size: 0.82rem; font-style: italic; }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 2.2rem 1rem; }
        .empty-state-icon { width: 52px; height: 52px; background: var(--gray-100); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: var(--gray-400); margin: 0 auto 0.9rem; }
        .empty-state-title { font-size: 0.9rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.2rem; }
        .empty-state-description { font-size: 0.8rem; color: var(--gray-500); max-width: 280px; margin: 0 auto; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .ems-topbar { flex-direction: column; align-items: flex-start; }
            .ems-topbar-right { width: 100%; justify-content: space-between; }
        }
        @media (max-width: 767px) {
            .kpi-value { font-size: 1.7rem; }
            .kpi-icon { width: 48px; height: 48px; font-size: 1.3rem; }
            .chart-box { height: 210px; }
            .rtable thead { display: none; }
            .rtable tbody td { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.9rem; }
            .rtable tbody td::before { content: attr(data-label); font-weight: 800; text-transform: uppercase; font-size: 0.6rem; color: var(--gray-400); letter-spacing: 0.05em; }
            .clinical-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- ===== BRANDED TOP BAR ===== -->
            <div class="ems-topbar">
                <div class="ems-brand">
                    <div class="ems-logo"><i class="bi bi-heart-pulse-fill"></i></div>
                    <div>
                        <h1 class="ems-brand-title">PRE-HOSPITAL EMS</h1>
                        <p class="ems-brand-sub">Emergency Medical Service</p>
                        <p class="ems-brand-meta"><?php echo $greeting; ?>, <?php echo e($first_name); ?> &nbsp;•&nbsp; Records &amp; Analytics</p>
                    </div>
                </div>
            </div>

            <!-- ===== KPI CARDS ===== -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl">
                    <div class="kpi-card c-blue">
                        <div>
                            <div class="kpi-label">Total Records</div>
                            <div class="kpi-value"><?php echo number_format($total_forms); ?></div>
                            <div class="kpi-foot muted">All time</div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-clipboard2-pulse-fill"></i></div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="kpi-card c-green">
                        <div>
                            <div class="kpi-label">Completed</div>
                            <div class="kpi-value"><?php echo number_format($completed_forms); ?></div>
                            <div class="kpi-foot up"><i class="bi bi-check2-circle"></i> <?php echo $completion_rate; ?>% rate</div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-heart-pulse-fill"></i></div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="kpi-card c-amber">
                        <div>
                            <div class="kpi-label">Drafts</div>
                            <div class="kpi-value"><?php echo number_format($draft_forms); ?></div>
                            <div class="kpi-foot muted">In progress</div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="kpi-card c-purple">
                        <div>
                            <div class="kpi-label">Today</div>
                            <div class="kpi-value"><?php echo number_format($today_forms); ?></div>
                            <div class="kpi-foot muted">New records</div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-calendar-day-fill"></i></div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="kpi-card c-teal">
                        <div>
                            <div class="kpi-label">This Week</div>
                            <div class="kpi-value"><?php echo number_format($week_forms); ?></div>
                            <?php if ($week_trend !== 0): ?>
                            <div class="kpi-foot <?php echo $week_trend > 0 ? 'up' : 'down'; ?>">
                                <i class="bi bi-arrow-<?php echo $week_trend > 0 ? 'up' : 'down'; ?>"></i> <?php echo abs($week_trend); ?>% vs last
                            </div>
                            <?php else: ?>
                            <div class="kpi-foot muted">vs last week</div>
                            <?php endif; ?>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-calendar-week-fill"></i></div>
                    </div>
                </div>
                <div class="col-6 col-xl">
                    <div class="kpi-card c-red">
                        <div>
                            <div class="kpi-label">Active Users</div>
                            <div class="kpi-value"><?php echo number_format($active_users); ?></div>
                            <div class="kpi-foot muted">System users</div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- ===== MIDDLE BAND: trend / type donut / type legend ===== -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-5">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Records Overview (7 Days)</h3>
                                <p class="card-sub">Daily form submissions</p>
                            </div>
                            <span class="card-badge">Last 7 days</span>
                        </div>
                        <div class="card-body-p"><div class="chart-box"><canvas id="weeklyChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Records by Type</h3>
                                <p class="card-sub">Emergency category</p>
                            </div>
                        </div>
                        <div class="card-body-p"><div class="chart-box short"><canvas id="emergencyChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Type Breakdown</h3>
                                <p class="card-sub">Share of records</p>
                            </div>
                        </div>
                        <div class="card-body-p">
                            <ul class="legend-list">
                                <?php foreach ($type_rows as $tr):
                                    $pct = $emergency_total > 0 ? round(($tr[1] / $emergency_total) * 100, 1) : 0;
                                ?>
                                <li>
                                    <span class="legend-swatch" style="background: <?php echo $tr[2]; ?>"></span>
                                    <span class="legend-name"><?php echo $tr[0]; ?></span>
                                    <span class="legend-val"><?php echo number_format($tr[1]); ?></span>
                                    <span class="legend-pct"><?php echo $pct; ?>%</span>
                                </li>
                                <?php endforeach; ?>
                                <li class="legend-total"><span>Total</span><span><?php echo number_format($emergency_total); ?></span></li>
                            </ul>
                            <p style="margin:0.6rem 0 0;font-size:0.65rem;color:var(--gray-400);">Vehicular Accident is derived from trauma notes.</p>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- ===== CONSOLIDATED RUN BAND (Incident Categories + By Emergency Type + Ambulance Units) ===== -->
            <div class="section-header">
                <h2 class="section-title"><span class="icon-dot"></span> Consolidated Run</h2>
            </div>
            <div class="row g-3 mb-4">
                <!-- Run Categories -- card with collapse toggle -->
                <div class="col-12 col-xl-5">
                    <div class="card-panel" id="cr-runcats-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Run Categories</h3>
                                <p class="card-sub">Incident, medical & OB — classified from notes</p>
                            </div>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span class="card-badge"><?php echo number_format($cr_total); ?> classified</span>
                                <button type="button" class="card-collapse-toggle" title="Expand/Collapse" data-panel="cr-runcats-panel">
                                    <i class="bi bi-chevron-down chevron-icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body-p">
                            <?php if (!empty($cr['categories'])): $cr_max = max($cr['categories']); $cr_limit = 5; $cr_idx = 0; ?>
                            <ul class="bar-list">
                                <?php foreach ($cr['categories'] as $cat => $cnt): $cr_idx++; $w = $cr_max > 0 ? round(($cnt / $cr_max) * 100) : 0; ?>
                                <li class="bar-row"<?php if ($cr_idx > $cr_limit): ?> data-collapse-hidden="1" style="display:none"<?php endif; ?>>
                                    <div class="bar-row-top">
                                        <span class="bar-row-label"><?php echo e($cat); ?></span>
                                        <span class="bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php $cr_remaining = count($cr['categories']) - $cr_limit; if ($cr_remaining > 0): ?>
                            <div class="collapsible-hint" data-panel="cr-runcats-panel">
                                <span class="hint-dots">&bull; &bull; &bull;</span>
                                <span>and <?php echo $cr_remaining; ?> more categor<?php echo $cr_remaining === 1 ? 'y' : 'ies'; ?></span>
                                <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                            </div>
                            <?php endif; ?>
                            <p style="margin:0.85rem 0 0;font-size:0.7rem;color:var(--gray-400);">Trauma incidents, medical & OB — derived from the saved category or the complaint/narrative and FAST/consciousness signals. Free-text spelling variants may affect counts.</p>
                            <?php else: ?>
                            <p class="clinical-no-data">No categorized incidents recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- By Emergency Type -- card with collapse toggle -->
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card-panel" id="cr-emergtype-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">By Emergency Type</h3>
                                <p class="card-sub">Parent totals</p>
                            </div>
                            <button type="button" class="card-collapse-toggle" title="Expand/Collapse" data-panel="cr-emergtype-panel">
                                <i class="bi bi-chevron-down chevron-icon"></i>
                            </button>
                        </div>
                        <div class="card-body-p">
                            <?php $et_idx = 0; $et_limit = 5; $et_items = $cr['parents']; if (!empty($cr['uncategorized'])) { $et_items['No type set'] = $cr['uncategorized']; } ?>
                            <ul class="rank-list">
                                <?php $cp = 0; foreach ($et_items as $pl => $pc): $cp++; $et_idx++; $rc = $cp <= 3 ? 'r'.$cp : ''; $is_uncat = ($pl === 'No type set'); ?>
                                <li<?php if ($et_idx > $et_limit): ?> data-collapse-hidden="1" style="display:none"<?php endif; ?>>
                                    <?php if ($is_uncat): ?>
                                    <span class="rank-badge" style="background:var(--gray-100);color:var(--gray-500);">–</span>
                                    <span class="rank-name">No type set</span>
                                    <?php else: ?>
                                    <span class="rank-badge <?php echo $rc; ?>"><?php echo $cp; ?></span>
                                    <span class="rank-name"><?php echo e($pl); ?></span>
                                    <?php endif; ?>
                                    <span class="rank-count"><?php echo number_format($pc); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php $et_remaining = count($et_items) - $et_limit; if ($et_remaining > 0): ?>
                            <div class="collapsible-hint" data-panel="cr-emergtype-panel">
                                <span class="hint-dots">&bull; &bull; &bull;</span>
                                <span>and <?php echo $et_remaining; ?> more</span>
                                <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Ambulance Units -- card with collapse toggle -->
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-panel" id="cr-ambunit-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Ambulance Units</h3>
                                <p class="card-sub">By unit ID</p>
                            </div>
                            <button type="button" class="card-collapse-toggle" title="Expand/Collapse" data-panel="cr-ambunit-panel">
                                <i class="bi bi-chevron-down chevron-icon"></i>
                            </button>
                        </div>
                        <div class="card-body-p">
                            <?php if (!empty($amb_units)): $au_limit = 5; $au_idx = 0; ?>
                            <ul class="rank-list">
                                <?php $vi = 0; foreach ($amb_units as $unit => $cnt): $vi++; $au_idx++; $rc = $vi <= 3 ? 'r'.$vi : ''; ?>
                                <li<?php if ($au_idx > $au_limit): ?> data-collapse-hidden="1" style="display:none"<?php endif; ?>>
                                    <span class="rank-badge <?php echo $rc; ?>"><?php echo e($unit); ?></span>
                                    <span class="rank-name">Ambulance <?php echo e($unit); ?></span>
                                    <span class="rank-count"><?php echo number_format($cnt); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php $au_remaining = count($amb_units) - $au_limit; if ($au_remaining > 0): ?>
                            <div class="collapsible-hint" data-panel="cr-ambunit-panel">
                                <span class="hint-dots">&bull; &bull; &bull;</span>
                                <span>and <?php echo $au_remaining; ?> more unit<?php echo $au_remaining === 1 ? '' : 's'; ?></span>
                                <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <p class="clinical-no-data">No unit IDs recorded.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- ===== BARANGAY BAND: bar / ranked list / status donut ===== -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-5">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Records by Barangay</h3>
                                <p class="card-sub">Top patient localities</p>
                            </div>
                        </div>
                        <div class="card-body-p">
                            <?php if (!empty($zone_data)): ?>
                            <div class="chart-box short"><canvas id="zoneChart"></canvas></div>
                            <?php else: ?>
                            <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-geo-alt"></i></div><div class="empty-state-title">No location data</div><div class="empty-state-description">Barangay breakdown appears once records include addresses.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Top Barangays</h3>
                                <p class="card-sub">Ranked by record count</p>
                            </div>
                        </div>
                        <div class="card-body-p">
                            <?php if (!empty($zone_rows)): ?>
                            <ul class="rank-list">
                                <?php foreach ($zone_rows as $i => $z): $rank = $i + 1; $rc = $rank <= 3 ? 'r'.$rank : ''; ?>
                                <li>
                                    <span class="rank-badge <?php echo $rc; ?>"><?php echo $rank; ?></span>
                                    <span class="rank-name"><?php echo e($z['zone']); ?></span>
                                    <span class="rank-count"><?php echo number_format((int)$z['count']); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="clinical-no-data">No barangay data recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Status</h3>
                                <p class="card-sub">Record lifecycle</p>
                            </div>
                        </div>
                        <div class="card-body-p"><div class="chart-box short"><canvas id="statusChart"></canvas></div></div>
                    </div>
                </div>
            </div>

            <!-- ===== CLINICAL DETAIL PANELS ===== -->
            <?php if ($emergency_medical > 0 || ($emergency_trauma > 0 && !empty($trauma_injuries_dash))): ?>
            <hr class="section-divider">
            <div class="section-header"><h2 class="section-title"><span class="icon-dot"></span> Clinical Detail Breakdown</h2></div>
            <?php endif; ?>

            <?php if ($emergency_medical > 0): ?>
            <div class="clinical-panel open">
                <button type="button" class="clinical-toggle">
                    <span class="clinical-toggle-icon medical"><i class="bi bi-heart-pulse-fill"></i></span>
                    <span class="clinical-toggle-main">
                        <span class="clinical-toggle-title">Medical Cases</span>
                        <span class="clinical-toggle-desc">Chief complaints, conditions &amp; vital sign averages</span>
                    </span>
                    <span class="clinical-toggle-count"><?php echo number_format($emergency_medical); ?> cases</span>
                    <i class="bi bi-chevron-down clinical-chevron"></i>
                </button>
                <div class="clinical-body">
                    <div class="clinical-grid">
                        <div class="clinical-card">
                            <div class="clinical-subtitle"><i class="bi bi-list-check"></i> Top Chief Complaints</div>
                            <?php if (!empty($medical_complaints_dash)): ?>
                            <?php $cc_max = max($medical_complaints_dash); ?>
                            <ul class="bar-list">
                                <?php
                                $complaint_labels = ['chestPain'=>'Chest Pain','headache'=>'Headache','blurredVision'=>'Blurred Vision','difficultyBreathing'=>'Difficulty Breathing','dizziness'=>'Dizziness','bodyMalaise'=>'Body Malaise'];
                                foreach ($medical_complaints_dash as $complaint => $cnt):
                                    $w = $cc_max > 0 ? round(($cnt / $cc_max) * 100) : 0; ?>
                                <li class="bar-row">
                                    <div class="bar-row-top">
                                        <span class="bar-row-label"><?php echo e($complaint_labels[$complaint] ?? ucwords(str_replace(['_','-'],' ',$complaint))); ?></span>
                                        <span class="bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill green" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p class="clinical-no-data">No chief complaint data recorded.</p><?php endif; ?>
                        </div>
                        <div class="clinical-card">
                            <div class="clinical-subtitle"><i class="bi bi-clipboard2-pulse"></i> Specified Conditions</div>
                            <?php if (!empty($medical_details_dash)): ?>
                            <?php $md_max = max($medical_details_dash); ?>
                            <ul class="bar-list">
                                <?php foreach ($medical_details_dash as $detail => $cnt):
                                    $w = $md_max > 0 ? round(($cnt / $md_max) * 100) : 0; ?>
                                <li class="bar-row">
                                    <div class="bar-row-top">
                                        <span class="bar-row-label"><?php echo e($detail); ?></span>
                                        <span class="bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill green" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p class="clinical-no-data">No specified condition details recorded.</p><?php endif; ?>
                        </div>
                        <div class="clinical-card">
                            <div class="clinical-subtitle"><i class="bi bi-activity"></i> Vital Signs (Averages)</div>
                            <div class="vital-grid">
                                <div class="vital-tile">
                                    <div class="vital-tile-label">Systolic BP</div>
                                    <div class="vital-tile-value"><?php
                                        if (!empty($medical_vitals_dash['bp'])) { $bps=[]; foreach ($medical_vitals_dash['bp'] as $bp){ $p=explode('/',$bp); $bps[]=intval($p[0]); } echo round(array_sum($bps)/count($bps)); echo ' <small>mmHg</small>'; } else { echo '—'; }
                                    ?></div>
                                </div>
                                <div class="vital-tile">
                                    <div class="vital-tile-label">Temperature</div>
                                    <div class="vital-tile-value"><?php echo !empty($medical_vitals_dash['temp']) ? round(array_sum($medical_vitals_dash['temp'])/count($medical_vitals_dash['temp']),1).' <small>°C</small>' : '—'; ?></div>
                                </div>
                                <div class="vital-tile">
                                    <div class="vital-tile-label">Pulse Rate</div>
                                    <div class="vital-tile-value"><?php echo !empty($medical_vitals_dash['pulse']) ? round(array_sum($medical_vitals_dash['pulse'])/count($medical_vitals_dash['pulse'])).' <small>BPM</small>' : '—'; ?></div>
                                </div>
                                <div class="vital-tile">
                                    <div class="vital-tile-label">SpO₂</div>
                                    <div class="vital-tile-value"><?php echo !empty($medical_vitals_dash['spo2']) ? round(array_sum($medical_vitals_dash['spo2'])/count($medical_vitals_dash['spo2'])).' <small>%</small>' : '—'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($emergency_trauma > 0 && !empty($trauma_injuries_dash)): ?>
            <?php
            // Pre-aggregate injury types & body parts for the bars
            $injury_types = [];
            foreach ($trauma_injuries_dash as $inj) {
                $t = $inj['injury_type'] ?: 'Unspecified';
                $injury_types[$t] = ($injury_types[$t] ?? 0) + (int)$inj['count'];
            }
            arsort($injury_types);
            $injury_types = array_slice($injury_types, 0, 8, true);
            $it_max = !empty($injury_types) ? max($injury_types) : 0;

            $body_parts = [];
            foreach ($trauma_injuries_dash as $inj) {
                if (!empty($inj['body_part'])) { $body_parts[$inj['body_part']] = ($body_parts[$inj['body_part']] ?? 0) + (int)$inj['count']; }
            }
            arsort($body_parts);
            $body_parts = array_slice($body_parts, 0, 8, true);
            $bp_max = !empty($body_parts) ? max($body_parts) : 0;
            ?>
            <div class="clinical-panel">
                <button type="button" class="clinical-toggle">
                    <span class="clinical-toggle-icon trauma"><i class="bi bi-bandaid-fill"></i></span>
                    <span class="clinical-toggle-main">
                        <span class="clinical-toggle-title">Trauma Cases</span>
                        <span class="clinical-toggle-desc">Injury types &amp; body parts affected</span>
                    </span>
                    <span class="clinical-toggle-count"><?php echo number_format($emergency_trauma); ?> cases</span>
                    <i class="bi bi-chevron-down clinical-chevron"></i>
                </button>
                <div class="clinical-body">
                    <div class="clinical-grid">
                        <div class="clinical-card">
                            <div class="clinical-subtitle"><i class="bi bi-exclamation-triangle"></i> Injury Types</div>
                            <?php if (!empty($injury_types)): ?>
                            <ul class="bar-list">
                                <?php foreach ($injury_types as $type => $cnt): $w = $it_max > 0 ? round(($cnt / $it_max) * 100) : 0; ?>
                                <li class="bar-row">
                                    <div class="bar-row-top">
                                        <span class="bar-row-label"><?php echo e($type); ?></span>
                                        <span class="bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill rose" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p class="clinical-no-data">No injury type data recorded.</p><?php endif; ?>
                        </div>
                        <div class="clinical-card">
                            <div class="clinical-subtitle"><i class="bi bi-person-bounding-box"></i> Body Parts Affected</div>
                            <?php if (!empty($body_parts)): ?>
                            <ul class="bar-list">
                                <?php foreach ($body_parts as $part => $cnt): $w = $bp_max > 0 ? round(($cnt / $bp_max) * 100) : 0; ?>
                                <li class="bar-row">
                                    <div class="bar-row-top">
                                        <span class="bar-row-label"><?php echo e($part); ?></span>
                                        <span class="bar-row-val"><?php echo number_format($cnt); ?></span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill rose" style="width: <?php echo $w; ?>%"></div></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?><p class="clinical-no-data">No body part data recorded.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <hr class="section-divider">

            <!-- ===== MONTHLY + HOSPITALS ===== -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-8">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Monthly Performance</h3>
                                <p class="card-sub">Records over the last 12 months</p>
                            </div>
                            <span class="card-badge">12 months</span>
                        </div>
                        <div class="card-body-p"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card-panel">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Top Destination Hospitals</h3>
                                <p class="card-sub">Most frequent arrival facilities</p>
                            </div>
                        </div>
                        <div class="card-body-p">
                            <?php if (!empty($hospital_data)): ?>
                            <div class="chart-box short"><canvas id="hospitalsChart"></canvas></div>
                            <?php else: ?>
                            <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-hospital"></i></div><div class="empty-state-title">No hospital data</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- ===== RECENT RECORDS + ACTIVITY ===== -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-xl-8">
                    <div class="card-panel">
                        <div class="card-head">
                            <div><h3 class="card-title">Recent Records</h3><p class="card-sub">Latest submissions</p></div>
                            <a href="../public/records.php" class="link-more">View All <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="table-responsive">
                            <table class="rtable">
                                <thead>
                                    <tr><th>Form #</th><th>Date</th><th>Patient</th><th>Age/Sex</th><th>Vehicle</th><th>Status</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_forms)): ?>
                                        <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-inbox"></i></div><div class="empty-state-title">No records yet</div></div></td></tr>
                                    <?php else: ?>
                                        <?php
                                        $av = ['', 'p', 't', 'r', '', 'p', 't', 'r', '', 'p']; $ri = 0;
                                        foreach (array_slice($recent_forms, 0, 5) as $form):
                                            $ac = $av[$ri % count($av)]; $ri++;
                                            $ini = strtoupper(substr($form['patient_name'] ?: '?', 0, 1));
                                            $sc = ['completed'=>'completed','draft'=>'draft','archived'=>'archived'][$form['status']] ?? 'draft';
                                        ?>
                                        <tr>
                                            <td data-label="Form #"><strong style="color:var(--gray-900);"><?php echo e($form['form_number']); ?></strong></td>
                                            <td data-label="Date"><?php echo $form['form_date'] ? date('M d, Y', strtotime($form['form_date'])) : '-'; ?><br><span class="relt"><?php echo time_ago($form['created_at']); ?></span></td>
                                            <td data-label="Patient"><span class="mini-avatar <?php echo $ac; ?>"><?php echo $ini; ?></span><?php echo e($form['patient_name'] ?: '-'); ?></td>
                                            <td data-label="Age/Sex"><?php echo e($form['age'] ?: '-'); ?> / <?php echo ucfirst(e($form['gender'] ?: '-')); ?></td>
                                            <td data-label="Vehicle"><?php if ($form['vehicle_used']): ?><span class="pill vehicle"><i class="bi bi-truck"></i> <?php echo ucfirst(e($form['vehicle_used'])); ?></span><?php else: ?><span style="color:var(--gray-400);">-</span><?php endif; ?></td>
                                            <td data-label="Status"><span class="pill <?php echo $sc; ?>"><?php if ($sc === 'completed'): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?><?php echo ucfirst(e($form['status'])); ?></span></td>
                                            <td data-label=""><a href="../public/records.php?view=<?php echo $form['id']; ?>" class="btn-view"><i class="bi bi-eye"></i> View</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card-panel">
                        <div class="card-head"><div><h3 class="card-title">Recent Activity</h3><p class="card-sub">Latest changes</p></div></div>
                        <div class="feed">
                            <?php if (empty($recent_activity)): ?>
                                <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-inbox"></i></div><div class="empty-state-title">No activity yet</div></div>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_activity, 0, 5) as $act):
                                    $as = $act['status'] ?? 'draft';
                                    $ad = $as === 'completed' ? 'completed' : ($as === 'draft' ? 'draft' : 'created');
                                    $al = $as === 'completed' ? 'completed form' : ($as === 'draft' ? 'saved draft' : 'created form');
                                ?>
                                <div class="feed-item">
                                    <div class="feed-dot <?php echo $ad; ?>"></div>
                                    <div style="flex:1;min-width:0;">
                                        <div class="feed-text"><strong><?php echo e($act['created_by_name'] ?? 'System'); ?></strong> <?php echo $al; ?> <strong>#<?php echo e($act['form_number']); ?></strong> — <?php echo e($act['patient_name'] ?: 'Unknown'); ?></div>
                                        <div class="feed-time"><?php echo time_ago($act['updated_at'] ?? $act['created_at']); ?></div>
                                    </div>
                                    <a href="../public/records.php?view=<?php echo $act['id']; ?>" class="btn-view"><i class="bi bi-eye"></i></a>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- ===== DARK FOOTER BAND (consolidated — replaces the redundant info tiles) ===== -->
            <footer class="dash-footer-v4">
                <div class="dash-footer-v4-inner">
                    <div class="dash-footer-v4-brand">
                        <div class="dash-footer-v4-mark"><i class="bi bi-heart-pulse-fill"></i></div>
                        <div><strong>RESCUE 116-link</strong><span>Pre-Hospital Emergency Care System</span></div>
                    </div>
                    <div class="dash-footer-v4-details">
                        <div><span>Emergency line</span><strong><i class="bi bi-telephone-fill"></i> 0967 379 7967</strong></div>
                        <div><span>Mission</span><strong>Magkasama sa Bilis na Tugon</strong></div>
                        <div class="dash-footer-v4-status"><i class="bi bi-check-circle-fill"></i><span>System operational</span></div>
                    </div>
                    <div class="dash-footer-v4-bottom"><span>&copy; <?php echo date('Y'); ?> RESCUE 116-link</span><span>Baggao MDRRMO &middot; Emergency response 24/7</span></div>
                </div>
            </footer>
            <footer class="dash-footer-v3">
                <div class="dash-footer-v3-inner">
                    <div class="dash-footer-v3-identity">
                        <div class="dash-footer-v3-brand">
                            <div class="dash-footer-v3-mark"><i class="bi bi-heart-pulse-fill"></i></div>
                            <div>
                                <span class="dash-footer-v3-eyebrow">RESCUE 116 / MDRRMO</span>
                                <h2>RESCUE 116-link</h2>
                                <p>Pre-Hospital Emergency Care System</p>
                            </div>
                        </div>
                        <div class="dash-footer-v3-hotline">
                            <span><i class="bi bi-telephone-fill"></i> Emergency hotline</span>
                            <strong>0967 379 7967</strong>
                            <small>Available 24 hours</small>
                        </div>
                    </div>
                    <div class="dash-footer-v3-information">
                        <div class="dash-footer-v3-mission">
                            <span>OUR MISSION</span>
                            <strong>Magkasama sa Bilis na Tugon</strong>
                            <small>Ligtas na Bayan</small>
                        </div>
                        <div class="dash-footer-v3-metrics" aria-label="System summary">
                            <div><i class="bi bi-people-fill"></i><strong><?php echo number_format($active_users); ?></strong><small>Active users</small></div>
                            <div><i class="bi bi-hospital-fill"></i><strong><?php echo number_format($hospital_network); ?></strong><small>Hospitals</small></div>
                            <div><i class="bi bi-shield-check"></i><strong>24/7</strong><small>Response ready</small></div>
                        </div>
                    </div>
                    <div class="dash-footer-v3-legal"><span>&copy; <?php echo date('Y'); ?> RESCUE 116-link &middot; Pre-Hospital Care System</span><span>For authorized responders</span></div>
                </div>
            </footer>
            <div class="dash-footer">
              <div class="dash-footer-inner">
                <div class="dash-footer-top">
                    <div class="dash-footer-brand">
                        <div class="dash-footer-logo"><i class="bi bi-heart-pulse-fill"></i></div>
                        <div>
                            <div class="dash-footer-kicker">RESCUE 116 / MDRRMO</div>
                            <div class="dash-footer-name">RESCUE 116-link</div>
                            <div class="dash-footer-tag">Pre-Hospital Emergency Care System</div>
                        </div>
                    </div>
                    <div class="dash-footer-status"><span class="dash-footer-status-dot"></span><span>System operational</span></div>
                </div>
                <div class="dash-footer-stats">
                    <div class="dash-footer-stat">
                        <div class="dash-footer-stat-icon green"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="dash-footer-stat-label">System Users</div>
                            <div class="dash-footer-stat-value"><?php echo number_format($active_users); ?></div>
                            <div class="dash-footer-stat-sub">Active accounts</div>
                        </div>
                    </div>
                    <div class="dash-footer-stat">
                        <div class="dash-footer-stat-icon blue"><i class="bi bi-hospital-fill"></i></div>
                        <div>
                            <div class="dash-footer-stat-label">Hospital Network</div>
                            <div class="dash-footer-stat-value"><?php echo number_format($hospital_network); ?></div>
                            <div class="dash-footer-stat-sub">Destination facilities</div>
                        </div>
                    </div>
                    <div class="dash-footer-stat">
                        <div class="dash-footer-stat-icon red"><i class="bi bi-telephone-fill"></i></div>
                        <div>
                            <div class="dash-footer-stat-label">Emergency Hotline</div>
                            <div class="dash-footer-stat-value">RESCUE 116</div>
                            <div class="dash-footer-stat-sub">0967 379 7967</div>
                        </div>
                    </div>
                    <div class="dash-footer-stat">
                        <div class="dash-footer-stat-icon amber"><i class="bi bi-heart-pulse-fill"></i></div>
                        <div>
                            <div class="dash-footer-stat-label">Our Mission</div>
                            <div class="dash-footer-stat-value" style="font-size:0.82rem;line-height:1.3;">Magkasama sa Bilis na Tugon</div>
                            <div class="dash-footer-stat-sub">Ligtas na Bayan</div>
                        </div>
                    </div>
                </div>
                <div class="dash-footer-bottom">
                    <div><span>&copy; <?php echo date('Y'); ?> RESCUE 116-link</span><span class="dash-footer-separator">•</span><span>Pre-Hospital Care System</span></div>
                    <div class="dash-footer-bottom-meta"><span>For authorized responders</span><span class="pill-tag"><i class="bi bi-shield-check"></i> Emergency 24/7</span></div>
                </div>
              </div>
            </div>
        </div>
    </div>

    <!-- Chart Data Passed via Data Attributes (CSP-safe) -->
    <div id="admin-chart-data" style="display:none"
         data-weekly-labels="<?php echo htmlspecialchars(json_encode($last_7_days), ENT_QUOTES, 'UTF-8'); ?>"
         data-weekly-data="<?php echo htmlspecialchars(json_encode($last_7_days_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-monthly-labels="<?php echo htmlspecialchars(json_encode($monthly_labels), ENT_QUOTES, 'UTF-8'); ?>"
         data-monthly-data="<?php echo htmlspecialchars(json_encode($monthly_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-completed="<?php echo $completed_forms; ?>"
         data-drafts="<?php echo $draft_forms; ?>"
         data-archived="<?php echo $archived_count; ?>"
         data-emergency-medical="<?php echo $emergency_medical; ?>"
         data-emergency-trauma="<?php echo $emergency_trauma; ?>"
         data-emergency-ob="<?php echo $emergency_ob; ?>"
         data-emergency-general="<?php echo $emergency_general; ?>"
         data-hospital-labels="<?php echo htmlspecialchars(json_encode($hospital_labels), ENT_QUOTES, 'UTF-8'); ?>"
         data-hospital-data="<?php echo htmlspecialchars(json_encode($hospital_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-hospital-colors="<?php echo htmlspecialchars(json_encode($hospital_colors), ENT_QUOTES, 'UTF-8'); ?>"
         data-zone-labels="<?php echo htmlspecialchars(json_encode($zone_labels), ENT_QUOTES, 'UTF-8'); ?>"
         data-zone-data="<?php echo htmlspecialchars(json_encode($zone_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-type-labels="<?php echo htmlspecialchars(json_encode(array_column($type_rows, 0)), ENT_QUOTES, 'UTF-8'); ?>"
         data-type-data="<?php echo htmlspecialchars(json_encode(array_map('intval', array_column($type_rows, 1))), ENT_QUOTES, 'UTF-8'); ?>"
         data-type-colors="<?php echo htmlspecialchars(json_encode(array_column($type_rows, 2)), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/admin-dashboard-charts.js?v=<?php echo time(); ?>"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        // Clinical panel toggle
        document.querySelectorAll('.clinical-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var panel = this.closest('.clinical-panel');
                if (panel) { panel.classList.toggle('open'); }
            });
        });

        // Consolidated Run card collapse/expand toggle
        (function() {
            function togglePanel(panelId) {
                var panel = document.getElementById(panelId);
                if (!panel) return;
                var isExpanded = panel.classList.toggle('expanded');
                var hiddenRows = panel.querySelectorAll('[data-collapse-hidden="1"]');
                hiddenRows.forEach(function(row) {
                    if (isExpanded) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            // Attach to chevron toggle buttons
            document.querySelectorAll('.card-collapse-toggle').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var panelId = this.getAttribute('data-panel');
                    if (panelId) togglePanel(panelId);
                });
            });

            // Attach to collapsible hint rows (click the hint to expand)
            document.querySelectorAll('.collapsible-hint').forEach(function(hint) {
                hint.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var panelId = this.getAttribute('data-panel');
                    if (panelId) togglePanel(panelId);
                });
            });
        })();
    </script>
</body>
</html>
