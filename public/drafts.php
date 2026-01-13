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
            --primary-color: #0f172a;
            --primary-hover: #1e293b;
            --accent-color: #3b82f6;
            --accent-hover: #2563eb;
            --success-color: #059669;
            --success-hover: #047857;
            --danger-color: #dc2626;
            --danger-hover: #b91c1c;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-page);
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            color: #2e6eaf;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page-header {
            background: transparent;
            padding: 0;
            margin-bottom: 2rem;
            border-bottom: none;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .header-left {
            flex: 1;
        }

        .header-right {
            flex-shrink: 0;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .page-subtitle {
            font-size: 0.9375rem;
            color: #64748b;
            margin: 0.5rem 0 0 0;
            font-weight: 400;
            line-height: 1.5;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2e6eaf;
            margin-bottom: 0;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #1e293b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0;
        }

        .stat-label i {
            display: none;
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 2rem 0;
            opacity: 0.6;
        }

        .table-container {
            background: var(--bg-card);
            border-radius: 6px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .drafts-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .drafts-table thead {
            background: #9ce9a0;
            border-bottom: 2px solid var(--border-color);
        }

        .drafts-table th {
            padding: 0.875rem 1.25rem;
            text-align: left;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 0.6875rem;
            letter-spacing: 0.08em;
            border-right: 1px solid var(--border-color);
        }

        .drafts-table th:last-child {
            border-right: none;
        }

        .drafts-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .drafts-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .drafts-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .drafts-table tbody tr:last-child td {
            border-bottom: none;
        }

        .form-number {
            font-weight: 700;
            color: #1e293b;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.8125rem;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            gap: 0;
            white-space: nowrap;
        }

        .form-number i {
            display: none;
        }

        .patient-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.875rem;
        }

        .patient-name.empty {
            color: #475569;
            font-style: normal;
            font-weight: 400;
        }

        .patient-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .patient-meta {
            font-size: 0.75rem;
            color: #334155;
            font-weight: 500;
        }

        .location-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.8125rem;
        }

        .location-item {
            display: flex;
            align-items: center;
            gap: 0;
            color: #334155;
            font-size: 0.8125rem;
        }

        .location-item i {
            display: none;
        }

        .location-item::before {
            content: "•";
            margin-right: 0.5rem;
            color: #64748b;
        }

        .location-item:first-child::before {
            content: "";
            margin-right: 0;
        }

        .date-info {
            color: #334155;
            white-space: nowrap;
            font-size: 0.8125rem;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            align-items: center;
        }

        .action-separator {
            width: 1px;
            height: 24px;
            background-color: var(--border-color);
            margin: 0 0.25rem;
        }

        .btn-resume {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s ease;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .btn-resume:hover {
            background: var(--accent-hover);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-resume i {
            font-size: 0.875rem;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-delete:hover {
            background: var(--danger-hover);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-delete i {
            font-size: 0.875rem;
        }

        .btn-new {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9375rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            transition: all 0.2s ease;
            cursor: pointer;
            text-transform: none;
            letter-spacing: 0;
            box-shadow: var(--shadow-sm);
        }

        .btn-new:hover {
            background: var(--accent-hover);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px rgba(59, 130, 246, 0.4);
        }

        .btn-new i {
            font-size: 1.125rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--bg-card);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .empty-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2e6eaf;
            margin-bottom: 0.75rem;
        }

        .empty-text {
            color: #2e6eaf;
            margin-bottom: 2rem;
            font-size: 0.9375rem;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .row-number {
            font-weight: 600;
            color: #64748b;
            font-size: 0.8125rem;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 0;
            margin-top: 1.5rem;
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.875rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .pagination a {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .pagination a:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }

        .pagination .current {
            background: var(--accent-color);
            color: white;
            border: 1px solid var(--accent-color);
            font-weight: 600;
        }

        .pagination .disabled {
            background: var(--bg-card);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            cursor: not-allowed;
            opacity: 0.5;
        }

        @media (max-width: 1200px) {
            .location-info {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                margin-bottom: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.25rem;
            }

            .header-right {
                width: 100%;
            }

            .btn-new {
                width: 100%;
                justify-content: center;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .page-subtitle {
                font-size: 0.875rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-box {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .drafts-table {
                font-size: 0.8125rem;
                min-width: 900px;
            }

            .drafts-table th,
            .drafts-table td {
                padding: 0.875rem 0.75rem;
            }

            .table-actions {
                flex-direction: row;
                gap: 0.375rem;
            }

            .btn-resume {
                padding: 0.5rem 0.875rem;
                font-size: 0.6875rem;
            }

            .btn-delete {
                padding: 0.5rem 0.625rem;
            }

            .btn-new {
                padding: 0.5rem 1.25rem;
                font-size: 0.8125rem;
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
                    <div class="stat-value"><?php echo count($drafts); ?></div>
                    <div class="stat-label">Total Drafts</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">
                        <?php
                        $today_drafts = array_filter($drafts, function($d) {
                            return date('Y-m-d', strtotime($d['updated_at'])) === date('Y-m-d');
                        });
                        echo count($today_drafts);
                        ?>
                    </div>
                    <div class="stat-label">Updated Today</div>
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
                                <?php
                                // Debug: Log draft ID
                                error_log("Draft ID type: " . gettype($draft['id']) . ", Value: " . var_export($draft['id'], true));
                                $row_number = $offset + $index + 1;
                                ?>
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
                                                <span style="color: var(--text-muted); font-style: italic;">Not set</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="location-info">
                                            <?php if ($draft['place_of_incident']): ?>
                                                <div class="location-item">
                                                    <i class="bi bi-geo-alt-fill"></i>
                                                    <?php echo e($draft['place_of_incident']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($draft['arrival_hospital_name']): ?>
                                                <div class="location-item">
                                                    <i class="bi bi-hospital-fill"></i>
                                                    <?php echo e($draft['arrival_hospital_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!$draft['place_of_incident'] && !$draft['arrival_hospital_name']): ?>
                                                <span style="color: var(--text-muted); font-style: italic;">Not set</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-info">
                                            <?php echo date('M d, Y', strtotime($draft['updated_at'])); ?><br>
                                            <span style="color: var(--text-muted); font-size: 0.75rem;">
                                                <?php echo date('h:i A', strtotime($draft['updated_at'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="prehospital_form.php?draft_id=<?php echo $draft['id']; ?>" class="btn-resume">
                                                <i class="bi bi-pencil-square"></i>
                                                Resume
                                            </a>
                                            <div class="action-separator"></div>
                                            <button onclick="deleteDraft(<?php echo $draft['id']; ?>)" class="btn-delete" title="Delete draft">
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
    <script>
        function deleteDraft(id) {
            console.log('Deleting draft ID:', id, 'Type:', typeof id);

            if (!id || id <= 0) {
                alert('Invalid draft ID: ' + id);
                return;
            }

            if (confirm('Are you sure you want to delete this draft?\n\nThis action cannot be undone.')) {
                fetch('../api/delete_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: parseInt(id) })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error deleting draft: ' + error.message);
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>
