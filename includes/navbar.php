<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Detect if we're in admin subdirectory
$is_admin_page = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$base_path = $is_admin_page ? '../' : '';

// Get user info from session via auth function
require_once 'auth.php';
$current_user = get_auth_user();
$user_full = $current_user['full_name'] ?? 'Guest';
$user_role = strtolower($current_user['role'] ?? 'guest');
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= $base_path ?>dashboard.php">
        <img src="<?= $base_path ?>uploads/logo.png" alt="Logo" style="height: 40px; margin-right: 10px;">
        RESCUE 116-link
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <!-- Left Nav -->
      <ul class="navbar-nav ms-auto">
        <!-- Dashboard -->
        <li class="nav-item">
          <a class="nav-link <?php if($current_page=='dashboard.php' || $current_page=='index.php') echo 'active'; ?>" href="<?= $base_path ?>dashboard.php">
            <span class="material-icons">home</span> Dashboard
          </a>
        </li>

        <!-- New Form -->
        <li class="nav-item">
          <a class="nav-link <?php if($current_page=='TONYANG.php') echo 'active'; ?>" href="<?= $base_path ?>TONYANG.php">
            <span class="material-icons">add</span> New Form
          </a>
        </li>

        <!-- Records -->
        <li class="nav-item">
          <a class="nav-link <?php if($current_page=='records.php') echo 'active'; ?>" href="<?= $base_path ?>records.php">
            <span class="material-icons">table_view</span> Records
          </a>
        </li>

        <!-- Admin Dashboard (if admin) -->
        <?php if ($user_role === 'admin'): ?>
          <li class="nav-item">
            <a class="nav-link <?php if($current_page=='users.php' || ($current_page=='dashboard.php' && $is_admin_page)) echo 'active'; ?>" href="<?= $base_path ?>admin/users.php">
              <span class="material-icons">admin_panel_settings</span> Admin
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- Right User Dropdown -->
      <ul class="navbar-nav ms-3">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
             role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                 style="width:35px; height:35px;">
              <?= strtoupper(substr($user_full,0,1)) ?>
            </div>
            <span class="ms-2"><?= htmlspecialchars($user_full) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="<?= $base_path ?>change_password.php">Change Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= $base_path ?>logout.php">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<style>
.navbar .nav-link {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  color: #555;
}
.navbar .nav-link:hover {
  color: #0d6efd;
}
.navbar .nav-link.active {
  color: #0d6efd;
  font-weight: 600;
}
.dropdown-menu .material-icons {
  font-size: 18px;
  vertical-align: middle;
  margin-right: 4px;
}
</style>

<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
