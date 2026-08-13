<?php
/**
 * Login Page - Civic Healthcare Portal
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        redirect('../admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

// Check if user is restricted (to disable form)
$is_restricted = false;
$restricted_username = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!verify_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
    } else {
        $result = login_user($username, $password);

        if ($result['success']) {
            if (is_admin()) {
                redirect('../admin/dashboard.php');
            } else {
                redirect('dashboard.php');
            }
        } else {
            set_flash($result['message'], 'error');

            // Check if this is a restriction error
            if (strpos($result['message'], 'restricted') !== false) {
                $is_restricted = true;
                $restricted_username = $username;
            }
        }
    }
}

$csrf_token = generate_token();

// Time-of-day greeting (Asia/Manila, UTC+8)
$manila_hour = (int) (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('G');
if ($manila_hour < 12) {
    $greeting = $manila_hour < 5 ? 'Good evening' : 'Good morning';
} elseif ($manila_hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#edf7f8">
    <title>Sign In - Baggao Rescue 116</title>

    <link href="vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendor/bootstrap-icons/1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/notiflix/3.2.6/notiflix-3.2.6.min.css">
    <link href="css/global-responsive.css" rel="stylesheet">
    <link href="vendor/fonts/brand.css" rel="stylesheet">
    <link href="css/login.css?v=<?php echo md5_file(__DIR__ . '/css/login.css'); ?>" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-orbit login-orbit-one" aria-hidden="true"></div>
    <div class="login-orbit login-orbit-two" aria-hidden="true"></div>
    <div class="login-grid" aria-hidden="true"></div>

    <div class="login-shell">
        <header class="portal-header">
            <a class="portal-brand" href="login.php" aria-label="Baggao Rescue 116 sign in">
                <span class="portal-brand-mark" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                <span class="portal-brand-copy">
                    <strong>Baggao Rescue 116</strong>
                    <small>MDRRMO &middot; PRE-HOSPITAL CARE</small>
                </span>
            </a>
        </header>

        <main class="login-main">
            <section class="login-card" aria-labelledby="signInHeading">
                <div class="card-logo-wrap">
                    <img src="uploads/logo.png" alt="Baggao Rescue 116" class="card-logo">
                </div>

                <div class="card-heading">
                    <h1 id="signInHeading">Welcome back</h1>
                    <p>Sign in to continue to the responder portal.</p>
                </div>

                <?php if ($is_restricted): ?>
                    <div class="restriction-badge"><i class="bi bi-lock-fill" aria-hidden="true"></i> Account access restricted</div>
                    <div class="restriction-notice" role="alert">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div>
                            <strong>Security lockout</strong>
                            <span>Your account has been temporarily restricted due to excessive failed attempts. Please contact the IT administrator.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="field">
                        <div class="field-label-row">
                            <label for="username">Username</label>
                        </div>
                        <div class="field-input-wrap">
                            <i class="bi bi-person field-icon" aria-hidden="true"></i>
                            <input type="text" id="username" name="username"
                                   autocomplete="username"
                                   placeholder="Enter your username"
                                   value="<?php echo htmlspecialchars($restricted_username); ?>"
                                   <?php echo $is_restricted ? 'disabled' : 'required autofocus'; ?>>
                        </div>
                    </div>

                    <div class="field has-toggle">
                        <div class="field-label-row">
                            <label for="password">Password</label>
                        </div>
                        <div class="field-input-wrap">
                            <i class="bi bi-key field-icon" aria-hidden="true"></i>
                            <input type="password" id="password" name="password"
                                   autocomplete="current-password"
                                   placeholder="Enter your password"
                                   <?php echo $is_restricted ? 'disabled' : 'required'; ?>>
                            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password" aria-pressed="false" <?php echo $is_restricted ? 'disabled' : ''; ?>>Show</button>
                        </div>
                    </div>

                    <?php if (!$is_restricted): ?>
                        <button type="submit" class="btn-primary-cta">
                            <span>Continue to portal</span>
                            <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-primary-cta" disabled>
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <span>Account access locked</span>
                        </button>
                    <?php endif; ?>
                </form>

                <!-- Biometric login (shown by JS if WebAuthn / Flutter biometric is available) -->
                <div id="webauthn-section">
                    <div class="or-divider"><span>or use device authentication</span></div>
                    <button type="button" id="webauthn-login-btn" class="btn-secondary-cta" aria-label="Sign in with biometric authentication">
                        <i class="bi bi-fingerprint" aria-hidden="true"></i>
                        <span>Use fingerprint or Face ID</span>
                    </button>
                    <div id="webauthn-status" role="status" aria-live="polite"></div>
                </div>

            </section>
        </main>

        <footer class="portal-footer">
            <span>&copy; <?php echo date('Y'); ?> MDRRMO Baggao &middot; Authorized personnel only</span>
            <span class="portal-clock" id="brandClock" aria-label="Local time">--:--:-- UTC+8</span>
        </footer>
    </div>

    <script src="vendor/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/notiflix/3.2.6/notiflix-aio-3.2.6.min.js"></script>
    <script src="js/webauthn.js"></script>

    <script nonce="<?php echo CSP_NONCE; ?>">
        // Show/hide password toggle
        (function() {
            var toggle = document.getElementById('pwToggle');
            var pw = document.getElementById('password');
            if (toggle && pw) {
                toggle.addEventListener('click', function() {
                    var shown = pw.type === 'text';
                    pw.type = shown ? 'password' : 'text';
                    toggle.textContent = shown ? 'Show' : 'Hide';
                    toggle.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
                    toggle.setAttribute('aria-pressed', shown ? 'false' : 'true');
                });
            }
        })();

        // Live clock in Manila, UTC+8
        (function() {
            var desktop = document.getElementById('brandClock');
            var mobile = document.getElementById('mobileClock');
            if (!desktop && !mobile) return;
            function pad(n) { return n < 10 ? '0' + n : '' + n; }
            function tick() {
                var now = new Date();
                var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                var ph = new Date(utc + 8 * 3600000);
                var text = pad(ph.getHours()) + ':' + pad(ph.getMinutes()) + ':' + pad(ph.getSeconds()) + ' UTC+8';
                if (desktop) desktop.textContent = text;
                if (mobile) mobile.textContent = text;
            }
            tick();
            setInterval(tick, 1000);
        })();

        // Configure notifications
        Notiflix.Notify.init({
            width: '360px',
            position: 'right-top',
            distance: '16px',
            borderRadius: '14px',
            fontFamily: 'Plus Jakarta Sans',
            fontSize: '13px',
            cssAnimationStyle: 'from-right',
            success: { background: '#087f8c', textColor: '#fff', notiflixIconColor: '#fff' },
            failure: { background: '#c62828', textColor: '#fff', notiflixIconColor: '#fff' },
            warning: { background: '#b26a00', textColor: '#fff', notiflixIconColor: '#fff' },
            info:    { background: '#2557a7', textColor: '#fff', notiflixIconColor: '#fff' }
        });

        // Biometric Login Setup (Flutter native OR WebAuthn fallback)
        (function() {
            var csrfToken = '<?php echo $csrf_token; ?>';

            // Pre-populate username from localStorage for returning users
            (function() {
                var saved = localStorage.getItem('rescue116_username');
                if (saved) {
                    var field = document.getElementById('username');
                    if (field && !field.value) field.value = saved;
                }
            })();

            // Save username on password form submit
            var loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    var un = document.getElementById('username').value.trim();
                    if (un) localStorage.setItem('rescue116_username', un);
                });
            }

            function showBiometricSection() {
                var section = document.getElementById('webauthn-section');
                if (section) section.style.display = 'block';
            }

            function setBtnLoading(btn, statusEl) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> <span>Authenticating...</span>';
                statusEl.textContent = 'Follow the biometric prompt on your device...';
                statusEl.style.display = 'block';
            }

            function resetBtn(btn, statusEl) {
                statusEl.style.display = 'none';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-fingerprint" aria-hidden="true"></i> <span>Use fingerprint or Face ID</span>';
            }

            // FlutterBiometric JS channel is injected by the Flutter WebView
            if (typeof FlutterBiometric !== 'undefined') {
                showBiometricSection();

                // Flutter calls this after biometric + server auth completes
                window.flutterBiometricResult = function(success, message) {
                    var btn = document.getElementById('webauthn-login-btn');
                    var statusEl = document.getElementById('webauthn-status');
                    if (success) {
                        statusEl.textContent = 'Success! Redirecting...';
                        statusEl.style.color = '#087f8c';
                        // Flutter navigates the WebView itself via flutter_token.php
                    } else {
                        resetBtn(btn, statusEl);
                        Notiflix.Notify.failure(message || 'Biometric authentication failed.');
                    }
                };

                document.getElementById('webauthn-login-btn').addEventListener('click', function() {
                    var username = document.getElementById('username').value.trim();
                    if (!username) {
                        Notiflix.Notify.warning('Please enter your username first');
                        document.getElementById('username').focus();
                        return;
                    }
                    setBtnLoading(this, document.getElementById('webauthn-status'));
                    FlutterBiometric.postMessage(username);
                });

                return;
            }

            // Browser WebAuthn fallback
            if (!webauthnIsAvailable()) return;

            webauthnCheckPlatformAuth().then(function(available) {
                if (!available) return;
                showBiometricSection();

                document.getElementById('webauthn-login-btn').addEventListener('click', function() {
                    var username = document.getElementById('username').value.trim();
                    if (!username) {
                        Notiflix.Notify.warning('Please enter your username first');
                        document.getElementById('username').focus();
                        return;
                    }

                    var btn = this;
                    var statusEl = document.getElementById('webauthn-status');
                    setBtnLoading(btn, statusEl);

                    webauthnLogin(username, csrfToken)
                        .then(function(data) {
                            localStorage.setItem('rescue116_username', username);
                            statusEl.textContent = 'Success! Redirecting...';
                            statusEl.style.color = '#087f8c';
                            Notiflix.Notify.success('Biometric authentication successful!');
                            window.location.href = data.redirect || 'dashboard.php';
                        })
                        .catch(function(error) {
                            console.error('WebAuthn login error:', error);
                            resetBtn(btn, statusEl);
                            if (error.name === 'NotAllowedError') {
                                Notiflix.Notify.warning('Authentication was cancelled or timed out.');
                            } else {
                                Notiflix.Notify.failure(error.message || 'Biometric authentication failed. Please use your password.');
                            }
                        });
                });
            });
        })();

        // Handle server flash messages
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['flash_message'])): ?>
                <?php
                $flash = $_SESSION['flash_message'];
                $type = $flash['type'];
                $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['flash_message']);
                ?>
                <?php if ($type === 'success'): ?>
                    Notiflix.Notify.success('<?php echo $message; ?>');
                <?php elseif ($type === 'error'): ?>
                    Notiflix.Notify.failure('<?php echo $message; ?>');
                <?php elseif ($type === 'warning'): ?>
                    Notiflix.Notify.warning('<?php echo $message; ?>');
                <?php else: ?>
                    Notiflix.Notify.info('<?php echo $message; ?>');
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
