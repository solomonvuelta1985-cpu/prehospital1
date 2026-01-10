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

// Get all drafts for current user
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
ORDER BY pf.updated_at DESC";

$stmt = db_query($sql, [$user_id]);
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
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --success-color: #10b981;
            --success-hover: #059669;
            --danger-color: #ef4444;
            --danger-hover: #dc2626;
            --border-color: #e5e7eb;
            --text-primary: #1f2937;
            --text-secondary: #4b5563;
            --text-muted: #6b7280;
            --bg-page: #f9fafb;
            --bg-card: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-page);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .page-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 2.5rem 0;
            margin-bottom: 2.5rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.75rem 0;
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .page-title i {
            color: var(--primary-color);
            font-size: 1.75rem;
        }

        .page-subtitle {
            font-size: 0.9375rem;
            color: #4b5563;
            margin: 0;
            padding-left: 2.375rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-box {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.75rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.8125rem;
            color: #1f2937;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-label i {
            color: var(--primary-color);
            font-size: 0.875rem;
        }

        .draft-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .draft-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-hover);
        }

        .draft-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }

        .draft-number {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .draft-number i {
            font-size: 1rem;
        }

        .draft-time {
            font-size: 0.8125rem;
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .draft-time i {
            font-size: 0.875rem;
        }

        .draft-patient {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .draft-patient i {
            color: var(--primary-color);
            font-size: 1.125rem;
        }

        .draft-details {
            display: flex;
            gap: 2.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.75rem;
            font-size: 0.875rem;
        }

        .draft-detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .draft-detail-item i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .draft-detail-item span {
            color: #1f2937;
            font-weight: 500;
        }

        .draft-actions {
            display: flex;
            gap: 0.875rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-color);
        }

        .btn-resume {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            transition: all 0.15s ease;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-resume:hover {
            background: var(--success-hover);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-resume i {
            font-size: 1rem;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.15s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-delete:hover {
            background: var(--danger-hover);
            box-shadow: var(--shadow-md);
        }

        .btn-delete i {
            font-size: 1rem;
        }

        .btn-new {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9375rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .btn-new:hover {
            background: var(--primary-hover);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-new i {
            font-size: 1.125rem;
        }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: var(--bg-card);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
        }

        .empty-icon {
            font-size: 5rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .empty-title {
            font-size: 1.625rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .empty-text {
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            font-size: 1rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="page-title"><i class="bi bi-file-text-fill"></i> My Drafts</h1>
                        <p class="page-subtitle">Resume your in-progress forms and continue where you left off</p>
                    </div>
                    <div class="col-md-4 text-md-end">
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
                    <div class="stat-label"><i class="bi bi-file-earmark-text-fill"></i> Draft Forms</div>
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
                    <div class="stat-label"><i class="bi bi-calendar-check-fill"></i> Updated Today</div>
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
                <?php foreach ($drafts as $draft): ?>
                    <div class="draft-card">
                        <div class="draft-header">
                            <div>
                                <div class="draft-number">
                                    <i class="bi bi-file-earmark-text-fill"></i><?php echo e($draft['form_number']); ?>
                                </div>
                            </div>
                            <div class="draft-time">
                                <i class="bi bi-clock-fill"></i>Last saved: <?php echo date('M d, Y \a\t h:i A', strtotime($draft['updated_at'])); ?>
                            </div>
                        </div>

                        <div class="draft-patient">
                            <i class="bi bi-person-fill"></i>
                            <?php echo e($draft['patient_name'] ?: 'Patient name not yet entered'); ?>
                        </div>

                        <div class="draft-details">
                            <?php if ($draft['form_date'] && $draft['form_date'] !== '0000-00-00'): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-calendar-fill"></i>
                                    <span><?php echo date('M d, Y', strtotime($draft['form_date'])); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($draft['age'] || $draft['gender']): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-person-vcard-fill"></i>
                                    <span>
                                        <?php if ($draft['age']): ?>
                                            <?php echo e($draft['age']); ?> yrs
                                        <?php endif; ?>
                                        <?php if ($draft['gender']): ?>
                                            / <?php echo ucfirst(e($draft['gender'])); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($draft['place_of_incident']): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <span><?php echo e($draft['place_of_incident']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($draft['arrival_hospital_name']): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-hospital-fill"></i>
                                    <span><?php echo e($draft['arrival_hospital_name']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="draft-actions">
                            <a href="prehospital_form.php?draft_id=<?php echo $draft['id']; ?>" class="btn-resume">
                                <i class="bi bi-pencil-square"></i>
                                Resume Editing
                            </a>
                            <button onclick="deleteDraft(<?php echo $draft['id']; ?>)" class="btn-delete">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteDraft(id) {
            if (confirm('Are you sure you want to delete this draft?\n\nThis action cannot be undone.')) {
                fetch('../api/delete_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting draft');
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>
