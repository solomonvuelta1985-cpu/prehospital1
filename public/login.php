<?php
/**
 * Login Page - Modern Corporate Design
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #0284c7;
            --brand-primary-light: #38bdf8;
            --brand-primary-hover: #0369a1;
            --brand-dark: #0c1222;
            --brand-dark-mid: #162032;
            --brand-surface: #ffffff;
            --brand-surface-alt: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --border-subtle: #e2e8f0;
            --border-focus: rgba(2, 132, 199, 0.12);
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--brand-surface-alt);
            min-height: 100vh;
            display: flex;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* ============================================
           LEFT PANEL: Brand Visual
           ============================================ */
        .brand-section {
            flex: 1;
            background: linear-gradient(160deg, #0c1222 0%, #0f2847 40%, #0c4a6e 70%, #0c1222 100%);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 56px 72px;
            overflow: hidden;
            color: white;
            transform: translateZ(0);
        }

        /* Animated Gradient Orbs */
        .brand-orbs {
            position: absolute;
            inset: 0;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        .brand-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            will-change: transform;
        }

        .brand-orb:nth-child(1) {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(2, 132, 199, 0.5), transparent 70%);
            top: -15%;
            left: -10%;
            animation: orbFloat1 25s ease-in-out infinite;
        }

        .brand-orb:nth-child(2) {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.4), transparent 70%);
            bottom: -10%;
            right: -8%;
            animation: orbFloat2 20s ease-in-out infinite;
        }

        .brand-orb:nth-child(3) {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.3), transparent 70%);
            top: 40%;
            left: 30%;
            animation: orbFloat3 22s ease-in-out infinite;
        }

        /* Subtle Grid Pattern */
        .brand-grid-pattern {
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 60V0h60' fill='none' stroke='rgba(255,255,255,0.03)' stroke-width='1'/%3E%3C/svg%3E");
            z-index: 2;
            pointer-events: none;
        }

        /* Particles Container */
        #tsparticles {
            position: absolute;
            inset: 0;
            z-index: 3;
        }

        .brand-content {
            position: relative;
            z-index: 10;
            max-width: 560px;
        }

        .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 48px;
            animation: fadeInDown 0.7s ease-out;
        }

        .logo-img {
            height: 44px;
            width: auto;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
        }

        .logo-text {
            font-weight: 700;
            font-size: 1.125rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.9);
        }

        .brand-title {
            font-size: clamp(2.5rem, 4vw, 3.25rem);
            font-weight: 800;
            line-height: 1.08;
            margin-bottom: 20px;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, #ffffff 0%, #bae6fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInLeft 0.7s ease-out 0.15s backwards;
        }

        .brand-desc {
            font-size: 1.0625rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
            margin-bottom: 40px;
            font-weight: 400;
            max-width: 440px;
            animation: fadeIn 0.8s ease-out 0.3s backwards;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            animation: fadeInUp 0.7s ease-out 0.45s backwards;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            padding: 22px 20px;
            border-radius: 14px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.12);
            transform: translateY(-3px);
            box-shadow: 0 16px 32px -8px rgba(0, 0, 0, 0.3);
        }

        .feature-card .feature-icon {
            font-size: 1.5rem;
            margin-bottom: 12px;
            display: inline-block;
            background: linear-gradient(135deg, var(--brand-primary-light), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .feature-card h5 {
            font-size: 0.875rem;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.01em;
            color: rgba(255, 255, 255, 0.95);
        }

        .feature-card p {
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
            line-height: 1.5;
        }

        .brand-footer {
            position: relative;
            z-index: 10;
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.35);
            letter-spacing: 0.01em;
        }

        /* ============================================
           RIGHT PANEL: Login Form
           ============================================ */
        .form-section {
            width: 540px;
            min-width: 540px;
            background: var(--brand-surface);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px 56px;
            position: relative;
            z-index: 20;
            box-shadow: -1px 0 0 var(--border-subtle), -24px 0 48px rgba(0, 0, 0, 0.03);
            overflow-y: auto;
            animation: fadeInScale 0.5s cubic-bezier(0.4, 0, 0.2, 1) 0.1s backwards;
        }

        /* Top accent bar */
        .form-decoration {
            position: absolute;
            top: 0;
            left: 56px;
            right: 56px;
            height: 3px;
            background: linear-gradient(90deg, var(--brand-primary), var(--accent-cyan), var(--brand-primary));
            border-radius: 0 0 3px 3px;
            opacity: 0.8;
        }

        .mobile-branding {
            display: none;
            text-align: center;
            margin-bottom: 32px;
        }

        .mobile-branding img {
            height: 56px;
            margin-bottom: 12px;
        }

        .mobile-branding h3 {
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 4px 0;
            font-size: 1.375rem;
            letter-spacing: -0.02em;
        }

        .mobile-branding p {
            color: var(--text-muted);
            font-size: 0.8125rem;
            margin: 0;
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 0.9375rem;
            line-height: 1.5;
            margin: 0;
        }

        /* Floating Label Input Groups */
        .form-floating-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-floating-group .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.125rem;
            pointer-events: none;
            transition: color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }

        .form-floating-group .form-control {
            height: 56px;
            padding: 22px 16px 8px 48px;
            border: 1.5px solid var(--border-subtle);
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 500;
            background: var(--brand-surface-alt);
            color: var(--text-primary);
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            -webkit-appearance: none;
        }

        .form-floating-group .form-control::placeholder {
            color: transparent;
        }

        .form-floating-group .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px var(--border-focus);
            background: var(--brand-surface);
            outline: none;
        }

        .form-floating-group .form-control:focus ~ .input-icon {
            color: var(--brand-primary);
        }

        .form-floating-group .form-label {
            position: absolute;
            top: 50%;
            left: 48px;
            transform: translateY(-50%);
            font-size: 0.9375rem;
            font-weight: 500;
            color: #94a3b8;
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
            margin: 0;
        }

        .form-floating-group .form-control:focus ~ .form-label,
        .form-floating-group .form-control:not(:placeholder-shown) ~ .form-label {
            top: 14px;
            transform: translateY(0);
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--brand-primary);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            height: 54px;
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-hover) 100%);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
            color: white;
            letter-spacing: 0.01em;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 4px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .btn-login:hover:not(:disabled)::before {
            transform: translateX(100%);
        }

        .btn-login:hover:not(:disabled) {
            box-shadow: 0 8px 24px -4px rgba(2, 132, 199, 0.45);
            transform: translateY(-1px);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 12px -2px rgba(2, 132, 199, 0.35);
        }

        .btn-login:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-login:disabled::before {
            display: none;
        }

        /* Biometric Button */
        .btn-biometric {
            width: 100%;
            height: 54px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
            border: 1.5px solid var(--border-subtle);
            background: var(--brand-surface-alt);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .btn-biometric:hover {
            border-color: var(--brand-primary);
            background: rgba(2, 132, 199, 0.04);
            color: var(--brand-primary);
            box-shadow: 0 4px 16px -4px rgba(2, 132, 199, 0.15);
        }

        .btn-biometric .bi-fingerprint {
            font-size: 1.25rem;
            background: linear-gradient(135deg, var(--brand-primary), var(--accent-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Alert */
        .alert-custom {
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 24px;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.5;
            animation: slideDown 0.3s ease-out;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
        }

        .recaptcha-container {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
            transform-origin: center;
        }

        .divider {
            height: 1px;
            background: var(--border-subtle);
            margin: 32px 0 24px 0;
            position: relative;
        }

        .divider span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--brand-surface);
            padding: 0 14px;
            color: var(--text-muted);
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        #webauthn-status {
            text-align: center;
            margin-top: 8px;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .sys-info {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.8125rem;
        }

        .sys-info i {
            color: var(--accent-emerald);
            font-size: 0.875rem;
        }

        /* ============================================
           ANIMATIONS
           ============================================ */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-16px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInLeft { from { opacity: 0; transform: translateX(-16px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.97) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes orbFloat1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(40px, -30px) scale(1.05); }
            50% { transform: translate(-20px, 40px) scale(0.95); }
            75% { transform: translate(30px, 20px) scale(1.02); }
        }

        @keyframes orbFloat2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-30px, -25px) scale(1.04); }
            66% { transform: translate(25px, 30px) scale(0.96); }
        }

        @keyframes orbFloat3 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(-20px, 20px) scale(1.06); }
            50% { transform: translate(30px, -15px) scale(0.94); }
            75% { transform: translate(-10px, -25px) scale(1.03); }
        }

        /* ============================================
           RESPONSIVE DESIGN
           ============================================ */

        /* 1. Small Desktop */
        @media (max-width: 1200px) {
            .brand-section { padding: 40px 48px; }
            .brand-title { font-size: clamp(2rem, 3vw, 2.5rem); }
            .form-section { min-width: 480px; width: 480px; padding: 48px 40px; }
            .form-decoration { left: 40px; right: 40px; }
        }

        /* 2. Tablets */
        @media (max-width: 992px) {
            body {
                flex-direction: column;
                justify-content: center;
                align-items: center;
                background: linear-gradient(160deg, #0c1222 0%, #0f2847 50%, #0c4a6e 100%);
                min-height: 100vh;
            }

            .brand-section {
                display: none;
            }

            .form-section {
                width: 100%;
                max-width: 440px;
                min-width: auto;
                height: auto;
                min-height: auto;
                border-radius: 24px;
                padding: 40px 32px;
                box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.25);
                margin: 24px;
            }

            .form-decoration {
                left: 32px;
                right: 32px;
                border-radius: 0 0 2px 2px;
            }

            .mobile-branding {
                display: block;
            }
        }

        /* 3. Mobile Phones */
        @media (max-width: 576px) {
            body {
                background: var(--brand-surface);
                align-items: flex-start;
            }

            .form-section {
                box-shadow: none;
                padding: 24px 20px;
                margin: 0;
                border-radius: 0;
                max-width: 100%;
                min-height: 100vh;
                justify-content: flex-start;
                overflow-y: auto;
            }

            .form-decoration { left: 20px; right: 20px; }
            .mobile-branding { margin-top: 16px; }
            .form-header h2 { font-size: 1.5rem; }

            .recaptcha-container {
                transform: scale(0.88);
            }
        }

        /* 4. Landscape Mobile */
        @media (max-height: 700px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding: 20px 0;
                background: var(--brand-surface-alt);
            }

            .form-section {
                margin: 0 auto;
                padding: 28px 32px;
                height: auto;
            }

            .form-decoration { left: 32px; right: 32px; }
        }

        /* Accessibility: Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            .brand-orb { animation: none !important; }
            .form-section { animation: none !important; }
            .brand-content * { animation: none !important; }
            .btn-login::before { transition: none !important; }
        }
    </style>
</head>
<body>

    <!-- LEFT PANEL: Visual & Branding -->
    <div class="brand-section">
        <!-- Animated Gradient Orbs -->
        <div class="brand-orbs">
            <div class="brand-orb"></div>
            <div class="brand-orb"></div>
            <div class="brand-orb"></div>
        </div>
        <!-- Grid Pattern -->
        <div class="brand-grid-pattern"></div>
        <!-- Particles -->
        <div id="tsparticles"></div>

        <div class="brand-content">
            <div class="logo-wrapper">
                <img src="uploads/logo.png" alt="Baggao Rescue Logo" class="logo-img">
                <span class="logo-text">Baggao Rescue</span>
            </div>

            <h1 class="brand-title">Rescue 116<br>Operations</h1>
            <p class="brand-desc">
                Advanced Pre-Hospital Care & Emergency Response Management System.
            </p>

            <div class="feature-grid">
                <div class="feature-card">
                    <i class="bi bi-shield-check feature-icon"></i>
                    <h5>Secure Access</h5>
                    <p>End-to-end encrypted data</p>
                </div>
                <div class="feature-card">
                    <i class="bi bi-activity feature-icon"></i>
                    <h5>Real-time Vitals</h5>
                    <p>Live patient monitoring</p>
                </div>
                <div class="feature-card">
                    <i class="bi bi-geo-alt feature-icon"></i>
                    <h5>GPS Tracking</h5>
                    <p>Response team dispatch</p>
                </div>
                <div class="feature-card">
                    <i class="bi bi-clipboard2-pulse feature-icon"></i>
                    <h5>Digital Records</h5>
                    <p>Paperless documentation</p>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            &copy; <?php echo date('Y'); ?> Municipality of Baggao &mdash; Authorized Personnel Only
        </div>
    </div>

    <!-- RIGHT PANEL: Login Form -->
    <div class="form-section">
        <div class="form-decoration"></div>

        <!-- Mobile Branding -->
        <div class="mobile-branding">
            <img src="uploads/logo.png" alt="Logo">
            <h3>Rescue 116</h3>
            <p>Official Operations Portal</p>
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
                <i class="bi bi-exclamation-triangle-fill" style="margin-top: 2px;"></i>
                <div>
                    <strong>Security Lockout</strong><br>
                    Your account has been temporarily restricted due to excessive failed attempts. Please contact the IT administrator.
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-floating-group">
                <input type="text" class="form-control" id="username" name="username"
                       placeholder=" "
                       value="<?php echo htmlspecialchars($restricted_username); ?>"
                       <?php echo $is_restricted ? 'disabled' : 'required autofocus'; ?>>
                <i class="bi bi-person input-icon"></i>
                <label for="username" class="form-label">Username</label>
            </div>

            <div class="form-floating-group">
                <input type="password" class="form-control" id="password" name="password"
                       placeholder=" "
                       <?php echo $is_restricted ? 'disabled' : 'required'; ?>>
                <i class="bi bi-key input-icon"></i>
                <label for="password" class="form-label">Password</label>
            </div>

            <?php if (!$is_restricted): ?>
                <div class="recaptcha-container">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                </div>

                <button type="submit" class="btn btn-login">
                    <span>Sign In</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-login" disabled>
                    <i class="bi bi-lock"></i>
                    <span>Account Locked</span>
                </button>
            <?php endif; ?>
        </form>

        <!-- Biometric Login Section (shown by JS if WebAuthn is available) -->
        <div id="webauthn-section" style="display: none;">
            <div class="divider">
                <span>OR</span>
            </div>
            <button type="button" id="webauthn-login-btn" class="btn btn-biometric" aria-label="Sign in with biometric authentication">
                <i class="bi bi-fingerprint"></i>
                <span>Sign in with Biometrics</span>
            </button>
            <div id="webauthn-status" style="display: none;"></div>
        </div>

        <div class="divider">
            <span>Secure System</span>
        </div>

        <div class="sys-info">
            <i class="bi bi-shield-lock-fill"></i>
            <span>256-bit SSL Encrypted</span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <!-- TSParticles Library -->
    <script src="https://cdn.jsdelivr.net/npm/tsparticles-slim@2.0.6/tsparticles.slim.bundle.min.js"></script>

    <!-- WebAuthn Biometric Login -->
    <script src="js/webauthn.js"></script>

    <script nonce="<?php echo CSP_NONCE; ?>">
        // 1. Initialize Particles (Refined medical network effect)
        (async () => {
            await tsParticles.load("tsparticles", {
                particles: {
                    number: { value: 40, density: { enable: true, value_area: 1000 } },
                    color: { value: ["#38bdf8", "#06b6d4", "#ffffff"] },
                    shape: { type: "circle" },
                    opacity: { value: 0.15, random: { enable: true, minimumValue: 0.05 } },
                    size: { value: 2.5, random: { enable: true, minimumValue: 1 } },
                    move: {
                        enable: true,
                        speed: 0.8,
                        direction: "none",
                        random: true,
                        straight: false,
                        out_mode: "out",
                        bounce: false,
                    },
                    links: {
                        enable: true,
                        distance: 180,
                        color: "#38bdf8",
                        opacity: 0.07,
                        width: 1
                    },
                },
                interactivity: {
                    detect_on: "canvas",
                    events: {
                        onhover: { enable: true, mode: "grab" },
                        onclick: { enable: false },
                        resize: true
                    },
                    modes: {
                        grab: { distance: 160, line_linked: { opacity: 0.2 } }
                    }
                },
                retina_detect: true
            });
        })();

        // 2. Configure Notifications
        Notiflix.Notify.init({
            width: '360px',
            position: 'right-top',
            distance: '16px',
            borderRadius: '12px',
            fontFamily: 'Plus Jakarta Sans',
            fontSize: '13px',
            cssAnimationStyle: 'from-right',
            success: { background: '#059669', textColor: '#fff', notiflixIconColor: '#fff' },
            failure: { background: '#dc2626', textColor: '#fff', notiflixIconColor: '#fff' },
            warning: { background: '#d97706', textColor: '#fff', notiflixIconColor: '#fff' },
            info:    { background: '#0284c7', textColor: '#fff', notiflixIconColor: '#fff' }
        });

        // 3. Biometric Login Setup (Flutter native OR WebAuthn fallback)
        (function() {
            var csrfToken = '<?php echo $csrf_token; ?>';

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
                btn.innerHTML = '<i class="bi bi-fingerprint"></i> <span>Sign in with Biometrics</span>';
            }

            // ── Flutter Native Biometric ──────────────────────────────────
            // FlutterBiometric JS channel is injected by the Flutter WebView
            if (typeof FlutterBiometric !== 'undefined') {
                showBiometricSection();

                // Flutter calls this after biometric + server auth completes
                window.flutterBiometricResult = function(success, message) {
                    var btn = document.getElementById('webauthn-login-btn');
                    var statusEl = document.getElementById('webauthn-status');
                    if (success) {
                        statusEl.textContent = 'Success! Redirecting...';
                        statusEl.style.color = '#059669';
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

                return; // Skip WebAuthn setup
            }

            // ── WebAuthn Fallback (browser-based) ────────────────────────
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
                            statusEl.textContent = 'Success! Redirecting...';
                            statusEl.style.color = '#059669';
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

        // 4. Handle Flash Messages
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
