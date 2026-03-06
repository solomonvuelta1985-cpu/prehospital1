<?php
/**
 * My Drafts - Quick access to resume draft forms
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

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $records_per_page;

// Get total count of drafts
$count_sql = "SELECT COUNT(*) as total FROM prehospital_forms pf WHERE pf.created_by = ? AND pf.status = 'draft'";
$count_stmt = db_query($count_sql, [$user_id]);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $records_per_page);

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
    pf.created_at,
    pf.updated_at
FROM prehospital_forms pf
WHERE pf.created_by = ? AND pf.status = 'draft'
ORDER BY pf.updated_at DESC
LIMIT ? OFFSET ?";

$stmt = db_query($sql, [$user_id, $records_per_page, $offset]);
$drafts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Drafts - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3a5f;
            --accent-color: #2563eb;
            --accent-hover: #1d4ed8;
            --danger-color: #b91c1c;
            --danger-hover: #991b1b;
            --border-color: #d1d5db;
            --border-light: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #1f2937;
            --text-muted: #374151;
            --text-light: #4b5563;
            --bg-page: #f9fafb;
            --bg-card: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background-color: #f9fafb !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
            color: #111827 !important;
            line-height: 1.5 !important;
            -webkit-font-smoothing: antialiased;
        }

        /* Page Header */
        .page-header {
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid #2563eb;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .header-left {
            flex: 1;
        }

        .header-right {
            flex-shrink: 0;
            align-self: center;
        }

        .page-title {
            font-size: 1.375rem !important;
            font-weight: 600 !important;
            color: #111827 !important;
            margin: 0 !important;
        }

        .page-subtitle {
            font-size: 0.875rem !important;
            color: #4b5563 !important;
            margin: 0.25rem 0 0 0 !important;
            font-weight: 400 !important;
        }

        /* Statistics */
        .stats-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-box {
            background: var(--bg-card);
            padding: 1.25rem 1.5rem;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--accent-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 180px;
        }

        .stat-box.stat-today {
            border-left-color: #059669;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: var(--accent-color);
            font-size: 1.25rem;
        }

        .stat-box.stat-today .stat-icon {
            background: #ecfdf5;
            color: #059669;
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 1.75rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            line-height: 1 !important;
            font-variant-numeric: tabular-nums;
        }

        .stat-label {
            font-size: 0.75rem !important;
            color: #374151 !important;
            font-weight: 500 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.025em;
            margin-top: 0.25rem;
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-light);
            margin: 1.5rem 0;
        }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .drafts-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .drafts-table thead {
            background: #f3f4f6;
            border-bottom: 1px solid var(--border-color);
        }

        .drafts-table th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600 !important;
            color: #374151 !important;
            text-transform: uppercase;
            font-size: 0.6875rem !important;
            letter-spacing: 0.05em;
        }

        .drafts-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            color: #1f2937 !important;
        }

        .drafts-table tbody tr:nth-child(even) {
            background-color: #e8f0fe;
        }

        .drafts-table tbody tr:hover {
            background-color: #eef2f7;
        }

        .drafts-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Table Content Styles */
        .drafts-table th:first-child,
        .drafts-table td:first-child {
            border-right: 1px solid #e5e7eb;
            text-align: center;
            width: 50px;
        }

        .row-number {
            font-weight: 500 !important;
            color: #4b5563 !important;
            font-size: 0.75rem !important;
            font-variant-numeric: tabular-nums;
        }

        .form-number {
            font-weight: 600 !important;
            color: #111827 !important;
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace !important;
            font-size: 0.8125rem !important;
        }

        .form-number i {
            display: none;
        }

        .patient-info {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .patient-name {
            font-weight: 500 !important;
            color: #111827 !important;
            font-size: 0.8125rem !important;
        }

        .patient-name.empty {
            color: #6b7280 !important;
            font-weight: 400 !important;
            font-style: italic;
        }

        .patient-meta {
            font-size: 0.75rem !important;
            color: #374151 !important;
            font-weight: 400 !important;
        }

        .location-info {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .location-item {
            color: #1f2937 !important;
            font-size: 0.8125rem !important;
        }

        .location-item i {
            display: none;
        }

        .location-item::before {
            display: none;
        }

        .date-info {
            color: #1f2937 !important;
            font-size: 0.8125rem !important;
            font-variant-numeric: tabular-nums;
        }

        .date-time {
            display: block;
            color: #374151 !important;
            font-size: 0.75rem !important;
            margin-top: 0.125rem;
        }

        .text-placeholder {
            color: #9ca3af !important;
        }

        /* Actions */
        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            align-items: center;
        }

        .action-separator {
            width: 1px;
            height: 20px;
            background-color: #d1d5db;
        }

        .btn-resume {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.375rem 0.875rem;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-resume:hover {
            background: var(--accent-hover);
            color: white;
        }

        .btn-resume i {
            font-size: 0.75rem;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: 1px solid #dc3545;
            padding: 0.375rem 0.5rem;
            font-weight: 500;
            font-size: 0.75rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }

        .btn-delete:hover {
            background: #b91c1c;
            color: white;
            border-color: #b91c1c;
        }

        .btn-delete i {
            font-size: 0.75rem;
        }

        .btn-new {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.8125rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-new:hover {
            background: var(--accent-hover);
            color: white;
        }

        .btn-new i {
            font-size: 0.875rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
        }

        .empty-icon {
            font-size: 3rem !important;
            color: #6b7280 !important;
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.125rem !important;
            font-weight: 600 !important;
            color: #111827 !important;
            margin-bottom: 0.5rem;
        }

        .empty-text {
            color: #4b5563 !important;
            margin-bottom: 1.5rem;
            font-size: 0.875rem !important;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-top: 1rem;
        }

        .pagination-info {
            font-size: 0.8125rem !important;
            color: #374151 !important;
        }

        .pagination {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }

        .pagination a,
        .pagination span {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem !important;
            font-weight: 500 !important;
            text-decoration: none !important;
        }

        .pagination a {
            background: #ffffff !important;
            color: #1f2937 !important;
            border: 1px solid #d1d5db !important;
        }

        .pagination a:hover {
            background: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
        }

        .pagination .current {
            background: #2563eb !important;
            color: white !important;
            border: 1px solid #2563eb !important;
        }

        .pagination .disabled {
            background: #ffffff !important;
            color: #9ca3af !important;
            border: 1px solid #e5e7eb !important;
            cursor: not-allowed;
        }

        /* Notiflix button overrides - remove underline */
        #NotiflixConfirmWrap button,
        #NotiflixConfirmWrap a,
        [id^="Notiflix"] button,
        [id^="Notiflix"] a {
            text-decoration: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .header-right {
                width: 100%;
            }

            .btn-new {
                width: 100%;
                justify-content: center;
            }

            .page-title {
                font-size: 1.25rem;
            }

            .stats-row {
                flex-direction: column;
                gap: 0.75rem;
            }

            .stat-box {
                padding: 1rem 1.25rem;
                min-width: auto;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .drafts-table {
                min-width: 800px;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <div class="page-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1 class="page-title">Draft Forms</h1>
                        <p class="page-subtitle">Manage and resume your in-progress form entries</p>
                    </div>
                    <div class="header-right">
                        <a href="prehospital_form.php" class="btn-new">
                            <i class="bi bi-plus-circle-fill"></i> New Form
                        </a>
                    </div>
                </div>
            </div>

            <?php show_flash(); ?>

            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $total_records; ?></div>
                        <div class="stat-label">Total Drafts</div>
                    </div>
                </div>
                <div class="stat-box stat-today">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">
                            <?php
                            // Count today's drafts from database for accuracy
                            $today_sql = "SELECT COUNT(*) as total FROM prehospital_forms WHERE created_by = ? AND status = 'draft' AND DATE(updated_at) = CURDATE()";
                            $today_stmt = db_query($today_sql, [$user_id]);
                            echo $today_stmt->fetch()['total'];
                            ?>
                        </div>
                        <div class="stat-label">Updated Today</div>
                    </div>
                </div>
            </div>

            <?php if (empty($drafts)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h2 class="empty-title">No Drafts Yet</h2>
                    <p class="empty-text">You don't have any saved drafts. Start a new form and it will be automatically saved as you work.</p>
                    <a href="prehospital_form.php" class="btn-new">
                        <i class="bi bi-plus-circle-fill"></i> Create Your First Form
                    </a>
                </div>
            <?php else: ?>
                <hr>
                <div class="table-container">
                    <table class="drafts-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Form Number</th>
                                <th>Patient Information</th>
                                <th>Form Date</th>
                                <th>Location</th>
                                <th>Last Saved</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drafts as $index => $draft): ?>
                                <?php $row_number = $offset + $index + 1; ?>
                                <tr>
                                    <td>
                                        <div class="row-number"><?php echo $row_number; ?></div>
                                    </td>
                                    <td>
                                        <div class="form-number">
                                            <i class="bi bi-file-earmark-text-fill"></i>
                                            <?php echo e($draft['form_number']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="patient-info">
                                            <div class="patient-name <?php echo !$draft['patient_name'] ? 'empty' : ''; ?>">
                                                <?php echo e($draft['patient_name'] ?: 'Patient name not yet entered'); ?>
                                            </div>
                                            <?php if ($draft['age'] || $draft['gender']): ?>
                                                <div class="patient-meta">
                                                    <?php if ($draft['age']): ?>
                                                        <?php echo e($draft['age']); ?> yrs
                                                    <?php endif; ?>
                                                    <?php if ($draft['gender']): ?>
                                                        <?php if ($draft['age']): ?>•<?php endif; ?> <?php echo ucfirst(e($draft['gender'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-info">
                                            <?php if ($draft['form_date'] && $draft['form_date'] !== '0000-00-00'): ?>
                                                <?php echo date('M d, Y', strtotime($draft['form_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-placeholder">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info">
                                            <?php if ($draft['place_of_incident']): ?>
                                                <div class="location-item"><?php echo e($draft['place_of_incident']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($draft['arrival_hospital_name']): ?>
                                                <div class="location-item"><?php echo e($draft['arrival_hospital_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!$draft['place_of_incident'] && !$draft['arrival_hospital_name']): ?>
                                                <span class="text-placeholder">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-info">
                                            <?php echo date('M d, Y', strtotime($draft['updated_at'])); ?>
                                            <span class="date-time"><?php echo date('h:i A', strtotime($draft['updated_at'])); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="prehospital_form.php?draft_id=<?php echo $draft['id']; ?>" class="btn-resume">
                                                <i class="bi bi-pencil-square"></i>
                                                Resume
                                            </a>
                                            <div class="action-separator"></div>
                                            <button class="btn-delete" data-delete-draft="<?php echo $draft['id']; ?>" title="Delete draft">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                            <?php else: ?>
                                <span class="disabled">Previous</span>
                            <?php endif; ?>

                            <?php
                            // Show page numbers
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="disabled">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $current_page) {
                                    echo '<span class="current">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '">' . $i . '</a>';
                                }
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="disabled">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>">Next</a>
                            <?php else: ?>
                                <span class="disabled">Next</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        function deleteDraft(id) {
            if (!id || id <= 0) {
                Notiflix.Notify.failure('Invalid draft ID.');
                return;
            }

            Notiflix.Confirm.show(
                'Delete Draft',
                'Are you sure you want to delete this draft? This action cannot be undone.',
                'Yes, Delete',
                'Cancel',
                function okCb() {
                    Notiflix.Loading.standard('Deleting draft...');

                    fetch('../api/delete_record.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: parseInt(id), csrf_token: '<?php echo generate_token(); ?>' })
                    })
                    .then(response => {
                        // Check if response was redirected (session expired)
                        if (response.redirected || response.url.includes('login.php')) {
                            throw new Error('Session expired. Please login again.');
                        }
                        if (!response.ok && response.headers.get('content-type')?.indexOf('application/json') === -1) {
                            throw new Error('Server error (' + response.status + '). Please try again.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        Notiflix.Loading.remove();

                        if (data.success) {
                            Notiflix.Notify.success('Draft deleted successfully!', {
                                timeout: 2000,
                            });
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        } else {
                            Notiflix.Notify.failure('Error: ' + (data.message || 'Unknown error'), {
                                timeout: 3000,
                            });
                        }
                    })
                    .catch(error => {
                        Notiflix.Loading.remove();
                        if (error.message.includes('Session expired')) {
                            Notiflix.Notify.warning(error.message, { timeout: 3000 });
                            setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                        } else {
                            Notiflix.Notify.failure('Error deleting draft: ' + error.message, {
                                timeout: 3000,
                            });
                        }
                        console.error('Delete draft error:', error);
                    });
                },
                function cancelCb() {
                    // User cancelled
                },
                {
                    borderRadius: '8px',
                    titleColor: '#dc3545',
                    okButtonBackground: '#dc3545',
                    okButtonColor: '#fff',
                    cssAnimationStyle: 'zoom',
                }
            );
        }

        // Attach delete handlers via event delegation (CSP blocks inline onclick)
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-delete-draft]');
            if (btn) {
                deleteDraft(parseInt(btn.getAttribute('data-delete-draft')));
            }
        });
    </script>
</body>
</html>
