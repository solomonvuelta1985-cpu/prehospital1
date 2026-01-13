<?php
/**
 * Debug Restriction System
 * DELETE THIS FILE AFTER CHECKING!
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Restriction Debug</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}";
echo "pre{background:white;padding:10px;border:1px solid #ddd;overflow-x:auto;}</style></head><body>";

echo "<h2>🔍 Restriction System Debug</h2>";

try {
    // 1. Check if column exists
    echo "<h3>1. Database Column Check</h3>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_is_restricted = false;
    $has_failed_attempts = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'is_restricted') {
            $has_is_restricted = true;
        }
        if ($column['Field'] === 'failed_attempts') {
            $has_failed_attempts = true;
        }
    }

    echo "<ul>";
    echo "<li><code>is_restricted</code>: " . ($has_is_restricted ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</li>";
    echo "<li><code>failed_attempts</code>: " . ($has_failed_attempts ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>") . "</li>";
    echo "</ul>";

    if (!$has_is_restricted) {
        echo "<div class='error'>";
        echo "<h3>❌ CRITICAL: is_restricted column is MISSING!</h3>";
        echo "<p>Run this SQL in phpMyAdmin:</p>";
        echo "<pre>";
        echo "ALTER TABLE `users`\n";
        echo "ADD COLUMN `is_restricted` TINYINT(1) NOT NULL DEFAULT 0\n";
        echo "COMMENT 'User restricted from login (1 = restricted, 0 = not restricted)'\n";
        echo "AFTER `status`;";
        echo "</pre>";
        echo "</div>";
    } else {
        // 2. Check current users
        echo "<h3>2. Current Users Status</h3>";
        $users_stmt = $pdo->query("SELECT id, username, full_name, status, is_restricted, failed_attempts, locked_until FROM users ORDER BY id");
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            echo "<pre>";
            echo sprintf("%-5s %-20s %-15s %-10s %-12s %-8s %s\n",
                "ID", "Full Name", "Username", "Status", "Restricted", "Attempts", "Locked Until"
            );
            echo str_repeat("-", 100) . "\n";

            $restricted_count = 0;
            foreach ($users as $user) {
                $restricted = $user['is_restricted'] ? 'YES' : 'No';
                if ($user['is_restricted']) $restricted_count++;

                $locked = $user['locked_until'] ? date('Y-m-d H:i', strtotime($user['locked_until'])) : 'Not locked';

                echo sprintf("%-5s %-20s %-15s %-10s %-12s %-8s %s\n",
                    $user['id'],
                    substr($user['full_name'], 0, 20),
                    $user['username'],
                    $user['status'],
                    $restricted,
                    $user['failed_attempts'] ?? 0,
                    $locked
                );
            }
            echo "</pre>";

            echo "<p><strong>Total Restricted Users: {$restricted_count}</strong></p>";

            // 3. Check API file
            echo "<h3>3. API Endpoint Check</h3>";
            $api_file = '../api/admin/toggle_user_restriction.php';
            if (file_exists($api_file)) {
                echo "<span class='success'>✓ API file exists: {$api_file}</span><br>";
                $api_content = file_get_contents($api_file);
                if (strpos($api_content, 'failed_attempts = 0') !== false) {
                    echo "<span class='success'>✓ API resets failed_attempts when unrestricting</span><br>";
                } else {
                    echo "<span class='error'>✗ API doesn't reset failed_attempts</span><br>";
                }
            } else {
                echo "<span class='error'>✗ API file MISSING: {$api_file}</span><br>";
            }

            // 4. Check users.php
            echo "<h3>4. Admin Users Page Check</h3>";
            $users_page = 'admin/users.php';
            if (file_exists($users_page)) {
                echo "<span class='success'>✓ users.php exists</span><br>";
                $users_content = file_get_contents($users_page);
                if (strpos($users_content, 'toggleRestriction') !== false) {
                    echo "<span class='success'>✓ toggleRestriction() function found</span><br>";
                } else {
                    echo "<span class='error'>✗ toggleRestriction() function MISSING</span><br>";
                }
                if (strpos($users_content, 'is_restricted') !== false) {
                    echo "<span class='success'>✓ is_restricted column queried</span><br>";
                } else {
                    echo "<span class='error'>✗ is_restricted NOT in query</span><br>";
                }
            } else {
                echo "<span class='error'>✗ users.php MISSING</span><br>";
            }

            // 5. Test query
            echo "<h3>5. Test Unrestrict Query</h3>";
            echo "<p>Example SQL to unrestrict a user:</p>";
            echo "<pre>";
            echo "UPDATE users\n";
            echo "SET is_restricted = 0, failed_attempts = 0, locked_until = NULL\n";
            echo "WHERE id = ?;";
            echo "</pre>";

        } else {
            echo "<p>No users found in database.</p>";
        }
    }

    echo "<hr>";
    echo "<h3>✅ Next Steps:</h3>";
    echo "<ol>";
    echo "<li>If column is missing, run the SQL above</li>";
    echo "<li>Login as admin to: <a href='admin/users.php'>User Management</a></li>";
    echo "<li>Look for users with 'Restricted' badge</li>";
    echo "<li>Click green 🔓 Unlock button</li>";
    echo "<li>Check browser console (F12) for JavaScript errors</li>";
    echo "</ol>";

    echo "<hr>";
    echo "<p><strong>⚠️ DELETE THIS FILE (check_restriction_debug.php) AFTER CHECKING!</strong></p>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
