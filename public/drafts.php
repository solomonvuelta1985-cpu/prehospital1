<?php
/**
 * My Drafts - Quick access to resume draft forms
 * Modern SaaS / Corporate Design — matching records/dashboard aesthetic
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
$user_id = $current_user['id'];

// Pagination settings
$per_page_options = [20, 50, 100];
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, array_merge($per_page_options, [10]))) $per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? (sanitize($_GET['search']) ?? '') : '';
$sort_by = isset($_GET['sort']) ? (sanitize($_GET['sort']) ?? 'newest') : 'newest';

// Validate sort option
$allowed_sorts = [
    'newest' => 'pf.updated_at DESC',
    'oldest' => 'pf.updated_at ASC',
    'name_asc' => 'pf.patient_name ASC',
    'name_desc' => 'pf.patient_name DESC',
];
$order_sql = $allowed_sorts[$sort_by] ?? 'pf.updated_at DESC';

// Build query
$where_conditions = ["pf.created_by = ?", "pf.status = 'draft'"];
$params = [$user_id];

if (!empty($search)) {
    $where_conditions[] = "(pf.form_number LIKE ? OR pf.patient_name LIKE ? OR pf.place_of_incident LIKE ? OR pf.arrival_hospital_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = "WHERE " . implode(" AND ", $where_conditions);

// Get total count of drafts
$count_sql = "SELECT COUNT(*) as total FROM prehospital_forms pf $where_sql";
$count_stmt = db_query($count_sql, $params);
$total_records = ($count_stmt) ? (int)$count_stmt->fetch()['total'] : 0;
$total_pages = max(1, ceil($total_records / max($per_page, 1)));

// Get all drafts for current user with pagination
$sql = "SELECT
    pf.id,
    pf.form_number,
    pf.form_date,
    pf.patient_name,
    pf.age,
    pf.gender,
    pf.place_of_incident,
    pf.arrival_hospital_name,
    pf.vehicle_used,
    pf.status,
    pf.created_at,
    pf.updated_at
FROM prehospital_forms pf
$where_sql
ORDER BY $order_sql
LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = db_query($sql, $params);
$drafts = $stmt ? $stmt->fetchAll() : [];

// Today's drafts count
$today_sql = "SELECT COUNT(*) as total FROM prehospital_forms WHERE created_by = ? AND status = 'draft' AND DATE(updated_at) = CURDATE()";
$today_stmt = db_query($today_sql, [$user_id]);
$today_count = (int)($today_stmt ? $today_stmt->fetch()['total'] : 0);

// This week's drafts
$week_sql = "SELECT COUNT(*) as total FROM prehospital_forms WHERE created_by = ? AND status = 'draft' AND YEARWEEK(updated_at, 1) = YEARWEEK(CURDATE(), 1)";
$week_stmt = db_query($week_sql, [$user_id]);
$week_count = (int)($week_stmt ? $week_stmt->fetch()['total'] : 0);

// Active filters count
$active_filters = (int)!empty($search);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Drafts - Pre-Hospital Care System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/records-style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="css/drafts-redesign.css?v=<?php echo time(); ?>&ui=clinical-v1" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid drafts-page-shell">
            <?php show_flash(); ?>

            <!-- ===== CLINICAL WORKFLOW BANNER ===== -->
            <section class="drafts-workflow-banner" aria-labelledby="draftsWorkflowTitle">
                <div class="drafts-workflow-copy">
                    <span class="drafts-workflow-eyebrow">FIELD DOCUMENTATION / RESQ-LINK EMS</span>
                    <h2 id="draftsWorkflowTitle">Draft Forms</h2>
                    <p>Continue an in-progress response record.</p>
                </div>
                <div class="drafts-workflow-actions" aria-label="Draft actions">
                    <a href="records.php" class="btn-ghost">
                        <i class="bi bi-table"></i> Records
                    </a>
                    <a href="prehospital_form.php" class="btn-primary">
                        <i class="bi bi-plus-lg"></i> New Form
                    </a>
                </div>
            </section>

            <!-- ===== STATISTICS CARDS ===== -->
            <div class="stats-grid mb-4">
                <div class="stat-card accent-indigo">
                    <div class="stat-card-top">
                        <div class="stat-icon indigo"><i class="bi bi-file-earmark-text"></i></div>
                    </div>
                    <div class="stat-label">Total Drafts</div>
                    <div class="stat-value"><?php echo number_format($total_records); ?></div>
                    <div class="stat-sub">All time</div>
                </div>

                <div class="stat-card accent-emerald">
                    <div class="stat-card-top">
                        <div class="stat-icon emerald"><i class="bi bi-calendar-check"></i></div>
                    </div>
                    <div class="stat-label">Updated Today</div>
                    <div class="stat-value"><?php echo number_format($today_count); ?></div>
                    <div class="stat-sub">Drafts modified today</div>
                </div>

                <div class="stat-card accent-amber">
                    <div class="stat-card-top">
                        <div class="stat-icon amber"><i class="bi bi-calendar-week"></i></div>
                    </div>
                    <div class="stat-label">This Week</div>
                    <div class="stat-value"><?php echo number_format($week_count); ?></div>
                    <div class="stat-sub">Drafts modified this week</div>
                </div>

                <div class="stat-card accent-purple">
                    <div class="stat-card-top">
                        <div class="stat-icon purple"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <div class="stat-label">Completion Rate</div>
                    <div class="stat-value">—</div>
                    <div class="stat-sub">Resume & complete your drafts</div>
                </div>
            </div>

            <!-- ===== TOOLBAR: Search + Actions ===== -->
            <div class="toolbar">
                <div class="toolbar-search">
                    <i class="bi bi-search toolbar-search-icon"></i>
                    <input type="text" id="searchInput" name="search" form="filtersForm"
                           placeholder="Search drafts by form #, patient name, or location…"
                           value="<?php echo htmlspecialchars($search ?? ''); ?>" autocomplete="off">
                    <?php if (!empty($search)): ?>
                        <a href="?<?php echo http_build_query(array_filter(['sort' => $sort_by !== 'newest' ? $sort_by : '', 'per_page' => $per_page !== 10 ? $per_page : ''])); ?>" class="search-clear" title="Clear search">&times;</a>
                    <?php endif; ?>
                </div>
                <div class="toolbar-actions">
                    <button type="button" class="btn-ghost btn-sm" id="btnToggleFilters" aria-expanded="<?php echo $active_filters > 0 ? 'true' : 'false'; ?>">
                        <i class="bi bi-sliders"></i> Filters
                        <?php if ($active_filters > 0): ?>
                            <span class="filter-count-pill"><?php echo $active_filters; ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" id="btnPrint" class="btn-ghost btn-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>

            <!-- ===== BATCH ACTION TOOLBAR ===== -->
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
                    <button type="button" class="btn-sm btn-ghost" id="btnClearSelection">
                        <i class="bi bi-x"></i> Clear
                    </button>
                </div>
            </div>

            <!-- ===== COLLAPSIBLE FILTERS PANEL ===== -->
            <div class="filters-panel <?php echo $active_filters > 0 ? 'is-open' : ''; ?>" id="filtersPanel">
                <form method="GET" action="" id="filtersForm" class="filters-grid">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <div class="filter-field">
                        <label>Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Last Updated (Newest)</option>
                            <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Last Updated (Oldest)</option>
                            <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Patient (A–Z)</option>
                            <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Patient (Z–A)</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>Show Per Page</label>
                        <select class="form-select" name="per_page">
                            <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $per_page === 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <div class="filters-actions">
                        <button type="submit" class="btn-primary btn-sm">
                            <i class="bi bi-funnel-fill"></i> Apply
                        </button>
                        <a href="drafts.php" class="btn-ghost btn-sm">
                            <i class="bi bi-x-lg"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>

            <!-- ===== RESULT BAR ===== -->
            <div class="result-bar">
                <span class="result-count-text">
                    <strong><?php echo number_format($total_records); ?></strong>
                    draft<?php echo $total_records != 1 ? 's' : ''; ?>
                    <?php if (!empty($search)): ?>
                        <span class="result-count-filtered">&middot; filtered</span>
                    <?php endif; ?>
                </span>
                <div class="result-bar-right">
                    <label class="per-page-label">Show</label>
                    <select id="perPageSelect" form="filtersForm" name="per_page" class="quick-sort">
                        <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $per_page === 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <label class="per-page-label" style="margin-left:12px;">Sort</label>
                    <select id="sortQuick" form="filtersForm" name="sort" class="quick-sort">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
                        <option value="name_asc" <?php echo $sort_by === 'name_asc' ? 'selected' : ''; ?>>Patient (A–Z)</option>
                        <option value="name_desc" <?php echo $sort_by === 'name_desc' ? 'selected' : ''; ?>>Patient (Z–A)</option>
                    </select>
                </div>
            </div>

            <!-- ===== DRAFTS TABLE ===== -->
            <?php if (empty($drafts)): ?>
                <div class="table-card">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-file-earmark-text"></i></div>
                        <div class="empty-state-title">
                            <?php echo !empty($search) ? 'No matching drafts' : 'No Drafts Yet'; ?>
                        </div>
                        <div class="empty-state-description">
                            <?php if (!empty($search)): ?>
                                No drafts match your search. Try adjusting your search terms.
                            <?php else: ?>
                                You don't have any saved drafts. Start a new form and it will be automatically saved as you work.
                            <?php endif; ?>
                        </div>
                        <?php if (empty($search)): ?>
                            <a href="prehospital_form.php" class="btn-primary btn-sm mt-3">
                                <i class="bi bi-plus-lg me-2"></i>Create Your First Form
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
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
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th class="col-modified">Last Saved</th>
                                    <th class="col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($drafts as $index => $draft): ?>
                                    <?php
                                    // Calculate draft age
                                    $draft_age_days = (time() - strtotime($draft['updated_at'])) / 86400;
                                    $age_class = $draft_age_days <= 1 ? 'fresh' : ($draft_age_days <= 7 ? 'aging' : 'old');
                                    $age_label = $draft_age_days <= 1 ? 'Recent' : ($draft_age_days <= 7 ? 'This week' : ($draft_age_days <= 30 ? round($draft_age_days) . 'd ago' : 'Older'));
                                    ?>
                                    <tr class="record-row" data-record-id="<?php echo (int)$draft['id']; ?>">
                                        <td class="col-check" data-label="Select">
                                            <input type="checkbox" class="record-checkbox" value="<?php echo (int)$draft['id']; ?>">
                                        </td>
                                        <td data-label="Form #">
                                            <a href="prehospital_form.php?draft_id=<?php echo (int)$draft['id']; ?>" class="form-number-link">
                                                <strong><?php echo e($draft['form_number']); ?></strong>
                                            </a>
                                        </td>
                                        <td data-label="Date">
                                            <?php echo ($draft['form_date'] && $draft['form_date'] !== '0000-00-00') ? date('M d, Y', strtotime($draft['form_date'])) : '<span style="color:var(--gray-400);">—</span>'; ?>
                                        </td>
                                        <td data-label="Patient">
                                            <a href="prehospital_form.php?draft_id=<?php echo (int)$draft['id']; ?>" class="patient-link">
                                                <?php echo e($draft['patient_name'] ?: '—'); ?>
                                            </a>
                                        </td>
                                        <td class="col-age-gender" data-label="Age/Gender">
                                            <?php echo e($draft['age'] ?: '—'); ?> &middot;
                                            <?php echo $draft['gender'] ? ucfirst((string)$draft['gender']) : '—'; ?>
                                        </td>
                                        <td data-label="Location">
                                            <?php if ($draft['place_of_incident'] || $draft['arrival_hospital_name']): ?>
                                                <div style="font-size:0.8125rem;">
                                                    <?php if ($draft['place_of_incident']): ?>
                                                        <div><?php echo e($draft['place_of_incident']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($draft['arrival_hospital_name']): ?>
                                                        <div style="color:var(--gray-500);font-size:0.75rem;">
                                                            <i class="bi bi-hospital" style="font-size:0.65rem;"></i> <?php echo e($draft['arrival_hospital_name']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:var(--gray-400);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="draft-age-badge <?php echo $age_class; ?>">
                                                <i class="bi bi-<?php echo $age_class === 'fresh' ? 'clock' : ($age_class === 'aging' ? 'clock-history' : 'exclamation-circle'); ?>"></i>
                                                <?php echo $age_label; ?>
                                            </span>
                                            <span class="badge-status-pill draft" style="margin-left:0.25rem;">
                                                <i class="bi bi-pencil-fill"></i> Draft
                                            </span>
                                        </td>
                                        <td class="col-modified" data-label="Last Saved">
                                            <?php echo time_ago($draft['updated_at']); ?>
                                        </td>
                                        <td class="col-actions" data-label="Actions">
                                            <div class="dropdown action-dropdown">
                                                <button class="btn-view-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Actions <i class="bi bi-chevron-down ms-1" style="font-size:0.625rem;"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item action-resume" href="prehospital_form.php?draft_id=<?php echo (int)$draft['id']; ?>">
                                                            <i class="bi bi-play-fill"></i> Resume
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item action-view" href="javascript:void(0)" data-view-record="<?php echo (int)$draft['id']; ?>">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item action-edit" href="edit_record.php?id=<?php echo (int)$draft['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item action-complete" href="javascript:void(0)" data-mark-completed="<?php echo (int)$draft['id']; ?>">
                                                            <i class="bi bi-check-circle"></i> Mark Completed
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item dropdown-item-danger" href="javascript:void(0)" data-delete-record="<?php echo (int)$draft['id']; ?>">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ===== PAGINATION ===== -->
                <?php
                $filter_params = http_build_query(array_filter([
                    'search' => $search,
                    'sort' => $sort_by !== 'newest' ? $sort_by : '',
                    'per_page' => $per_page !== 10 ? $per_page : '',
                ]));
                ?>
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination-nav" aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&<?php echo $filter_params; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || abs($i - $current_page) <= 2): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $filter_params; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif (abs($i - $current_page) == 3): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&<?php echo $filter_params; ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== BACK TO TOP ===== -->
    <button class="back-to-top" id="backToTop" title="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <!-- ===== VIEW RECORD MODAL ===== -->
    <div class="modal fade" id="viewRecordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-medical me-2"></i>View Draft</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalRecordContent">
                    <div class="text-center py-5">
                        <div class="spinner-border" role="status" style="color:var(--primary);">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3" style="color:var(--gray-500);">Loading draft details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Close
                    </button>
                    <button type="button" class="btn-primary btn-sm" id="resumeDraftBtn">
                        <i class="bi bi-play-fill"></i> Resume Editing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== DELETE CONFIRMATION MODAL ===== -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" style="color:var(--danger);">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Draft
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteConfirmMessage" style="color:var(--gray-700);">Are you sure you want to delete this draft? This action cannot be undone.</p>
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

    <!-- ===== BATCH DELETE CONFIRMATION MODAL ===== -->
    <div class="modal fade" id="batchDeleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" style="color:var(--danger);">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Drafts
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="batchDeleteConfirmMessage" style="color:var(--gray-700);">Are you sure you want to delete the selected drafts? This action cannot be undone.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-ghost btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-danger btn-sm" id="confirmBatchDeleteBtn">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== CSRF TOKEN ===== -->
    <input type="hidden" id="csrfToken" value="<?php echo generate_token(); ?>">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/action-menu-mobile.js?v=<?php echo time(); ?>"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
    (function() {
        'use strict';

        // ===== BACK TO TOP =====
        var backToTop = document.getElementById('backToTop');
        if (backToTop) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 400) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            });
            backToTop.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // ===== FILTERS TOGGLE =====
        var btnToggleFilters = document.getElementById('btnToggleFilters');
        var filtersPanel = document.getElementById('filtersPanel');
        if (btnToggleFilters && filtersPanel) {
            btnToggleFilters.addEventListener('click', function() {
                var isOpen = filtersPanel.classList.toggle('is-open');
                btnToggleFilters.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        // ===== AUTO-SUBMIT SORT/PER-PAGE =====
        var sortQuick = document.getElementById('sortQuick');
        var perPageSelect = document.getElementById('perPageSelect');
        var filtersForm = document.getElementById('filtersForm');
        if (sortQuick && filtersForm) {
            sortQuick.addEventListener('change', function() { filtersForm.submit(); });
        }
        if (perPageSelect && filtersForm) {
            perPageSelect.addEventListener('change', function() { filtersForm.submit(); });
        }

        // ===== SELECT ALL CHECKBOX =====
        var selectAllCheckbox = document.getElementById('selectAllCheckbox');
        var batchToolbar = document.getElementById('batchToolbar');
        var batchCount = document.getElementById('batchCount');
        var recordCheckboxes = [];

        function updateBatchUI() {
            recordCheckboxes = document.querySelectorAll('.record-checkbox:checked');
            var count = recordCheckboxes.length;
            if (batchCount) batchCount.textContent = count;
            if (batchToolbar) {
                batchToolbar.style.display = count > 0 ? 'flex' : 'none';
            }
            if (selectAllCheckbox) {
                var allBoxes = document.querySelectorAll('.record-checkbox');
                selectAllCheckbox.checked = allBoxes.length > 0 && count === allBoxes.length;
            }
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.record-checkbox').forEach(function(cb) {
                    cb.checked = selectAllCheckbox.checked;
                });
                updateBatchUI();
            });
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('record-checkbox')) {
                updateBatchUI();
            }
        });

        // ===== BATCH: CLEAR SELECTION =====
        var btnClearSelection = document.getElementById('btnClearSelection');
        if (btnClearSelection) {
            btnClearSelection.addEventListener('click', function() {
                document.querySelectorAll('.record-checkbox').forEach(function(cb) { cb.checked = false; });
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                updateBatchUI();
            });
        }

        // ===== BATCH: SELECT ALL (PAGE) =====
        var btnSelectAll = document.getElementById('btnSelectAll');
        if (btnSelectAll) {
            btnSelectAll.addEventListener('click', function() {
                document.querySelectorAll('.record-checkbox').forEach(function(cb) { cb.checked = true; });
                updateBatchUI();
            });
        }

        // ===== BATCH: DELETE SELECTED =====
        var batchDeleteModal = document.getElementById('batchDeleteConfirmModal');
        var batchDeleteBsModal = batchDeleteModal ? new bootstrap.Modal(batchDeleteModal) : null;
        var confirmBatchDeleteBtn = document.getElementById('confirmBatchDeleteBtn');
        var btnBatchDelete = document.getElementById('btnBatchDelete');

        if (btnBatchDelete && batchDeleteBsModal) {
            btnBatchDelete.addEventListener('click', function() {
                var selectedIds = [];
                document.querySelectorAll('.record-checkbox:checked').forEach(function(cb) {
                    selectedIds.push(parseInt(cb.value));
                });
                if (selectedIds.length === 0) {
                    Notiflix.Notify.warning('No drafts selected.');
                    return;
                }
                document.getElementById('batchDeleteConfirmMessage').textContent =
                    'Are you sure you want to delete ' + selectedIds.length + ' selected draft' + (selectedIds.length > 1 ? 's' : '') + '? This action cannot be undone.';
                batchDeleteBsModal.show();
            });
        }

        if (confirmBatchDeleteBtn) {
            confirmBatchDeleteBtn.addEventListener('click', function() {
                var selectedIds = [];
                document.querySelectorAll('.record-checkbox:checked').forEach(function(cb) {
                    selectedIds.push(parseInt(cb.value));
                });
                if (selectedIds.length === 0) return;

                Notiflix.Loading.standard('Deleting drafts...');

                var promises = selectedIds.map(function(id) {
                    return fetch('../api/delete_record.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, csrf_token: document.getElementById('csrfToken').value })
                    }).then(function(r) { return r.json(); });
                });

                Promise.all(promises).then(function(results) {
                    Notiflix.Loading.remove();
                    var allSuccess = results.every(function(r) { return r.success; });
                    if (allSuccess) {
                        Notiflix.Notify.success('All selected drafts deleted successfully!', { timeout: 2000 });
                    } else {
                        Notiflix.Notify.warning('Some drafts could not be deleted.', { timeout: 3000 });
                    }
                    if (batchDeleteBsModal) batchDeleteBsModal.hide();
                    setTimeout(function() { location.reload(); }, 800);
                }).catch(function(err) {
                    Notiflix.Loading.remove();
                    Notiflix.Notify.failure('Error deleting drafts: ' + err.message, { timeout: 3000 });
                    console.error('Batch delete error:', err);
                });
            });
        }

        // ===== BATCH: MARK COMPLETED =====
        var btnBatchComplete = document.getElementById('btnBatchComplete');
        if (btnBatchComplete) {
            btnBatchComplete.addEventListener('click', function() {
                var selectedIds = [];
                document.querySelectorAll('.record-checkbox:checked').forEach(function(cb) {
                    selectedIds.push(parseInt(cb.value));
                });
                if (selectedIds.length === 0) {
                    Notiflix.Notify.warning('No drafts selected.');
                    return;
                }
                Notiflix.Confirm.show(
                    'Mark as Completed',
                    'Mark ' + selectedIds.length + ' draft' + (selectedIds.length > 1 ? 's' : '') + ' as completed?',
                    'Yes, Mark Completed',
                    'Cancel',
                    function() {
                        Notiflix.Loading.standard('Updating drafts...');
                        var promises = selectedIds.map(function(id) {
                            return fetch('../api/update_record.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: id, status: 'completed', csrf_token: document.getElementById('csrfToken').value })
                            }).then(function(r) { return r.json(); });
                        });
                        Promise.all(promises).then(function(results) {
                            Notiflix.Loading.remove();
                            var allSuccess = results.every(function(r) { return r.success; });
                            if (allSuccess) {
                                Notiflix.Notify.success('Drafts marked as completed!', { timeout: 2000 });
                            } else {
                                Notiflix.Notify.warning('Some drafts could not be updated.', { timeout: 3000 });
                            }
                            setTimeout(function() { location.reload(); }, 800);
                        }).catch(function(err) {
                            Notiflix.Loading.remove();
                            Notiflix.Notify.failure('Error: ' + err.message, { timeout: 3000 });
                        });
                    },
                    function() {},
                    { borderRadius: '8px', cssAnimationStyle: 'zoom' }
                );
            });
        }

        // ===== SINGLE DELETE (via dropdown) =====
        var deleteConfirmModal = document.getElementById('deleteConfirmModal');
        var deleteBsModal = deleteConfirmModal ? new bootstrap.Modal(deleteConfirmModal) : null;
        var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        var currentDeleteId = null;

        document.addEventListener('click', function(e) {
            var deleteBtn = e.target.closest('[data-delete-record]');
            if (deleteBtn) {
                currentDeleteId = parseInt(deleteBtn.getAttribute('data-delete-record'));
                if (deleteBsModal) {
                    document.getElementById('deleteConfirmMessage').textContent =
                        'Are you sure you want to delete this draft? This action cannot be undone.';
                    deleteBsModal.show();
                }
            }
        });

        if (confirmDeleteBtn && deleteBsModal) {
            confirmDeleteBtn.addEventListener('click', function() {
                if (!currentDeleteId) return;
                Notiflix.Loading.standard('Deleting draft...');
                fetch('../api/delete_record.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: currentDeleteId, csrf_token: document.getElementById('csrfToken').value })
                })
                .then(function(response) {
                    if (response.redirected || (response.url && response.url.includes('login.php'))) {
                        throw new Error('Session expired. Please login again.');
                    }
                    return response.json();
                })
                .then(function(data) {
                    Notiflix.Loading.remove();
                    if (data.success) {
                        Notiflix.Notify.success('Draft deleted successfully!', { timeout: 2000 });
                        deleteBsModal.hide();
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        Notiflix.Notify.failure('Error: ' + (data.message || 'Unknown error'), { timeout: 3000 });
                    }
                })
                .catch(function(err) {
                    Notiflix.Loading.remove();
                    if (err.message.includes('Session expired')) {
                        Notiflix.Notify.warning(err.message, { timeout: 3000 });
                        setTimeout(function() { window.location.href = 'login.php'; }, 2000);
                    } else {
                        Notiflix.Notify.failure('Error deleting draft: ' + err.message, { timeout: 3000 });
                    }
                    console.error('Delete error:', err);
                });
            });
        }

        // ===== MARK COMPLETED (single) =====
        document.addEventListener('click', function(e) {
            var completeBtn = e.target.closest('[data-mark-completed]');
            if (completeBtn) {
                var id = parseInt(completeBtn.getAttribute('data-mark-completed'));
                Notiflix.Confirm.show(
                    'Mark as Completed',
                    'Mark this draft as completed?',
                    'Yes',
                    'Cancel',
                    function() {
                        Notiflix.Loading.standard('Updating...');
                        fetch('../api/update_record.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: id, status: 'completed', csrf_token: document.getElementById('csrfToken').value })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            Notiflix.Loading.remove();
                            if (data.success) {
                                Notiflix.Notify.success('Draft marked as completed!', { timeout: 2000 });
                                setTimeout(function() { location.reload(); }, 600);
                            } else {
                                Notiflix.Notify.failure('Error: ' + (data.message || 'Unknown error'), { timeout: 3000 });
                            }
                        })
                        .catch(function(err) {
                            Notiflix.Loading.remove();
                            Notiflix.Notify.failure('Error: ' + err.message, { timeout: 3000 });
                        });
                    },
                    function() {},
                    { borderRadius: '8px', cssAnimationStyle: 'zoom' }
                );
            }
        });

        // ===== VIEW RECORD MODAL =====
        var viewRecordModal = document.getElementById('viewRecordModal');
        var viewBsModal = viewRecordModal ? new bootstrap.Modal(viewRecordModal) : null;
        var resumeDraftBtn = document.getElementById('resumeDraftBtn');
        var currentViewId = null;

        document.addEventListener('click', function(e) {
            var viewBtn = e.target.closest('[data-view-record]');
            if (viewBtn) {
                currentViewId = parseInt(viewBtn.getAttribute('data-view-record'));
                var modalContent = document.getElementById('modalRecordContent');
                if (modalContent) {
                    modalContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status" style="color:var(--primary);"><span class="visually-hidden">Loading...</span></div><p class="mt-3" style="color:var(--gray-500);">Loading draft details...</p></div>';
                }
                if (resumeDraftBtn) {
                    resumeDraftBtn.onclick = function() {
                        window.location.href = 'prehospital_form.php?draft_id=' + currentViewId;
                    };
                }
                if (viewBsModal) viewBsModal.show();

                // Fetch record details
                fetch('../api/get_record.php?id=' + currentViewId + '&csrf_token=' + document.getElementById('csrfToken').value)
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to load record');
                    return response.json();
                })
                .then(function(data) {
                    if (data.success && data.record && modalContent) {
                        var r = data.record;
                        var statusClass = 'draft';
                        var statusIcon = 'bi-pencil-fill';
                        var html = '<div class="modal-record-view">';
                        // Header
                        html += '<div class="mv-header">';
                        html += '<div class="mv-header-top">';
                        html += '<div><div class="mv-form-number">' + escapeHtml(r.form_number || '') + '</div>';
                        html += '<h3 class="mv-patient-name">' + escapeHtml(r.patient_name || 'Unnamed Patient') + '</h3></div>';
                        html += '<span class="modal-record-status ' + statusClass + '"><i class="bi ' + statusIcon + '"></i> Draft</span>';
                        html += '</div>';
                        html += '<div class="mv-header-meta">';
                        if (r.form_date && r.form_date !== '0000-00-00') {
                            html += '<span><i class="bi bi-calendar3"></i> ' + escapeHtml(r.form_date) + '</span>';
                        }
                        if (r.place_of_incident) {
                            html += '<span><i class="bi bi-geo-alt"></i> ' + escapeHtml(r.place_of_incident) + '</span>';
                        }
                        if (r.arrival_hospital_name) {
                            html += '<span><i class="bi bi-hospital"></i> ' + escapeHtml(r.arrival_hospital_name) + '</span>';
                        }
                        html += '</div></div>';

                        // Patient Info
                        html += '<div class="mv-section"><div class="mv-section-title"><i class="bi bi-person"></i> Patient Information</div>';
                        html += '<div class="mv-grid">';
                        html += '<div class="mv-item"><span class="mv-label">Age</span><span class="mv-value">' + (r.age ? escapeHtml(r.age) + ' yrs' : '<span class="mv-empty">—</span>') + '</span></div>';
                        html += '<div class="mv-item"><span class="mv-label">Gender</span><span class="mv-value">' + (r.gender ? escapeHtml(r.gender.charAt(0).toUpperCase() + r.gender.slice(1)) : '<span class="mv-empty">—</span>') + '</span></div>';
                        html += '<div class="mv-item mv-item--full"><span class="mv-label">Place of Incident</span><span class="mv-value">' + (r.place_of_incident ? escapeHtml(r.place_of_incident) : '<span class="mv-empty">—</span>') + '</span></div>';
                        html += '</div></div>';

                        // Emergency Info
                        var emergencies = [];
                        if (r.emergency_medical == 1) emergencies.push('Medical');
                        if (r.emergency_trauma == 1) emergencies.push('Trauma');
                        if (r.emergency_ob == 1) emergencies.push('OB');
                        if (r.emergency_general == 1) emergencies.push('General');
                        if (emergencies.length > 0) {
                            html += '<div class="mv-section"><div class="mv-section-title"><i class="bi bi-exclamation-triangle"></i> Emergency Type</div>';
                            html += '<div class="mv-chips">';
                            emergencies.forEach(function(em) {
                                html += '<span class="mv-chip mv-chip--emergency">' + em + '</span>';
                            });
                            html += '</div></div>';
                        }

                        // Timestamps
                        html += '<div class="mv-section"><div class="mv-section-title"><i class="bi bi-clock"></i> Activity</div>';
                        html += '<div class="mv-grid">';
                        html += '<div class="mv-item"><span class="mv-label">Created</span><span class="mv-value">' + (r.created_at || '—') + '</span></div>';
                        html += '<div class="mv-item"><span class="mv-label">Last Updated</span><span class="mv-value">' + (r.updated_at || '—') + '</span></div>';
                        html += '</div></div>';

                        html += '</div>';
                        modalContent.innerHTML = html;
                    } else {
                        if (modalContent) {
                            modalContent.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-exclamation-circle"></i></div><div class="empty-state-title">Unable to load draft</div><div class="empty-state-description">' + (data.message || 'Record not found') + '</div></div>';
                        }
                    }
                })
                .catch(function(err) {
                    console.error('View record error:', err);
                    if (modalContent) {
                        modalContent.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-exclamation-circle"></i></div><div class="empty-state-title">Error loading draft</div><div class="empty-state-description">' + err.message + '</div></div>';
                    }
                });
            }
        });

        // Helper: escape HTML
        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // ===== PRINT =====
        var btnPrint = document.getElementById('btnPrint');
        if (btnPrint) {
            btnPrint.addEventListener('click', function() {
                window.print();
            });
        }

        // ===== KEYBOARD SHORTCUT: Ctrl+K for search focus =====
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                var searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.focus();
            }
        });

    })();
    </script>
</body>
</html>
