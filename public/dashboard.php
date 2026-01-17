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
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');

// Optimized: Batch all statistics in a single query
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
    WHERE created_by = ?
", [$week_start, $month_start, $user_id]);

$stats = $stats_stmt->fetch();
$total_forms = (int)$stats['total_forms'];
$today_forms = (int)$stats['today_forms'];
$draft_forms = (int)$stats['draft_forms'];
$completed_forms = (int)$stats['completed_forms'];
$archived_count = (int)$stats['archived_count'];
$week_forms = (int)$stats['week_forms'];
$month_forms = (int)$stats['month_forms'];

// Get recent activity (last 5 forms) - optimized to only fetch needed columns
$recent_activity_stmt = db_query("
    SELECT pf.id, pf.form_number, pf.patient_name, pf.arrival_hospital_name,
           pf.status, pf.created_at, u.full_name as created_by_name
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    WHERE pf.created_by = ?
    ORDER BY pf.created_at DESC
    LIMIT 5
", [$user_id]);
$recent_activity = $recent_activity_stmt->fetchAll();

// Optimized: Get data for charts - Last 7 days in a single query
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));
$daily_stats_stmt = db_query("
    SELECT DATE(form_date) as date, COUNT(*) as count
    FROM prehospital_forms
    WHERE created_by = ? AND form_date >= ? AND form_date <= CURDATE()
    GROUP BY DATE(form_date)
    ORDER BY DATE(form_date) ASC
", [$user_id, $seven_days_ago]);

$daily_counts = [];
while ($row = $daily_stats_stmt->fetch()) {
    $daily_counts[$row['date']] = (int)$row['count'];
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
$monthly_stats_stmt = db_query("
    SELECT DATE_FORMAT(form_date, '%Y-%m') as month, COUNT(*) as count
    FROM prehospital_forms
    WHERE created_by = ? AND form_date >= ?
    GROUP BY DATE_FORMAT(form_date, '%Y-%m')
    ORDER BY month ASC
", [$user_id, $twelve_months_ago]);

$monthly_counts = [];
while ($row = $monthly_stats_stmt->fetch()) {
    $monthly_counts[$row['month']] = (int)$row['count'];
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
            --primary-blue: #1e40af;
            --primary-dark: #1e3a8a;
            --accent-blue: #3b82f6;
            --success-green: #059669;
            --warning-orange: #d97706;
            --danger-red: #dc2626;
            --neutral-50: #f9fafb;
            --neutral-100: #f3f4f6;
            --neutral-200: #e5e7eb;
            --neutral-300: #d1d5db;
            --neutral-400: #9ca3af;
            --neutral-500: #6b7280;
            --neutral-600: #4b5563;
            --neutral-700: #374151;
            --neutral-800: #1f2937;
            --neutral-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--neutral-100);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--neutral-800);
            line-height: 1.6;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--neutral-200);
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.025em;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--neutral-500);
            margin: 0;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 8px;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--neutral-300);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.blue {
            background: var(--primary-blue);
            color: white;
        }

        .stat-icon.green {
            background: var(--success-green);
            color: white;
        }

        .stat-icon.orange {
            background: var(--warning-orange);
            color: white;
        }

        .stat-icon.red {
            background: var(--danger-red);
            color: white;
        }

        .stat-icon.purple {
            background: #7c3aed;
            color: white;
        }

        .stat-icon.teal {
            background: #0d9488;
            color: white;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--neutral-900);
            line-height: 1;
        }

        .stat-trend {
            font-size: 0.875rem;
            color: var(--neutral-500);
            margin-top: 0.5rem;
        }

        .stat-trend.positive {
            color: var(--success-green);
        }

        .stat-trend.negative {
            color: var(--danger-red);
        }

        /* Action Cards */
        .action-card {
            background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 8px;
            padding: 2rem;
            height: 100%;
            text-align: center;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .action-card:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: translateY(-2px);
        }

        .action-card-icon {
            width: 64px;
            height: 64px;
            background: var(--neutral-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-blue);
        }

        .action-card:hover .action-card-icon {
            background: var(--primary-blue);
            color: white;
        }

        .action-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }

        .action-card-description {
            font-size: 0.875rem;
            color: var(--neutral-500);
        }

        /* Recent Activity */
        .activity-card {
            background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 8px;
            padding: 1.5rem;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--neutral-200);
        }

        .activity-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            transition: background 0.2s ease;
        }

        .activity-item:hover {
            background: var(--neutral-50);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-icon.completed {
            background: #d1fae5;
            color: var(--success-green);
        }

        .activity-icon.pending {
            background: #fef3c7;
            color: var(--warning-orange);
        }

        .activity-icon.draft {
            background: var(--neutral-100);
            color: var(--neutral-500);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title-text {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            font-size: 0.8125rem;
            color: var(--neutral-500);
        }

        .activity-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .activity-badge.completed {
            background: #d1fae5;
            color: var(--success-green);
        }

        .activity-badge.pending {
            background: #fef3c7;
            color: var(--warning-orange);
        }

        .activity-badge.draft {
            background: var(--neutral-100);
            color: var(--neutral-600);
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-blue);
            border: 1px solid var(--primary-blue);
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-outline-primary {
            background: white;
            border: 1px solid var(--neutral-300);
            color: var(--neutral-700);
            font-weight: 600;
            padding: 0.625rem 1.25rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-outline-primary:hover {
            background: var(--neutral-50);
            border-color: var(--neutral-400);
            color: var(--neutral-900);
        }

        /* Section Titles */
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: 1.5rem;
            letter-spacing: -0.025em;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: var(--neutral-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--neutral-400);
            margin: 0 auto 1.5rem;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }

        .empty-state-description {
            font-size: 1rem;
            color: var(--neutral-500);
            margin-bottom: 1.5rem;
        }

        /* Chart Container */
        .chart-card {
            background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 8px;
            padding: 1.5rem;
            height: 100%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--neutral-200);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin: 0;
        }

        .chart-subtitle {
            font-size: 0.875rem;
            color: var(--neutral-500);
            margin-top: 0.25rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-container.pie {
            height: 280px;
        }

        /* Analytics Cards */
        .analytics-card {
            background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 8px;
            padding: 1.25rem;
            height: 100%;
        }

        .analytics-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .analytics-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 1rem;
        }

        .analytics-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .analytics-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }

        .analytics-description {
            font-size: 0.875rem;
            color: var(--neutral-500);
        }

        .trend-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .trend-indicator.up {
            background: #d1fae5;
            color: var(--success-green);
        }

        .trend-indicator.down {
            background: #fee2e2;
            color: var(--danger-red);
        }

        .trend-indicator.neutral {
            background: var(--neutral-100);
            color: var(--neutral-600);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 0;
                margin-bottom: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .page-subtitle {
                font-size: 0.875rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }

            .section-title {
                font-size: 1.25rem;
            }

            .activity-card, .chart-card {
                padding: 1.25rem;
            }

            .chart-container {
                height: 250px;
            }

            .chart-container.pie {
                height: 230px;
            }
        }

        @media (max-width: 576px) {
            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }

            .activity-item {
                padding: 0.75rem;
            }

            .chart-card {
                padding: 1rem;
            }

            .chart-container {
                height: 220px;
            }

            .chart-container.pie {
                height: 200px;
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
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="page-title">Welcome back, <?php echo e($current_user['full_name']); ?></h1>
                        <p class="page-subtitle">Here's what's happening with your pre-hospital care records</p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="prehospital_form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Create New Form
                        </a>
                    </div>
                </div>
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
    <script>
        // Chart.js Global Configuration
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, "Helvetica Neue", Arial, sans-serif';
        Chart.defaults.color = '#6b7280';

        // Weekly Activity Chart (Bar Chart)
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyChart = new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($last_7_days); ?>,
                datasets: [{
                    label: 'Forms Created',
                    data: <?php echo json_encode($last_7_days_data); ?>,
                    backgroundColor: '#3b82f6',
                    borderColor: '#1e40af',
                    borderWidth: 1,
                    borderRadius: 6,
                    barThickness: 40
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
                        backgroundColor: '#1f2937',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        borderColor: '#374151',
                        borderWidth: 1,
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
                                size: 12
                            }
                        },
                        grid: {
                            color: '#f3f4f6',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
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
                        '#059669',
                        '#d97706',
                        '#dc2626'
                    ],
                    borderWidth: 0,
                    spacing: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        borderColor: '#374151',
                        borderWidth: 1,
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
                    borderColor: '#1e40af',
                    backgroundColor: 'rgba(30, 64, 175, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#1e40af',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#1e40af',
                    pointHoverBorderWidth: 3
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
                        backgroundColor: '#1f2937',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        borderColor: '#374151',
                        borderWidth: 1,
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
                                size: 12
                            }
                        },
                        grid: {
                            color: '#f3f4f6',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 11
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
