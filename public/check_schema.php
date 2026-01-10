<?php
/**
 * Check Database Schema
 * DELETE THIS FILE after debugging!
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';

header('Content-Type: text/plain');

echo "=== DATABASE SCHEMA CHECK ===\n\n";

try {
    // Check the id column structure
    $stmt = $pdo->query('DESCRIBE prehospital_forms');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Table: prehospital_forms\n\n";

    foreach ($columns as $column) {
        if ($column['Field'] === 'id') {
            echo "ID Column Details:\n";
            print_r($column);
            echo "\n";
        }
    }

    echo "\nAll columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']} {$column['Extra']}\n";
    }

    // Check for actual draft records
    echo "\n=== SAMPLE DRAFT RECORDS ===\n\n";
    $sample_sql = "SELECT id, form_number, patient_name FROM prehospital_forms WHERE status = 'draft' LIMIT 3";
    $sample_stmt = $pdo->query($sample_sql);
    $samples = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $idx => $sample) {
        echo "Sample #" . ($idx + 1) . ":\n";
        echo "  ID: " . var_export($sample['id'], true) . " (type: " . gettype($sample['id']) . ")\n";
        echo "  Form Number: " . var_export($sample['form_number'], true) . "\n";
        echo "  Patient Name: " . var_export($sample['patient_name'], true) . "\n\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== DELETE THIS FILE AFTER DEBUGGING ===\n";
