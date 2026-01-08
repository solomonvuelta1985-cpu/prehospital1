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
            --primary-blue: #1e40af;
            --success-green: #059669;
            --warning-orange: #d97706;
            --neutral-100: #f3f4f6;
            --neutral-200: #e5e7eb;
            --neutral-700: #374151;
            --neutral-900: #111827;
        }

        body {
            background-color: var(--neutral-100);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Roboto, sans-serif;
        }

        .page-header {
            background: white;
            border-bottom: 1px solid var(--neutral-200);
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #6b7280;
        }

        .draft-card {
            background: white;
            border: 1px solid var(--neutral-200);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .draft-card:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .draft-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .draft-number {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary-blue);
        }

        .draft-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .draft-patient {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }

        .draft-details {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .draft-detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .draft-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-resume {
            background: var(--success-green);
            color: white;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-resume:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(5, 150, 105, 0.3);
            color: white;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-delete:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            border: 2px dashed var(--neutral-200);
        }

        .empty-icon {
            font-size: 4rem;
            color: #9ca3af;
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--neutral-900);
            margin-bottom: 0.5rem;
        }

        .empty-text {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .btn-new {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-new:hover {
            background: #1e3a8a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--neutral-200);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
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
                        <h1 class="page-title"><i class="bi bi-file-earmark-text"></i> My Drafts</h1>
                        <p class="page-subtitle">Resume your in-progress forms and continue where you left off</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="prehospital_form.php" class="btn-new">
                            <i class="bi bi-plus-circle"></i> New Form
                        </a>
                    </div>
                </div>
            </div>

            <?php show_flash(); ?>

            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?php echo count($drafts); ?></div>
                    <div class="stat-label"><i class="bi bi-file-earmark"></i> Draft Forms</div>
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
                    <div class="stat-label"><i class="bi bi-calendar-day"></i> Updated Today</div>
                </div>
            </div>

            <?php if (empty($drafts)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h2 class="empty-title">No Drafts Yet</h2>
                    <p class="empty-text">You don't have any saved drafts. Start a new form and it will be automatically saved as you work.</p>
                    <a href="prehospital_form.php" class="btn-new">
                        <i class="bi bi-plus-circle"></i> Create Your First Form
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($drafts as $draft): ?>
                    <div class="draft-card">
                        <div class="draft-header">
                            <div>
                                <div class="draft-number">
                                    <i class="bi bi-hash"></i> <?php echo e($draft['form_number']); ?>
                                </div>
                            </div>
                            <div class="draft-time">
                                <i class="bi bi-clock"></i> Last saved: <?php echo date('M d, Y \a\t h:i A', strtotime($draft['updated_at'])); ?>
                            </div>
                        </div>

                        <div class="draft-patient">
                            <i class="bi bi-person"></i>
                            <?php echo e($draft['patient_name'] ?: 'Patient name not yet entered'); ?>
                        </div>

                        <div class="draft-details">
                            <?php if ($draft['form_date']): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-calendar"></i>
                                    <span>Date: <?php echo date('M d, Y', strtotime($draft['form_date'])); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($draft['age'] || $draft['gender']): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-person-badge"></i>
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
                                    <i class="bi bi-geo-alt"></i>
                                    <span><?php echo e($draft['place_of_incident']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($draft['arrival_hospital_name']): ?>
                                <div class="draft-detail-item">
                                    <i class="bi bi-hospital"></i>
                                    <span><?php echo e($draft['arrival_hospital_name']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="draft-actions">
                            <a href="prehospital_form.php?draft_id=<?php echo $draft['id']; ?>" class="btn-resume">
                                <i class="bi bi-play-circle"></i>
                                Resume Editing
                            </a>
                            <button onclick="deleteDraft(<?php echo $draft['id']; ?>)" class="btn-delete">
                                <i class="bi bi-trash"></i>
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
