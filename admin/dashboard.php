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

// Batched statistics (admin sees ALL records - no created_by filter)
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

// Active users count
$active_users_stmt = db_query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$active_users = (int)$active_users_stmt->fetch()['count'];

// Recent forms (using form_summary VIEW)
$recent_forms_stmt = db_query("SELECT * FROM form_summary ORDER BY created_at DESC LIMIT 10");
$recent_forms = $recent_forms_stmt->fetchAll();

// Daily stats for weekly bar chart (last 7 days)
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
    $last_7_days_data[] = isset($daily_counts[$date]) ? $daily_counts[$date] : 0;
}

// Monthly stats for line chart (last 12 months)
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
    $monthly_data[] = isset($monthly_counts[$month]) ? $monthly_counts[$month] : 0;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --success: #16a34a;
            --warning: #ca8a04;
            --danger: #dc2626;
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
            --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--gray-50);
            font-family: var(--font-sans);
            color: var(--gray-800);
            line-height: 1.5;
            font-size: 14px;
            -webkit-font-smoothing: antialiased;
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

        .stat-icon.blue { background: #eff6ff; color: var(--primary); }
        .stat-icon.green { background: #f0fdf4; color: var(--success); }
        .stat-icon.orange { background: #fefce8; color: var(--warning); }
        .stat-icon.red { background: #fef2f2; color: var(--danger); }
        .stat-icon.purple { background: #faf5ff; color: #7c3aed; }
        .stat-icon.teal { background: #f0fdfa; color: #0d9488; }

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

        /* Section Titles */
        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        /* Chart Cards */
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

        .action-card:hover { border-color: var(--gray-300); }

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

        /* Recent Forms Table */
        .table-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 1.25rem;
        }

        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .table-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .table-card .table {
            font-size: 0.8125rem;
            margin-bottom: 0;
        }

        .table-card .table th {
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            border-bottom: 1px solid var(--gray-200);
            padding: 0.75rem;
            white-space: nowrap;
        }

        .table-card .table td {
            padding: 0.75rem;
            color: var(--gray-700);
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-100);
        }

        .table-card .table tbody tr:hover {
            background: var(--gray-50);
        }

        .badge-status {
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: inline-block;
        }

        .badge-status.completed { background: #f0fdf4; color: var(--success); }
        .badge-status.draft { background: var(--gray-100); color: var(--gray-600); }
        .badge-status.archived { background: #faf5ff; color: #7c3aed; }

        .badge-vehicle {
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 500;
            background: #eff6ff;
            color: var(--primary);
        }

        .badge-injury {
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6875rem;
            font-weight: 500;
            background: #fef2f2;
            color: var(--danger);
        }

        .btn-view {
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 500;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 4px;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .btn-view:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-900);
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
            max-width: 280px;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header-inline {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1.25rem;
                padding-bottom: 1rem;
            }

            .page-title { font-size: 1rem; }
            .page-subtitle { font-size: 0.75rem; }

            .stat-card { padding: 1rem; }
            .stat-value { font-size: 1.5rem; }
            .stat-icon { width: 32px; height: 32px; font-size: 1rem; }

            .section-title { font-size: 0.8125rem; }
            .chart-card, .table-card { padding: 1rem; }
            .chart-container { height: 240px; }
            .chart-container.pie { height: 220px; }
        }

        @media (max-width: 576px) {
            .stat-card { padding: 0.875rem; }
            .stat-value { font-size: 1.25rem; }
            .stat-label { font-size: 0.6875rem; }
            .chart-card { padding: 0.875rem; }
            .chart-container { height: 200px; }
            .chart-container.pie { height: 180px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- Page Header -->
            <div class="page-header-inline">
                <div>
                    <h1 class="page-title">Admin Dashboard</h1>
                    <p class="page-subtitle">System-wide overview and analytics</p>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="row g-3 g-md-4 mb-4">
                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon blue"><i class="bi bi-file-earmark-medical"></i></div>
                        </div>
                        <div class="stat-label">Total Forms</div>
                        <div class="stat-value"><?php echo number_format($total_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                        </div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-value"><?php echo number_format($completed_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon orange"><i class="bi bi-file-earmark-text"></i></div>
                        </div>
                        <div class="stat-label">Drafts</div>
                        <div class="stat-value"><?php echo number_format($draft_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon purple"><i class="bi bi-calendar-day"></i></div>
                        </div>
                        <div class="stat-label">Today</div>
                        <div class="stat-value"><?php echo number_format($today_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon teal"><i class="bi bi-calendar-week"></i></div>
                        </div>
                        <div class="stat-label">This Week</div>
                        <div class="stat-value"><?php echo number_format($week_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon red"><i class="bi bi-calendar-month"></i></div>
                        </div>
                        <div class="stat-label">This Month</div>
                        <div class="stat-value"><?php echo number_format($month_forms); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
                        </div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-value"><?php echo number_format($active_users); ?></div>
                    </div>
                </div>

                <div class="col-6 col-lg-4 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div class="stat-icon purple"><i class="bi bi-archive"></i></div>
                        </div>
                        <div class="stat-label">Archived</div>
                        <div class="stat-value"><?php echo number_format($archived_count); ?></div>
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
                                <h3 class="chart-title">Weekly Activity</h3>
                                <p class="chart-subtitle">Forms created in the last 7 days</p>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Status Distribution -->
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

            <div class="row g-3 g-md-4 mb-4">
                <!-- Monthly Performance Chart -->
                <div class="col-12 col-xl-8">
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

                <!-- Emergency Type Breakdown -->
                <div class="col-12 col-xl-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div>
                                <h3 class="chart-title">Emergency Types</h3>
                                <p class="chart-subtitle">Breakdown by category</p>
                            </div>
                        </div>
                        <div class="chart-container pie">
                            <canvas id="emergencyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Forms Table -->
            <div class="row g-3 g-md-4 mb-4">
                <div class="col-12">
                    <div class="table-card">
                        <div class="table-card-header">
                            <h3 class="table-card-title">Recent Forms</h3>
                            <a href="../public/records.php" class="btn-outline-primary">
                                View All <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Form #</th>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Age/Gender</th>
                                        <th>Vehicle</th>
                                        <th>Injuries</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_forms)): ?>
                                        <tr>
                                            <td colspan="9">
                                                <div class="empty-state">
                                                    <div class="empty-state-icon">
                                                        <i class="bi bi-inbox"></i>
                                                    </div>
                                                    <div class="empty-state-title">No forms yet</div>
                                                    <div class="empty-state-description">No pre-hospital care forms have been created in the system.</div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_forms as $form): ?>
                                            <tr>
                                                <td><strong><?php echo e($form['form_number']); ?></strong></td>
                                                <td><?php echo $form['form_date'] ? date('M d, Y', strtotime($form['form_date'])) : '-'; ?></td>
                                                <td><?php echo e($form['patient_name'] ?: '-'); ?></td>
                                                <td><?php echo e($form['age'] ?: '-'); ?> / <?php echo ucfirst(e($form['gender'] ?: '-')); ?></td>
                                                <td>
                                                    <?php if ($form['vehicle_used']): ?>
                                                        <span class="badge-vehicle"><?php echo ucfirst(e($form['vehicle_used'])); ?></span>
                                                    <?php else: ?>
                                                        <span style="color:var(--gray-400);">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($form['injury_count'] > 0): ?>
                                                        <span class="badge-injury"><?php echo $form['injury_count']; ?> injur<?php echo $form['injury_count'] === 1 ? 'y' : 'ies'; ?></span>
                                                    <?php else: ?>
                                                        <span style="color:var(--gray-400);">None</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_classes = [
                                                        'completed' => 'completed',
                                                        'draft' => 'draft',
                                                        'archived' => 'archived'
                                                    ];
                                                    $sc = $status_classes[$form['status']] ?? 'draft';
                                                    ?>
                                                    <span class="badge-status <?php echo $sc; ?>"><?php echo ucfirst(e($form['status'])); ?></span>
                                                </td>
                                                <td><?php echo e($form['created_by_name'] ?? 'Unknown'); ?></td>
                                                <td>
                                                    <a href="../public/records.php?view=<?php echo $form['id']; ?>" class="btn-view">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h2 class="section-title">Quick Actions</h2>
            <div class="row g-3 g-md-4 mb-4">
                <div class="col-6 col-md-4">
                    <a href="../public/admin/users.php" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div class="action-card-title">Manage Users</div>
                        <div class="action-card-description">Add or manage system users</div>
                    </a>
                </div>

                <div class="col-6 col-md-4">
                    <a href="../public/records.php" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-table"></i>
                        </div>
                        <div class="action-card-title">View All Records</div>
                        <div class="action-card-description">Browse all pre-hospital forms</div>
                    </a>
                </div>

                <div class="col-6 col-md-4">
                    <a href="../public/prehospital_form.php" class="action-card">
                        <div class="action-card-icon">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <div class="action-card-title">New Form</div>
                        <div class="action-card-description">Create a new pre-hospital care form</div>
                    </a>
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

        // Shared tooltip config
        const tooltipConfig = {
            backgroundColor: '#18181b',
            padding: 10,
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 11 },
            borderColor: '#27272a',
            borderWidth: 1,
            cornerRadius: 4,
            displayColors: false,
            callbacks: {
                label: function(ctx) {
                    return ctx.parsed.y + ' form' + (ctx.parsed.y !== 1 ? 's' : '');
                }
            }
        };

        const doughnutTooltip = {
            backgroundColor: '#18181b',
            padding: 10,
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 11 },
            borderColor: '#27272a',
            borderWidth: 1,
            cornerRadius: 4,
            callbacks: {
                label: function(ctx) {
                    const value = ctx.parsed || 0;
                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                    return ctx.label + ': ' + value + ' (' + pct + '%)';
                }
            }
        };

        const doughnutLegend = {
            position: 'bottom',
            labels: {
                padding: 12,
                font: { size: 11, weight: '500' },
                usePointStyle: true,
                pointStyle: 'circle'
            }
        };

        // 1. Weekly Activity Bar Chart
        new Chart(document.getElementById('weeklyChart').getContext('2d'), {
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
                plugins: { legend: { display: false }, tooltip: tooltipConfig },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 11 } },
                        grid: { color: '#f4f4f5', drawBorder: false },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });

        // 2. Form Status Doughnut Chart
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Draft', 'Archived'],
                datasets: [{
                    data: [<?php echo $completed_forms; ?>, <?php echo $draft_forms; ?>, <?php echo $archived_count; ?>],
                    backgroundColor: ['#16a34a', '#ca8a04', '#a1a1aa'],
                    borderWidth: 0,
                    spacing: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: doughnutLegend, tooltip: doughnutTooltip }
            }
        });

        // 3. Monthly Performance Line Chart
        new Chart(document.getElementById('monthlyChart').getContext('2d'), {
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
                plugins: { legend: { display: false }, tooltip: tooltipConfig },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 11 } },
                        grid: { color: '#f4f4f5', drawBorder: false },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 45 }
                    }
                }
            }
        });

        // 4. Emergency Type Doughnut Chart
        new Chart(document.getElementById('emergencyChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Medical', 'Trauma', 'OB', 'General'],
                datasets: [{
                    data: [<?php echo $emergency_medical; ?>, <?php echo $emergency_trauma; ?>, <?php echo $emergency_ob; ?>, <?php echo $emergency_general; ?>],
                    backgroundColor: ['#2563eb', '#dc2626', '#7c3aed', '#ca8a04'],
                    borderWidth: 0,
                    spacing: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: doughnutLegend, tooltip: doughnutTooltip }
            }
        });
    </script>
</body>
</html>
