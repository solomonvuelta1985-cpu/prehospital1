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
    <title>Sign In - Baggao Rescue 116</title>

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <!-- Global Responsive Styles -->
    <link href="css/global-responsive.css" rel="stylesheet">

    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink-950: #060E1A;
            --ink-900: #0B1B2B;
            --ink-850: #0F2238;
            --ink-800: #0F172A;
            --ink-600: #334155;
            --ink-400: #64748B;
            --line: #E2E8F0;
            --surface: #FFFFFF;
            --surface-alt: #F8FAFC;
            --rescue: #E11D48;
            --rescue-hover: #BE123C;
            --rescue-ring: rgba(225, 29, 72, 0.18);
            --accent: #0284C7;
            --accent-hover: #0369A1;
            --accent-ring: rgba(2, 132, 199, 0.15);
            --signal: #22C55E;
            --danger: #DC2626;
            --success: #059669;
            --mono: 'JetBrains Mono', 'SF Mono', ui-monospace, Menlo, Consolas, monospace;
            --radius: 10px;
            --radius-sm: 8px;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--surface);
            color: var(--ink-800);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-feature-settings: "ss01", "cv11";
            animation: fadeIn 240ms ease-out both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (prefers-reduced-motion: reduce) {
            body { animation: none; }
        }

        /* ============================================
           LEFT: Brand panel — dispatch console aesthetic
           ============================================ */
        .brand-panel {
            flex: 1;
            min-height: 100vh;
            background:
                radial-gradient(1200px 600px at 20% 30%, rgba(225, 29, 72, 0.08), transparent 60%),
                radial-gradient(900px 500px at 80% 80%, rgba(2, 132, 199, 0.10), transparent 65%),
                linear-gradient(160deg, var(--ink-950) 0%, var(--ink-900) 45%, var(--ink-850) 100%);
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 56px 72px;
            position: relative;
            overflow: hidden;
        }

        /* Topographic line texture */
        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                repeating-linear-gradient(0deg, rgba(255,255,255,0.025) 0 1px, transparent 1px 64px),
                repeating-linear-gradient(90deg, rgba(255,255,255,0.025) 0 1px, transparent 1px 64px);
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
            pointer-events: none;
        }

        /* Vignette / scanline whisper */
        .brand-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(0,0,0,0.20) 0%, transparent 12%, transparent 88%, rgba(0,0,0,0.25) 100%);
            pointer-events: none;
        }

        .brand-panel > * { position: relative; z-index: 1; }

        /* Top row: logo + LIVE pill */
        .brand-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand-head-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-head img {
            height: 40px;
            width: auto;
            display: block;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.4));
        }

        .brand-wordmark {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.9);
        }

        .brand-wordmark small {
            display: block;
            font-family: var(--mono);
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.12em;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 2px;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px 6px 10px;
            background: rgba(225, 29, 72, 0.12);
            border: 1px solid rgba(225, 29, 72, 0.35);
            border-radius: 999px;
            font-family: var(--mono);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.15em;
            color: #FECDD3;
            text-transform: uppercase;
        }

        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--rescue);
            position: relative;
            box-shadow: 0 0 0 0 rgba(225, 29, 72, 0.8);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(225, 29, 72, 0.7); }
            50% { box-shadow: 0 0 0 6px rgba(225, 29, 72, 0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .live-dot { animation: none; }
        }

        /* Middle: editorial quote */
        .brand-middle {
            max-width: 520px;
            position: relative;
        }

        .brand-middle::before {
            content: '\201C';
            position: absolute;
            top: -64px;
            left: -28px;
            font-family: 'Plus Jakarta Sans', serif;
            font-size: 200px;
            line-height: 1;
            font-weight: 800;
            color: rgba(225, 29, 72, 0.14);
            pointer-events: none;
            user-select: none;
        }

        .brand-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: var(--mono);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
            margin-bottom: 20px;
        }

        .brand-eyebrow::before {
            content: '';
            width: 24px;
            height: 1px;
            background: var(--rescue);
        }

        .brand-quote {
            font-size: clamp(2.125rem, 3.2vw, 2.75rem);
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: #FFFFFF;
            margin: 0 0 24px;
            max-width: 500px;
        }

        .brand-quote em {
            font-style: normal;
            color: #FCA5A5;
            position: relative;
        }

        .brand-desc {
            font-size: 16px;
            line-height: 1.65;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.65);
            max-width: 440px;
            margin: 0;
        }

        /* Footer: KPIs + live clock */
        .brand-foot {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        .brand-kpis {
            display: flex;
            gap: 32px;
            margin-bottom: 20px;
        }

        .kpi {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .kpi-value {
            font-family: var(--mono);
            font-size: 18px;
            font-weight: 600;
            color: #FFFFFF;
            letter-spacing: -0.01em;
            line-height: 1;
        }

        .kpi-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.45);
        }

        .brand-divider {
            height: 1px;
            background: linear-gradient(90deg, rgba(255,255,255,0.12), rgba(255,255,255,0.02));
            margin-bottom: 16px;
        }

        .brand-bottomrow {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand-copy {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 500;
        }

        .brand-clock {
            font-family: var(--mono);
            font-size: 11px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.45);
            letter-spacing: 0.08em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .brand-clock::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--signal);
            box-shadow: 0 0 6px rgba(34, 197, 94, 0.6);
        }

        /* ============================================
           RIGHT: Form panel
           ============================================ */
        .form-panel {
            width: 520px;
            min-width: 520px;
            background: var(--surface);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px;
            min-height: 100vh;
            position: relative;
        }

        /* Thin vertical accent strip running down the left edge of the form panel */
        .form-panel::before {
            content: '';
            position: absolute;
            top: 64px;
            bottom: 64px;
            left: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--rescue) 0%, var(--accent) 60%, transparent 100%);
            opacity: 0.9;
        }


        .form-inner {
            width: 100%;
            max-width: 360px;
            margin: 0 auto;
        }

        /* Mobile-only hero + footer (hidden on desktop) */
        .mobile-hero { display: none; }
        .mobile-footer { display: none; }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h1 {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--ink-800);
            margin: 0 0 10px;
            line-height: 1.1;
        }

        .form-header-accent {
            color: var(--rescue);
            font-weight: 800;
        }

        .form-header p {
            font-size: 15px;
            font-weight: 500;
            color: var(--ink-400);
            margin: 0;
            line-height: 1.5;
        }

        .form-header p.restricted {
            color: var(--danger);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Inline notice for restriction */
        .notice {
            background: var(--surface-alt);
            border: 1px solid var(--line);
            border-left: 3px solid var(--danger);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--ink-600);
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .notice i {
            color: var(--danger);
            font-size: 16px;
            margin-top: 1px;
        }

        .notice strong {
            color: var(--ink-800);
            font-weight: 600;
            display: block;
            margin-bottom: 2px;
        }

        /* Form fields — labels above inputs */
        .field {
            margin-bottom: 18px;
        }

        .field-label-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 6px;
        }

        .field label {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-600);
            display: block;
        }

        .field-input-wrap {
            position: relative;
        }

        .field input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
            color: var(--ink-800);
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            transition: border-color 150ms ease, box-shadow 150ms ease;
            -webkit-appearance: none;
        }

        .field input::placeholder {
            color: #94A3B8;
            font-weight: 400;
        }

        .field input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .field input:disabled {
            background: var(--surface-alt);
            color: var(--ink-400);
            cursor: not-allowed;
        }

        .field.has-toggle input {
            padding-right: 64px;
        }

        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: 0;
            padding: 4px 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-400);
            cursor: pointer;
            font-family: inherit;
            border-radius: 6px;
            transition: color 150ms ease, background 150ms ease;
        }

        .pw-toggle:hover {
            color: var(--ink-800);
            background: var(--surface-alt);
        }

        .pw-toggle:focus {
            outline: none;
            color: var(--accent);
        }

        /* Buttons */
        .btn-primary-cta {
            width: 100%;
            height: 52px;
            background: var(--rescue);
            background-image: linear-gradient(180deg, #F43F5E 0%, var(--rescue) 100%);
            border: 0;
            border-radius: var(--radius);
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 180ms ease, background-color 150ms ease;
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.15) inset, 0 6px 16px -6px rgba(225, 29, 72, 0.5);
        }

        .btn-primary-cta:hover:not(:disabled) {
            background: var(--rescue-hover);
            background-image: linear-gradient(180deg, var(--rescue) 0%, var(--rescue-hover) 100%);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.15) inset, 0 10px 24px -8px rgba(225, 29, 72, 0.6);
            transform: translateY(-1px);
        }

        .btn-primary-cta:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary-cta:focus-visible {
            outline: none;
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.15) inset, 0 0 0 3px var(--rescue-ring);
        }

        .btn-primary-cta:disabled {
            background: #94A3B8;
            background-image: none;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-primary-cta i {
            font-size: 16px;
        }

        .btn-secondary-cta {
            width: 100%;
            height: 48px;
            background: var(--surface-alt);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            color: var(--ink-800);
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: background-color 150ms ease, border-color 150ms ease;
        }

        .btn-secondary-cta:hover:not(:disabled) {
            background: #F1F5F9;
        }

        .btn-secondary-cta:focus-visible {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .btn-secondary-cta:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary-cta i {
            font-size: 20px;
            color: var(--ink-600);
        }

        /* Divider */
        .or-divider {
            position: relative;
            text-align: center;
            margin: 28px 0 20px;
        }

        .or-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--line);
        }

        .or-divider span {
            position: relative;
            background: var(--surface);
            padding: 0 12px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-400);
        }

        /* WebAuthn status */
        #webauthn-status {
            text-align: center;
            margin-top: 10px;
            font-size: 13px;
            color: var(--ink-400);
        }

        /* SSL footer line */
        .ssl-line {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 28px;
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-400);
        }

        .ssl-line i {
            font-size: 13px;
            color: var(--success);
        }

        /* ============================================
           RESPONSIVE — mobile-first redesign
           ============================================ */

        /* Mid-desktop tighten */
        @media (max-width: 1200px) {
            .brand-panel { padding: 48px 56px; }
            .form-panel { width: 480px; min-width: 480px; padding: 48px; }
        }

        /* ============================================
           TABLET & PHONE (< 992px)
           Single-column: navy hero strip on top, white form below
           ============================================ */
        @media (max-width: 992px) {
            body {
                display: block;
                background: var(--surface);
                min-height: 100vh;
                min-height: 100dvh;
                /* iOS safe area */
                padding-top: env(safe-area-inset-top);
                padding-bottom: env(safe-area-inset-bottom);
            }

            .brand-panel { display: none; }

            .form-panel {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                min-height: 100vh;
                min-height: 100dvh;
                padding: 0;
                margin: 0;
                background: var(--surface);
                border: 0;
                border-radius: 0;
                box-shadow: none;
                display: block;
                justify-content: flex-start;
            }

            /* Vertical accent strip changes to horizontal under the hero */
            .form-panel::before {
                top: auto;
                bottom: auto;
                left: 0;
                right: 0;
                width: 100%;
                height: 2px;
                background: linear-gradient(90deg, var(--rescue) 0%, var(--accent) 60%, transparent 100%);
            }

            .form-inner {
                width: 100%;
                max-width: 100%;
                padding: 0 24px 0;
                min-height: 100vh;
                min-height: 100dvh;
                display: flex;
                flex-direction: column;
            }

            /* Middle wrapper: holds form-header through ssl-line, vertically centers in remaining space */
            .form-main {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 28px 0 24px;
                position: relative;
                margin: 0 -24px;
                padding-left: 24px;
                padding-right: 24px;
                overflow: hidden;
            }

            /* Decorative seal watermark — bleeds off the top-right of the form area */
            .form-main::before {
                content: '';
                position: absolute;
                top: 24px;
                right: -160px;
                width: 280px;
                height: 280px;
                background-image: url('uploads/logo.png');
                background-repeat: no-repeat;
                background-position: center;
                background-size: contain;
                opacity: 0.12;
                pointer-events: none;
                z-index: 0;
            }

            .form-main > * {
                position: relative;
                z-index: 1;
            }

            .form-header {
                margin-top: 0;
            }

            /* === Mobile hero (navy strip with logo + LIVE pill) === */
            .mobile-hero {
                display: block;
                position: relative;
                margin: 0 -24px 0;
                padding: 28px 24px;
                background:
                    radial-gradient(800px 300px at 80% 100%, rgba(2, 132, 199, 0.18), transparent 60%),
                    linear-gradient(160deg, var(--ink-950) 0%, var(--ink-900) 60%, var(--ink-850) 100%);
                color: #FFFFFF;
                overflow: hidden;
                /* Move the form-panel's top accent strip down so it sits under the hero */
            }

            /* Move the top accent strip from form-panel::before to bottom of hero */
            .form-panel::before {
                top: 0;
                height: 0;
            }

            .mobile-hero-bg {
                position: absolute;
                inset: 0;
                background-image:
                    repeating-linear-gradient(0deg, rgba(255,255,255,0.04) 0 1px, transparent 1px 48px),
                    repeating-linear-gradient(90deg, rgba(255,255,255,0.04) 0 1px, transparent 1px 48px);
                pointer-events: none;
                mask-image: radial-gradient(ellipse at 80% 50%, #000 30%, transparent 80%);
                -webkit-mask-image: radial-gradient(ellipse at 80% 50%, #000 30%, transparent 80%);
            }

            .mobile-hero-glow {
                position: absolute;
                top: -40px;
                left: -40px;
                width: 200px;
                height: 200px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(225, 29, 72, 0.25), transparent 70%);
                filter: blur(20px);
                pointer-events: none;
            }

            /* Red bottom accent line on hero */
            .mobile-hero::after {
                content: '';
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                height: 2px;
                background: linear-gradient(90deg, var(--rescue) 0%, var(--accent) 70%, transparent 100%);
            }

            .mobile-hero-row {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                z-index: 1;
            }

            .mobile-hero-brand {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .mobile-hero-brand img {
                height: 36px;
                width: auto;
                filter: drop-shadow(0 4px 10px rgba(0,0,0,0.4));
            }

            .mobile-hero-label span {
                display: block;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: #FFFFFF;
                line-height: 1.2;
            }

            .mobile-hero-label small {
                display: block;
                font-family: var(--mono);
                font-size: 9px;
                font-weight: 500;
                letter-spacing: 0.18em;
                color: rgba(255, 255, 255, 0.5);
                margin-top: 3px;
            }

            /* Form header */
            .form-header { margin-bottom: 24px; }
            .form-header h1 { font-size: 24px; }
            .form-header p { font-size: 14px; }

            /* Touch-friendly inputs (≥ 52px, 16px font to prevent iOS zoom) */
            .field { margin-bottom: 22px; }
            .field-label-row { margin-bottom: 8px; }
            .field input {
                height: 52px;
                font-size: 16px;
                border-radius: 12px;
                padding: 0 16px;
            }
            .field.has-toggle input { padding-right: 76px; }
            .pw-toggle {
                font-size: 13px;
                padding: 8px 10px;
                right: 8px;
            }

            /* Bigger buttons for gloved hands */
            .btn-primary-cta {
                height: 56px;
                font-size: 16px;
                border-radius: 12px;
            }
            .btn-secondary-cta {
                height: 52px;
                font-size: 15px;
                border-radius: 12px;
            }

            .or-divider { margin: 24px 0 16px; }

            .ssl-line { margin-top: 24px; }

            /* SSL line stays centered as its own block */
            .ssl-line {
                margin-top: 24px;
                justify-content: center;
            }

            /* === Mobile footer dock — pinned to bottom with KPIs + clock + copy === */
            .mobile-footer {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                margin: 0 -24px;
                padding: 0;
                background: var(--surface-alt);
                border-top: 1px solid var(--line);
                font-size: 11px;
                color: var(--ink-400);
                text-align: center;
            }

            .mobile-footer-meta {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                padding: 12px 20px 14px;
            }

            .mobile-footer-clock {
                font-family: var(--mono);
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.08em;
                color: var(--ink-600);
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .mobile-footer-clock::before {
                content: '';
                width: 5px;
                height: 5px;
                border-radius: 50%;
                background: var(--signal);
                box-shadow: 0 0 6px rgba(34, 197, 94, 0.5);
            }

            .mobile-footer-copy {
                font-size: 11px;
                font-weight: 500;
                color: var(--ink-400);
            }

            .mobile-footer-tagline {
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #94A3B8;
            }
        }

        /* ============================================
           SMALL PHONES (< 480px)
           Tighter spacing, more breathing room for thumb zone
           ============================================ */
        @media (max-width: 480px) {
            .mobile-hero {
                margin: 0 -20px 0;
                padding: 24px 20px;
            }

            .form-inner {
                padding: 0 20px 0;
            }

            .form-main {
                margin: 0 -20px;
                padding: 24px 20px 20px;
            }

            .form-main::before {
                top: 20px;
                right: -140px;
                width: 240px;
                height: 240px;
            }

            .form-header h1 { font-size: 22px; }
            .form-header p { font-size: 13px; }

            .mobile-hero-brand img { height: 32px; }
            .mobile-hero-label span { font-size: 13px; }

            .live-pill {
                padding: 5px 10px 5px 9px;
                font-size: 9px;
                letter-spacing: 0.12em;
            }
            .live-dot { width: 6px; height: 6px; }

            .mobile-footer { margin: 0 -20px; }
            .mobile-footer-meta { padding: 10px 18px 12px; }
        }

        /* ============================================
           VERY SMALL PHONES (< 360px)
           ============================================ */
        @media (max-width: 360px) {
            .mobile-hero {
                margin: 0 -16px 0;
                padding: 20px 16px;
            }
            .mobile-hero-row { gap: 8px; }
            .mobile-hero-label span { font-size: 12px; }
            .mobile-hero-brand img { height: 28px; }
            .form-inner { padding: 0 16px 0; }
            .form-main {
                margin: 0 -16px;
                padding: 20px 16px 16px;
            }
            .form-main::before {
                top: 16px;
                right: -120px;
                width: 200px;
                height: 200px;
            }
            .form-header h1 { font-size: 20px; }

            .mobile-footer { margin: 0 -16px; }
            .mobile-footer-meta { padding: 10px 14px 12px; }
            .mobile-footer-copy { font-size: 10px; }
        }

        /* ============================================
           LANDSCAPE PHONES (short viewport)
           ============================================ */
        @media (max-width: 992px) and (max-height: 600px) and (orientation: landscape) {
            body { min-height: 0; }
            .form-panel { min-height: 0; }
            .form-inner { min-height: 0; }
            .form-main {
                flex: 0 0 auto;
                justify-content: flex-start;
                margin: 0 -24px;
                padding: 4px 24px 16px;
            }
            .form-main::before { display: none; }
            .mobile-hero {
                padding: 16px 24px 14px;
                margin-bottom: 0;
            }
            .mobile-hero-brand img { height: 28px; }
            .form-header { margin-bottom: 16px; }
            .form-header h1 { font-size: 20px; margin-bottom: 4px; }
            .form-header p { font-size: 13px; }
            .field { margin-bottom: 12px; }
            .field input { height: 46px; }
            .btn-primary-cta { height: 48px; }
            .mobile-footer-meta { padding: 8px 18px 10px; }
        }
    </style>
</head>
<body>

    <!-- LEFT: Brand panel — dispatch console -->
    <aside class="brand-panel">
        <div class="brand-head">
            <div class="brand-head-left">
                <img src="uploads/logo.png" alt="">
                <span class="brand-wordmark">
                    Baggao Rescue
                    <small>RESQ-LINK / PRE-HOSPITAL CARE</small>
                </span>
            </div>
            <span class="live-pill" aria-label="System status: dispatch online">
                <span class="live-dot" aria-hidden="true"></span>
                Dispatch Online
            </span>
        </div>

        <div class="brand-middle">
            <span class="brand-eyebrow">Field-tested emergency response</span>
            <h2 class="brand-quote">Every second counts.<br>Be ready when <em>it does</em>.</h2>
            <p class="brand-desc">
                A unified pre-hospital care platform built for the emergency response teams of Baggao Municipality. Triage, dispatch, vitals, and reporting — one console, end to end.
            </p>
        </div>

        <div class="brand-foot">
            <div class="brand-kpis">
                <div class="kpi">
                    <span class="kpi-value">24/7</span>
                    <span class="kpi-label">Operations</span>
                </div>
                <div class="kpi">
                    <span class="kpi-value">&lt; 90s</span>
                    <span class="kpi-label">Avg. Dispatch</span>
                </div>
                <div class="kpi">
                    <span class="kpi-value">2024</span>
                    <span class="kpi-label">In service</span>
                </div>
            </div>
            <div class="brand-divider"></div>
            <div class="brand-bottomrow">
                <div class="brand-copy">&copy; <?php echo date('Y'); ?> MDRRMO Baggao &middot; Authorized personnel only</div>
                <div class="brand-clock" id="brandClock" aria-label="Local time">
                    --:--:-- UTC+8
                </div>
            </div>
        </div>
    </aside>

    <!-- RIGHT: Form panel -->
    <main class="form-panel">
        <div class="form-inner">

            <div class="mobile-hero" aria-hidden="false">
                <div class="mobile-hero-bg">
                    <div class="mobile-hero-glow"></div>
                </div>
                <div class="mobile-hero-row">
                    <div class="mobile-hero-brand">
                        <img src="uploads/logo.png" alt="">
                        <div class="mobile-hero-label">
                            <span>Baggao Rescue</span>
                            <small>RESQ-LINK</small>
                        </div>
                    </div>
                    <span class="live-pill" aria-label="System status: dispatch online">
                        <span class="live-dot" aria-hidden="true"></span>
                        Online
                    </span>
                </div>
            </div>

            <div class="form-main">

            <div class="form-header">
                <h1><?php echo htmlspecialchars($greeting); ?>,<br><span class="form-header-accent">responder</span>.</h1>
                <?php if ($is_restricted): ?>
                    <p class="restricted"><i class="bi bi-lock-fill"></i> Account access restricted</p>
                <?php endif; ?>
            </div>

            <?php if ($is_restricted): ?>
                <div class="notice" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>Security lockout</strong>
                        Your account has been temporarily restricted due to excessive failed attempts. Please contact the IT administrator.
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
                        <input type="password" id="password" name="password"
                               autocomplete="current-password"
                               placeholder="Enter your password"
                               <?php echo $is_restricted ? 'disabled' : 'required'; ?>>
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password" aria-pressed="false" <?php echo $is_restricted ? 'disabled' : ''; ?>>Show</button>
                    </div>
                </div>

                <?php if (!$is_restricted): ?>
                    <button type="submit" class="btn-primary-cta">
                        <span>Sign in</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-primary-cta" disabled>
                        <i class="bi bi-lock"></i>
                        <span>Account locked</span>
                    </button>
                <?php endif; ?>
            </form>

            <!-- Biometric login (shown by JS if WebAuthn / Flutter biometric is available) -->
            <div id="webauthn-section" style="display: none;">
                <div class="or-divider"><span>or</span></div>
                <button type="button" id="webauthn-login-btn" class="btn-secondary-cta" aria-label="Sign in with biometric authentication">
                    <i class="bi bi-fingerprint"></i>
                    <span>Sign in with biometrics</span>
                </button>
                <div id="webauthn-status" style="display: none;"></div>
            </div>

            <div class="ssl-line">
                <i class="bi bi-shield-lock-fill"></i>
                <span>256-bit SSL secured &middot; CSRF protected</span>
            </div>

            </div><!-- /.form-main -->

            <div class="mobile-footer" aria-hidden="false">
                <div class="mobile-footer-meta">
                    <div class="mobile-footer-clock" id="mobileClock">--:--:-- UTC+8</div>
                    <div class="mobile-footer-copy">&copy; <?php echo date('Y'); ?> MDRRMO Baggao &middot; Authorized personnel only</div>
                </div>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-aio-3.2.6.min.js"></script>
    <!-- WebAuthn Biometric Login -->
    <script src="js/webauthn.js"></script>

    <script nonce="<?php echo CSP_NONCE; ?>">
        // 1. Show/Hide password toggle
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

        // 1b. Live clock (desktop brand footer + mobile footer, UTC+8 Manila)
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

            // Pre-populate username from localStorage (returning users)
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
                btn.innerHTML = '<i class="bi bi-fingerprint"></i> <span>Sign in with biometrics</span>';
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
                            localStorage.setItem('rescue116_username', username);
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
