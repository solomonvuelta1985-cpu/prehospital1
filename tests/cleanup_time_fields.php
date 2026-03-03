<?php
/**
 * Cleanup Script: Convert 00:00:00 time values to NULL in existing drafts
 * Run this once to fix existing draft data
 */

define('APP_ACCESS', true);
require_once 'includes/config.php';

try {
    global $pdo;

    // List of all TIME columns that need cleaning
    $time_columns = [
        'departure_time',
        'arrival_time',
        'arrival_scene_time',
        'departure_scene_time',
        'arrival_hospital_time',
        'departure_hospital_time',
        'arrival_station_time',
        'incident_time',
        'call_arrival_time',
        'initial_time',
        'followup_time',
        'ob_delivery_time'
    ];

    echo "Starting cleanup of time fields in prehospital_forms table...\n\n";

    $total_updates = 0;

    foreach ($time_columns as $column) {
        $sql = "UPDATE prehospital_forms
                SET $column = NULL
                WHERE $column = '00:00:00'
                AND status = 'draft'";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $affected = $stmt->rowCount();

        if ($affected > 0) {
            echo "✓ Cleaned $column: $affected records updated\n";
            $total_updates += $affected;
        }
    }

    // Also handle datetime field
    $sql = "UPDATE prehospital_forms
            SET endorsement_datetime = NULL
            WHERE (endorsement_datetime = '0000-00-00 00:00:00' OR endorsement_datetime LIKE '%00:00:00')
            AND status = 'draft'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        echo "✓ Cleaned endorsement_datetime: $affected records updated\n";
        $total_updates += $affected;
    }

    echo "\n✅ Cleanup complete! Total field updates: $total_updates\n";
    echo "You can now delete this file (cleanup_time_fields.php)\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
