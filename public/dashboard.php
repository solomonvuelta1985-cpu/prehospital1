<?php
/**
 * User Dashboard
 * View and manage personal pre-hospital care forms
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

// Get current user
$current_user = get_auth_user();

// Get user statistics
$user_id = $current_user['id'];
$is_admin = ($current_user['role'] === 'admin');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

// Optimized: Batch all statistics in a single query
$stats_sql = "
    SELECT
        COUNT(*) as total_forms,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_forms,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_forms,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_forms,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived_count,
        SUM(CASE WHEN form_date >= ? THEN 1 ELSE 0 END) as week_forms,
        SUM(CASE WHEN form_date >= ? THEN 1 ELSE 0 END) as month_forms
    FROM prehospital_forms";
if ($is_admin) {
    $stats_stmt = db_query($stats_sql, [$week_start, $month_start]);
} else {
    $stats_stmt = db_query($stats_sql . " WHERE created_by = ?", [$week_start, $month_start, $user_id]);
}

$stats = $stats_stmt ? $stats_stmt->fetch() : false;
if (!$stats) {
    $stats = ['total_forms' => 0, 'today_forms' => 0, 'draft_forms' => 0, 'completed_forms' => 0, 'archived_count' => 0, 'week_forms' => 0, 'month_forms' => 0];
}
$total_forms = (int)$stats['total_forms'];
$today_forms = (int)$stats['today_forms'];
$draft_forms = (int)$stats['draft_forms'];
$completed_forms = (int)$stats['completed_forms'];
$archived_count = (int)$stats['archived_count'];
$week_forms = (int)$stats['week_forms'];
$month_forms = (int)$stats['month_forms'];

// Get recent activity (last 5 forms) - optimized to only fetch needed columns
$recent_sql = "
    SELECT pf.id, pf.form_number, pf.patient_name, pf.arrival_hospital_name,
           pf.status, pf.created_at, u.full_name as created_by_name
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id";
if ($is_admin) {
    $recent_activity_stmt = db_query($recent_sql . " ORDER BY pf.created_at DESC LIMIT 5");
} else {
    $recent_activity_stmt = db_query($recent_sql . " WHERE pf.created_by = ? ORDER BY pf.created_at DESC LIMIT 5", [$user_id]);
}
$recent_activity = $recent_activity_stmt ? $recent_activity_stmt->fetchAll() : [];

// Optimized: Get data for charts - Last 7 days in a single query
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));
if ($is_admin) {
    $daily_stats_stmt = db_query("
        SELECT DATE(form_date) as date, COUNT(*) as count
        FROM prehospital_forms
        WHERE form_date >= ? AND form_date <= CURDATE()
        GROUP BY DATE(form_date)
        ORDER BY DATE(form_date) ASC
    ", [$seven_days_ago]);
} else {
    $daily_stats_stmt = db_query("
        SELECT DATE(form_date) as date, COUNT(*) as count
        FROM prehospital_forms
        WHERE created_by = ? AND form_date >= ? AND form_date <= CURDATE()
        GROUP BY DATE(form_date)
        ORDER BY DATE(form_date) ASC
    ", [$user_id, $seven_days_ago]);
}

$daily_counts = [];
if ($daily_stats_stmt) {
    while ($row = $daily_stats_stmt->fetch()) {
        $daily_counts[$row['date']] = (int)$row['count'];
    }
}

// Build 7-day chart data
$last_7_days = [];
$last_7_days_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime("-$i days"));
    $last_7_days[] = $label;
    $last_7_days_data[] = isset($daily_counts[$date]) ? $daily_counts[$date] : 0;
}

// Optimized: Get monthly data for the year in a single query
$twelve_months_ago = date('Y-m-01', strtotime('-11 months'));
if ($is_admin) {
    $monthly_stats_stmt = db_query("
        SELECT DATE_FORMAT(form_date, '%Y-%m') as month, COUNT(*) as count
        FROM prehospital_forms
        WHERE form_date >= ?
        GROUP BY DATE_FORMAT(form_date, '%Y-%m')
        ORDER BY month ASC
    ", [$twelve_months_ago]);
} else {
    $monthly_stats_stmt = db_query("
        SELECT DATE_FORMAT(form_date, '%Y-%m') as month, COUNT(*) as count
        FROM prehospital_forms
        WHERE created_by = ? AND form_date >= ?
        GROUP BY DATE_FORMAT(form_date, '%Y-%m')
        ORDER BY month ASC
    ", [$user_id, $twelve_months_ago]);
}

$monthly_counts = [];
if ($monthly_stats_stmt) {
    while ($row = $monthly_stats_stmt->fetch()) {
        $monthly_counts[$row['month']] = (int)$row['count'];
    }
}

// Build 12-month chart data
$monthly_data = [];
$monthly_labels = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $monthly_labels[] = $label;
    $monthly_data[] = isset($monthly_counts[$month]) ? $monthly_counts[$month] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            /* Primary - Muted corporate blue */
            --primary: #2563eb;
            --primary-hover: #1d4ed8;

            /* Semantic colors - Muted, professional */
            --success: #16a34a;
            --warning: #ca8a04;
            --danger: #dc2626;

            /* Neutral palette */
            --gray-50: #fafafa;
            --gray-100: #f4f4f5;
            --gray-200: #e4e4e7;
            --gray-300: #d4d4d8;
            --gray-400: #a1a1aa;
            --gray-500: #71717a;
            --gray-600: #52525b;
            --gray-700: #3f3f46;
            --gray-800: #27272a;
            --gray-900: #18181b;

            /* Typography */
            --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', system-ui, sans-serif;
            --font-mono: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', monospace;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--gray-50);
            font-family: var(--font-sans);
            color: var(--gray-800);
            line-height: 1.5;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Page Header */
        .page-header-inline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.125rem 0;
            letter-spacing: -0.01em;
        }

        .page-subtitle {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin: 0;
            font-weight: 400;
        }

        @media (max-width: 576px) {
            .page-header-inline {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 1.25rem;
            height: 100%;
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: var(--primary);
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: var(--success);
        }

        .stat-icon.orange {
            background: #fefce8;
            color: var(--warning);
        }

        .stat-icon.red {
            background: #fef2f2;
            color: var(--danger);
        }

        .stat-icon.purple {
            background: #faf5ff;
            color: #7c3aed;
        }

        .stat-icon.teal {
            background: #f0fdfa;
            color: #0d9488;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-900);
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .stat-trend {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .stat-trend.positive {
            color: var(--success);
        }

        .stat-trend.negative {
            color: var(--danger);
        }

        /* Action Cards */
        .action-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 1.5rem;
            height: 100%;
            text-align: center;
            transition: border-color 0.15s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .action-card:hover {
            border-color: var(--gray-300);
        }

        .action-card-icon {
            width: 48px;
            height: 48px;
            background: var(--gray-100);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            color: var(--gray-600);
            transition: all 0.15s ease;
        }

        .action-card:hover .action-card-icon {
            background: var(--primary);
            color: white;
        }

        .action-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .action-card-description {
            font-size: 0.75rem;
            color: var(--gray-500);
            line-height: 1.4;
        }

        /* Recent Activity */
        .activity-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 1.25rem;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .activity-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 0.25rem;
            transition: background 0.15s ease;
        }

        .activity-item:hover {
            background: var(--gray-50);
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .activity-icon.completed {
            background: #f0fdf4;
            color: var(--success);
        }

        .activity-icon.pending {
            background: #fefce8;
            color: var(--warning);
        }

        .activity-icon.draft {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-title-text {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--gray-900);
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-meta {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .activity-badge {
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .activity-badge.completed {
            background: #f0fdf4;
            color: var(--success);
        }

        .activity-badge.pending {
            background: #fefce8;
            color: var(--warning);
        }

        .activity-badge.draft {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: white;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.15s ease;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            color: white;
        }

        .btn-outline-primary {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.8125rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .btn-outline-primary:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-900);
        }

        /* Section Titles */
        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
        }

        .empty-state-icon {
            width: 56px;
            height: 56px;
            background: var(--gray-100);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--gray-400);
            margin: 0 auto 1rem;
        }

        .empty-state-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .empty-state-description {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Chart Container */
        .chart-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 1.25rem;
            height: 100%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .chart-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .chart-subtitle {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.125rem;
        }

        .chart-container {
            position: relative;
            height: 280px;
        }

        .chart-container.pie {
            height: 260px;
        }

        /* Analytics Cards */
        .analytics-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 1rem;
            height: 100%;
        }

        .analytics-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .analytics-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: 0.75rem;
        }

        .analytics-title {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .analytics-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            font-variant-numeric: tabular-nums;
        }

        .analytics-description {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .trend-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
        }

        .trend-indicator.up {
            background: #f0fdf4;
            color: var(--success);
        }

        .trend-indicator.down {
            background: #fef2f2;
            color: var(--danger);
        }

        .trend-indicator.neutral {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header-inline {
                margin-bottom: 1.25rem;
                padding-bottom: 1rem;
            }

            .page-title {
                font-size: 1rem;
            }

            .page-subtitle {
                font-size: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .section-title {
                font-size: 0.8125rem;
            }

            .activity-card, .chart-card {
                padding: 1rem;
            }

            .chart-container {
                height: 240px;
            }

            .chart-container.pie {
                height: 220px;
            }
        }

        @media (max-width: 576px) {
            .stat-card {
                padding: 0.875rem;
            }

            .stat-value {
                font-size: 1.25rem;
            }

            .stat-label {
                font-size: 0.6875rem;
            }

            .activity-item {
                padding: 0.625rem;
            }

            .chart-card {
                padding: 0.875rem;
            }

            .chart-container {
                height: 200px;
            }

            .chart-container.pie {
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- Page Header -->
            <div class="page-header-inline">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Welcome back, <?php echo e($current_user['full_name']); ?></p>
                </div>
                <a href="prehospital_form.php" class="btn btn-primary">
                    <i class="bi bi-plus me-1"></i>New Form
                </a>
            </div>

            <!-- Statistics Grid -->
            <div class="row g-3 g-md-4 mb-4">
                <div class="col-6 col-lg-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon blue">
                                <i class="bi bi-file-earmark-medical"></i>
                            </div>
                        </div>
                        <div class="stat-label">Total Forms</div>
                        <div class="stat-value"><?php echo number_format($total_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon green">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-value"><?php echo number_format($completed_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon orange">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                        </div>
                        <div class="stat-label">Draft</div>
                        <div class="stat-value"><?php echo number_format($draft_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon purple">
                                <i class="bi bi-calendar-day"></i>
                            </div>
                        </div>
                        <div class="stat-label">Today</div>
                        <div class="stat-value"><?php echo number_format($today_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon teal">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                        <div class="stat-label">This Week</div>
                        <div class="stat-value"><?php echo number_format($week_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon red">
                                <i class="bi bi-calendar-month"></i>
                            </div>
                        </div>
                        <div class="stat-label">This Month</div>
                        <div class="stat-value"><?php echo number_format($month_forms); ?></div>
                    </div>
                </div>
            </div>

            <!-- Analytics & Charts -->
            <h2 class="section-title">Analytics Overview</h2>
            <div class="row g-3 g-md-4 mb-4">
                <!-- Weekly Trend Chart -->
                <div class="col-12 col-xl-8">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title">Weekly Activity Trend</h3>
                                <p class="chart-subtitle">Forms created in the last 7 days</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Status Distribution Pie Chart -->
                <div class="col-12 col-xl-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title">Form Status</h3>
                                <p class="chart-subtitle">Distribution by status</p>
                            </div>
                        </div>
                        <div class="chart-container pie">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="row g-3 g-md-4 mb-4">
                <div class="col-12">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title">Monthly Performance</h3>
                                <p class="chart-subtitle">Forms created over the last 12 months</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h2 class="section-title">Quick Actions</h2>
            <div class="row g-3 g-md-4 mb-4">
                <div class="col-6 col-md-3">
                    <a href="prehospital_form.php" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <div class="action-card-title">New Form</div>
                        <div class="action-card-description">Create a new pre-hospital care form</div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="records.php" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <div class="action-card-title">All Records</div>
                        <div class="action-card-description">View all your submitted forms</div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="records.php?status=draft" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="action-card-title">Draft Forms</div>
                        <div class="action-card-description">View forms in progress</div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="records.php?status=completed" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="action-card-title">Completed</div>
                        <div class="action-card-description">View completed forms</div>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <div class="activity-header">
                            <h3 class="activity-title">Recent Activity</h3>
                            <a href="records.php" class="btn-outline-primary">
                                View All <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>

                        <?php if (empty($recent_activity)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-inbox"></i>
                                </div>
                                <div class="empty-state-title">No activity yet</div>
                                <div class="empty-state-description">You haven't created any forms yet. Get started by creating your first pre-hospital care form.</div>
                                <a href="prehospital_form.php" class="btn btn-primary mt-2">
                                    <i class="bi bi-plus-circle me-2"></i>Create Your First Form
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <?php
                                $status_classes = [
                                    'completed' => 'completed',
                                    'draft' => 'draft',
                                    'archived' => 'draft'
                                ];
                                $status_class = $status_classes[$activity['status']] ?? 'draft';
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $status_class; ?>">
                                        <i class="bi bi-file-earmark-medical"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title-text">
                                            <?php echo e($activity['form_number']); ?> - <?php echo e($activity['patient_name']); ?>
                                        </div>
                                        <div class="activity-meta">
                                            <?php echo date('M d, Y \a\t h:i A', strtotime($activity['created_at'])); ?>
                                            <?php if ($activity['arrival_hospital_name']): ?>
                                                • <?php echo e($activity['arrival_hospital_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="activity-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst(e($activity['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        // Chart.js Global Configuration
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", system-ui, sans-serif';
        Chart.defaults.color = '#71717a';
        Chart.defaults.font.size = 11;

        // Weekly Activity Chart (Bar Chart)
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($last_7_days); ?>,
                datasets: [{
                    label: 'Forms Created',
                    data: <?php echo json_encode($last_7_days_data); ?>,
                    backgroundColor: '#2563eb',
                    borderWidth: 0,
                    borderRadius: 4,
                    barThickness: 32
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#18181b',
                        padding: 10,
                        titleFont: {
                            size: 12,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 11
                        },
                        borderColor: '#27272a',
                        borderWidth: 1,
                        cornerRadius: 4,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' form' + (context.parsed.y !== 1 ? 's' : '');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: '#f4f4f5',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Status Distribution Chart (Doughnut Chart)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Draft', 'Archived'],
                datasets: [{
                    data: [
                        <?php echo $completed_forms; ?>,
                        <?php echo $draft_forms; ?>,
                        <?php echo $archived_count; ?>
                    ],
                    backgroundColor: [
                        '#16a34a',
                        '#ca8a04',
                        '#a1a1aa'
                    ],
                    borderWidth: 0,
                    spacing: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 12,
                            font: {
                                size: 11,
                                weight: '500'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#18181b',
                        padding: 10,
                        titleFont: {
                            size: 12,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 11
                        },
                        borderColor: '#27272a',
                        borderWidth: 1,
                        cornerRadius: 4,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Monthly Performance Chart (Line Chart)
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Forms Created',
                    data: <?php echo json_encode($monthly_data); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.04)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#2563eb',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#18181b',
                        padding: 10,
                        titleFont: {
                            size: 12,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 11
                        },
                        borderColor: '#27272a',
                        borderWidth: 1,
                        cornerRadius: 4,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' form' + (context.parsed.y !== 1 ? 's' : '');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: '#f4f4f5',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
