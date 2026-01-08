<?php
/**
 * PHP Error Log Viewer
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

$log_file = dirname(__DIR__) . '/php_error.log';
$log_exists = file_exists($log_file);
$log_content = $log_exists ? file_get_contents($log_file) : 'Log file not found';
$log_size = $log_exists ? filesize($log_file) : 0;
$log_lines = $log_exists ? count(file($log_file)) : 0;

// Handle clear log action
if (isset($_POST['clear_log'])) {
    file_put_contents($log_file, "Log cleared at " . date('Y-m-d H:i:s') . "\n");
    header('Location: view_logs.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Error Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        .log-container {
            max-width: 1400px;
            margin: 20px auto;
            background: #252526;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
        .log-header {
            background: #2d2d30;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid #007acc;
        }
        .log-header h1 {
            color: #007acc;
            margin: 0;
            font-size: 24px;
        }
        .log-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        .log-stat {
            color: #858585;
            font-size: 14px;
        }
        .log-stat strong {
            color: #d4d4d4;
        }
        .log-content {
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
            background: #1e1e1e;
            border-radius: 0 0 8px 8px;
        }
        .log-content pre {
            margin: 0;
            color: #d4d4d4;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-line-error {
            color: #f48771;
            font-weight: bold;
        }
        .log-line-warning {
            color: #dcdcaa;
        }
        .log-line-success {
            color: #4ec9b0;
        }
        .log-line-info {
            color: #9cdcfe;
        }
        .btn-custom {
            background: #007acc;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-custom:hover {
            background: #005a9e;
        }
        .btn-danger-custom {
            background: #f14c4c;
        }
        .btn-danger-custom:hover {
            background: #d13838;
        }
        .btn-success-custom {
            background: #16825d;
        }
        .btn-success-custom:hover {
            background: #0e6345;
        }
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #1e1e1e;
        }
        ::-webkit-scrollbar-thumb {
            background: #424242;
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #4e4e4e;
        }
    </style>
</head>
<body>
    <div class="log-container">
        <div class="log-header">
            <h1><i class="bi bi-file-earmark-text"></i> PHP Error Log</h1>
            <div class="log-stats">
                <div class="log-stat">
                    <i class="bi bi-file-earmark"></i>
                    <strong>Size:</strong> <?php echo number_format($log_size / 1024, 2); ?> KB
                </div>
                <div class="log-stat">
                    <i class="bi bi-list-ol"></i>
                    <strong>Lines:</strong> <?php echo number_format($log_lines); ?>
                </div>
                <div class="log-stat">
                    <i class="bi bi-clock"></i>
                    <strong>Last Modified:</strong> <?php echo $log_exists ? date('M d, Y h:i:s A', filemtime($log_file)) : 'N/A'; ?>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <button onclick="location.reload()" class="btn-custom">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button onclick="autoScroll()" class="btn-custom btn-success-custom" id="autoScrollBtn">
                    <i class="bi bi-arrow-down-circle"></i> Auto-scroll: OFF
                </button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_log" class="btn-custom btn-danger-custom" onclick="return confirm('Are you sure you want to clear the log?')">
                        <i class="bi bi-trash"></i> Clear Log
                    </button>
                </form>
                <a href="TONYANG.php" class="btn-custom">
                    <i class="bi bi-arrow-left"></i> Back to Form
                </a>
            </div>
        </div>
        <div class="log-content" id="logContent">
            <pre><?php
            if ($log_exists && !empty($log_content)) {
                $lines = explode("\n", $log_content);
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;

                    // Color code based on content
                    if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) {
                        echo '<span class="log-line-error">' . htmlspecialchars($line) . '</span>' . "\n";
                    } elseif (stripos($line, 'warning') !== false) {
                        echo '<span class="log-line-warning">' . htmlspecialchars($line) . '</span>' . "\n";
                    } elseif (stripos($line, 'success') !== false || stripos($line, 'Draft ID:') !== false) {
                        echo '<span class="log-line-success">' . htmlspecialchars($line) . '</span>' . "\n";
                    } elseif (stripos($line, '━━━') !== false) {
                        echo '<span class="log-line-info">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
            } else {
                echo '<span class="log-line-warning">No log entries yet. Errors will appear here.</span>';
            }
            ?></pre>
        </div>
    </div>

    <script>
        let autoScrollEnabled = false;
        let autoScrollInterval = null;

        function autoScroll() {
            const btn = document.getElementById('autoScrollBtn');
            const content = document.getElementById('logContent');

            autoScrollEnabled = !autoScrollEnabled;

            if (autoScrollEnabled) {
                btn.innerHTML = '<i class="bi bi-arrow-down-circle-fill"></i> Auto-scroll: ON';
                btn.style.background = '#16825d';

                // Scroll to bottom initially
                content.scrollTop = content.scrollHeight;

                // Refresh every 3 seconds
                autoScrollInterval = setInterval(() => {
                    location.reload();
                }, 3000);
            } else {
                btn.innerHTML = '<i class="bi bi-arrow-down-circle"></i> Auto-scroll: OFF';
                btn.style.background = '#007acc';
                clearInterval(autoScrollInterval);
            }
        }

        // Scroll to bottom on load
        window.addEventListener('load', () => {
            const content = document.getElementById('logContent');
            content.scrollTop = content.scrollHeight;
        });
    </script>
</body>
</html>
