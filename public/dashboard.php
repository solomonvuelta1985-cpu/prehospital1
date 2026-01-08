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

$total_forms_stmt = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE created_by = ?", [$user_id]);
$total_forms = $total_forms_stmt->fetch()['count'];

$today_forms_stmt = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE created_by = ? AND DATE(created_at) = CURDATE()", [$user_id]);
$today_forms = $today_forms_stmt->fetch()['count'];

$pending_forms_stmt = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE created_by = ? AND status IN ('draft', 'pending')", [$user_id]);
$pending_forms = $pending_forms_stmt->fetch()['count'];

$completed_forms_stmt = db_query("SELECT COUNT(*) as count FROM prehospital_forms WHERE created_by = ? AND status = 'completed'", [$user_id]);
$completed_forms = $completed_forms_stmt->fetch()['count'];

// Get recent forms with creator information
$recent_forms_stmt = db_query("
    SELECT pf.*, u.full_name as created_by_name
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    WHERE pf.created_by = ?
    ORDER BY pf.created_at DESC
    LIMIT 10
", [$user_id]);
$recent_forms = $recent_forms_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0066cc;
            --secondary-color: #004d99;
            --light-bg: #f8fafc;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            --card-hover-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #334155;
        }

        .navbar {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid #e2e8f0;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Simplified Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #f1f5f9;
            transition: all 0.2s ease;
            height: 100%;
        }

        .stat-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            background: #f8fafc;
        }

        .stat-icon.blue { color: var(--primary-color); }
        .stat-icon.green { color: #10b981; }
        .stat-icon.orange { color: #f59e0b; }
        .stat-icon.purple { color: #8b5cf6; }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            line-height: 1.2;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0.25rem 0 0 0;
        }

        /* Clean Table Design */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #f1f5f9;
        }

        .table-card h5 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 1.25rem;
            font-size: 1.125rem;
        }

        .table th {
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table tbody tr {
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* LARGER ACTION BUTTONS */
        .btn-action {
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
            border-radius: 6px;
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-group-sm > .btn-action {
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #f1f5f9;
        }

        .welcome-section h2 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .welcome-section p {
            color: #64748b;
            margin: 0;
        }

        /* Mobile Cards for Forms */
        .mobile-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.875rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #f1f5f9;
        }

        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.625rem;
            gap: 0.5rem;
        }

        .mobile-card-title {
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            font-size: 0.95rem;
            flex: 1;
        }

        .mobile-card-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            white-space: nowrap;
        }

        .mobile-card-detail {
            display: flex;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
        }

        .mobile-card-label {
            font-weight: 500;
            color: #64748b;
            min-width: 75px;
            font-size: 0.8rem;
        }

        .mobile-card-value {
            color: #334155;
            font-size: 0.82rem;
            flex: 1;
            word-break: break-word;
        }

        /* LARGER MOBILE ACTION BUTTONS */
        .mobile-card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.875rem;
            justify-content: stretch;
        }

        .mobile-card-actions .btn-action {
            flex: 1;
            padding: 0.65rem 0.4rem;
            font-size: 0.8rem;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .stat-card {
                padding: 0.875rem;
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 0.95rem;
                margin-bottom: 0.5rem;
            }

            .stat-value {
                font-size: 1.35rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }

            .welcome-section {
                padding: 1rem;
            }

            .welcome-section h2 {
                font-size: 1.15rem;
            }

            .welcome-section p {
                font-size: 0.875rem;
            }

            .table-card {
                padding: 0.875rem;
            }

            .table-card h5 {
                font-size: 1rem;
            }

            .table-card .btn-sm {
                padding: 0.4rem 0.7rem;
                font-size: 0.75rem;
            }

            /* More compact mobile cards */
            .mobile-card {
                padding: 0.875rem;
                margin-bottom: 0.75rem;
            }

            .mobile-card-title {
                font-size: 0.9rem;
            }

            .mobile-card-label {
                min-width: 70px;
                font-size: 0.75rem;
            }

            .mobile-card-value {
                font-size: 0.8rem;
            }

            .mobile-card-detail {
                margin-bottom: 0.35rem;
            }

            .mobile-card-actions {
                margin-top: 0.75rem;
                gap: 0.5rem;
            }

            /* Optimized buttons on mobile */
            .btn-action {
                padding: 0.6rem 0.7rem;
                font-size: 0.85rem;
            }

            .mobile-card-actions .btn-action {
                padding: 0.6rem 0.4rem;
                font-size: 0.8rem;
                min-height: 38px;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 0.625rem;
                padding-right: 0.625rem;
            }

            /* More compact stats on very small screens */
            .stat-card {
                padding: 0.75rem;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
                margin-bottom: 0.4rem;
            }

            .stat-value {
                font-size: 1.25rem;
            }

            .stat-label {
                font-size: 0.7rem;
            }

            .welcome-section {
                padding: 0.875rem;
            }

            .welcome-section h2 {
                font-size: 1.05rem;
            }

            .welcome-section p {
                font-size: 0.8rem;
            }

            /* More compact mobile cards */
            .mobile-card {
                padding: 0.75rem;
            }

            .mobile-card-actions .btn-action {
                padding: 0.55rem 0.35rem;
                font-size: 0.75rem;
                min-height: 36px;
            }

            .mobile-card-actions .btn-action i {
                font-size: 0.85rem;
            }

            .btn-group-sm > .btn-action {
                padding: 0.6rem 0.7rem;
            }

            .btn-primary {
                padding: 0.625rem 1.25rem;
            }

            /* Better overflow handling on small screens */
            .table-card {
                overflow: hidden;
            }

            .mobile-card-title {
                line-height: 1.3;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            min-height: 44px;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Ensure button group has proper spacing */
        .btn-group {
            gap: 0.5rem;
        }

        .btn-group .btn-action {
            border-radius: 6px !important;
        }
    </style>
</head>
<body>
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-3">
        <?php show_flash(); ?>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>Welcome back, <?php echo e($current_user['full_name']); ?>!</h2>
                    <p>Here's an overview of your pre-hospital care forms and recent activity.</p>
                </div>
                <div class="col-md-4 text-md-end text-start mt-3 mt-md-0">
                    <a href="TONYANG.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Create New Form
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-2 g-md-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($total_forms); ?></p>
                    <p class="stat-label">Total Forms</p>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($today_forms); ?></p>
                    <p class="stat-label">Today's Forms</p>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="bi bi-clock"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($pending_forms); ?></p>
                    <p class="stat-label">Pending Forms</p>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <p class="stat-value"><?php echo number_format($completed_forms); ?></p>
                    <p class="stat-label">Completed Forms</p>
                </div>
            </div>
        </div>

        <!-- Recent Forms Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-clock-history me-2"></i>Your Recent Forms</h5>
                <a href="records.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-list-ul me-1"></i> View All Records
                </a>
            </div>

            <!-- Desktop Table -->
            <div class="d-none d-md-block">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Form Number</th>
                                <th>Date</th>
                                <th>Patient Name</th>
                                <th>Age/Gender</th>
                                <th>Vehicle</th>
                                <th>Hospital</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_forms)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p class="mt-2 mb-3">No forms found</p>
                                            <a href="TONYANG.php" class="btn btn-primary">
                                                <i class="bi bi-plus-circle me-1"></i> Create Your First Form
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_forms as $form): ?>
                                    <tr>
                                        <td><strong><?php echo e($form['form_number']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($form['form_date'])); ?></td>
                                        <td><?php echo e($form['patient_name']); ?></td>
                                        <td><?php echo e($form['age']); ?> / <?php echo ucfirst(e($form['gender'])); ?></td>
                                        <td>
                                            <?php if ($form['vehicle_used']): ?>
                                                <span class="badge bg-light text-dark border"><?php echo ucfirst(e($form['vehicle_used'])); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($form['arrival_hospital_name'] ?: '-'); ?></td>
                                        <td>
                                            <i class="bi bi-person-circle" style="color: #6c757d;"></i>
                                            <?php echo e($form['created_by_name'] ?: 'Unknown'); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'draft' => 'bg-light text-dark',
                                                'completed' => 'bg-success',
                                                'pending' => 'bg-warning text-dark',
                                                'archived' => 'bg-dark'
                                            ];
                                            $class = $status_class[$form['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge badge-status <?php echo $class; ?>">
                                                <?php echo ucfirst(e($form['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_record.php?id=<?php echo $form['id']; ?>"
                                                   class="btn btn-outline-primary btn-action" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_record.php?id=<?php echo $form['id']; ?>"
                                                   class="btn btn-outline-success btn-action" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="btn btn-outline-danger btn-action" title="Delete"
                                                        onclick="deleteRecord(<?php echo $form['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="d-md-none">
                <?php if (empty($recent_forms)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p class="mt-2 mb-3">No forms found</p>
                        <a href="TONYANG.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Create Your First Form
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_forms as $form): ?>
                        <div class="mobile-card">
                            <div class="mobile-card-header">
                                <h6 class="mobile-card-title"><?php echo e($form['form_number']); ?></h6>
                                <?php
                                $status_class = [
                                    'draft' => 'bg-light text-dark',
                                    'completed' => 'bg-success',
                                    'pending' => 'bg-warning text-dark',
                                    'archived' => 'bg-dark'
                                ];
                                $class = $status_class[$form['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge mobile-card-badge <?php echo $class; ?>">
                                    <?php echo ucfirst(e($form['status'])); ?>
                                </span>
                            </div>
                            
                            <div class="mobile-card-detail">
                                <span class="mobile-card-label">Date:</span>
                                <span class="mobile-card-value"><?php echo date('M d, Y', strtotime($form['form_date'])); ?></span>
                            </div>
                            
                            <div class="mobile-card-detail">
                                <span class="mobile-card-label">Patient:</span>
                                <span class="mobile-card-value"><?php echo e($form['patient_name']); ?></span>
                            </div>
                            
                            <div class="mobile-card-detail">
                                <span class="mobile-card-label">Age/Gender:</span>
                                <span class="mobile-card-value"><?php echo e($form['age']); ?> / <?php echo ucfirst(e($form['gender'])); ?></span>
                            </div>
                            
                            <div class="mobile-card-detail">
                                <span class="mobile-card-label">Vehicle:</span>
                                <span class="mobile-card-value">
                                    <?php if ($form['vehicle_used']): ?>
                                        <?php echo ucfirst(e($form['vehicle_used'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="mobile-card-detail">
                                <span class="mobile-card-label">Hospital:</span>
                                <span class="mobile-card-value"><?php echo e($form['arrival_hospital_name'] ?: '-'); ?></span>
                            </div>

                            <div class="mobile-card-detail">
                                <span class="mobile-card-label">Created By:</span>
                                <span class="mobile-card-value">
                                    <i class="bi bi-person-circle" style="color: #6c757d;"></i>
                                    <?php echo e($form['created_by_name'] ?: 'Unknown'); ?>
                                </span>
                            </div>

                            <div class="mobile-card-actions">
                                <a href="view_record.php?id=<?php echo $form['id']; ?>"
                                   class="btn btn-outline-primary btn-action" title="View">
                                    <i class="bi bi-eye me-1 d-none d-sm-inline"></i> View
                                </a>
                                <a href="edit_record.php?id=<?php echo $form['id']; ?>"
                                   class="btn btn-outline-success btn-action" title="Edit">
                                    <i class="bi bi-pencil me-1 d-none d-sm-inline"></i> Edit
                                </a>
                                <button class="btn btn-outline-danger btn-action" title="Delete"
                                        onclick="deleteRecord(<?php echo $form['id']; ?>)">
                                    <i class="bi bi-trash me-1 d-none d-sm-inline"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                fetch('api/delete_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Record deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting record');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>