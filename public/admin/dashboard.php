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

// Stats
$total_records = db_query("SELECT COUNT(*) as c FROM prehospital_forms")->fetch()['c'] ?? 0;
$today_records = db_query("SELECT COUNT(*) as c FROM prehospital_forms WHERE DATE(created_at) = CURDATE()")->fetch()['c'] ?? 0;
$total_users = db_query("SELECT COUNT(*) as c FROM users")->fetch()['c'] ?? 0;
$active_users = db_query("SELECT COUNT(*) as c FROM users WHERE status = 'active'")->fetch()['c'] ?? 0;
$restricted_users = db_query("SELECT COUNT(*) as c FROM users WHERE is_restricted = 1")->fetch()['c'] ?? 0;

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-header { background: #fff; border-bottom: 2px solid #dee2e6; padding: 1.5rem 0; margin-bottom: 2rem; }
        .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 600; }
        .page-header h1 i { color: #0d6efd; margin-right: 0.5rem; }
        .stat-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.25rem; text-align: center; transition: box-shadow 0.2s; }
        .stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0; }
        .stat-card small { color: #6c757d; font-size: 0.85rem; }
        .stat-card.primary h3 { color: #0d6efd; }
        .stat-card.success h3 { color: #198754; }
        .stat-card.warning h3 { color: #fd7e14; }
        .stat-card.danger h3 { color: #dc3545; }
        .panel { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; margin-bottom: 1.5rem; }
        .panel-header { padding: 1rem 1.25rem; border-bottom: 1px solid #dee2e6; font-weight: 600; font-size: 0.95rem; }
        .panel-header i { color: #0d6efd; margin-right: 0.5rem; }
        .table { margin-bottom: 0; font-size: 0.85rem; }
        .table th { font-weight: 600; background: #f8f9fa; }
        .nav-pills .nav-link.active { background-color: #0d6efd; }
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
