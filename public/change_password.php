<?php
/**
 * Force Password Change Page
 * Shown when user must change their temporary password
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Must be logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$csrf_token = generate_token();

// Handle password change submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
        redirect('change_password.php');
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } else {
        $validation = validate_password_strength($new_password);
        if (!$validation['valid']) {
            $errors = array_merge($errors, $validation['errors']);
        }
    }

    if ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (!empty($errors)) {
        set_flash(implode('<br>', $errors), 'error');
        redirect('change_password.php');
    }

    // Update password and clear force flag
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
    $stmt->execute([$hash, $user_id]);

    log_activity('password_changed', 'User changed their password');
    set_flash('Password changed successfully!', 'success');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password Required</title>
    <link href="vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .change-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 2rem; max-width: 450px; width: 100%; }
        .change-card h3 { color: #dc3545; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="change-card">
        <h3><i class="bi bi-shield-lock"></i> Password Change Required</h3>
        <p class="text-muted">You must change your temporary password before continuing.</p>

        <?php show_flash(); ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="mb-3">
                <label class="form-label fw-bold">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8" autofocus>
                <small class="text-muted">Min 8 chars, uppercase, lowercase, number, special character</small>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary w-100">Change Password</button>
        </form>
    </div>
</body>
</html>
