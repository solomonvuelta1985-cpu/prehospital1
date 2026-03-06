<?php
/**
 * Comprehensive Reports & Analytics Page
 * View detailed analytics, charts, and reports for Pre-Hospital Care System
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

// Get filter parameters - Default to last 3 months for meaningful data
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01', strtotime('-2 months'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build WHERE clause
$where = ["1=1"];
$params = [];

if ($date_from) {
    $where[] = "DATE(pf.form_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where[] = "DATE(pf.form_date) <= ?";
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
    // Non-admin users only see their own data
    $where[] = "pf.created_by = ?";
    $params[] = $user_id;
}

$where_clause = implode(' AND ', $where);

// Get summary statistics
$summary_sql = "
    SELECT
        COUNT(*) as total_forms,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_forms,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_forms,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_forms,
        SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(NOW()) THEN 1 ELSE 0 END) as week_forms
    FROM prehospital_forms pf
    WHERE $where_clause
";
$summary_stmt = db_query($summary_sql, $params);
$summary = $summary_stmt ? $summary_stmt->fetch() : false;
if (!$summary) {
    $summary = ['total_forms' => 0, 'completed_forms' => 0, 'draft_forms' => 0, 'archived_forms' => 0, 'today_forms' => 0, 'week_forms' => 0];
}

// Get forms by emergency type
$emergency_sql = "
    SELECT
        SUM(CASE WHEN emergency_medical = 1 THEN 1 ELSE 0 END) as medical,
        SUM(CASE WHEN emergency_trauma = 1 THEN 1 ELSE 0 END) as trauma,
        SUM(CASE WHEN emergency_ob = 1 THEN 1 ELSE 0 END) as obstetric,
        SUM(CASE WHEN emergency_general = 1 THEN 1 ELSE 0 END) as general
    FROM prehospital_forms pf
    WHERE $where_clause
";
$emergency_stmt = db_query($emergency_sql, $params);
$emergency_data = $emergency_stmt ? $emergency_stmt->fetch() : false;
if (!$emergency_data) {
    $emergency_data = ['medical' => 0, 'trauma' => 0, 'obstetric' => 0, 'general' => 0];
}

// Get forms by vehicle type
$vehicle_sql = "
    SELECT
        vehicle_used,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause
    GROUP BY vehicle_used
    ORDER BY count DESC
";
$vehicle_stmt = db_query($vehicle_sql, $params);
$vehicle_data = $vehicle_stmt ? $vehicle_stmt->fetchAll() : [];

// Get patient demographics
$age_sql = "
    SELECT
        CASE
            WHEN age < 18 THEN '0-17'
            WHEN age BETWEEN 18 AND 30 THEN '18-30'
            WHEN age BETWEEN 31 AND 50 THEN '31-50'
            WHEN age BETWEEN 51 AND 70 THEN '51-70'
            ELSE '71+'
        END as age_group,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND age IS NOT NULL
    GROUP BY age_group
    ORDER BY age_group
";
$age_stmt = db_query($age_sql, $params);
$age_data = $age_stmt ? $age_stmt->fetchAll() : [];

$gender_sql = "
    SELECT
        gender,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND gender IS NOT NULL
    GROUP BY gender
";
$gender_stmt = db_query($gender_sql, $params);
$gender_data = $gender_stmt ? $gender_stmt->fetchAll() : [];

// Get injury statistics
$injury_sql = "
    SELECT
        i.injury_type,
        COUNT(*) as count
    FROM injuries i
    JOIN prehospital_forms pf ON i.form_id = pf.id
    WHERE $where_clause
    GROUP BY i.injury_type
    ORDER BY count DESC
";
$injury_stmt = db_query($injury_sql, $params);
$injury_data = $injury_stmt ? $injury_stmt->fetchAll() : [];

// Get forms by hospital
$hospital_sql = "
    SELECT
        arrival_hospital_name,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND arrival_hospital_name IS NOT NULL AND arrival_hospital_name != ''
    GROUP BY arrival_hospital_name
    ORDER BY count DESC
    LIMIT 10
";
$hospital_stmt = db_query($hospital_sql, $params);
$hospital_data = $hospital_stmt ? $hospital_stmt->fetchAll() : [];

// Get daily trends (last 30 days or filtered range)
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

// Get top performing users
if ($is_admin) {
    $user_perf_sql = "
        SELECT
            u.full_name,
            u.username,
            COUNT(pf.id) as total_forms,
            SUM(CASE WHEN pf.status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
            ROUND(SUM(CASE WHEN pf.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(pf.id), 1) as completion_rate
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
}

// Get all users for filter dropdown (admin only)
if ($is_admin) {
    $users_stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role IN ('admin', 'user') ORDER BY full_name");
    $all_users = $users_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Pre-Hospital Care System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --rpt-primary: #1e3a5f;
            --rpt-primary-light: #2d5a8a;
            --rpt-accent: #2563eb;
            --rpt-text: #1e3a5f;
            --rpt-text-secondary: #475569;
            --rpt-text-muted: #64748b;
            --rpt-bg-body: #f8fafc;
            --rpt-bg-white: #ffffff;
            --rpt-border: #e2e8f0;
            --rpt-border-light: #f1f5f9;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--rpt-bg-body);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--rpt-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #1e3a5f !important;
        }

        /* Page Header */
        .page-header {
            background: var(--rpt-bg-white);
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--rpt-accent);
        }

        .page-header h1 {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--rpt-text);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-header p {
            color: var(--rpt-text-muted);
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            font-weight: 400;
        }

        /* Section */
        .section {
            background: var(--rpt-bg-white);
            border: 1px solid var(--rpt-border);
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--rpt-border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e3a5f !important;
            margin: 0;
            letter-spacing: -0.01em;
        }

        .section-badge {
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--rpt-text-muted);
            background: var(--rpt-bg-body);
            padding: 0.25rem 0.625rem;
            border-radius: 4px;
        }

        .section-body { padding: 1.5rem; }

        /* Filter Bar */
        .filter-bar {
            background: var(--rpt-bg-white);
            border: 1px solid var(--rpt-border);
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-bar .form-label {
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--rpt-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.375rem;
        }

        .filter-bar .form-control,
        .filter-bar .form-select {
            border: 1px solid var(--rpt-border);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 500;
            background: var(--rpt-bg-white);
            color: var(--rpt-text);
        }

        .filter-bar .form-control:focus,
        .filter-bar .form-select:focus {
            border-color: var(--rpt-accent);
            box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.1);
            outline: none;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            background: var(--rpt-bg-white);
            padding: 1rem 1.25rem;
            border-radius: 8px;
            border: 1px solid var(--rpt-border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .stat-item:nth-child(1) .stat-icon { background: #eff6ff; color: #3b82f6; }
        .stat-item:nth-child(2) .stat-icon { background: #ecfdf5; color: #10b981; }
        .stat-item:nth-child(3) .stat-icon { background: #fffbeb; color: #f59e0b; }
        .stat-item:nth-child(4) .stat-icon { background: #f5f3ff; color: #8b5cf6; }

        .stat-content { min-width: 0; }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3a5f;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.6875rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* Chart Container */
        .chart-container { position: relative; height: 280px; }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .data-table th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--rpt-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--rpt-border);
            background: var(--rpt-bg-body);
        }

        .data-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--rpt-border-light);
            color: var(--rpt-text-secondary);
        }

        .data-table td:first-child { color: var(--rpt-text); font-weight: 500; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: var(--rpt-bg-body); }

        /* Progress Bars */
        .progress-bar-wrap {
            height: 4px;
            background: var(--rpt-border-light);
            border-radius: 2px;
            overflow: hidden;
            min-width: 100px;
        }

        .progress-bar-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 2px;
        }

        /* Buttons */
        .btn-primary-custom {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8125rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: background 0.15s;
        }

        .btn-primary-custom:hover { background: #1d4ed8; color: white; }

        .btn-outline {
            background: transparent;
            color: var(--rpt-text-secondary);
            border: 1px solid var(--rpt-border);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8125rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-outline:hover {
            background: var(--rpt-bg-body);
            border-color: var(--rpt-text-muted);
            color: var(--rpt-text);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .status-badge.success { background: #e8f5e9; color: #2e7d32; }
        .status-badge.warning { background: #fff3e0; color: #e65100; }
        .status-badge.danger { background: #ffebee; color: #c62828; }

        /* Grid Layouts */
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }

        /* Row Styling */
        .row { --bs-gutter-x: 1.5rem; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .grid-3 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .page-header h1 { font-size: 1.125rem; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
            .stat-item { padding: 0.875rem 1rem; }
            .stat-icon { width: 32px; height: 32px; font-size: 0.875rem; }
            .stat-value { font-size: 1.25rem; }
            .section-body { padding: 1rem; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stat-value { font-size: 1.125rem; }
            .filter-bar { padding: 1rem; }
        }

        /* Print */
        @media print {
            .filter-bar, .btn-primary-custom, .btn-outline { display: none !important; }
            .section { border: 1px solid var(--rpt-border); box-shadow: none; }
        }
    </style>
</head>
<body>
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container-fluid">
                <h1>Reports & Analytics</h1>
                <p>Performance metrics and operational insights</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php show_flash(); ?>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" action="reports.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                   value="<?php echo e($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo e($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <?php if ($is_admin): ?>
                        <div class="col-md-2">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="0">All Users</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-primary-custom">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($summary['total_forms']); ?></div>
                        <div class="stat-label">Total Forms</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format((int)($summary['completed_forms'] ?? 0)); ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format((int)($summary['draft_forms'] ?? 0)); ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format((int)($summary['week_forms'] ?? 0)); ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                </div>
            </div>

            <!-- Main Trend Chart -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Forms Over Time</h2>
                    <span class="section-badge"><?php echo count($trend_data); ?> days</span>
                </div>
                <div class="section-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Distribution Charts -->
            <div class="grid-3">
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">By Status</h2>
                    </div>
                    <div class="section-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">Emergency Types</h2>
                    </div>
                    <div class="section-body">
                        <div class="chart-container">
                            <canvas id="emergencyChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">Age Groups</h2>
                    </div>
                    <div class="section-body">
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Hospitals Table -->
            <?php if (!empty($hospital_data)): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Top Receiving Hospitals</h2>
                    <span class="section-badge">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hospital</th>
                                <th>Forms</th>
                                <th>Share</th>
                                <th style="width: 200px;">Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($hospital_data as $hospital):
                                $percentage = $summary['total_forms'] > 0 ? ($hospital['count'] / $summary['total_forms']) * 100 : 0;
                            ?>
                            <tr>
                                <td><?php echo e($hospital['arrival_hospital_name']); ?></td>
                                <td><?php echo number_format($hospital['count']); ?></td>
                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                <td>
                                    <div class="progress-bar-wrap">
                                        <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- User Performance Table (Admin Only) -->
            <?php if ($is_admin && !empty($user_performance)): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Team Performance</h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Rate</th>
                                <th style="width: 150px;">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_performance as $user): ?>
                            <tr>
                                <td><?php echo e($user['full_name']); ?></td>
                                <td><?php echo number_format($user['total_forms']); ?></td>
                                <td><?php echo number_format($user['completed_forms']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['completion_rate'] >= 80 ? 'success' : ($user['completion_rate'] >= 50 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($user['completion_rate'], 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-bar-wrap">
                                        <div class="progress-bar-fill" style="width: <?php echo $user['completion_rate']; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Export Actions -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Export</h2>
                </div>
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2">
                        <button onclick="window.print()" class="btn btn-outline">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button onclick="exportToCSV()" class="btn btn-outline">
                            <i class="bi bi-file-earmark-spreadsheet"></i> CSV
                        </button>
                        <button onclick="exportToPDF()" class="btn btn-outline">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        // Professional blue color palette
        const colors = {
            blue: '#3b82f6',
            blueDark: '#1d4ed8',
            blueLight: '#93c5fd',
            teal: '#14b8a6',
            emerald: '#10b981',
            amber: '#f59e0b',
            slate: '#64748b'
        };

        // Chart.js default configuration
        Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
        Chart.defaults.font.weight = 400;
        Chart.defaults.color = '#64748b';

        // Shared chart options
        const sharedOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    cornerRadius: 6,
                    titleFont: { size: 12, weight: 600 },
                    bodyFont: { size: 12, weight: 400 }
                }
            }
        };

        // Trend Chart
        const trendData = <?php echo json_encode($trend_data); ?> || [];
        console.log('TREND DATA DEBUG:', JSON.stringify(trendData[0]));
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Forms',
                    data: trendData.map(d => d.count),
                    borderColor: colors.blue,
                    backgroundColor: '#eff6ff',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: colors.blue,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 6,
                        titleFont: { size: 12, weight: 600 },
                        bodyFont: { size: 12, weight: 400 },
                        footerFont: { size: 11, weight: 400 },
                        footerColor: '#94a3b8',
                        footerMarginTop: 8,
                        callbacks: {
                            title: function(tooltipItems) {
                                var idx = tooltipItems[0].dataIndex;
                                return new Date(trendData[idx].date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                            },
                            label: function(context) {
                                return 'Forms: ' + context.parsed.y;
                            },
                            footer: function(tooltipItems) {
                                var idx = tooltipItems[0].dataIndex;
                                var d = trendData[idx];
                                console.log('FOOTER CALLED, data:', d);
                                var lines = [];
                                if (Number(d.medical) > 0) lines.push('Medical: ' + d.medical);
                                if (Number(d.trauma) > 0) lines.push('Trauma: ' + d.trauma);
                                if (Number(d.obstetric) > 0) lines.push('Obstetric: ' + d.obstetric);
                                if (Number(d.general) > 0) lines.push('General: ' + d.general);
                                if (lines.length > 0) {
                                    return ['Emergency Types:'].concat(lines);
                                }
                                return 'No emergency type';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } },
                        border: { display: false }
                    }
                }
            }
        });

        // Status Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Draft', 'Archived'],
                datasets: [{
                    data: [
                        <?php echo (int)($summary['completed_forms'] ?? 0); ?>,
                        <?php echo (int)($summary['draft_forms'] ?? 0); ?>,
                        <?php echo (int)($summary['archived_forms'] ?? 0); ?>
                    ],
                    backgroundColor: [colors.emerald, colors.amber, colors.slate],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                ...sharedOptions,
                cutout: '65%',
                plugins: {
                    ...sharedOptions.plugins,
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            font: { size: 11, weight: 500 },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        ...sharedOptions.plugins.tooltip,
                        callbacks: {
                            label: function(context) {
                                const total = <?php echo (int)($summary['total_forms'] ?? 0); ?>;
                                const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Emergency Types Chart
        new Chart(document.getElementById('emergencyChart'), {
            type: 'bar',
            data: {
                labels: ['Medical', 'Trauma', 'Obstetric', 'General'],
                datasets: [{
                    data: [
                        <?php echo (int)($emergency_data['medical'] ?? 0); ?>,
                        <?php echo (int)($emergency_data['trauma'] ?? 0); ?>,
                        <?php echo (int)($emergency_data['obstetric'] ?? 0); ?>,
                        <?php echo (int)($emergency_data['general'] ?? 0); ?>
                    ],
                    backgroundColor: [colors.blue, colors.teal, colors.emerald, colors.amber],
                    borderRadius: 4,
                    barThickness: 32
                }]
            },
            options: {
                ...sharedOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } },
                        border: { display: false }
                    }
                }
            }
        });

        // Age Distribution Chart
        const ageData = <?php echo json_encode($age_data); ?> || [];
        new Chart(document.getElementById('ageChart'), {
            type: 'bar',
            data: {
                labels: ageData.map(a => a.age_group),
                datasets: [{
                    data: ageData.map(a => a.count),
                    backgroundColor: colors.teal,
                    borderRadius: 4,
                    barThickness: 40
                }]
            },
            options: {
                ...sharedOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } },
                        border: { display: false }
                    }
                }
            }
        });

        // Export functions
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'api/export_reports.php?' + params.toString();
        }

        function exportToPDF() {
            window.print();
        }
    </script>
</body>
</html>
