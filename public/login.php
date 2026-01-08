<?php
/**
 * Login Page
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (!verify_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
    } elseif (!verify_recaptcha($recaptcha_response)) {
        set_flash('Please complete the CAPTCHA verification', 'error');
    } else {
        $result = login_user($username, $password, $recaptcha_response);

        if ($result['success']) {
            redirect('dashboard.php');
        } else {
            set_flash($result['message'], 'error');
        }
    }
}

$csrf_token = generate_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pre-Hospital Care System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 20px;
        }

        .login-wrapper {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-right: 1px solid #e5e7eb;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            margin-bottom: 30px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.1));
        }

        .brand-title {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 15px;
            color: #64748b;
            font-weight: 400;
            line-height: 1.5;
        }

        .login-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 40px;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 14px;
            color: #64748b;
            font-weight: 400;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.08);
            outline: none;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .btn-login {
            background: #0066cc;
            border: none;
            color: white;
            padding: 13px 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: #0052a3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.2);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer p {
            color: #94a3b8;
            font-size: 13px;
            margin: 0;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 450px;
            }

            .login-left {
                padding: 40px 30px;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
            }

            .logo-container img {
                width: 140px;
                height: 140px;
            }

            .brand-title {
                font-size: 22px;
            }

            .brand-subtitle {
                font-size: 14px;
            }

            .login-right {
                padding: 40px 30px;
            }

            .login-header h1 {
                font-size: 24px;
            }
        }

        /* reCAPTCHA responsive fix */
        .recaptcha-wrapper {
            transform: scale(0.95);
            transform-origin: 0 0;
            margin-bottom: 16px;
        }

        @media (max-width: 400px) {
            .recaptcha-wrapper {
                transform: scale(0.85);
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Branding -->
        <div class="login-left">
            <div class="logo-container">
                <img src="uploads/logo.png" alt="Baggao Rescue Logo">
                <h2 class="brand-title">Baggao Rescue</h2>
                <p class="brand-subtitle">Pre-Hospital Care Records System</p>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Please sign in to your account to continue</p>
            </div>

            <?php // Flash messages handled by Notiflix ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-icon">
                        <i class="bi bi-person-circle"></i>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-icon">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="recaptcha-wrapper">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                </div>

                <button type="submit" class="btn btn-login">
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Baggao Rescue. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        // Configure Notiflix
        Notiflix.Notify.init({
            width: '320px',
            position: 'right-top',
            distance: '15px',
            timeout: 4000,
            fontSize: '15px',
            cssAnimationStyle: 'from-right',
            success: {
                background: '#10b981',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            failure: {
                background: '#ef4444',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            warning: {
                background: '#f59e0b',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
            info: {
                background: '#0066cc',
                textColor: '#fff',
                notiflixIconColor: '#fff',
            },
        });

        // Show flash messages with Notiflix
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['flash_message'])): ?>
                <?php
                $flash = $_SESSION['flash_message'];
                $type = $flash['type'];
                $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['flash_message']);
                ?>
                <?php if ($type === 'success'): ?>
                    Notiflix.Notify.success('<?php echo $message; ?>', { timeout: 3000 });
                <?php elseif ($type === 'error'): ?>
                    Notiflix.Notify.failure('<?php echo $message; ?>', { timeout: 4000 });
                <?php elseif ($type === 'warning'): ?>
                    Notiflix.Notify.warning('<?php echo $message; ?>', { timeout: 3500 });
                <?php else: ?>
                    Notiflix.Notify.info('<?php echo $message; ?>', { timeout: 3000 });
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
