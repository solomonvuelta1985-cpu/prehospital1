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

// Build query
$where_conditions = [];
$params = [];

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

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM prehospital_forms $where_sql";
$count_stmt = db_query($count_sql, $params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get records with creator information
$sql = "SELECT
    pf.id, pf.form_number, pf.form_date, pf.patient_name, pf.age, pf.gender,
    pf.place_of_incident, pf.vehicle_used, pf.status, pf.created_at,
    pf.arrival_hospital_name,
    u.full_name as created_by_name
    FROM prehospital_forms pf
    LEFT JOIN users u ON pf.created_by = u.id
    $where_sql
    ORDER BY pf.created_at DESC
    LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = db_query($sql, $params);
$records = $stmt->fetchAll();
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
            <h2>View and manage all saved forms | User: <?php echo e($current_user['full_name']); ?></h2>
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
                <p><i class="fas fa-file-alt"></i> Total Records</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?php
                    $completed_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE status = 'completed'";
                    $completed_stmt = db_query($completed_sql);
                    echo number_format($completed_stmt->fetch()['count']);
                    ?>
                </h3>
                <p><i class="fas fa-check-circle"></i> Completed</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?php
                    $today_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE DATE(created_at) = CURDATE()";
                    $today_stmt = db_query($today_sql);
                    echo number_format($today_stmt->fetch()['count']);
                    ?>
                </h3>
                <p><i class="fas fa-calendar-check"></i> Today</p>
            </div>
            <div class="stat-card">
                <h3>
                    <?php
                    $week_sql = "SELECT COUNT(*) as count FROM prehospital_forms WHERE YEARWEEK(created_at) = YEARWEEK(NOW())";
                    $week_stmt = db_query($week_sql);
                    echo number_format($week_stmt->fetch()['count']);
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
                <button onclick="exportToCSV()" class="btn-custom btn-success-custom">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn-custom btn-secondary-custom" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-card">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from"
                           value="<?php echo e($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to"
                           value="<?php echo e($date_to); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn-custom btn-primary-custom">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="records.php" class="btn-custom btn-secondary-custom">
                        <i class="fas fa-times-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="table-container" id="tableContainer">
            <table class="custom-table" id="recordsTable">
                <thead>
                    <tr>
                        <th>Form #</th>
                        <th>Date</th>
                        <th>Patient Name</th>
                        <th>Age/Gender</th>
                        <th>Incident Location</th>
                        <th>Hospital</th>
                        <th>Vehicle</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <!-- Skeleton Loader -->
                <tbody class="skeleton-loader" id="skeletonLoader">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <tr class="skeleton-row">
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
                            <td colspan="10" style="text-align: center; padding: 30px;">
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
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><strong><?php echo e($record['form_number']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($record['form_date'])); ?></td>
                                <td><?php echo e($record['patient_name']); ?></td>
                                <td>
                                    <?php echo e($record['age']); ?> /
                                    <?php echo ucfirst($record['gender']); ?>
                                </td>
                                <td><?php echo e($record['place_of_incident'] ?: '-'); ?></td>
                                <td><?php echo e($record['arrival_hospital_name'] ?: '-'); ?></td>
                                <td>
                                    <?php if ($record['vehicle_used']): ?>
                                        <span class="badge-custom status-pending"><?php echo ucfirst(e($record['vehicle_used'])); ?></span>
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
                                        <?php echo ucfirst(e($record['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['status'] === 'draft'): ?>
                                        <a href="prehospital_form.php?draft_id=<?php echo $record['id']; ?>"
                                           class="btn btn-table btn-success" title="Resume Draft" style="background: #28a745; color: white; border-color: #28a745;">
                                            <i class="fas fa-play"></i> Resume
                                        </a>
                                    <?php endif; ?>
                                    <a href="view_record.php?id=<?php echo $record['id']; ?>"
                                       class="btn btn-table btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_record.php?id=<?php echo $record['id']; ?>"
                                       class="btn btn-table btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteRecord(<?php echo $record['id']; ?>)"
                                            class="btn btn-table btn-delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" style="margin-top: clamp(20px, 4vw, 25px);">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                            Previous
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php elseif (abs($i - $page) == 3): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" onclick="scrollToTop()" title="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script>
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
                    body: JSON.stringify({ id: id })
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
        window.location.href = '../api/export_records.php?search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>';
    }
    </script>
</body>
</html>
