<?php
/**
 * DEBUG PAGE - Check Drafts in Database
 * Temporary diagnostic page to verify drafts are being saved
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

$current_user = get_auth_user();
$user_id = $current_user['id'];

// Get ALL records for this user (not just drafts)
$sql_all = "SELECT id, form_number, status, patient_name, created_at, updated_at
            FROM prehospital_forms
            WHERE created_by = ?
            ORDER BY created_at DESC
            LIMIT 20";
$stmt_all = db_query($sql_all, [$user_id]);
$all_records = $stmt_all->fetchAll();

// Get ONLY drafts
$sql_drafts = "SELECT id, form_number, status, patient_name, created_at, updated_at
               FROM prehospital_forms
               WHERE created_by = ? AND status = 'draft'
               ORDER BY created_at DESC";
$stmt_drafts = db_query($sql_drafts, [$user_id]);
$draft_records = $stmt_drafts->fetchAll();

// Get status counts
$sql_counts = "SELECT status, COUNT(*) as count
               FROM prehospital_forms
               WHERE created_by = ?
               GROUP BY status";
$stmt_counts = db_query($sql_counts, [$user_id]);
$status_counts = $stmt_counts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEBUG: Draft Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background: #f5f5f5;
        }
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .alert-info {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1565c0;
        }
        table {
            font-size: 14px;
        }
        .badge {
            font-size: 12px;
            padding: 4px 8px;
        }
        h2 {
            margin-top: 30px;
            margin-bottom: 15px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔍 Draft Records Debug Page</h1>
        <p class="text-muted">Current User: <strong><?php echo e($current_user['full_name']); ?></strong> (ID: <?php echo $user_id; ?>)</p>

        <div class="alert alert-info">
            <strong>Instructions:</strong> This page shows all your records in the database. If autosave says "Draft saved" but nothing appears in drafts.php, check this page to see if the record exists with status='draft'.
        </div>

        <h2>Status Counts</h2>
        <div class="row mb-4">
            <?php foreach ($status_counts as $count): ?>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3><?php echo $count['count']; ?></h3>
                            <p class="mb-0"><?php echo ucfirst($count['status']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2>Draft Records Only (status='draft')</h2>
        <p>This is what should appear in <a href="drafts.php" target="_blank">drafts.php</a></p>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Form Number</th>
                    <th>Status</th>
                    <th>Patient Name</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($draft_records)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-danger">
                            <strong>NO DRAFT RECORDS FOUND</strong><br>
                            If autosave showed "Draft saved", but nothing is here, the record might have a different status.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($draft_records as $record): ?>
                        <tr>
                            <td><?php echo $record['id']; ?></td>
                            <td><?php echo e($record['form_number']); ?></td>
                            <td><span class="badge bg-warning"><?php echo $record['status']; ?></span></td>
                            <td><?php echo e($record['patient_name'] ?: '(empty)'); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($record['updated_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>Last 20 Records (All Statuses)</h2>
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Form Number</th>
                    <th>Status</th>
                    <th>Patient Name</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_records)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No records found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_records as $record): ?>
                        <tr>
                            <td><?php echo $record['id']; ?></td>
                            <td><?php echo e($record['form_number']); ?></td>
                            <td>
                                <?php
                                $badge_class = [
                                    'draft' => 'bg-warning',
                                    'completed' => 'bg-success',
                                    'archived' => 'bg-secondary'
                                ];
                                $class = $badge_class[$record['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $class; ?>"><?php echo $record['status']; ?></span>
                            </td>
                            <td><?php echo e($record['patient_name'] ?: '(empty)'); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($record['updated_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="mt-4">
            <a href="TONYANG.php" class="btn btn-primary">← Back to Form</a>
            <a href="drafts.php" class="btn btn-success">View Drafts Page</a>
            <button onclick="location.reload()" class="btn btn-secondary">Refresh</button>
        </div>
    </div>
</body>
</html>
