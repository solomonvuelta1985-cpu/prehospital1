<?php
/**
 * Pre-Hospital Care Records - View All Saved Forms
 * Modern SaaS / Corporate Design
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
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page_options = [20, 50, 100];
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, $per_page_options)) $per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? (sanitize($_GET['search']) ?? '') : '';
$status_filter = isset($_GET['status']) ? (sanitize($_GET['status']) ?? '') : '';
$date_from = isset($_GET['date_from']) ? (sanitize($_GET['date_from']) ?? '') : '';
$date_to = isset($_GET['date_to']) ? (sanitize($_GET['date_to']) ?? '') : '';
$month_filter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year_filter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$emergency_filter = isset($_GET['emergency']) ? (sanitize($_GET['emergency']) ?? '') : '';
$vehicle_filter = isset($_GET['vehicle']) ? (sanitize($_GET['vehicle']) ?? '') : '';
$sort_by = isset($_GET['sort']) ? (sanitize($_GET['sort']) ?? 'newest') : 'newest';

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

// Build query
$where_conditions = [];
$params = [];

if (!is_admin()) {
    $where_conditions[] = "pf.created_by = ?";
    $params[] = $current_user['id'];
}

if (!empty($search)) {
    $where_conditions[] = "(pf.form_number LIKE ? OR pf.patient_name LIKE ? OR pf.place_of_incident LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "pf.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "pf.form_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "pf.form_date <= ?";
    $params[] = $date_to;
}

if ($month_filter >= 1 && $month_filter <= 12) {
    $where_conditions[] = "MONTH(pf.form_date) = ?";
    $params[] = $month_filter;
}

if ($year_filter >= 2000 && $year_filter <= 2099) {
    $where_conditions[] = "YEAR(pf.form_date) = ?";
    $params[] = $year_filter;
}

if (!empty($emergency_filter)) {
    $valid_emergency = ['medical', 'trauma', 'ob', 'general'];
    if (in_array($emergency_filter, $valid_emergency)) {
        $where_conditions[] = "pf.emergency_{$emergency_filter} = 1";
    }
}

if (!empty($vehicle_filter)) {
    $where_conditions[] = "pf.vehicle_used = ?";
    $params[] = $vehicle_filter;
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM prehospital_forms pf $where_sql";
$count_stmt = db_query($count_sql, $params);
$total_records = ($count_stmt) ? (int)$count_stmt->fetch()['total'] : 0;
$total_pages = max(1, ceil($total_records / max($per_page, 1)));

// Get available years for filter dropdown
$years_sql = "SELECT DISTINCT YEAR(form_date) as yr FROM prehospital_forms WHERE form_date IS NOT NULL AND form_date != '0000-00-00' ORDER BY yr DESC";
$years_stmt = db_query($years_sql);
$available_years = ($years_stmt) ? $years_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

// Get records
$sql = "SELECT
    pf.id, pf.form_number, pf.form_date, pf.patient_name, pf.age, pf.gender,
    pf.place_of_incident, pf.vehicle_used, pf.status, pf.created_at, pf.updated_at,
    pf.arrival_hospital_name,
    pf.emergency_medical, pf.emergency_medical_details,
    pf.emergency_trauma, pf.emergency_trauma_details,
    pf.emergency_ob, pf.emergency_ob_details,
    pf.emergency_general, pf.emergency_general_details,
    pf.care_management, pf.other_care,
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

// Stat counts (batched)
$user_clause = is_admin() ? "" : " AND created_by = ?";
$stat_params = is_admin() ? [] : [$current_user['id']];

$completed_stmt = db_query("SELECT COUNT(*) as c FROM prehospital_forms WHERE status = 'completed'" . $user_clause, $stat_params);
$completed_count = (int)($completed_stmt ? $completed_stmt->fetch()['c'] : 0);

$today_stmt = db_query("SELECT COUNT(*) as c FROM prehospital_forms WHERE DATE(created_at) = CURDATE()" . $user_clause, $stat_params);
$today_count = (int)($today_stmt ? $today_stmt->fetch()['c'] : 0);

$week_stmt = db_query("SELECT COUNT(*) as c FROM prehospital_forms WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)" . $user_clause, $stat_params);
$week_count = (int)($week_stmt ? $week_stmt->fetch()['c'] : 0);

// Active filters count
$active_filters = (int)!empty($status_filter)
    + (int)!empty($date_from)
    + (int)!empty($date_to)
    + (int)($month_filter > 0)
    + (int)($year_filter > 0)
    + (int)!empty($emergency_filter)
    + (int)!empty($vehicle_filter);

// Completion rate
$completion_rate = $total_records > 0 ? round(($completed_count / $total_records) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Care Records - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/records-style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- Page Header -->
            <div class="page-header-inline">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon"><i class="bi bi-file-earmark-medical"></i></span>
                        Patient Care Records
                    </h1>
                    <p class="page-subtitle">
                        <?php if (is_admin()): ?>
                            Viewing <strong>all records</strong> across users &middot; <strong><?php echo number_format($total_records); ?></strong> total
                        <?php else: ?>
                            Your personal pre-hospital care records &middot; <strong><?php echo number_format($total_records); ?></strong> total
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="reports.php" class="btn-ghost">
                        <i class="bi bi-bar-chart"></i> Reports
                    </a>
                    <a href="prehospital_form.php" class="btn-primary">
                        <i class="bi bi-plus-lg"></i> New Record
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card accent-indigo">
                    <div class="stat-card-top">
                        <div class="stat-icon indigo"><i class="bi bi-folder-fill"></i></div>
                    </div>
                    <div class="stat-label"><?php echo is_admin() ? 'Total Records' : 'My Records'; ?></div>
                    <div class="stat-value"><?php echo number_format($total_records); ?></div>
                    <div class="stat-sub">All time</div>
                </div>

                <div class="stat-card accent-emerald">
                    <div class="stat-card-top">
                        <div class="stat-icon emerald"><i class="bi bi-check-circle-fill"></i></div>
                        <span class="stat-trend up"><i class="bi bi-graph-up"></i> <?php echo $completion_rate; ?>%</span>
                    </div>
                    <div class="stat-label">Completed</div>
                    <div class="stat-value"><?php echo number_format($completed_count); ?></div>
                    <div class="stat-sub">Completion rate</div>
                </div>

                <div class="stat-card accent-amber">
                    <div class="stat-card-top">
                        <div class="stat-icon amber"><i class="bi bi-calendar-day-fill"></i></div>
                    </div>
                    <div class="stat-label">Today</div>
                    <div class="stat-value"><?php echo number_format($today_count); ?></div>
                    <div class="stat-sub">Forms created</div>
                </div>

                <div class="stat-card accent-purple">
                    <div class="stat-card-top">
                        <div class="stat-icon purple"><i class="bi bi-calendar-week-fill"></i></div>
                    </div>
                    <div class="stat-label">This Week</div>
                    <div class="stat-value"><?php echo number_format($week_count); ?></div>
                    <div class="stat-sub">Forms created</div>
                </div>
            </div>

            <!-- Toolbar: Search + Filter + Actions -->
            <div class="toolbar">
                <div class="toolbar-search">
                    <i class="bi bi-search toolbar-search-icon"></i>
                    <input type="text" id="searchInput" name="search" form="filtersForm"
                           placeholder="Search by form #, patient name, or place of incident…"
                           value="<?php echo htmlspecialchars($search ?? ''); ?>" autocomplete="off">
                    <?php if (!empty($search)): ?>
                        <a href="?<?php echo http_build_query(array_filter(['status'=>$status_filter,'date_from'=>$date_from,'date_to'=>$date_to,'month'=>$month_filter?:'','year'=>$year_filter?:'','emergency'=>$emergency_filter,'vehicle'=>$vehicle_filter,'sort'=>$sort_by,'per_page'=>$per_page])); ?>" class="search-clear" title="Clear search">&times;</a>
                    <?php endif; ?>
                </div>
                <div class="toolbar-actions">
                    <button type="button" class="btn-ghost btn-sm" id="btnToggleFilters" aria-expanded="<?php echo $active_filters > 0 ? 'true' : 'false'; ?>">
                        <i class="bi bi-sliders"></i> Filters
                        <?php if ($active_filters > 0): ?>
                            <span class="filter-count-pill"><?php echo $active_filters; ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" id="btnExportCSV" class="btn-ghost btn-sm">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <div class="column-toggle-wrapper">
                        <button type="button" class="btn-ghost btn-sm" id="btnColumnToggle">
                            <i class="bi bi-layout-three-columns"></i> Columns
                        </button>
                        <div class="column-toggle-menu" id="columnToggleMenu">
                            <label class="col-toggle-item"><input type="checkbox" data-column="form-number" checked> Form #</label>
                            <label class="col-toggle-item"><input type="checkbox" data-column="date" checked> Date</label>
                            <label class="col-toggle-item"><input type="checkbox" data-column="patient" checked> Patient</label>
                            <label class="col-toggle-item"><input type="checkbox" data-column="age-gender" checked> Age/Gender</label>
                            <label class="col-toggle-item"><input type="checkbox" data-column="vehicle" checked> Vehicle</label>
                            <label class="col-toggle-item"><input type="checkbox" data-column="status" checked> Status</label>
                            <label class="col-toggle-item"><input type="checkbox" data-column="modified" checked> Last Modified</label>
                        </div>
                    </div>
                    <button type="button" id="btnPrint" class="btn-ghost btn-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>

            <!-- Batch Action Toolbar (hidden by default, shown when items selected) -->
            <div class="batch-toolbar" id="batchToolbar" style="display:none;">
                <span class="batch-selected-count"><span id="batchCount">0</span> selected</span>
                <div class="batch-actions">
                    <button type="button" class="btn-sm btn-ghost" id="btnSelectAll" data-select="all">
                        <i class="bi bi-check-all"></i> Select All
                    </button>
                    <button type="button" class="btn-sm btn-danger-ghost" id="btnBatchDelete">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                    <button type="button" class="btn-sm btn-ghost" id="btnBatchComplete">
                        <i class="bi bi-check-circle"></i> Mark Completed
                    </button>
                    <button type="button" class="btn-sm btn-ghost" id="btnBatchExport">
                        <i class="bi bi-download"></i> Export Selected
                    </button>
                    <button type="button" class="btn-sm btn-ghost" id="btnClearSelection">
                        <i class="bi bi-x"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Collapsible Filters Panel -->
            <div class="filters-panel <?php echo $active_filters > 0 ? 'is-open' : ''; ?>" id="filtersPanel">
                <form method="GET" action="" id="filtersForm" class="filters-grid">
                    <div class="filter-field">
                        <label>Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>Month</label>
                        <select class="form-select" name="month">
                            <option value="">All Months</option>
                            <?php
                            $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                            foreach ($months as $i => $m): ?>
                                <option value="<?php echo $i + 1; ?>" <?php echo $month_filter === ($i + 1) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>Year</label>
                        <select class="form-select" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($available_years as $yr): ?>
                                <option value="<?php echo $yr; ?>" <?php echo $year_filter === (int)$yr ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                    </div>
                    <div class="filter-field">
                        <label>Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                    </div>
                    <div class="filter-field">
                        <label>Emergency Type</label>
                        <select class="form-select" name="emergency">
                            <option value="">All Types</option>
                            <option value="medical" <?php echo $emergency_filter === 'medical' ? 'selected' : ''; ?>>Medical</option>
                            <option value="trauma" <?php echo $emergency_filter === 'trauma' ? 'selected' : ''; ?>>Trauma</option>
                            <option value="ob" <?php echo $emergency_filter === 'ob' ? 'selected' : ''; ?>>OB</option>
                            <option value="general" <?php echo $emergency_filter === 'general' ? 'selected' : ''; ?>>General</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>Vehicle Used</label>
                        <select class="form-select" name="vehicle">
                            <option value="">All Vehicles</option>
                            <option value="ambulance" <?php echo $vehicle_filter === 'ambulance' ? 'selected' : ''; ?>>Ambulance</option>
                            <option value="fireTruck" <?php echo $vehicle_filter === 'fireTruck' ? 'selected' : ''; ?>>Fire Truck</option>
                            <option value="others" <?php echo $vehicle_filter === 'others' ? 'selected' : ''; ?>>Others</option>
                        </select>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn-primary btn-sm">
                            <i class="bi bi-funnel-fill"></i> Apply Filters
                        </button>
                        <a href="records.php" class="btn-ghost btn-sm">
                            <i class="bi bi-x-lg"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>

            <!-- Result Count + Sort + Per Page -->
            <div class="result-bar">
                <span class="result-count-text">
                    <strong><?php echo number_format($total_records); ?></strong>
                    record<?php echo $total_records != 1 ? 's' : ''; ?>
                    <?php if ($active_filters > 0 || !empty($search)): ?>
                        <span class="result-count-filtered">&middot; filtered</span>
                    <?php endif; ?>
                </span>
                <div class="result-bar-right">
                    <label class="per-page-label">Show</label>
                    <select id="perPageSelect" form="filtersForm" name="per_page" class="quick-sort">
                        <?php foreach ($per_page_options as $ppo): ?>
                            <option value="<?php echo $ppo; ?>" <?php echo $per_page === $ppo ? 'selected' : ''; ?>><?php echo $ppo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="per-page-label" style="margin-left:12px;">Sort</label>
                    <select id="sortQuick" form="filtersForm" name="sort" class="quick-sort">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
                        <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Form date (newest)</option>
                        <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Form date (oldest)</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Patient (A–Z)</option>
                        <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Patient (Z–A)</option>
                    </select>
                </div>
            </div>

            <!-- Records Table -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table records-table mb-0">
                        <thead>
                            <tr>
                                <th class="col-check"><input type="checkbox" id="selectAllCheckbox" title="Select all"></th>
                                <th>Form #</th>
                                <th>Date</th>
                                <th>Patient</th>
                                <th class="col-age-gender">Age / Gender</th>
                                <th class="col-vehicle">Vehicle</th>
                                <th>Status</th>
                                <th class="col-modified">Last Modified</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                                            <div class="empty-state-title">No records found</div>
                                            <div class="empty-state-description">
                                                <?php echo !empty($search) || $active_filters > 0 ? 'Try adjusting your search or filters.' : 'Get started by creating your first pre-hospital care form.'; ?>
                                            </div>
                                            <?php if (empty($search) && $active_filters === 0): ?>
                                                <a href="prehospital_form.php" class="btn-primary btn-sm mt-3">
                                                    <i class="bi bi-plus-lg me-2"></i>Create First Record
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                foreach ($records as $index => $record):
                                    decrypt_record_fields($record);
                                    $status_class = ['completed'=>'completed','draft'=>'draft','archived'=>'archived'][$record['status']] ?? 'draft';
                                ?>
                                    <tr class="record-row" data-record-id="<?php echo (int)$record['id']; ?>">
                                        <td class="col-check" data-label="Select">
                                            <input type="checkbox" class="record-checkbox" value="<?php echo (int)$record['id']; ?>">
                                        </td>
                                        <td data-label="Form #">
                                            <a href="javascript:void(0)" data-view-record="<?php echo (int)$record['id']; ?>" class="form-number-link">
                                                <strong><?php echo e($record['form_number']); ?></strong>
                                            </a>
                                        </td>
                                        <td data-label="Date">
                                            <?php echo ($record['form_date'] && $record['form_date'] !== '0000-00-00') ? date('M d, Y', strtotime($record['form_date'])) : '<span style="color:var(--gray-400);">—</span>'; ?>
                                        </td>
                        <td data-label="Patient">
                            <a href="javascript:void(0)" data-view-record="<?php echo (int)$record['id']; ?>" class="patient-link">
                                <?php echo e($record['patient_name'] ?: '—'); ?>
                            </a>
                                        </td>
                                        <td class="col-age-gender" data-label="Age/Gender">
                                            <?php echo e($record['age'] ?: '—'); ?> &middot;
                                            <?php echo $record['gender'] ? ucfirst((string)$record['gender']) : '—'; ?>
                                        </td>
                                        <td class="col-vehicle" data-label="Vehicle">
                                            <?php if ($record['vehicle_used']): ?>
                                                <span class="badge-vehicle">
                                                    <i class="bi bi-truck"></i> <?php echo ucfirst(e($record['vehicle_used'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--gray-400);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge-status-pill <?php echo $status_class; ?>">
                                                <?php if ($status_class === 'completed'): ?><i class="bi bi-check-circle-fill"></i><?php endif; ?>
                                                <?php if ($status_class === 'draft'): ?><i class="bi bi-pencil-fill"></i><?php endif; ?>
                                                <?php echo ucfirst(e($record['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="col-modified" data-label="Last Modified">
                                            <?php echo time_ago($record['updated_at'] ?? $record['created_at']); ?>
                                        </td>
                                        <td class="col-actions" data-label="Actions">
                                            <div class="dropdown action-dropdown">
                                                <button class="btn-view-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions <i class="bi bi-chevron-down ms-1" style="font-size:0.625rem;"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <?php if ($record['status'] === 'draft'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="prehospital_form.php?draft_id=<?php echo (int)$record['id']; ?>">
                                                            <i class="bi bi-play-fill"></i> Resume
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)" data-view-record="<?php echo (int)$record['id']; ?>">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="edit_record.php?id=<?php echo (int)$record['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                    </li>
                                                    <?php if ($record['status'] === 'draft'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)" data-mark-completed="<?php echo (int)$record['id']; ?>">
                                                            <i class="bi bi-check-circle"></i> Mark Completed
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item dropdown-item-danger" href="javascript:void(0)" data-delete-record="<?php echo (int)$record['id']; ?>">
                                                            <i class="bi bi-trash"></i> Delete
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
            </div>

            <!-- Pagination -->
            <?php
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
                'per_page' => $per_page !== 20 ? $per_page : '',
            ]));
            $showing_from = $total_records > 0 ? $offset + 1 : 0;
            $showing_to = min($offset + $per_page, $total_records);
            ?>
            <?php if ($total_pages > 1 || $total_records > 0): ?>
                <div class="pagination-bar">
                    <div class="pagination-info">
                        <span class="pagination-info-text">
                            Showing <strong><?php echo number_format($showing_from); ?></strong>–<strong><?php echo number_format($showing_to); ?></strong> of <strong><?php echo number_format($total_records); ?></strong> records
                        </span>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination-modern">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link page-link-icon" href="?page=1&<?php echo $filter_params; ?>" aria-label="First page" title="First page">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link page-link-icon" href="?page=<?php echo $page - 1; ?>&<?php echo $filter_params; ?>" aria-label="Previous" title="Previous page">
                                    <i class="bi bi-chevron-left"></i>
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
                                    <li class="page-item disabled page-ellipsis"><span class="page-link">…</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link page-link-icon" href="?page=<?php echo $page + 1; ?>&<?php echo $filter_params; ?>" aria-label="Next" title="Next page">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link page-link-icon" href="?page=<?php echo $total_pages; ?>&<?php echo $filter_params; ?>" aria-label="Last page" title="Last page">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="pagination-jump">
                        <span class="pagination-jump-label">Go to</span>
                        <form method="GET" action="" class="pagination-jump-form" onsubmit="var p=parseInt(this.elements.page.value,10);if(p<1||p><?php echo $total_pages; ?>||isNaN(p)){this.elements.page.value='';return false;}return true;">
                            <?php
                            $jump_params = array_filter([
                                'search' => $search,
                                'status' => $status_filter,
                                'date_from' => $date_from,
                                'date_to' => $date_to,
                                'month' => $month_filter ?: '',
                                'year' => $year_filter ?: '',
                                'emergency' => $emergency_filter,
                                'vehicle' => $vehicle_filter,
                                'sort' => $sort_by !== 'newest' ? $sort_by : '',
                                'per_page' => $per_page !== 20 ? $per_page : '',
                            ]);
                            foreach ($jump_params as $key => $val):
                                if ($val !== ''): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars((string)$val); ?>">
                                <?php endif;
                            endforeach; ?>
                            <input type="number" name="page" class="pagination-jump-input"
                                   min="1" max="<?php echo $total_pages; ?>"
                                   placeholder="<?php echo $page; ?>"
                                   aria-label="Go to page number">
                            <span class="pagination-jump-total">/ <?php echo $total_pages; ?></span>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Top -->
    <button class="back-to-top" id="backToTop" title="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <!-- View Record Modal -->
    <div class="modal fade" id="viewRecordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-medical me-2"></i>View Record</h5>
                    <div class="modal-header-actions">
                        <button type="button" class="btn-ghost btn-sm" id="viewFullDetailsBtn">
                            <i class="bi bi-arrows-fullscreen"></i> Full Details
                        </button>
                        <button type="button" class="btn-primary btn-sm" id="editRecordBtn">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body" id="modalRecordContent">
                    <div class="text-center py-5">
                        <div class="spinner-border" role="status" style="color:var(--primary);">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3" style="color:var(--gray-500);">Loading record details...</p>
                    </div>
                </div>
                <div class="modal-footer modal-footer-record">
                    <span class="modal-footer-hint"><i class="bi bi-info-circle"></i> Use the tabs to navigate sections</span>
                    <button type="button" class="btn-ghost btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" style="color:var(--danger);">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Record
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteConfirmMessage" style="color:var(--gray-700);">Are you sure you want to delete this record? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-ghost btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-danger btn-sm" id="confirmDeleteBtn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CSRF token for JS -->
    <input type="hidden" id="csrfToken" value="<?php echo generate_token(); ?>">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/records.js?v=<?php echo time(); ?>"></script>
</body>
</html>