<?php
/**
 * Admin Settings Page
 * Configure data retention, IP whitelist, and system settings
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Require admin
require_login();
require_admin();

$csrf_token = generate_token();
$current_user = get_auth_user();

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_token($_POST['csrf_token'] ?? '')) {
    $setting_key = sanitize($_POST['setting_key'] ?? '', false);
    $setting_value = sanitize($_POST['setting_value'] ?? '', false);

    if (!empty($setting_key)) {
        $sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
        db_query($sql, [$setting_key, $setting_value, $setting_value]);
        log_activity('settings_updated', "Updated setting: $setting_key");
        set_flash('Setting saved successfully!', 'success');
    }
    redirect('settings.php');
}

// Load current settings
function get_setting($key, $default = '') {
    $sql = "SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1";
    $stmt = db_query($sql, [$key]);
    if ($stmt) {
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }
    return $default;
}

$retention_period = get_setting('data_retention_months', '0');
$ip_whitelist = get_setting('admin_ip_whitelist', '');

// Handle backup trigger
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    if (!check_rate_limit('backup', 3, 3600)) {
        set_flash('Too many backup requests. Please wait 1 hour.', 'error');
        redirect('settings.php');
    }
    // Include backup script
    ob_start();
    $_GET = []; // Clear GET params to prevent recursion
    include __DIR__ . '/../../scripts/backup_database.php';
    ob_end_clean();
    // The backup script handles its own response
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-header { background: #fff; border-bottom: 2px solid #dee2e6; padding: 1.5rem 0; margin-bottom: 2rem; }
        .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 600; }
        .page-header h1 i { color: #0d6efd; margin-right: 0.5rem; }
        .settings-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .settings-card h5 { font-weight: 600; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #eee; }
        .settings-card h5 i { color: #0d6efd; margin-right: 0.5rem; }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1><i class="bi bi-gear"></i>System Settings</h1>
                    <p class="text-muted mb-0">Configure system behavior and security</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> Users</a>
                    <a href="activity_logs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text"></i> Logs</a>
                    <a href="../records.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4">
        <?php show_flash(); ?>

        <!-- Data Retention -->
        <div class="settings-card">
            <h5><i class="bi bi-clock-history"></i> Data Retention Policy</h5>
            <p class="text-muted small">Automatically archive old records after a specified period. Set to 0 to keep records forever.</p>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="setting_key" value="data_retention_months">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Retention Period</label>
                    <select name="setting_value" class="form-select">
                        <option value="0" <?= $retention_period == '0' ? 'selected' : '' ?>>Keep Forever (No Auto-Archive)</option>
                        <option value="6" <?= $retention_period == '6' ? 'selected' : '' ?>>6 Months</option>
                        <option value="12" <?= $retention_period == '12' ? 'selected' : '' ?>>1 Year</option>
                        <option value="24" <?= $retention_period == '24' ? 'selected' : '' ?>>2 Years</option>
                        <option value="36" <?= $retention_period == '36' ? 'selected' : '' ?>>3 Years</option>
                        <option value="60" <?= $retention_period == '60' ? 'selected' : '' ?>>5 Years</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>

        <!-- IP Whitelist -->
        <div class="settings-card">
            <h5><i class="bi bi-shield-lock"></i> Admin IP Whitelist</h5>
            <p class="text-muted small">Restrict admin panel access to specific IP addresses. Leave empty to allow all IPs. One IP per line.</p>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="setting_key" value="admin_ip_whitelist">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Allowed IP Addresses</label>
                    <textarea name="setting_value" class="form-control" rows="4" placeholder="e.g.&#10;192.168.1.100&#10;10.0.0.1"><?= e($ip_whitelist) ?></textarea>
                    <small class="text-muted">Your current IP: <code><?= e(get_client_ip()) ?></code></small>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>

        <!-- Database Backup -->
        <div class="settings-card">
            <h5><i class="bi bi-database"></i> Database Backup</h5>
            <p class="text-muted small">Create a database backup manually. Backups are stored in the <code>/backups</code> directory with automatic rotation.</p>
            <a href="settings.php?action=backup" class="btn btn-success" id="btnBackup">
                <i class="bi bi-download"></i> Backup Now
            </a>
        </div>

        <!-- System Info -->
        <div class="settings-card">
            <h5><i class="bi bi-info-circle"></i> System Information</h5>
            <table class="table table-sm">
                <tr><th style="width:200px;">App Version</th><td><?= e(APP_VERSION) ?></td></tr>
                <tr><th>PHP Version</th><td><?= phpversion() ?></td></tr>
                <tr><th>Server</th><td><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></td></tr>
                <tr><th>Database</th><td><?= e(DB_NAME) ?> @ <?= e(DB_HOST) ?></td></tr>
                <tr><th>Encryption</th><td><?= !empty(APP_ENCRYPTION_KEY) ? '<span class="text-success">Enabled</span>' : '<span class="text-warning">Not configured</span>' ?></td></tr>
                <tr><th>Timezone</th><td><?= date_default_timezone_get() ?></td></tr>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
    document.getElementById('btnBackup')?.addEventListener('click', function(e) {
        if (!confirm('Create a database backup now?')) { e.preventDefault(); }
    });
    </script>
</body>
</html>
