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

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today
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
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_forms,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_forms,
        SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(NOW()) THEN 1 ELSE 0 END) as week_forms
    FROM prehospital_forms pf
    WHERE $where_clause
";
$summary_stmt = db_query($summary_sql, $params);
$summary = $summary_stmt->fetch();

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
$emergency_data = $emergency_stmt->fetch();

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
$vehicle_data = $vehicle_stmt->fetchAll();

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
$age_data = $age_stmt->fetchAll();

$gender_sql = "
    SELECT
        gender,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause AND gender IS NOT NULL
    GROUP BY gender
";
$gender_stmt = db_query($gender_sql, $params);
$gender_data = $gender_stmt->fetchAll();

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
$injury_data = $injury_stmt->fetchAll();

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
$hospital_data = $hospital_stmt->fetchAll();

// Get daily trends (last 30 days or filtered range)
$trend_sql = "
    SELECT
        DATE(form_date) as date,
        COUNT(*) as count
    FROM prehospital_forms pf
    WHERE $where_clause
    GROUP BY DATE(form_date)
    ORDER BY DATE(form_date)
";
$trend_stmt = db_query($trend_sql, $params);
$trend_data = $trend_stmt->fetchAll();

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
    $user_performance = $user_perf_stmt->fetchAll();
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --light-bg: #f8fafc;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            --card-hover-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .page-header p {
            opacity: 0.95;
            margin-bottom: 0;
        }

        /* Filter Section */
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
        }

        .filter-card h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Summary Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-color);
        }

        .stat-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-4px);
        }

        .stat-card.blue { --accent-color: #0d6efd; }
        .stat-card.green { --accent-color: #198754; }
        .stat-card.orange { --accent-color: #fd7e14; }
        .stat-card.purple { --accent-color: #6f42c1; }
        .stat-card.cyan { --accent-color: #0dcaf0; }
        .stat-card.red { --accent-color: #dc3545; }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #198754, #146c43); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #fd7e14, #dc6502); }
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #6f42c1, #5a32a3); }
        .stat-card.cyan .stat-icon { background: linear-gradient(135deg, #0dcaf0, #0aa2c0); }
        .stat-card.red .stat-icon { background: linear-gradient(135deg, #dc3545, #b02a37); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .stat-trend.up {
            color: #198754;
        }

        .stat-trend.down {
            color: #dc3545;
        }

        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e2e8f0;
        }

        .chart-card h5 {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-container.large {
            height: 400px;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead {
            background: #f8fafc;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Action Buttons */
        .btn-custom {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #0a58ca, #084298);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }

        .btn-success-custom {
            background: linear-gradient(135deg, #198754, #146c43);
            color: white;
        }

        .btn-success-custom:hover {
            background: linear-gradient(135deg, #146c43, #0f5132);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }

        .btn-secondary-custom {
            background: #6c757d;
            color: white;
        }

        .btn-secondary-custom:hover {
            background: #5c636a;
            transform: translateY(-2px);
        }

        /* Progress Bars */
        .progress-custom {
            height: 8px;
            border-radius: 10px;
            background: #e9ecef;
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, #0d6efd, #0a58ca);
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        /* Badge Styles */
        .badge-custom {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 250px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .filter-card {
                padding: 1rem;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        /* Print Styles */
        @media print {
            .filter-card,
            .btn-custom {
                display: none !important;
            }

            .chart-card {
                page-break-inside: avoid;
            }
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
                <h1><i class="bi bi-bar-chart-line"></i> Reports & Analytics</h1>
                <p>Comprehensive insights and data visualization for Pre-Hospital Care System</p>
            </div>
        </div>

        <div class="container-fluid">
            <?php show_flash(); ?>

            <!-- Filter Section -->
            <div class="filter-card">
                <h5><i class="bi bi-funnel"></i> Filters</h5>
                <form method="GET" action="reports.php">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from"
                                   value="<?php echo e($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo e($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
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
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-search"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($summary['total_forms']); ?></p>
                    <p class="stat-label">Total Forms</p>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($summary['completed_forms']); ?></p>
                    <p class="stat-label">Completed Forms</p>
                    <?php if ($summary['total_forms'] > 0): ?>
                        <div class="progress-custom mt-2">
                            <div class="progress-bar-custom" style="width: <?php echo ($summary['completed_forms'] / $summary['total_forms'] * 100); ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="stat-card cyan">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-day"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($summary['today_forms']); ?></p>
                    <p class="stat-label">Today's Forms</p>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($summary['week_forms']); ?></p>
                    <p class="stat-label">This Week</p>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($summary['pending_forms']); ?></p>
                    <p class="stat-label">Pending Forms</p>
                </div>
                <div class="stat-card red">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($summary['draft_forms']); ?></p>
                    <p class="stat-label">Draft Forms</p>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row">
                <!-- Forms Trend Chart -->
                <div class="col-lg-8">
                    <div class="chart-card">
                        <h5>
                            <span><i class="bi bi-graph-up"></i> Forms Trend Over Time</span>
                            <span class="badge bg-primary"><?php echo count($trend_data); ?> days</span>
                        </h5>
                        <div class="chart-container large">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Forms by Status -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5><i class="bi bi-pie-chart"></i> Forms by Status</h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row">
                <!-- Emergency Types -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5><i class="bi bi-exclamation-triangle"></i> Emergency Types</h5>
                        <div class="chart-container">
                            <canvas id="emergencyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Usage -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5><i class="bi bi-truck"></i> Vehicle Usage</h5>
                        <div class="chart-container">
                            <canvas id="vehicleChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Gender Distribution -->
                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5><i class="bi bi-gender-ambiguous"></i> Patient Gender</h5>
                        <div class="chart-container">
                            <canvas id="genderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <div class="row">
                <!-- Age Distribution -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5><i class="bi bi-people"></i> Patient Age Distribution</h5>
                        <div class="chart-container">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Injury Types -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5><i class="bi bi-bandaid"></i> Injury Types</h5>
                        <div class="chart-container">
                            <canvas id="injuryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Hospitals Table -->
            <?php if (!empty($hospital_data)): ?>
            <div class="chart-card">
                <h5><i class="bi bi-hospital"></i> Top 10 Receiving Hospitals</h5>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Hospital Name</th>
                                <th>Total Forms</th>
                                <th>Percentage</th>
                                <th>Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($hospital_data as $hospital):
                                $percentage = ($hospital['count'] / $summary['total_forms']) * 100;
                            ?>
                            <tr>
                                <td><strong>#<?php echo $rank++; ?></strong></td>
                                <td><?php echo e($hospital['arrival_hospital_name']); ?></td>
                                <td><strong><?php echo number_format($hospital['count']); ?></strong></td>
                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                <td>
                                    <div class="progress-custom">
                                        <div class="progress-bar-custom" style="width: <?php echo $percentage; ?>%"></div>
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
            <div class="chart-card">
                <h5><i class="bi bi-trophy"></i> User Performance</h5>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Username</th>
                                <th>Total Forms</th>
                                <th>Completed</th>
                                <th>Completion Rate</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($user_performance as $user):
                            ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-trophy-fill"></i> #<?php echo $rank; ?>
                                        </span>
                                    <?php else: ?>
                                        <strong>#<?php echo $rank; ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo e($user['full_name']); ?></strong></td>
                                <td><?php echo e($user['username']); ?></td>
                                <td><?php echo number_format($user['total_forms']); ?></td>
                                <td><?php echo number_format($user['completed_forms']); ?></td>
                                <td>
                                    <span class="badge-custom <?php echo $user['completion_rate'] >= 80 ? 'bg-success' : ($user['completion_rate'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo number_format($user['completion_rate'], 1); ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="progress-custom">
                                        <div class="progress-bar-custom" style="width: <?php echo $user['completion_rate']; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Export Actions -->
            <div class="chart-card">
                <h5><i class="bi bi-download"></i> Export Reports</h5>
                <div class="d-flex flex-wrap gap-2">
                    <button onclick="window.print()" class="btn btn-primary-custom">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                    <button onclick="exportToCSV()" class="btn btn-success-custom">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
                    </button>
                    <button onclick="exportToPDF()" class="btn btn-secondary-custom">
                        <i class="bi bi-file-earmark-pdf"></i> Export to PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Chart.js default configuration
        Chart.defaults.font.family = "'Segoe UI', system-ui, -apple-system, sans-serif";
        Chart.defaults.color = '#64748b';

        // Trend Chart
        const trendData = <?php echo json_encode($trend_data); ?>;
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Forms Created',
                    data: trendData.map(d => d.count),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#0d6efd',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
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
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Draft', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $summary['completed_forms']; ?>,
                        <?php echo $summary['draft_forms']; ?>,
                        <?php echo $summary['pending_forms']; ?>
                    ],
                    backgroundColor: [
                        '#198754',
                        '#dc3545',
                        '#ffc107'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = <?php echo $summary['total_forms']; ?>;
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Emergency Types Chart
        const emergencyCtx = document.getElementById('emergencyChart').getContext('2d');
        new Chart(emergencyCtx, {
            type: 'bar',
            data: {
                labels: ['Medical', 'Trauma', 'Obstetric', 'General'],
                datasets: [{
                    label: 'Cases',
                    data: [
                        <?php echo $emergency_data['medical']; ?>,
                        <?php echo $emergency_data['trauma']; ?>,
                        <?php echo $emergency_data['obstetric']; ?>,
                        <?php echo $emergency_data['general']; ?>
                    ],
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(25, 135, 84, 0.8)'
                    ],
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Vehicle Chart
        const vehicleData = <?php echo json_encode($vehicle_data); ?>;
        const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
        new Chart(vehicleCtx, {
            type: 'pie',
            data: {
                labels: vehicleData.map(v => v.vehicle_used || 'Not Specified'),
                datasets: [{
                    data: vehicleData.map(v => v.count),
                    backgroundColor: [
                        '#0d6efd',
                        '#198754',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        // Gender Chart
        const genderData = <?php echo json_encode($gender_data); ?>;
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: genderData.map(g => g.gender === 'M' ? 'Male' : (g.gender === 'F' ? 'Female' : 'Other')),
                datasets: [{
                    data: genderData.map(g => g.count),
                    backgroundColor: [
                        '#0d6efd',
                        '#d63384',
                        '#6c757d'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        // Age Distribution Chart
        const ageData = <?php echo json_encode($age_data); ?>;
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: ageData.map(a => a.age_group),
                datasets: [{
                    label: 'Patients',
                    data: ageData.map(a => a.count),
                    backgroundColor: 'rgba(13, 110, 253, 0.8)',
                    borderRadius: 8,
                    barThickness: 50
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Injury Types Chart
        const injuryData = <?php echo json_encode($injury_data); ?>;
        const injuryCtx = document.getElementById('injuryChart').getContext('2d');
        new Chart(injuryCtx, {
            type: 'bar',
            data: {
                labels: injuryData.map(i => i.injury_type.charAt(0).toUpperCase() + i.injury_type.slice(1)),
                datasets: [{
                    label: 'Injuries',
                    data: injuryData.map(i => i.count),
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(253, 126, 20, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(13, 202, 240, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
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
            alert('PDF export feature coming soon!');
            // window.location.href = 'api/export_reports.php?format=pdf&' + new URLSearchParams(window.location.search);
        }
    </script>
</body>
</html>
