<?php
/**
 * Biometric Settings Page
 * Manage WebAuthn credentials (fingerprint, Face ID)
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication
require_login();

$current_user = get_auth_user();
$csrf_token = generate_token();

// Fetch user's registered credentials
$credentials = [];
$stmt = db_query(
    "SELECT id, credential_name, created_at, last_used_at FROM webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC",
    [$current_user['id']]
);
if ($stmt) {
    while ($row = $stmt->fetch()) {
        $credentials[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Settings - Pre-Hospital Care System</title>
    <link href="vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendor/bootstrap-icons/1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="vendor/fonts/material-icons.css" rel="stylesheet">
    <link href="vendor/notiflix/3.2.6/notiflix-3.2.6.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --success: #16a34a;
            --danger: #dc2626;
            --gray-50: #fafafa;
            --gray-100: #f4f4f5;
            --gray-200: #e4e4e7;
            --gray-300: #d4d4d8;
            --gray-400: #a1a1aa;
            --gray-500: #71717a;
            --gray-600: #52525b;
            --gray-700: #3f3f46;
            --gray-800: #27272a;
        }

        body {
            background: var(--gray-50);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .content {
            margin-left: 260px;
            padding-top: 64px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .page-header-inline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.125rem;
        }

        .page-subtitle {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0;
        }

        .bio-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .bio-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .bio-card-header .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb15, #2563eb25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .bio-card-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--gray-800);
        }

        .bio-card-header p {
            margin: 0;
            font-size: 0.813rem;
            color: var(--gray-500);
        }

        .credential-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            margin-bottom: 0.75rem;
            transition: border-color 0.2s;
        }

        .credential-item:hover {
            border-color: var(--gray-300);
        }

        .credential-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .credential-info .device-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
        }

        .credential-info .name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.938rem;
        }

        .credential-info .meta {
            font-size: 0.75rem;
            color: var(--gray-400);
        }

        .btn-register {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.938rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-register:hover {
            background: var(--primary-hover);
            color: white;
            transform: translateY(-1px);
        }

        .btn-remove {
            background: none;
            border: 1px solid var(--gray-200);
            color: var(--danger);
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            font-size: 0.813rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-remove:hover {
            background: #fef2f2;
            border-color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 0.938rem;
            margin-bottom: 0;
        }

        .info-banner {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .info-banner i {
            color: var(--primary);
            font-size: 1.25rem;
            margin-top: 2px;
        }

        .info-banner p {
            margin: 0;
            font-size: 0.875rem;
            color: #1e40af;
            line-height: 1.5;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 14px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid var(--gray-100);
            padding: 1.25rem 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-100);
            padding: 1rem 1.5rem;
        }

        @media (max-width: 992px) {
            .content {
                margin-left: 0;
                padding-top: 60px;
            }
        }

        @media (max-width: 576px) {
            .credential-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .btn-remove {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid py-4">
            <?php show_flash(); ?>

            <!-- Page Header -->
            <div class="page-header-inline">
                <div>
                    <h1 class="page-title"><i class="bi bi-fingerprint me-2"></i>Biometric Settings</h1>
                    <p class="page-subtitle">Manage your fingerprint and Face ID login credentials</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>

            <!-- WebAuthn Unsupported Warning -->
            <div id="webauthn-unsupported" class="alert alert-warning d-flex align-items-center" role="alert" style="display: none;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div id="webauthn-unsupported-msg">Your browser does not support biometric authentication. Please use a modern browser (Chrome, Safari, Edge, Firefox) on a device with biometric capabilities.</div>
            </div>

            <!-- Info Banner (hidden until WebAuthn support confirmed) -->
            <div class="info-banner" id="webauthn-info" style="display: none;">
                <i class="bi bi-shield-check"></i>
                <p>
                    Register your device to sign in with <strong>fingerprint</strong> or <strong>Face ID</strong> instead of typing your password.
                    Each device needs to be registered separately. Your biometric data never leaves your device.
                </p>
            </div>

            <!-- Registered Devices Card -->
            <div class="bio-card">
                <div class="bio-card-header">
                    <div class="icon-circle">
                        <i class="bi bi-phone"></i>
                    </div>
                    <div>
                        <h5>Registered Devices</h5>
                        <p><?php echo count($credentials); ?> device(s) registered</p>
                    </div>
                </div>

                <div id="credentials-list">
                    <?php if (empty($credentials)): ?>
                        <div class="empty-state" id="empty-state">
                            <i class="bi bi-fingerprint"></i>
                            <p>No biometric credentials registered yet.<br>Register your device to enable biometric login.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($credentials as $cred): ?>
                            <div class="credential-item" data-id="<?php echo $cred['id']; ?>">
                                <div class="credential-info">
                                    <div class="device-icon">
                                        <i class="bi bi-phone"></i>
                                    </div>
                                    <div>
                                        <div class="name"><?php echo htmlspecialchars($cred['credential_name'] ?: 'Unnamed Device'); ?></div>
                                        <div class="meta">
                                            Registered: <?php echo date('M j, Y g:i A', strtotime($cred['created_at'])); ?>
                                            <?php if ($cred['last_used_at']): ?>
                                                &bull; Last used: <?php echo date('M j, Y g:i A', strtotime($cred['last_used_at'])); ?>
                                            <?php else: ?>
                                                &bull; Never used
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn-remove" data-credential-id="<?php echo $cred['id']; ?>">
                                    <i class="bi bi-trash3 me-1"></i>Remove
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Register Button (hidden until WebAuthn support confirmed) -->
                <div class="mt-3" id="register-section" style="display: none;">
                    <button type="button" class="btn-register" id="register-btn">
                        <i class="bi bi-plus-circle"></i>
                        Register This Device
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Name Modal -->
    <div class="modal fade" id="deviceNameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-fingerprint me-2"></i>Name Your Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Give this device a name so you can identify it later (e.g., "My iPhone", "Work Laptop").</p>
                    <input type="text" class="form-control" id="device-name-input" placeholder="My Device" maxlength="100" autofocus>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-register-btn">
                        <i class="bi bi-fingerprint me-1"></i>Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="vendor/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/notiflix/3.2.6/notiflix-aio-3.2.6.min.js"></script>
    <script src="js/webauthn.js"></script>

    <script nonce="<?php echo CSP_NONCE; ?>">
        var csrfToken = '<?php echo $csrf_token; ?>';
        var deviceNameModal;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Configure Notiflix
            Notiflix.Notify.init({
                width: '380px', position: 'right-top', distance: '20px',
                borderRadius: '10px', fontFamily: 'Inter, sans-serif', fontSize: '14px',
                cssAnimationStyle: 'zoom',
                success: { background: '#10b981' },
                failure: { background: '#ef4444' },
                warning: { background: '#f59e0b' },
                info: { background: '#0ea5e9' }
            });

            deviceNameModal = new bootstrap.Modal(document.getElementById('deviceNameModal'));

            // Check WebAuthn support
            if (!webauthnIsAvailable()) {
                document.getElementById('webauthn-unsupported').style.display = 'flex';
                return;
            }

            webauthnCheckPlatformAuth().then(function(available) {
                if (!available) {
                    document.getElementById('webauthn-unsupported-msg').textContent =
                        'This device does not have biometric hardware (fingerprint reader or face camera) available. ' +
                        'Please open this page on your phone or a device with biometric capabilities to register.';
                    document.getElementById('webauthn-unsupported').style.display = 'flex';
                } else {
                    // Device supports biometrics — show info banner and register button
                    document.getElementById('webauthn-info').style.display = 'flex';
                    document.getElementById('register-section').style.display = 'block';
                }
            });

            // Register button click
            document.getElementById('register-btn').addEventListener('click', function() {
                startRegistration();
            });

            // Delegated click handler for remove buttons
            document.getElementById('credentials-list').addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-remove');
                if (btn) {
                    var credentialId = parseInt(btn.getAttribute('data-credential-id'), 10);
                    removeCredential(credentialId, btn);
                }
            });

            // Handle Enter key in device name input
            document.getElementById('device-name-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('confirm-register-btn').click();
                }
            });

            // Handle confirm registration
            document.getElementById('confirm-register-btn').addEventListener('click', function() {
                var name = document.getElementById('device-name-input').value.trim() || 'My Device';
                deviceNameModal.hide();
                performRegistration(name);
            });
        });

        function startRegistration() {
            document.getElementById('device-name-input').value = '';
            deviceNameModal.show();
            setTimeout(function() {
                document.getElementById('device-name-input').focus();
            }, 500);
        }

        function performRegistration(credentialName) {
            var btn = document.getElementById('register-btn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';

            Notiflix.Notify.info('Please follow the biometric prompt on your device...');

            webauthnRegister(csrfToken, credentialName)
                .then(function(data) {
                    Notiflix.Notify.success('Biometric credential registered successfully!');
                    // Reload to show the new credential
                    setTimeout(function() { location.reload(); }, 1000);
                })
                .catch(function(error) {
                    console.error('Registration error:', error);
                    if (error.name === 'NotAllowedError') {
                        Notiflix.Notify.warning('Registration was cancelled or timed out.');
                    } else if (error.name === 'InvalidStateError') {
                        Notiflix.Notify.warning('This device is already registered.');
                    } else {
                        Notiflix.Notify.failure(error.message || 'Registration failed. Please try again.');
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-plus-circle"></i> Register This Device';
                });
        }

        function removeCredential(credentialId, btnElement) {
            Notiflix.Confirm.show(
                'Remove Device',
                'Are you sure you want to remove this biometric credential? You will no longer be able to sign in with this device\'s biometrics.',
                'Remove',
                'Cancel',
                function() {
                    var formData = new FormData();
                    formData.append('csrf_token', csrfToken);
                    formData.append('_method', 'DELETE');
                    formData.append('credential_id', credentialId);

                    fetch('../api/webauthn/credentials.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            Notiflix.Notify.success('Credential removed successfully');
                            var item = btnElement.closest('.credential-item');
                            item.style.transition = 'opacity 0.3s, transform 0.3s';
                            item.style.opacity = '0';
                            item.style.transform = 'translateX(20px)';
                            setTimeout(function() {
                                item.remove();
                                // Check if empty
                                var remaining = document.querySelectorAll('.credential-item');
                                if (remaining.length === 0) {
                                    document.getElementById('credentials-list').innerHTML =
                                        '<div class="empty-state"><i class="bi bi-fingerprint"></i>' +
                                        '<p>No biometric credentials registered yet.<br>Register your device to enable biometric login.</p></div>';
                                }
                            }, 300);
                        } else {
                            Notiflix.Notify.failure(data.message || 'Failed to remove credential');
                        }
                    })
                    .catch(function() {
                        Notiflix.Notify.failure('Network error. Please try again.');
                    });
                },
                function() { /* cancelled */ },
                { titleColor: '#dc2626', okButtonBackground: '#dc2626' }
            );
        }
    </script>
</body>
</html>
