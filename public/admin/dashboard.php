<?php
/**
 * Admin Dashboard
 * Overview of system activity, recent logins, and quick stats
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Require admin authentication
require_login();
require_admin();

$current_user = get_auth_user();

// Stats - null-safe to prevent fatal errors if queries fail
$stmt = db_query("SELECT COUNT(*) as c FROM prehospital_forms");
$total_records = $stmt ? ($stmt->fetch()['c'] ?? 0) : 0;

$stmt = db_query("SELECT COUNT(*) as c FROM prehospital_forms WHERE DATE(created_at) = CURDATE()");
$today_records = $stmt ? ($stmt->fetch()['c'] ?? 0) : 0;

$stmt = db_query("SELECT COUNT(*) as c FROM users");
$total_users = $stmt ? ($stmt->fetch()['c'] ?? 0) : 0;

$stmt = db_query("SELECT COUNT(*) as c FROM users WHERE status = 'active'");
$active_users = $stmt ? ($stmt->fetch()['c'] ?? 0) : 0;

$stmt = db_query("SELECT COUNT(*) as c FROM users WHERE is_restricted = 1");
$restricted_users = $stmt ? ($stmt->fetch()['c'] ?? 0) : 0;

// Monthly data for chart (last 12 months)
$twelve_months_ago = date('Y-m-01', strtotime('-11 months'));
$monthly_stmt = db_query("
    SELECT DATE_FORMAT(form_date, '%Y-%m') as month, COUNT(*) as count
    FROM prehospital_forms
    WHERE form_date >= ?
    GROUP BY DATE_FORMAT(form_date, '%Y-%m')
    ORDER BY month ASC
", [$twelve_months_ago]);

$monthly_counts = [];
if ($monthly_stmt) {
    while ($row = $monthly_stmt->fetch()) {
        $monthly_counts[$row['month']] = (int)$row['count'];
    }
}

$monthly_labels = [];
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $monthly_labels[] = $label;
    $monthly_data[] = $monthly_counts[$month] ?? 0;
}

// Emergency type distribution for chart
$emergency_stmt = db_query("
    SELECT
        SUM(emergency_medical) as medical,
        SUM(emergency_trauma) as trauma,
        SUM(emergency_ob) as ob,
        SUM(emergency_general) as general_emergency
    FROM prehospital_forms
");
$emergency_data = $emergency_stmt ? $emergency_stmt->fetch() : false;
$emergency_counts = [
    'Medical' => (int)($emergency_data['medical'] ?? 0),
    'Trauma' => (int)($emergency_data['trauma'] ?? 0),
    'OB/GYN' => (int)($emergency_data['ob'] ?? 0),
    'General' => (int)($emergency_data['general_emergency'] ?? 0),
];

// Daily data for last 7 days
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));
$daily_stmt = db_query("
    SELECT DATE(form_date) as date, COUNT(*) as count
    FROM prehospital_forms
    WHERE form_date >= ? AND form_date <= CURDATE()
    GROUP BY DATE(form_date)
    ORDER BY DATE(form_date) ASC
", [$seven_days_ago]);

$daily_counts = [];
if ($daily_stmt) {
    while ($row = $daily_stmt->fetch()) {
        $daily_counts[$row['date']] = (int)$row['count'];
    }
}

$daily_labels = [];
$daily_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('M d', strtotime("-$i days"));
    $daily_data[] = $daily_counts[$date] ?? 0;
}

// Recent logins (last 15)
$login_sql = "SELECT lh.*, u.username, u.full_name
              FROM login_history lh
              JOIN users u ON lh.user_id = u.id
              ORDER BY lh.login_at DESC LIMIT 15";
$login_stmt = db_query($login_sql);
$recent_logins = $login_stmt ? $login_stmt->fetchAll() : [];

// Recent activity (last 10)
$activity_sql = "SELECT al.*, u.username
                 FROM activity_logs al
                 LEFT JOIN users u ON al.user_id = u.id
                 ORDER BY al.created_at DESC LIMIT 10";
$activity_stmt = db_query($activity_sql);
$recent_activity = $activity_stmt ? $activity_stmt->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="../vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="../vendor/bootstrap-icons/1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../vendor/chartjs/4.4.0/chart.umd.min.js"></script>
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
    <style>
        :root {
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-soft: #eef2ff;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px rgba(16, 24, 40, 0.04);
            --shadow-md: 0 4px 12px rgba(16, 24, 40, 0.08);
            --radius: 12px;
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: var(--gray-50);
            font-family: var(--font-sans);
            color: var(--gray-800, #1e293b);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Page Header */
        .page-header {
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
        }
        .page-header h1 i { color: var(--accent); margin-right: 0.6rem; font-size: 1.4rem; }
        .page-header .text-muted { color: var(--gray-500) !important; font-size: 0.9rem; }

        /* Header buttons */
        .page-header .btn-outline-primary {
            border-color: var(--accent);
            color: var(--accent);
            font-weight: 500;
        }
        .page-header .btn-outline-primary:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .page-header .btn-outline-secondary {
            border-color: var(--gray-300);
            color: var(--gray-600);
            font-weight: 500;
        }
        .page-header .btn-outline-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
            color: var(--gray-900);
        }

        /* Stat Cards */
        .stat-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem 1.25rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--gray-300);
            transform: translateY(-1px);
        }
        .stat-card h3 {
            font-size: 1.9rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-900);
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
        }
        .stat-card small {
            color: var(--gray-500);
            font-size: 0.78rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-card.primary h3 { color: var(--accent); }
        .stat-card.success h3 { color: var(--success); }
        .stat-card.warning h3 { color: var(--warning); }
        .stat-card.danger h3 { color: var(--danger); }

        /* Panels */
        .panel, .chart-panel {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .panel-header, .chart-panel .panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-100);
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--gray-900);
        }
        .panel-header i, .chart-panel .panel-header i { color: var(--accent); margin-right: 0.5rem; }
        .chart-panel .panel-body { padding: 1.25rem; }

        /* Tables */
        .table { margin-bottom: 0; font-size: 0.85rem; color: var(--gray-700); }
        .table th {
            font-weight: 600;
            background: var(--gray-50);
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-size: 0.72rem;
            border-bottom-color: var(--gray-200);
        }
        .table td { border-color: var(--gray-100); vertical-align: middle; }
        .table code { color: var(--accent); background: var(--accent-soft); padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.78rem; }
        .table .badge.bg-secondary { background: var(--gray-100) !important; color: var(--gray-600); font-weight: 500; }

        .nav-pills .nav-link.active { background-color: var(--accent); }
        .chart-container { position: relative; height: 300px; }
        .chart-container.pie { height: 280px; }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1><i class="bi bi-speedometer2"></i>Admin Dashboard</h1>
                    <p class="text-muted mb-0">Welcome back, <?= e($current_user['full_name'] ?? $current_user['username']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> Users</a>
                    <a href="activity_logs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text"></i> Logs</a>
                    <a href="settings.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Settings</a>
                    <a href="../records.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4">
        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <div class="stat-card primary">
                    <h3><?= number_format($total_records) ?></h3>
                    <small>Total Records</small>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card success">
                    <h3><?= number_format($today_records) ?></h3>
                    <small>Today's Records</small>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card primary">
                    <h3><?= number_format($total_users) ?></h3>
                    <small>Total Users</small>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card success">
                    <h3><?= number_format($active_users) ?></h3>
                    <small>Active Users</small>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card <?= $restricted_users > 0 ? 'danger' : 'success' ?>">
                    <h3><?= number_format($restricted_users) ?></h3>
                    <small>Restricted</small>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <!-- Monthly Performance -->
            <div class="col-lg-8">
                <div class="chart-panel">
                    <div class="panel-header"><i class="bi bi-graph-up"></i> Monthly Performance</div>
                    <div class="panel-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Types -->
            <div class="col-lg-4">
                <div class="chart-panel">
                    <div class="panel-header"><i class="bi bi-pie-chart"></i> Emergency Types</div>
                    <div class="panel-body">
                        <div class="chart-container pie">
                            <canvas id="emergencyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Activity -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="chart-panel">
                    <div class="panel-header"><i class="bi bi-bar-chart"></i> Daily Activity (Last 7 Days)</div>
                    <div class="panel-body">
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Recent Logins -->
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-header"><i class="bi bi-box-arrow-in-right"></i> Recent Logins</div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr><th>User</th><th>IP Address</th><th>Time</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_logins)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No login history yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_logins as $login): ?>
                                    <tr>
                                        <td><?= e($login['username']) ?></td>
                                        <td><code><?= e($login['ip_address']) ?></code></td>
                                        <td><?= e(date('M d, h:i A', strtotime($login['login_at']))) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-header"><i class="bi bi-activity"></i> Recent Activity</div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr><th>User</th><th>Action</th><th>Time</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activity as $act): ?>
                                <tr>
                                    <td><?= e($act['username'] ?? 'System') ?></td>
                                    <td><span class="badge bg-secondary"><?= e($act['action']) ?></span></td>
                                    <td><?= e(date('M d, h:i A', strtotime($act['created_at']))) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart data passed via data attributes (CSP-safe, no inline script needed) -->
    <div id="admin-chart-data" style="display:none"
         data-monthly-labels="<?php echo htmlspecialchars(json_encode($monthly_labels), ENT_QUOTES, 'UTF-8'); ?>"
         data-monthly-data="<?php echo htmlspecialchars(json_encode($monthly_data), ENT_QUOTES, 'UTF-8'); ?>"
         data-emergency-labels="<?php echo htmlspecialchars(json_encode(array_keys($emergency_counts)), ENT_QUOTES, 'UTF-8'); ?>"
         data-emergency-data="<?php echo htmlspecialchars(json_encode(array_values($emergency_counts)), ENT_QUOTES, 'UTF-8'); ?>"
         data-daily-labels="<?php echo htmlspecialchars(json_encode($daily_labels), ENT_QUOTES, 'UTF-8'); ?>"
         data-daily-data="<?php echo htmlspecialchars(json_encode($daily_data), ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <script src="../vendor/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../js/admin-dashboard-charts.js?v=<?php echo time(); ?>"></script>
</body>
</html>
