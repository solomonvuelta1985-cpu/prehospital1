<?php
/**
 * Login Page - Enhanced Corporate Design
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
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (!verify_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
    } else {
        // reCAPTCHA verification is handled inside login_user()
        $result = login_user($username, $password, $recaptcha_response);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In - Baggao Rescue 116</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    
    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #0284c7; /* Medical Blue */
            --brand-primary-hover: #0369a1;
            --brand-dark: #0f172a;    /* Navy */
            --brand-surface: #ffffff;
            --text-main: #334155;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --input-focus: rgba(2, 132, 199, 0.15);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc; /* Very light slate for mobile background */
            min-height: 100vh;
            display: flex;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* --- LEFT SIDE: Brand Visual --- */
        .brand-section {
            flex: 1;
            background-color: var(--brand-dark);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 60px 80px;
            overflow: hidden;
            color: white;
            /* Force hardware acceleration for smoother animation */
            transform: translateZ(0);
        }

        /* Static Background Image */
        .brand-bg-image {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url('https://images.unsplash.com/photo-1516574187841-693083f049ce?ixlib=rb-4.0.3&auto=format&fit=crop&w=1800&q=80');
            background-size: cover;
            background-position: center;
            opacity: 0.2;
            mix-blend-mode: luminosity;
            z-index: 0;
        }

        /* Gradient Overlay */
        .brand-gradient {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(2, 132, 199, 0.85) 100%);
            z-index: 1;
        }

        /* Particles Container */
        #tsparticles {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 2;
        }

        .brand-content {
            position: relative;
            z-index: 10;
            max-width: 600px;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease-out;
        }

        .logo-img {
            height: 48px;
            width: auto;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }

        .brand-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -0.03em;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: fadeInLeft 0.8s ease-out 0.2s backwards;
        }

        .brand-desc {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
            margin-bottom: 56px;
            font-weight: 400;
            animation: fadeIn 1s ease-out 0.4s backwards;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            animation: fadeInUp 0.8s ease-out 0.6s backwards;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 24px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, background 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .feature-card i {
            font-size: 1.75rem;
            color: #7dd3fc;
            margin-bottom: 16px;
            display: block;
        }

        .feature-card h5 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.01em;
        }

        .feature-card p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
            line-height: 1.5;
        }

        .brand-footer {
            position: relative;
            z-index: 10;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.5);
        }

        /* --- RIGHT SIDE: Login Form --- */
        .form-section {
            width: 520px;
            min-width: 520px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            position: relative;
            z-index: 20;
            box-shadow: -20px 0 50px rgba(0,0,0,0.05);
            overflow-y: auto; /* Handle vertical overflow on small screens */
        }

        .mobile-branding {
            display: none;
            text-align: center;
            margin-bottom: 32px;
        }
        
        .mobile-branding img {
            height: 64px;
            margin-bottom: 16px;
        }

        .form-header {
            margin-bottom: 36px;
        }

        .form-header h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand-dark);
            margin-bottom: 12px;
            letter-spacing: -0.03em;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            height: 54px;
            padding: 10px 16px;
            padding-left: 48px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.25s ease;
            background-color: #ffffff;
            color: var(--brand-dark);
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px var(--input-focus);
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.25rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-control:focus ~ .input-icon {
            color: var(--brand-primary);
        }

        .btn-primary {
            width: 100%;
            height: 54px;
            background-color: var(--brand-primary);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: var(--brand-primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(2, 132, 199, 0.4);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background-color: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert-custom {
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.5;
            animation: slideDown 0.3s ease-out;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .recaptcha-container {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
            /* Prevent reCAPTCHA from breaking layout on very small screens */
            transform-origin: center;
        }

        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 40px 0 32px 0;
            position: relative;
        }

        .divider span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 0 12px;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sys-info {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* --- ANIMATIONS --- */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInLeft { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- RESPONSIVE DESIGN --- */
        
        /* 1. Tablet & Small Desktop (Hide Left Panel, Center Form) */
        @media (max-width: 1200px) {
            .brand-section {
                padding: 40px;
            }
            .brand-title { font-size: 2.5rem; }
            .form-section { min-width: 480px; width: 480px; }
        }

        /* 2. Tablets and Large Phones */
        @media (max-width: 992px) {
            body {
                flex-direction: column;
                justify-content: center;
                align-items: center;
                background-color: #f1f5f9; /* Light gray bg for card effect */
            }

            .brand-section {
                display: none; /* Hide brand section on mobile/tablet */
            }

            .form-section {
                width: 100%;
                max-width: 480px;
                min-width: auto;
                height: auto;
                min-height: auto;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
                margin: 20px;
            }

            .mobile-branding {
                display: block;
            }
        }

        /* 3. Mobile Phones */
        @media (max-width: 576px) {
            body {
                background-color: #ffffff; /* White bg for full screen mobile */
                align-items: flex-start; /* Stick to top */
            }

            .form-section {
                box-shadow: none;
                padding: 24px;
                margin: 0;
                border-radius: 0;
                max-width: 100%;
                height: 100vh; /* Full height */
                justify-content: flex-start; /* Start from top */
                overflow-y: auto;
            }

            .mobile-branding { margin-top: 20px; }

            .form-header h2 { font-size: 1.75rem; }
            
            .recaptcha-container {
                transform: scale(0.9); /* Scale down reCAPTCHA slightly */
            }
        }

        /* 4. Landscape Mobile (Short Screens) */
        @media (max-height: 700px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding: 20px 0;
                background-color: #f1f5f9;
            }
            
            .form-section {
                margin: 0 auto;
                padding: 30px;
                height: auto;
            }
        }
    </style>
</head>
<body>

    <!-- LEFT PANEL: Visual & Branding -->
    <div class="brand-section">
        <!-- 1. Static BG -->
        <div class="brand-bg-image"></div>
        <!-- 2. Gradient Overlay -->
        <div class="brand-gradient"></div>
        <!-- 3. Particles -->
        <div id="tsparticles"></div>
        
        <div class="brand-content">
            <div class="logo-wrapper">
                <img src="uploads/logo.png" alt="Logo" class="logo-img">
                <span style="font-weight: 700; font-size: 1.25rem; letter-spacing: 0.5px;">BAGGAO RESCUE</span>
            </div>
            
            <h1 class="brand-title">Rescue 116<br>Operations</h1>
            <p class="brand-desc">
                Advanced Pre-Hospital Care & Emergency Response Management System.
            </p>

            <div class="feature-grid">
                <div class="feature-card">
                    <i class="bi bi-shield-check"></i>
                    <h5>Secure Access</h5>
                    <p>Encrypted data transmission</p>
                </div>
                <div class="feature-card">
                    <i class="bi bi-activity"></i>
                    <h5>Real-time Vitals</h5>
                    <p>Live patient monitoring logs</p>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            &copy; <?php echo date('Y'); ?> Municipality of Baggao. Authorized Personnel Only.
        </div>
    </div>

    <!-- RIGHT PANEL: Login Form -->
    <div class="form-section">
        
        <!-- Mobile Branding (Logo on Top for Small Screens) -->
        <div class="mobile-branding">
            <img src="uploads/logo.png" alt="Logo">
            <h3 style="font-weight: 800; color: var(--brand-dark); margin:0;">Rescue 116</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem;">Official Operations Portal</p>
        </div>

        <div class="form-header">
            <h2>Welcome Back</h2>
            <?php if ($is_restricted): ?>
                <p class="text-danger fw-bold"><i class="bi bi-lock-fill"></i> Account Access Restricted</p>
            <?php else: ?>
                <p>Sign in to access your dashboard.</p>
            <?php endif; ?>
        </div>

        <!-- Restricted Alert -->
        <?php if ($is_restricted): ?>
            <div class="alert-custom alert-error">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <strong>Security Lockout</strong><br>
                    Your account has been temporarily restricted due to excessive failed attempts. Please contact the IT administrator.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Enter your username"
                           value="<?php echo htmlspecialchars($restricted_username); ?>"
                           <?php echo $is_restricted ? 'disabled' : 'required autofocus'; ?>>
                    <i class="bi bi-person input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="••••••••"
                           <?php echo $is_restricted ? 'disabled' : 'required'; ?>>
                    <i class="bi bi-key input-icon"></i>
                </div>
            </div>

            <?php if (!$is_restricted): ?>
                <div class="recaptcha-container">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-primary" disabled>
                    <i class="bi bi-lock"></i> Account Locked
                </button>
            <?php endif; ?>
        </form>

        <div class="divider">
            <span>SECURE SYSTEM</span>
        </div>

        <div class="sys-info">
            <i class="bi bi-shield-lock-fill text-success"></i>
            <span>256-bit SSL Encrypted</span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <!-- TSParticles Library -->
    <script src="https://cdn.jsdelivr.net/npm/tsparticles-slim@2.0.6/tsparticles.slim.bundle.min.js"></script>
    
    <script>
        // 1. Initialize Particles (The "Medical Connections" effect)
        (async () => {
            await tsParticles.load("tsparticles", {
                particles: {
                    number: { value: 60, density: { enable: true, value_area: 800 } },
                    color: { value: "#ffffff" },
                    shape: { type: "circle" },
                    opacity: { value: 0.3, random: true },
                    size: { value: 3, random: true },
                    move: {
                        enable: true,
                        speed: 1.5, // Slow movement
                        direction: "none",
                        random: false,
                        straight: false,
                        out_mode: "out",
                        bounce: false,
                    },
                    links: {
                        enable: true,
                        distance: 150,
                        color: "#ffffff",
                        opacity: 0.2, // Subtle connecting lines
                        width: 1
                    },
                },
                interactivity: {
                    detect_on: "canvas",
                    events: {
                        onhover: { enable: true, mode: "grab" },
                        onclick: { enable: true, mode: "push" },
                        resize: true
                    },
                    modes: {
                        grab: { distance: 140, line_linked: { opacity: 0.5 } },
                        push: { particles_nb: 4 }
                    }
                },
                retina_detect: true
            });
        })();

        // 2. Configure Notifications
        Notiflix.Notify.init({
            width: '380px',
            position: 'right-top',
            distance: '20px',
            borderRadius: '10px',
            fontFamily: 'Plus Jakarta Sans',
            fontSize: '14px',
            cssAnimationStyle: 'zoom',
            success: { background: '#10b981', textColor: '#fff', notiflixIconColor: '#fff' },
            failure: { background: '#ef4444', textColor: '#fff', notiflixIconColor: '#fff' },
            warning: { background: '#f59e0b', textColor: '#fff', notiflixIconColor: '#fff' },
            info:    { background: '#0ea5e9', textColor: '#fff', notiflixIconColor: '#fff' }
        });

        // 3. Handle Flash Messages
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