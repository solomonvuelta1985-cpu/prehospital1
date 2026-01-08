<?php
define('APP_ACCESS', true);
require_once 'includes/config.php';

global $pdo;

echo "Fixing 'age' column to allow NULL...\n";

try {
    $pdo->exec("ALTER TABLE prehospital_forms MODIFY COLUMN age INT(11) NULL DEFAULT NULL");
    echo "✓ SUCCESS: 'age' column now allows NULL\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
?>
