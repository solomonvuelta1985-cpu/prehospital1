<?php
/**
 * Check actual column names in prehospital_forms table
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';

global $pdo;

echo "<pre>";
echo "=== TIME/DATETIME COLUMNS IN prehospital_forms ===\n\n";

$stmt = $pdo->query('DESCRIBE prehospital_forms');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    if (stripos($col['Type'], 'time') !== false || stripos($col['Type'], 'datetime') !== false) {
        echo $col['Field'] . "\n";
    }
}

echo "\n=== ALL COLUMNS ===\n\n";
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . " - " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "</pre>";
?>
