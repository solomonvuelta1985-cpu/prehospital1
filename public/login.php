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
    <style>
        body {
            background: linear-gradient(135deg, #0066cc, #004d99);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #0066cc, #004d99);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.75rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 1.5px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.15);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0066cc, #004d99);
            border: none;
            color: white;
            padding: 0.85rem;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 102, 204, 0.3);
        }
        
        .login-footer {
            text-align: center;
            padding: 1rem 2rem 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 1.5px solid #dee2e6;
            border-right: none;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: #0066cc;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="bi bi-shield-lock"></i> Login</h1>
            <p>Pre-Hospital Care System</p>
        </div>
        
        <div class="login-body">
            <?php show_flash(); ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter username" required autofocus>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter password" required>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                </div>

                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
        </div>
        
        <div class="login-footer">
           
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</body>
</html>
