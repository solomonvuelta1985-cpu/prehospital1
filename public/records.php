<?php
/**
 * Pre-Hospital Care Records - View All Saved Forms
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Require authentication
require_login();

// Get current user
$current_user = get_auth_user();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$month_filter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$emergency_filter = isset($_GET['emergency']) ? sanitize($_GET['emergency']) : '';
$vehicle_filter = isset($_GET['vehicle']) ? sanitize($_GET['vehicle']) : '';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Validate sort option
$allowed_sorts = [
    'newest' => 'pf.created_at DESC',
    'oldest' => 'pf.created_at ASC',
    'date_desc' => 'pf.form_date DESC',
    'date_asc' => 'pf.form_date ASC',
    'name_asc' => 'pf.patient_name ASC',
    'name_desc' => 'pf.patient_name DESC',
];
$order_sql = $allowed_sorts[$sort_by] ?? 'pf.created_at DESC';

// Build query - Filter by user unless admin
$where_conditions = [];
$params = [];

// IMPORTANT: Users can only see their own records (admins see all)
if (!is_admin()) {
    $where_conditions[] = "created_by = ?";
    $params[] = $current_user['id'];
}

if (!empty($search)) {
    $where_conditions[] = "(form_number LIKE ? OR patient_name LIKE ? OR place_of_incident LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "form_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "form_date <= ?";
    $params[] = $date_to;
}

if ($month_filter >= 1 && $month_filter <= 12) {
    $where_conditions[] = "MONTH(form_date) = ?";
    $params[] = $month_filter;
}

if ($year_filter >= 2000 && $year_filter <= 2099) {
    $where_conditions[] = "YEAR(form_date) = ?";
    $params[] = $year_filter;
}

if (!empty($emergency_filter)) {
    $valid_emergency = ['medical', 'trauma', 'ob', 'general'];
    if (in_array($emergency_filter, $valid_emergency)) {
        $where_conditions[] = "emergency_{$emergency_filter} = 1";
    }
}

if (!empty($vehicle_filter)) {
    $where_conditions[] = "vehicle_used = ?";
    $params[] = $vehicle_filter;
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM prehospital_forms $where_sql";
$count_stmt = db_query($count_sql, $params);
$total_records = ($count_stmt) ? $count_stmt->fetch()['total'] : 0;
$total_pages = ceil($total_records / max($per_page, 1));

// Get available years for year filter dropdown
$years_sql = "SELECT DISTINCT YEAR(form_date) as yr FROM prehospital_forms WHERE form_date IS NOT NULL AND form_date != '0000-00-00' ORDER BY yr DESC";
$years_stmt = db_query($years_sql);
$available_years = ($years_stmt) ? $years_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Get records with creator information
$sql = "SELECT
    pf.id, pf.form_number, pf.form_date, pf.patient_name, pf.age, pf.gender,
    pf.place_of_incident, pf.vehicle_used, pf.status, pf.created_at,
    pf.arrival_hospital_name,
    pf.emergency_medical, pf.emergency_medical_details,
    pf.emergency_trauma, pf.emergency_trauma_details,
    pf.emergency_ob, pf.emergency_ob_details,
    pf.emergency_general, pf.emergency_general_details,
    pf.care_management,
    u.full_name as created_by_name
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    $where_sql
    ORDER BY $order_sql
    LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = db_query($sql, $params);
$records = ($stmt) ? $stmt->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <link href="css/records-style.css" rel="stylesheet">
</head>
<body>
    <!-- Include the sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="records-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1><i class="fas fa-folder-open"></i> Pre-Hospital Care Records</h1>
            <h2>
                <?php if (is_admin()): ?>
                    View and manage all records from all users | Admin: <?php echo e($current_user['full_name']); ?>
                <?php else: ?>
                    View and manage your personal records | User: <?php echo e($current_user['full_name']); ?>
                <?php endif; ?>
            </h2>
        </div>

        <?php show_flash(); ?>

        <!-- Statistics Cards -->
        <div class="stats-cards" id="statsCards">
            <!-- Skeleton Cards -->
            <div class="stat-card skeleton-card">
                <div class="skeleton-title skeleton"></div>
                <div class="skeleton-text skeleton short"></div>
            </div>
            <div class="stat-card skeleton-card">
                <div class="skeleton-title skeleton"></div>
                <div class="skeleton-text skeleton short"></div>
            </div>
            <div class="stat-card skeleton-card">
                <div class="skeleton-title skeleton"></div>
                <div class="skeleton-text skeleton short"></div>
            </div>
            <div class="stat-card skeleton-card">
                <div class="skeleton-title skeleton"></div>
                <div class="skeleton-text skeleton short"></div>
            </div>

            <!-- Actual Cards -->
            <div class="stat-card">
                <h3><?php echo number_format($total_records); ?></h3>
                <p><i class="fas fa-file-alt"></i> <?php echo is_admin() ? 'Total Records' : 'My Records'; ?></p>
            </div>
            <div class="stat-card">
                <h3>
                    <?php
                    if (is_admin()) {
                        $completed_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE status = 'completed'";
                        $completed_stmt = db_query($completed_sql);
                    } else {
                        $completed_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE status = 'completed' AND created_by = ?";
                        $completed_stmt = db_query($completed_sql, [$current_user['id']]);
                    }
                    echo number_format($completed_stmt ? $completed_stmt->fetch()['count'] : 0);
                    ?>
                </h3>
                <p><i class="fas fa-check-circle"></i> Completed</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?php
                    if (is_admin()) {
                        $today_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE DATE(created_at) = CURDATE()";
                        $today_stmt = db_query($today_sql);
                    } else {
                        $today_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE DATE(created_at) = CURDATE() AND created_by = ?";
                        $today_stmt = db_query($today_sql, [$current_user['id']]);
                    }
                    echo number_format($today_stmt ? $today_stmt->fetch()['count'] : 0);
                    ?>
                </h3>
                <p><i class="fas fa-calendar-check"></i> Today</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?php
                    if (is_admin()) {
                        $week_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE YEARWEEK(created_at) = YEARWEEK(NOW())";
                        $week_stmt = db_query($week_sql);
                    } else {
                        $week_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE YEARWEEK(created_at) = YEARWEEK(NOW()) AND created_by = ?";
                        $week_stmt = db_query($week_sql, [$current_user['id']]);
                    }
                    echo number_format($week_stmt ? $week_stmt->fetch()['count'] : 0);
                    ?>
                </h3>
                <p><i class="fas fa-calendar-week"></i> This Week</p>
            </div>
        </div>

        <!-- Action Section with Search and Filters -->
        <div class="action-section">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Search records..." onkeyup="searchRecords()">
            </div>
            <div class="action-buttons">
                <a href="prehospital_form.php" class="btn-custom btn-primary-custom">
                    <i class="fas fa-plus"></i> Add New Record
                </a>
                <button id="btnExportCSV" class="btn-custom btn-success-custom">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button id="btnPrint" class="btn-custom btn-secondary-custom">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-card">
            <form method="GET" action="" class="row g-3">
                <!-- Row 1: Status, Month, Year, Sort -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Month</label>
                    <select class="form-select" name="month">
                        <option value="">All Months</option>
                        <?php
                        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        foreach ($months as $i => $m): ?>
                            <option value="<?php echo $i + 1; ?>" <?php echo $month_filter === ($i + 1) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Year</label>
                    <select class="form-select" name="year">
                        <option value="">All Years</option>
                        <?php foreach ($available_years as $yr): ?>
                            <option value="<?php echo $yr; ?>" <?php echo $year_filter === (int)$yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" name="sort">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Form Date (Newest)</option>
                        <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Form Date (Oldest)</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Patient Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Patient Name (Z-A)</option>
                    </select>
                </div>

                <!-- Row 2: Date Range, Emergency Type, Vehicle, Actions -->
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from"
                           value="<?php echo e($date_from); ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to"
                           value="<?php echo e($date_to); ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Emergency Type</label>
                    <select class="form-select" name="emergency">
                        <option value="">All Types</option>
                        <option value="medical" <?php echo $emergency_filter === 'medical' ? 'selected' : ''; ?>>Medical</option>
                        <option value="trauma" <?php echo $emergency_filter === 'trauma' ? 'selected' : ''; ?>>Trauma</option>
                        <option value="ob" <?php echo $emergency_filter === 'ob' ? 'selected' : ''; ?>>OB</option>
                        <option value="general" <?php echo $emergency_filter === 'general' ? 'selected' : ''; ?>>General</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Vehicle Used</label>
                    <select class="form-select" name="vehicle">
                        <option value="">All Vehicles</option>
                        <option value="ambulance" <?php echo $vehicle_filter === 'ambulance' ? 'selected' : ''; ?>>Ambulance</option>
                        <option value="fireTruck" <?php echo $vehicle_filter === 'fireTruck' ? 'selected' : ''; ?>>Fire Truck</option>
                        <option value="others" <?php echo $vehicle_filter === 'others' ? 'selected' : ''; ?>>Others</option>
                    </select>
                </div>

                <!-- Row 3: Action buttons -->
                <div class="col-12 d-flex align-items-end gap-2">
                    <button type="submit" class="btn-custom btn-primary-custom">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="records.php" class="btn-custom btn-secondary-custom">
                        <i class="fas fa-times-circle"></i> Clear All
                    </a>
                </div>
            </form>
        </div>

        <!-- Record Count Display -->
        <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6;">
            <strong style="color: #212529;">
                <i class="fas fa-list"></i>
                Showing <?php echo number_format($total_records); ?> record<?php echo $total_records != 1 ? 's' : ''; ?>
                <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to) || $month_filter || $year_filter || !empty($emergency_filter) || !empty($vehicle_filter)): ?>
                    (filtered)
                <?php endif; ?>
            </strong>
        </div>

        <!-- Records Table -->
        <div class="table-container" id="tableContainer">
            <table class="custom-table" id="recordsTable">
                <thead>
                    <tr>
                        <th style="border-right: 1px solid #dee2e6;">#</th>
                        <th style="border-right: 1px solid #dee2e6;">Form #</th>
                        <th style="border-right: 1px solid #dee2e6;">Date</th>
                        <th style="border-right: 1px solid #dee2e6;">Patient Name</th>
                        <th style="border-right: 1px solid #dee2e6;">Age/Gender</th>
                        <th style="border-right: 1px solid #dee2e6;">Type of Emergency Call</th>
                        <th style="border-right: 1px solid #dee2e6;">Care Management</th>
                        <th style="border-right: 1px solid #dee2e6;">Vehicle</th>
                        <th style="border-right: 1px solid #dee2e6;">Created By</th>
                        <th style="border-right: 1px solid #dee2e6;">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <!-- Skeleton Loader -->
                <tbody class="skeleton-loader" id="skeletonLoader">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <tr class="skeleton-row">
                        <td class="skeleton-cell">
                            <div class="skeleton-bar short skeleton" style="width: 30px;"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar short skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar medium skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar long skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar short skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar medium skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar medium skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar short skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar medium skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar short skeleton"></div>
                        </td>
                        <td class="skeleton-cell">
                            <div class="skeleton-bar short skeleton" style="width: 70px; display: inline-block; margin-right: 5px;"></div>
                            <div class="skeleton-bar short skeleton" style="width: 70px; display: inline-block; margin-right: 5px;"></div>
                            <div class="skeleton-bar short skeleton" style="width: 70px; display: inline-block;"></div>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>

                <!-- Actual Content -->
                <tbody class="table-content" id="tableContent">
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 30px;">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p style="color: #6c757d; margin-top: 15px;">No records found.</p>
                                    <a href="prehospital_form.php" class="btn-custom btn-primary-custom" style="margin-top: 15px;">
                                        <i class="fas fa-plus"></i> Create First Record
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $index => $record): ?>
                            <?php $row_number = $offset + $index + 1; ?>
                            <tr>
                                <td style="font-weight: 600; color: #6c757d;"><?php echo $row_number; ?></td>
                                <td><strong><?php echo e($record['form_number']); ?></strong></td>
                                <td><?php echo ($record['form_date'] && $record['form_date'] !== '0000-00-00') ? date('M d, Y', strtotime($record['form_date'])) : 'N/A'; ?></td>
                                <td><?php echo e($record['patient_name']); ?></td>
                                <td>
                                    <?php echo e($record['age']); ?> /
                                    <?php echo $record['gender'] ? ucfirst((string)$record['gender']) : '-'; ?>
                                </td>
                                <td>
                                    <?php
                                    $emergencyTypes = [];
                                    if ($record['emergency_medical']) {
                                        $detail = $record['emergency_medical_details'] ? ' - ' . $record['emergency_medical_details'] : '';
                                        $emergencyTypes[] = 'Medical' . $detail;
                                    }
                                    if ($record['emergency_trauma']) {
                                        $detail = $record['emergency_trauma_details'] ? ' - ' . $record['emergency_trauma_details'] : '';
                                        $emergencyTypes[] = 'Trauma' . $detail;
                                    }
                                    if ($record['emergency_ob']) {
                                        $detail = $record['emergency_ob_details'] ? ' - ' . $record['emergency_ob_details'] : '';
                                        $emergencyTypes[] = 'OB' . $detail;
                                    }
                                    if ($record['emergency_general']) {
                                        $detail = $record['emergency_general_details'] ? ' - ' . $record['emergency_general_details'] : '';
                                        $emergencyTypes[] = 'General' . $detail;
                                    }
                                    echo $emergencyTypes ? e(implode('; ', $emergencyTypes)) : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $careItems = [];
                                    if ($record['care_management']) {
                                        $decoded = json_decode($record['care_management'], true);
                                        if (is_array($decoded)) {
                                            $careItems = array_map('ucfirst', $decoded);
                                        }
                                    }
                                    echo $careItems ? implode(', ', $careItems) : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($record['vehicle_used']): ?>
                                        <span class="badge-custom status-pending"><?php echo ucfirst((string)e($record['vehicle_used'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-user-circle" style="color: #6c757d; margin-right: 4px;"></i>
                                    <?php echo e($record['created_by_name'] ?: 'Unknown'); ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'draft' => 'status-draft',
                                        'completed' => 'status-completed',
                                        'archived' => 'status-archived'
                                    ];
                                    $class = $status_class[$record['status']] ?? 'status-draft';
                                    ?>
                                    <span class="badge-custom <?php echo $class; ?>">
                                        <?php echo ucfirst((string)e($record['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-sm action-dropdown-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-cog"></i> Actions <i class="fas fa-chevron-down action-chevron"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end action-dropdown-menu">
                                            <?php if ($record['status'] === 'draft'): ?>
                                            <li>
                                                <a class="dropdown-item" href="prehospital_form.php?draft_id=<?php echo $record['id']; ?>">
                                                    <i class="fas fa-play text-success"></i> Resume
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php endif; ?>
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0)" data-view-record="<?php echo $record['id']; ?>">
                                                    <i class="fas fa-eye" style="color: #0065FF;"></i> View
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="edit_record.php?id=<?php echo $record['id']; ?>">
                                                    <i class="fas fa-edit" style="color: #FF8B00;"></i> Edit
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item dropdown-item-danger" href="javascript:void(0)" data-delete-record="<?php echo $record['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        // Build filter query string for pagination links
        $filter_params = http_build_query(array_filter([
            'search' => $search,
            'status' => $status_filter,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'month' => $month_filter ?: '',
            'year' => $year_filter ?: '',
            'emergency' => $emergency_filter,
            'vehicle' => $vehicle_filter,
            'sort' => $sort_by !== 'newest' ? $sort_by : '',
        ]));
        ?>
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" style="margin-top: clamp(20px, 4vw, 25px);">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $filter_params; ?>">
                            Previous
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $filter_params; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php elseif (abs($i - $page) == 3): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $filter_params; ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" title="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- View Record Modal -->
    <div class="modal fade" id="viewRecordModal" tabindex="-1" aria-labelledby="viewRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewRecordModalLabel">
                        <i class="fas fa-file-medical"></i> View Record
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalRecordContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading record details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="font-size: 0.875rem;">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="viewFullDetailsBtn" style="font-size: 0.875rem;">
                        <i class="fas fa-expand"></i> See Full Details
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="editRecordBtn" style="font-size: 0.875rem;">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
    // Skeleton Loader
    document.addEventListener('DOMContentLoaded', function() {
        const tableContainer = document.getElementById('tableContainer');
        const statsCards = document.getElementById('statsCards');

        tableContainer.classList.add('loading');
        statsCards.classList.add('loading');

        setTimeout(function() {
            tableContainer.classList.remove('loading');
            statsCards.classList.remove('loading');
        }, 1500);
    });

    // Search function
    function searchRecords() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('recordsTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            let txtValue = tr[i].textContent || tr[i].innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }

    // Back to top button
    const backToTopButton = document.getElementById('backToTop');

    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });

    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Delete record
    function deleteRecord(id) {
        Notiflix.Confirm.show(
            'Delete Record',
            'Are you sure you want to delete this record? This action cannot be undone.',
            'Yes, Delete',
            'Cancel',
            function okCb() {
                // Show loading
                Notiflix.Loading.standard('Deleting record...');

                fetch('../api/delete_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id, csrf_token: '<?php echo generate_token(); ?>' })
                })
                .then(response => response.json())
                .then(data => {
                    // Remove loading
                    Notiflix.Loading.remove();

                    if (data.success) {
                        Notiflix.Notify.success('Record deleted successfully!', {
                            timeout: 2000,
                        });
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        Notiflix.Notify.failure('Error: ' + data.message, {
                            timeout: 3000,
                        });
                    }
                })
                .catch(error => {
                    // Remove loading
                    Notiflix.Loading.remove();

                    Notiflix.Notify.failure('Error deleting record. Please try again.', {
                        timeout: 3000,
                    });
                    console.error('Error:', error);
                });
            },
            function cancelCb() {
                // User cancelled
            },
            {
                width: '350px',
                borderRadius: '8px',
                titleColor: '#dc3545',
                okButtonBackground: '#dc3545',
                okButtonColor: '#fff',
            }
        );
    }

    // Export to CSV
    function exportToCSV() {
        window.location.href = '../api/export_records.php?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&month=<?php echo $month_filter; ?>&year=<?php echo $year_filter; ?>&emergency=<?php echo urlencode($emergency_filter); ?>&vehicle=<?php echo urlencode($vehicle_filter); ?>&sort=<?php echo urlencode($sort_by); ?>';
    }

    // View record in modal
    let currentRecordId = null;

    function viewRecord(id) {
        currentRecordId = id;
        const modal = new bootstrap.Modal(document.getElementById('viewRecordModal'));
        const modalContent = document.getElementById('modalRecordContent');
        const editBtn = document.getElementById('editRecordBtn');
        const viewFullDetailsBtn = document.getElementById('viewFullDetailsBtn');

        // Reset content to loading state
        modalContent.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading record details...</p>
            </div>
        `;

        // Set edit button URL
        editBtn.onclick = function() {
            window.location.href = 'edit_record.php?id=' + id;
        };

        // Set view full details button URL
        viewFullDetailsBtn.onclick = function() {
            window.location.href = 'view_record.php?id=' + id;
        };

        // Show modal
        modal.show();

        // Fetch record content
        fetch('../api/get_record.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalContent.innerHTML = data.html;
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error loading record: ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Failed to load record. Please try again.
                    </div>
                `;
            });
    }

    // Attach all event handlers via JS (CSP blocks inline onclick)
    document.addEventListener('click', function(e) {
        var deleteBtn = e.target.closest('[data-delete-record]');
        if (deleteBtn) {
            deleteRecord(parseInt(deleteBtn.getAttribute('data-delete-record')));
            return;
        }
        var viewBtn = e.target.closest('[data-view-record]');
        if (viewBtn) {
            viewRecord(parseInt(viewBtn.getAttribute('data-view-record')));
            return;
        }
    });

    document.getElementById('btnExportCSV')?.addEventListener('click', function() { exportToCSV(); });
    document.getElementById('btnPrint')?.addEventListener('click', function() { window.print(); });
    document.getElementById('backToTop')?.addEventListener('click', function() { scrollToTop(); });

    </script>
</body>
</html>
