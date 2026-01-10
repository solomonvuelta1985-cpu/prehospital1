<?php
/**
 * Check if is_restricted column exists
 * DELETE THIS FILE after checking!
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Column Check</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}";
echo "pre{background:white;padding:10px;border:1px solid #ddd;}</style></head><body>";

echo "<h2>🔍 Checking Database Structure</h2>";

try {
    // Check if column exists
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_is_restricted = false;
    $has_failed_attempts = false;
    $has_locked_until = false;

    echo "<h3>Users Table Columns:</h3>";
    echo "<pre>";
    foreach ($columns as $column) {
        echo sprintf("%-20s %-15s %-8s %-8s %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Extra']
        );

        if ($column['Field'] === 'is_restricted') {
            $has_is_restricted = true;
        }
        if ($column['Field'] === 'failed_attempts') {
            $has_failed_attempts = true;
        }
        if ($column['Field'] === 'locked_until') {
            $has_locked_until = true;
        }
    }
    echo "</pre>";

    echo "<h3>Required Columns Status:</h3>";
    echo "<ul>";
    echo "<li><code>is_restricted</code>: " . ($has_is_restricted ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</li>";
    echo "<li><code>failed_attempts</code>: " . ($has_failed_attempts ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</li>";
    echo "<li><code>locked_until</code>: " . ($has_locked_until ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</li>";
    echo "</ul>";

    if (!$has_is_restricted) {
        echo "<hr>";
        echo "<h3 class='error'>❌ MISSING COLUMN: is_restricted</h3>";
        echo "<p><strong>You MUST run this SQL in phpMyAdmin:</strong></p>";
        echo "<pre style='background:#ffe6e6;border-color:#ff0000;'>";
        echo "ALTER TABLE `users`\n";
        echo "ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0\n";
        echo "COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'\n";
        echo "AFTER `status`;\n\n";
        echo "ALTER TABLE `users`\n";
        echo "ADD INDEX `idx_is_restricted` (`is_restricted`);";
        echo "</pre>";
    }

    // Check current restricted users
    echo "<hr>";
    echo "<h3>Current Users Status:</h3>";

    if ($has_is_restricted) {
        $users_stmt = $pdo->query("SELECT id, username, status, is_restricted, failed_attempts, locked_until FROM users ORDER BY id");
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            echo "<pre>";
            echo sprintf("%-5s %-15s %-10s %-12s %-15s %s\n",
                "ID", "Username", "Status", "Restricted", "Failed Attempts", "Locked Until"
            );
            echo str_repeat("-", 80) . "\n";
            foreach ($users as $user) {
                $restricted = $user['is_restricted'] ? 'YES' : 'No';
                $locked = $user['locked_until'] ? date('Y-m-d H:i', strtotime($user['locked_until'])) : 'Not locked';

                echo sprintf("%-5s %-15s %-10s %-12s %-15s %s\n",
                    $user['id'],
                    $user['username'],
                    $user['status'],
                    $restricted,
                    $user['failed_attempts'] ?? 0,
                    $locked
                );
            }
            echo "</pre>";

            $restricted_count = count(array_filter($users, fn($u) => $u['is_restricted'] == 1));
            echo "<p><strong>Total Restricted Users: {$restricted_count}</strong></p>";
        } else {
            echo "<p>No users found in database.</p>";
        }
    } else {
        echo "<p class='error'>Cannot check user status - is_restricted column missing!</p>";
    }

    echo "<hr>";
    echo "<h3>✅ Next Steps:</h3>";
    if (!$has_is_restricted) {
        echo "<ol>";
        echo "<li>Copy the SQL code above</li>";
        echo "<li>Open phpMyAdmin</li>";
        echo "<li>Select your database: <code>pre_hospital_db</code></li>";
        echo "<li>Click 'SQL' tab</li>";
        echo "<li>Paste and execute the SQL</li>";
        echo "<li>Refresh this page to verify</li>";
        echo "</ol>";
    } else {
        echo "<p class='success'>✓ All required columns exist! You can now test the restriction feature.</p>";
        echo "<p><strong>Test by:</strong></p>";
        echo "<ol>";
        echo "<li>Try logging in with wrong password 5 times</li>";
        echo "<li>Should see: 'Your account has been restricted...'</li>";
        echo "<li>Login as admin to unrestrict the user</li>";
        echo "</ol>";
    }

    echo "<hr>";
    echo "<p><strong>⚠️ DELETE THIS FILE (check_restriction_column.php) AFTER CHECKING!</strong></p>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
