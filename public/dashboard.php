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

// Time-aware greeting
$hour = (int)date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
// First name only, for a friendlier greeting
$first_name = trim(explode(' ', trim($current_user['full_name']))[0]);
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
            /* Accent - calm corporate indigo (single accent, used sparingly) */
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-soft: #eef2ff;
            --accent-border: #e0e7ff;

            /* Semantic - muted, flat */
            --success: #16a34a;
            --success-soft: #ecfdf5;
            --warning: #d97706;
            --warning-soft: #fffbeb;
            --danger: #dc2626;
            --danger-soft: #fef2f2;

            /* Neutral palette (slate) */
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

            /* Elevation - soft, low */
            --shadow-sm: 0 1px 2px rgba(16, 24, 40, 0.04);
            --shadow-md: 0 4px 12px rgba(16, 24, 40, 0.08);

            --radius: 12px;

            /* Typography */
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
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
            margin-bottom: 1.75rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .page-title {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
            letter-spacing: -0.02em;
        }

        .page-subtitle {
            font-size: 0.875rem;
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
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
            height: 100%;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--gray-300);
            transform: translateY(-1px);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-icon.blue {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .stat-icon.green {
            background: var(--success-soft);
            color: var(--success);
        }

        .stat-icon.orange {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .stat-icon.red {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .stat-icon.purple {
            background: #f5f3ff;
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
            margin-bottom: 0.375rem;
        }

        .stat-value {
            font-size: 1.9rem;
            font-weight: 600;
            color: var(--gray-900);
            line-height: 1.1;
            letter-spacing: -0.02em;
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
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
            height: 100%;
            text-align: center;
            transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .action-card:hover {
            border-color: var(--gray-300);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .action-card-icon {
            width: 48px;
            height: 48px;
            background: var(--gray-100);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.875rem;
            color: var(--gray-600);
            transition: all 0.18s ease;
        }

        .action-card:hover .action-card-icon {
            background: var(--accent);
            color: white;
        }

        .action-card-title {
            font-size: 0.9375rem;
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
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .activity-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 0.875rem 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.125rem;
            transition: background 0.15s ease;
        }

        .activity-item:hover {
            background: var(--gray-50);
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-right: 0.875rem;
            flex-shrink: 0;
        }

        .activity-icon.completed {
            background: var(--success-soft);
            color: var(--success);
        }

        .activity-icon.pending {
            background: var(--warning-soft);
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
            font-size: 0.875rem;
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
            padding: 0.2rem 0.625rem;
            border-radius: 999px;
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .activity-badge.completed {
            background: var(--success-soft);
            color: var(--success);
        }

        .activity-badge.pending {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .activity-badge.draft {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Buttons */
        .btn-primary {
            background: var(--accent);
            border: 1px solid var(--accent);
            color: white;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-outline-primary {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.8125rem;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
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
            font-size: 1rem;
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
            border-radius: 12px;
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
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
            height: 100%;
            box-shadow: var(--shadow-sm);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .chart-title {
            font-size: 1rem;
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
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.25rem;
            height: 100%;
            box-shadow: var(--shadow-sm);
        }

        .analytics-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .analytics-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
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
            padding: 0.15rem 0.45rem;
            border-radius: 999px;
        }

        .trend-indicator.up {
            background: var(--success-soft);
            color: var(--success);
        }

        .trend-indicator.down {
            background: var(--danger-soft);
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
                font-size: 1.15rem;
            }

            .page-subtitle {
                font-size: 0.8125rem;
            }

            .stat-card {
                padding: 1.15rem;
            }

            .stat-value {
                font-size: 1.6rem;
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1.05rem;
            }

            .section-title {
                font-size: 0.9375rem;
            }

            .activity-card, .chart-card {
                padding: 1.15rem;
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
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.4rem;
            }

            .stat-label {
                font-size: 0.6875rem;
            }

            .activity-item {
                padding: 0.625rem;
            }

            .chart-card {
                padding: 1rem;
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
                    <h1 class="page-title"><?php echo $greeting; ?>, <?php echo e($first_name); ?></h1>
                    <p class="page-subtitle">Here's an overview of your pre-hospital care activity.</p>
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

    <!-- Chart data passed via data attributes (CSP-safe, no inline script needed) -->
    <div id="dashboard-chart-data" style="display:none"
         data-weekly-labels="<?php echo htmlspecialchars(json_encode($last_7_days), ENT_QUOTES, 'UTF-8'); ?>"
         data-weekly-data="<?php echo htmlspecialchars(json_encode($last_7_days_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-monthly-labels="<?php echo htmlspecialchars(json_encode($monthly_labels), ENT_QUOTES, 'UTF-8'); ?>"
         data-monthly-data="<?php echo htmlspecialchars(json_encode($monthly_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-completed="<?php echo $completed_forms; ?>"
         data-drafts="<?php echo $draft_forms; ?>"
         data-archived="<?php echo $archived_count; ?>">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard-charts.js?v=<?php echo time(); ?>"></script>
</body>
</html>
