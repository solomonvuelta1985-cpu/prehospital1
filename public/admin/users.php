<?php
/**
 * Admin User Management Page
 * Create, view, edit, deactivate users and change passwords
 */

define('APP_ACCESS', true);
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Require admin authentication
require_login();
require_admin();

// Generate CSRF token
$csrf_token = generate_token();

// Get current user
$current_user = get_auth_user();

// Get all users
$stmt = $pdo->query("SELECT id, username, full_name, email, role, status, is_restricted, created_at, last_login FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <style>
        /* Clean Professional Design - Admin Users Page */
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Page Header - Clean & Simple */
        .page-header {
            background: #ffffff;
            border-bottom: 2px solid #dee2e6;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            margin: 0;
            font-size: clamp(1.5rem, 3vw, 1.75rem);
            font-weight: 600;
            color: #212529;
        }

        .page-header h1 i {
            color: #0d6efd;
            margin-right: 0.5rem;
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            color: #6c757d;
            font-size: clamp(0.85rem, 2vw, 0.95rem);
        }

        /* Statistics Cards - Clean with Border Accent */
        .stats-card {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            transition: box-shadow 0.2s ease;
        }

        .stats-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .stats-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            border: 2px solid;
        }

        .stats-card.total {
            border-left: 4px solid #0d6efd;
        }

        .stats-card.total .icon {
            background: #e7f1ff;
            color: #0d6efd;
            border-color: #0d6efd;
        }

        .stats-card.active {
            border-left: 4px solid #198754;
        }

        .stats-card.active .icon {
            background: #d1e7dd;
            color: #198754;
            border-color: #198754;
        }

        .stats-card.inactive {
            border-left: 4px solid #dc3545;
        }

        .stats-card.inactive .icon {
            background: #f8d7da;
            color: #dc3545;
            border-color: #dc3545;
        }

        .stats-card h3 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
            color: #212529;
        }

        .stats-card p {
            color: #6c757d;
            margin: 0;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Action Bar */
        .action-bar {
            background: #f8f9fa;
            padding: 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }

        /* Users Table */
        .users-table-container {
            background: #ffffff;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            overflow: hidden;
        }

        .users-table {
            margin: 0;
        }

        .users-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .users-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #495057;
            border: none;
            padding: 1rem;
        }

        .users-table tbody tr {
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.15s ease;
        }

        .users-table tbody tr:last-child {
            border-bottom: none;
        }

        .users-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .users-table td {
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        /* User Avatar - Simple Circle */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Badges - Clean Rectangle Style */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid;
        }

        .status-badge.active {
            background: #d1e7dd;
            color: #0f5132;
            border-color: #badbcc;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
        }

        .status-badge.restricted {
            background: #343a40;
            color: #ffffff;
            border-color: #212529;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid;
        }

        .role-badge.admin {
            background: #fff3cd;
            color: #664d03;
            border-color: #ffecb5;
        }

        .role-badge.user {
            background: #cfe2ff;
            color: #084298;
            border-color: #b6d4fe;
        }

        /* Buttons - Flat & Clean */
        .btn {
            font-weight: 500;
            border-radius: 4px;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #0d6efd;
            border: 1px solid #0d6efd;
        }

        .btn-primary:hover {
            background: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 4px;
            margin: 0 0.15rem;
            border: 1px solid;
        }

        .btn-info {
            background: #0dcaf0;
            border-color: #0dcaf0;
            color: #000;
        }

        .btn-info:hover {
            background: #31d2f2;
            border-color: #25cff2;
        }

        .btn-warning {
            background: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background: #ffca2c;
            border-color: #ffc720;
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background: #bb2d3b;
            border-color: #b02a37;
        }

        .btn-success {
            background: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            background: #157347;
            border-color: #146c43;
        }

        /* Modal - Clean Header */
        .modal-header {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #212529;
        }

        .modal-header .modal-title {
            font-weight: 600;
            color: #212529;
        }

        .modal-header .modal-title i {
            color: #0d6efd;
            margin-right: 0.5rem;
        }

        .modal-header .btn-close {
            filter: none;
        }

        /* Form Elements */
        .form-label {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control,
        .form-select {
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        /* Search Box */
        .search-box {
            position: relative;
        }

        .search-box input {
            padding-left: 2.5rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        /* Alert Info */
        .alert-info {
            background: #cfe2ff;
            border: 1px solid #b6d4fe;
            border-left: 4px solid #0d6efd;
            color: #084298;
        }

        .alert-success {
            background: #d1e7dd;
            border: 1px solid #badbcc;
            border-left: 4px solid #198754;
            color: #0f5132;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-card h3 {
                font-size: 1.5rem;
            }

            .btn-action {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <div class="content">
        <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="bi bi-people-fill"></i> User Management</h1>
            <p>Create, manage, and monitor user accounts</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card total">
                    <div class="icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h3 id="totalUsers"><?= count($users) ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card active">
                    <div class="icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h3 id="activeUsers"><?= count(array_filter($users, fn($u) => $u['status'] === 'active')) ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card inactive">
                    <div class="icon">
                        <i class="bi bi-ban"></i>
                    </div>
                    <h3 id="restrictedUsers"><?= count(array_filter($users, fn($u) => isset($u['is_restricted']) && $u['is_restricted'] == 1)) ?></h3>
                    <p>Restricted Users</p>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php show_flash(); ?>

        <!-- Action Bar -->
        <div class="action-bar">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search users by name, email, or username...">
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-plus-circle"></i> Create New User
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <table class="table users-table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Restricted</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">
                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                        <td>
                            <span class="role-badge <?= $user['role'] ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $user['status'] ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['is_restricted']): ?>
                                <span class="status-badge restricted">
                                    <i class="bi bi-ban"></i> Restricted
                                </span>
                            <?php else: ?>
                                <span class="text-success">
                                    <i class="bi bi-check-circle"></i> Allowed
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                        <td>
                            <button class="btn btn-sm btn-info btn-action" onclick="viewUser(<?= $user['id'] ?>)" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary btn-action" onclick="editUser(<?= $user['id'] ?>)" title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-warning btn-action" onclick="changePassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" title="Change Password">
                                <i class="bi bi-key"></i>
                            </button>
                            <?php if ($user['is_restricted']): ?>
                            <button class="btn btn-sm btn-success btn-action" onclick="toggleRestriction(<?= $user['id'] ?>, 0)" title="Unrestrict User">
                                <i class="bi bi-unlock"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-secondary btn-action" onclick="toggleRestriction(<?= $user['id'] ?>, 1)" title="Restrict User">
                                <i class="bi bi-lock"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($user['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-danger btn-action" onclick="toggleUserStatus(<?= $user['id'] ?>, 'inactive')" title="Deactivate">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-success btn-action" onclick="toggleUserStatus(<?= $user['id'] ?>, 'active')" title="Activate">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createUserForm" method="POST" action="../../api/admin/create_user.php">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="mb-3">
                            <label for="fullName" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="fullName" name="full_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <small class="text-muted">Must be unique</small>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">
                                Must be at least 8 characters with: uppercase, lowercase, number, and special character (!@#$%^&*)
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key"></i> Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="changePasswordForm" method="POST" action="../../api/admin/change_user_password.php">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="user_id" id="changePasswordUserId">

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Changing password for: <strong id="changePasswordUsername"></strong>
                        </div>

                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password *</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                            <small class="text-muted">
                                Must be at least 8 characters with: uppercase, lowercase, number, and special character (!@#$%^&*)
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm" method="POST" action="../../api/admin/update_user.php">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="user_id" id="editUserId">

                        <div class="mb-3">
                            <label for="editFullName" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="editFullName" name="full_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="editUsername" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role *</label>
                            <select class="form-select" id="editRole" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status *</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person"></i> User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Content loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Edit user function
        function editUser(userId) {
            fetch(`../../api/admin/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('editUserId').value = user.id;
                        document.getElementById('editFullName').value = user.full_name;
                        document.getElementById('editUsername').value = user.username;
                        document.getElementById('editEmail').value = user.email || '';
                        document.getElementById('editRole').value = user.role;
                        document.getElementById('editStatus').value = user.status;

                        new bootstrap.Modal(document.getElementById('editUserModal')).show();
                    } else {
                        alert('Error loading user details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user details');
                });
        }

        // Change password function
        function changePassword(userId, username) {
            document.getElementById('changePasswordUserId').value = userId;
            document.getElementById('changePasswordUsername').textContent = username;

            // Reset form
            document.getElementById('changePasswordForm').reset();
            document.getElementById('changePasswordUserId').value = userId;

            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }

        // View user details
        function viewUser(userId) {
            fetch(`../../api/admin/get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        document.getElementById('userDetailsContent').innerHTML = `
                            <div class="row">
                                <div class="col-md-12 text-center mb-3">
                                    <div class="user-avatar mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                                        ${user.full_name.charAt(0).toUpperCase()}
                                    </div>
                                </div>
                            </div>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Full Name:</th>
                                    <td>${user.full_name}</td>
                                </tr>
                                <tr>
                                    <th>Username:</th>
                                    <td>${user.username}</td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>${user.email || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <th>Role:</th>
                                    <td><span class="role-badge ${user.role}">${user.role.toUpperCase()}</span></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td><span class="status-badge ${user.status}">${user.status.toUpperCase()}</span></td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td>${new Date(user.created_at).toLocaleString()}</td>
                                </tr>
                                <tr>
                                    <th>Last Login:</th>
                                    <td>${user.last_login ? new Date(user.last_login).toLocaleString() : 'Never'}</td>
                                </tr>
                            </table>
                        `;
                        new bootstrap.Modal(document.getElementById('viewUserModal')).show();
                    } else {
                        alert('Error loading user details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading user details');
                });
        }

        // Toggle user status
        function toggleUserStatus(userId, newStatus) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            if (!confirm(`Are you sure you want to ${action} this user?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', '<?= $csrf_token ?>');
            formData.append('user_id', userId);
            formData.append('status', newStatus);

            fetch('../../api/admin/toggle_user_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error updating user status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating user status');
            });
        }

        // Toggle user restriction
        function toggleRestriction(userId, isRestricted) {
            const action = isRestricted ? 'restrict' : 'unrestrict';
            const message = isRestricted
                ? 'Are you sure you want to restrict this user? They will not be able to login.'
                : 'Are you sure you want to unrestrict this user? They will be able to login again.';

            if (!confirm(message)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', '<?= $csrf_token ?>');
            formData.append('user_id', userId);
            formData.append('is_restricted', isRestricted);

            fetch('../../api/admin/toggle_user_restriction.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.success(data.message);
                    }
                    location.reload();
                } else {
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(data.message || 'Error updating user restriction');
                    } else {
                        alert(data.message || 'Error updating user restriction');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('Error updating user restriction');
                } else {
                    alert('Error updating user restriction');
                }
            });
        }

        // Form validation
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;

            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (newPass.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });

        // Password validation helper
        function validatePassword(password) {
            const errors = [];
            if (password.length < 8) {
                errors.push('Password must be at least 8 characters');
            }
            if (!/[A-Z]/.test(password)) {
                errors.push('Password must contain at least one uppercase letter');
            }
            if (!/[a-z]/.test(password)) {
                errors.push('Password must contain at least one lowercase letter');
            }
            if (!/[0-9]/.test(password)) {
                errors.push('Password must contain at least one number');
            }
            if (!/[^A-Za-z0-9]/.test(password)) {
                errors.push('Password must contain at least one special character');
            }
            return errors;
        }

        // Create user form submission
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Client-side password validation
            const password = document.getElementById('password').value;
            const passwordErrors = validatePassword(password);

            if (passwordErrors.length > 0) {
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure(passwordErrors.join(', '));
                } else {
                    alert(passwordErrors.join('\n'));
                }
                return false;
            }

            const formData = new FormData(this);

            fetch('../../api/admin/create_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.success(data.message);
                    } else {
                        alert(data.message);
                    }
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
                    // Reload page after short delay
                    setTimeout(() => location.reload(), 1000);
                } else {
                    // Show error message
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(data.message);
                    } else {
                        alert(data.message || 'Error creating user');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('Network error occurred while creating user');
                } else {
                    alert('Network error occurred while creating user');
                }
            });
        });

        // Edit user form submission
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('../../api/admin/update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.success(data.message);
                    } else {
                        alert(data.message);
                    }
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                    // Reload page after short delay
                    setTimeout(() => location.reload(), 1000);
                } else {
                    // Show error message
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(data.message);
                    } else {
                        alert(data.message || 'Error updating user');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Notiflix !== 'undefined') {
                    Notiflix.Notify.failure('Network error occurred while updating user');
                } else {
                    alert('Network error occurred while updating user');
                }
            });
        });
    </script>
</body>
</html>
