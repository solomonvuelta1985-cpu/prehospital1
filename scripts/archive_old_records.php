<?php
/**
 * Archive Old Records Script
 * Moves records older than the configured retention period to archived_records table
 *
 * Usage:
 *   CLI:  php scripts/archive_old_records.php
 *   Cron: 0 3 * * 0 php /path/to/scripts/archive_old_records.php
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    http_response_code(403);
    exit("This maintenance task is CLI-only.\n");
}

try {
    // Get retention setting
    $sql = "SELECT setting_value FROM app_settings WHERE setting_key = 'data_retention_months' LIMIT 1";
    $stmt = db_query($sql);
    $result = $stmt ? $stmt->fetch() : null;
    $retention_months = $result ? (int)$result['setting_value'] : 0;

    if ($retention_months <= 0) {
        $msg = "Data retention is disabled (set to keep forever). No records archived.";
        if ($is_cli) echo "[INFO] $msg\n";
        exit(0);
    }

    // Calculate cutoff date
    $cutoff_date = date('Y-m-d', strtotime("-{$retention_months} months"));

    // Find records to archive
    $find_sql = "SELECT id FROM prehospital_forms WHERE DATE(created_at) < ? AND id NOT IN (SELECT id FROM archived_records)";
    $find_stmt = db_query($find_sql, [$cutoff_date]);
    $records_to_archive = $find_stmt ? $find_stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    if (empty($records_to_archive)) {
        $msg = "No records older than $cutoff_date to archive.";
        if ($is_cli) echo "[INFO] $msg\n";
        exit(0);
    }

    $archived_count = 0;
    $pdo->beginTransaction();

    foreach ($records_to_archive as $record_id) {
        // Get full record data
        $record_sql = "SELECT * FROM prehospital_forms WHERE id = ?";
        $record_stmt = db_query($record_sql, [$record_id]);
        $record = $record_stmt->fetch();

        if (!$record) continue;

        // Get injuries
        $injury_sql = "SELECT * FROM injuries WHERE form_id = ?";
        $injury_stmt = db_query($injury_sql, [$record_id]);
        $injuries_data = $injury_stmt->fetchAll();

        // Bundle all data as JSON
        $archive_data = json_encode([
            'record' => $record,
            'injuries' => $injuries_data,
            'archived_reason' => "Data retention policy: {$retention_months} months"
        ]);

        // Insert into archive
        $archive_sql = "INSERT INTO archived_records (id, original_data, archived_at) VALUES (?, ?, NOW())";
        db_query($archive_sql, [$record_id, $archive_data]);

        // Delete injuries first (foreign key)
        db_query("DELETE FROM injuries WHERE form_id = ?", [$record_id]);

        // Delete original record
        db_query("DELETE FROM prehospital_forms WHERE id = ?", [$record_id]);

        $archived_count++;
    }

    $pdo->commit();

    $msg = "Archived $archived_count records older than $cutoff_date.";
    if ($is_cli) echo "[OK] $msg\n";

    // Log if functions available
    if (function_exists('log_activity')) {
        log_activity('data_archive', $msg);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = "Archive error: " . $e->getMessage();
    error_log($msg);
    if ($is_cli) {
        echo "[ERROR] $msg\n";
        exit(1);
    }
}
