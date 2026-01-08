<?php
/**
 * Database Diagnostic and Fix Script
 * This will check the database structure and fix any issues
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<html><head><title>Database Fix</title><style>
body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #00ff00; }
.success { color: #00ff00; }
.error { color: #ff0000; }
.warning { color: #ffaa00; }
.info { color: #00aaff; }
pre { background: #000; padding: 10px; border: 1px solid #333; }
</style></head><body>";

echo "<h1>🔧 DATABASE DIAGNOSTIC AND FIX</h1>\n";
echo "<pre>\n";

global $pdo;

// 1. Check table structure
echo "<span class='info'>━━━ STEP 1: Checking Table Structure ━━━</span>\n";
try {
    $stmt = $pdo->query("DESCRIBE prehospital_forms");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Table: prehospital_forms\n";
    $id_column = null;
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} {$col['Extra']}\n";
        if ($col['Field'] === 'id') {
            $id_column = $col;
        }
    }

    // Check if id is auto_increment
    if ($id_column) {
        if (stripos($id_column['Extra'], 'auto_increment') !== false) {
            echo "<span class='success'>✓ ID column is AUTO_INCREMENT</span>\n";
        } else {
            echo "<span class='error'>✗ ID column is NOT AUTO_INCREMENT!</span>\n";
            echo "<span class='warning'>  Attempting to fix...</span>\n";

            // Fix: Make id auto_increment
            $pdo->exec("ALTER TABLE prehospital_forms MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
            echo "<span class='success'>✓ Fixed: ID column now set to AUTO_INCREMENT</span>\n";
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 2. Check current AUTO_INCREMENT value
echo "\n<span class='info'>━━━ STEP 2: Checking AUTO_INCREMENT Value ━━━</span>\n";
try {
    $stmt = $pdo->query("SHOW TABLE STATUS LIKE 'prehospital_forms'");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Current AUTO_INCREMENT value: {$status['Auto_increment']}\n";

    // Get max ID from table
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM prehospital_forms");
    $max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'];

    echo "Max ID in table: " . ($max_id ?? 0) . "\n";

    if ($status['Auto_increment'] <= $max_id) {
        $new_auto_inc = $max_id + 1;
        echo "<span class='warning'>⚠ AUTO_INCREMENT ({$status['Auto_increment']}) is not greater than MAX ID ({$max_id})</span>\n";
        echo "<span class='warning'>  Fixing AUTO_INCREMENT to {$new_auto_inc}...</span>\n";

        $pdo->exec("ALTER TABLE prehospital_forms AUTO_INCREMENT = {$new_auto_inc}");
        echo "<span class='success'>✓ Fixed: AUTO_INCREMENT set to {$new_auto_inc}</span>\n";
    } else {
        echo "<span class='success'>✓ AUTO_INCREMENT value is correct</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 3. Fix column constraints (allow NULL for optional fields)
echo "\n<span class='info'>━━━ STEP 3: Fixing Column Constraints ━━━</span>\n";
try {
    // Check if age column allows NULL
    $stmt = $pdo->query("SHOW COLUMNS FROM prehospital_forms LIKE 'age'");
    $age_col = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($age_col && $age_col['Null'] === 'NO') {
        echo "<span class='warning'>⚠ 'age' column does NOT allow NULL (causing autosave failures)</span>\n";
        echo "<span class='warning'>  Fixing...</span>\n";

        $pdo->exec("ALTER TABLE prehospital_forms MODIFY COLUMN age INT(11) NULL DEFAULT NULL");
        echo "<span class='success'>✓ Fixed: 'age' column now allows NULL</span>\n";
    } else {
        echo "<span class='success'>✓ 'age' column allows NULL</span>\n";
    }

    // Also fix other required fields that should be nullable for drafts
    $nullable_fields = [
        'patient_name' => 'VARCHAR(255)',
        'date_of_birth' => 'DATE',
        'gender' => 'ENUM(\'male\',\'female\')',
        // Time fields (using actual database column names)
        'departure_time' => 'TIME',
        'arrival_time' => 'TIME',
        'arrival_scene_time' => 'TIME',
        'departure_scene_time' => 'TIME',
        'arrival_hospital_time' => 'TIME',
        'departure_hospital_time' => 'TIME',
        'arrival_station_time' => 'TIME',
        'incident_time' => 'TIME',
        'call_arrival_time' => 'TIME',
        'initial_time' => 'TIME',
        'followup_time' => 'TIME',
        'ob_delivery_time' => 'TIME',  // Changed from 'delivery_time'
        'endorsement_datetime' => 'DATETIME'
    ];

    foreach ($nullable_fields as $field => $type) {
        $stmt = $pdo->query("SHOW COLUMNS FROM prehospital_forms LIKE '{$field}'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($col && $col['Null'] === 'NO') {
            echo "<span class='warning'>  Fixing '{$field}' to allow NULL...</span>\n";
            $pdo->exec("ALTER TABLE prehospital_forms MODIFY COLUMN {$field} {$type} NULL DEFAULT NULL");
            echo "<span class='success'>  ✓ '{$field}' now allows NULL</span>\n";
        }
    }

    echo "<span class='info'>All nullable fields checked and fixed</span>\n";
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 4. Clean up 00:00:00 time values (set to NULL)
echo "\n<span class='info'>━━━ STEP 4: Cleaning Invalid Time Values ━━━</span>\n";
try {
    $time_fields = [
        'departure_time', 'arrival_time', 'arrival_scene_time', 'departure_scene_time',
        'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time',
        'incident_time', 'call_arrival_time', 'initial_time', 'followup_time', 'ob_delivery_time'
    ];

    $cleaned_count = 0;
    foreach ($time_fields as $field) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM prehospital_forms WHERE {$field} = '00:00:00'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($count > 0) {
            echo "<span class='warning'>  Found {$count} records with {$field} = '00:00:00'</span>\n";
            $pdo->exec("UPDATE prehospital_forms SET {$field} = NULL WHERE {$field} = '00:00:00'");
            $cleaned_count += $count;
        }
    }

    if ($cleaned_count > 0) {
        echo "<span class='success'>✓ Cleaned {$cleaned_count} invalid time values (set to NULL)</span>\n";
    } else {
        echo "<span class='success'>✓ No invalid time values found</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 5. Check for duplicate IDs or ID=0 records
echo "\n<span class='info'>━━━ STEP 5: Checking for Invalid Records ━━━</span>\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM prehospital_forms WHERE id = 0");
    $zero_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($zero_count > 0) {
        echo "<span class='error'>✗ Found {$zero_count} records with ID = 0</span>\n";
        echo "<span class='warning'>  Deleting invalid records...</span>\n";

        $pdo->exec("DELETE FROM prehospital_forms WHERE id = 0");
        echo "<span class='success'>✓ Deleted {$zero_count} invalid records</span>\n";
    } else {
        echo "<span class='success'>✓ No records with ID = 0</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 6. Test INSERT and lastInsertId
echo "\n<span class='info'>━━━ STEP 6: Testing INSERT and lastInsertId() ━━━</span>\n";
try {
    $test_form_number = 'TEST-' . date('YmdHis');
    $test_sql = "INSERT INTO prehospital_forms (form_number, status, created_by, created_at, updated_at)
                 VALUES (?, 'draft', 1, NOW(), NOW())";

    $stmt = $pdo->prepare($test_sql);
    $stmt->execute([$test_form_number]);

    $test_id = $pdo->lastInsertId();

    echo "Test INSERT executed\n";
    echo "lastInsertId() returned: {$test_id}\n";

    if ($test_id > 0) {
        echo "<span class='success'>✓ INSERT and lastInsertId() working correctly</span>\n";

        // Clean up test record
        $pdo->exec("DELETE FROM prehospital_forms WHERE form_number = '{$test_form_number}'");
        echo "  (Test record deleted)\n";
    } else {
        echo "<span class='error'>✗ lastInsertId() returned 0 - there's a problem with the database</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 7. Check PDO connection attributes
echo "\n<span class='info'>━━━ STEP 7: Checking PDO Configuration ━━━</span>\n";
try {
    echo "PDO Error Mode: " . $pdo->getAttribute(PDO::ATTR_ERRMODE) . "\n";
    echo "PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

    if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
        echo "<span class='warning'>⚠ Setting PDO error mode to EXCEPTION...</span>\n";
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<span class='success'>✓ PDO error mode set to EXCEPTION</span>\n";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

// 8. Show current draft records
echo "\n<span class='info'>━━━ STEP 8: Current Draft Records ━━━</span>\n";
try {
    $stmt = $pdo->query("SELECT id, form_number, status, created_at FROM prehospital_forms WHERE status = 'draft' ORDER BY id DESC LIMIT 5");
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($drafts)) {
        echo "<span class='warning'>No draft records found</span>\n";
    } else {
        echo "Recent drafts:\n";
        foreach ($drafts as $draft) {
            echo "  ID: {$draft['id']}, Form: {$draft['form_number']}, Created: {$draft['created_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Error: {$e->getMessage()}</span>\n";
}

echo "\n<span class='success'>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span>\n";
echo "<span class='success'>DATABASE DIAGNOSTIC COMPLETE</span>\n";
echo "<span class='success'>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</span>\n";

echo "</pre>\n";
echo "<p><a href='TONYANG.php' style='color: #00ff00;'>← Back to Form</a> | ";
echo "<a href='check_drafts_debug.php' style='color: #00aaff;'>View Debug Page</a></p>";
echo "</body></html>";
?>
