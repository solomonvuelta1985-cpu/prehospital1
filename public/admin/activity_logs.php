<?php
/**
 * Admin Activity Logs Dashboard
 * View, search, filter, and export system activity logs
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Require admin authentication
require_login();
require_admin();

// Generate CSRF token
$csrf_token = generate_token();
$current_user = get_auth_user();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? sanitize($_GET['action'], false) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from'], false) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to'], false) : '';

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(al.details LIKE ? OR al.action LIKE ? OR al.ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($user_filter > 0) {
    $where[] = "al.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($action_filter)) {
    $where[] = "al.action = ?";
    $params[] = $action_filter;
}

if (!empty($date_from) && validate_date($date_from)) {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to) && validate_date($date_to)) {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_logs al $where_sql";
$count_stmt = db_query($count_sql, $params);
$total_records = $count_stmt ? $count_stmt->fetch()['total'] : 0;
$total_pages = max(1, ceil($total_records / $per_page));

// Get logs with user info
$sql = "SELECT al.*, u.username, u.full_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_sql
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset";
$stmt = db_query($sql, $params);
$logs = $stmt ? $stmt->fetchAll() : [];

// Get distinct actions for filter dropdown
$actions_stmt = db_query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$distinct_actions = $actions_stmt ? $actions_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Get users for filter dropdown
$users_stmt = db_query("SELECT id, username, full_name FROM users ORDER BY username");
$all_users = $users_stmt ? $users_stmt->fetchAll() : [];

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!check_rate_limit('export', 5, 300)) {
        set_flash('Too many export requests. Please try again later.', 'error');
        redirect('activity_logs.php');
    }

    // Get all filtered results (no pagination)
    $export_sql = "SELECT al.*, u.username, u.full_name
                   FROM activity_logs al
                   LEFT JOIN users u ON al.user_id = u.id
                   $where_sql
                   ORDER BY al.created_at DESC";
    $export_stmt = db_query($export_sql, $params);
    $export_logs = $export_stmt ? $export_stmt->fetchAll() : [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8
    fputcsv($output, ['Date/Time', 'User', 'Action', 'Details', 'IP Address']);

    foreach ($export_logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['username'] ?? 'System',
            $log['action'],
            $log['details'],
            $log['ip_address']
        ]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-header { background: #ffffff; border-bottom: 2px solid #dee2e6; padding: 1.5rem 0; margin-bottom: 2rem; }
        .page-header h1 { margin: 0; font-size: clamp(1.5rem, 3vw, 1.75rem); font-weight: 600; color: #212529; }
        .page-header h1 i { color: #0d6efd; margin-right: 0.5rem; }
        .page-header p { margin: 0.5rem 0 0 0; color: #6c757d; font-size: 0.95rem; }
        .filter-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .table-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; }
        .table-card .table { margin-bottom: 0; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #495057; border-bottom: 2px solid #dee2e6; white-space: nowrap; }
        .table td { vertical-align: middle; font-size: 0.9rem; }
        .action-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .action-badge.login { background: #d1e7dd; color: #0f5132; }
        .action-badge.logout { background: #e2e3e5; color: #41464b; }
        .action-badge.form { background: #cff4fc; color: #055160; }
        .action-badge.delete { background: #f8d7da; color: #842029; }
        .action-badge.admin { background: #fff3cd; color: #664d03; }
        .action-badge.default { background: #e2e3e5; color: #41464b; }
        .details-cell { max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .details-cell:hover { white-space: normal; word-break: break-word; }
        .pagination { margin: 0; }
        .stats-row .stat-box { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 1rem; text-align: center; }
        .stats-row .stat-box h3 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #0d6efd; }
        .stats-row .stat-box small { color: #6c757d; }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1><i class="bi bi-journal-text"></i>Activity Logs</h1>
                    <p>Monitor system activity and user actions</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= '?' . http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <a href="users.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-people"></i> Users
                    </a>
                    <a href="../records.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4">
        <!-- Stats Row -->
        <div class="row stats-row mb-3 g-3">
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <h3><?= number_format($total_records) ?></h3>
                    <small>Total Entries</small>
                </div>
            </div>
            <?php
            $today_count = db_query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch()['c'] ?? 0;
            $login_count = db_query("SELECT COUNT(*) as c FROM activity_logs WHERE action = 'user_login' AND DATE(created_at) = CURDATE()")->fetch()['c'] ?? 0;
            $error_count = db_query("SELECT COUNT(*) as c FROM activity_logs WHERE (action LIKE '%restricted%' OR action LIKE '%failed%') AND DATE(created_at) = CURDATE()")->fetch()['c'] ?? 0;
            ?>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <h3><?= number_format($today_count) ?></h3>
                    <small>Today's Activity</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <h3><?= number_format($login_count) ?></h3>
                    <small>Logins Today</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <h3 style="color: <?= $error_count > 0 ? '#dc3545' : '#198754' ?>"><?= number_format($error_count) ?></h3>
                    <small>Security Events</small>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search details, IP..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="0">All Users</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>><?= e($u['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach ($distinct_actions as $a): ?>
                            <option value="<?= e($a) ?>" <?= $action_filter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($date_to) ?>">
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search"></i></button>
                    <a href="activity_logs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No activity logs found</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $action = $log['action'];
                                $badge_class = 'default';
                                if (strpos($action, 'login') !== false) $badge_class = 'login';
                                elseif (strpos($action, 'logout') !== false) $badge_class = 'logout';
                                elseif (strpos($action, 'form') !== false) $badge_class = 'form';
                                elseif (strpos($action, 'delete') !== false) $badge_class = 'delete';
                                elseif (strpos($action, 'restrict') !== false || strpos($action, 'admin') !== false) $badge_class = 'admin';
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?= e(date('M d, Y h:i A', strtotime($log['created_at']))) ?></td>
                                    <td><?= e($log['username'] ?? 'System') ?></td>
                                    <td><span class="action-badge <?= $badge_class ?>"><?= e($action) ?></span></td>
                                    <td class="details-cell" title="<?= e($log['details']) ?>"><?= e($log['details']) ?></td>
                                    <td><code><?= e($log['ip_address']) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <small class="text-muted">
                        Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_records) ?> of <?= number_format($total_records) ?> entries
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
