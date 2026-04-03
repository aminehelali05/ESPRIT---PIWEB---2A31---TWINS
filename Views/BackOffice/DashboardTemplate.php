<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$users = $userController->listUsers();
$activeUsers = count(array_filter($users, fn($u) => (int) $u->getStatus() === 1));
$adminUsers = count(array_filter($users, fn($u) => strtolower($u->getRole()) === 'admin'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is user dashboard with rich CRUD, AI assistance, analytics and operations modules.">
  <title>Dashboard — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg dashboard-body">
  <div class="dashboard-layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <div class="brand-icon">⬡</div>
          <span class="sidebar-brand-text">Diversity.is</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a href="#overview" class="sidebar-link active" data-page="overview"><i data-lucide="layout-dashboard" class="w-4 h-4"></i><span>Dashboard</span></a>
        <a href="#users" class="sidebar-link" data-page="users"><i data-lucide="users" class="w-4 h-4"></i><span>User Management</span></a>
        <a href="#clients" class="sidebar-link" data-page="clients"><i data-lucide="briefcase" class="w-4 h-4"></i><span>Clients</span></a>
        <a href="#employees" class="sidebar-link" data-page="employees"><i data-lucide="contact" class="w-4 h-4"></i><span>Employees</span></a>
        <a href="#products" class="sidebar-link" data-page="products"><i data-lucide="package" class="w-4 h-4"></i><span>Products</span></a>
        <a href="#sales" class="sidebar-link" data-page="sales"><i data-lucide="shopping-cart" class="w-4 h-4"></i><span>Sales</span></a>
        <a href="#suppliers" class="sidebar-link" data-page="suppliers"><i data-lucide="truck" class="w-4 h-4"></i><span>Suppliers</span></a>
      </nav>

      <div class="sidebar-footer">
        <a href="../FrontOffice/auth.php" class="sidebar-link sidebar-logout"><i data-lucide="log-out" class="w-4 h-4"></i><span>Sign Out</span></a>
      </div>
    </aside>

    <main class="dashboard-main" id="dashboardMain">
      <header class="dashboard-header">
        <div class="header-left">
          <button class="header-menu-btn" id="headerToggle"><i data-lucide="menu" class="w-5 h-5"></i></button>
          <div class="header-search">
            <i data-lucide="search" class="w-4 h-4 search-icon"></i>
            <input type="text" placeholder="Search dashboard modules..." class="search-input" id="globalSearchInput">
          </div>
        </div>
        <div class="header-right">
          <button class="theme-toggle" aria-label="Toggle theme">
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          </button>
          <button class="header-icon-btn" id="notifBtn"><i data-lucide="bell" class="w-5 h-5"></i><span class="notif-badge">4</span></button>
          <div class="header-divider"></div>
          <div class="nav-profile">
            <button class="nav-profile-btn" aria-label="User menu">
              <div class="nav-avatar">AD</div>
              <span class="user-role-label hidden-mobile" style="font-size: 0.8rem; margin-left: var(--space-xs); font-weight: 500;">Admin User</span>
            </button>
            <div class="nav-dropdown">
              <div class="nav-dropdown-header">
                <strong>Admin User</strong>
                <span>admin@diversity.is</span>
              </div>
              <a href="../FrontOffice/profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
              <a href="../FrontOffice/home.php" class="nav-dropdown-item"><i data-lucide="home" class="w-4 h-4"></i> Back to Home</a>
              <a href="../FrontOffice/auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
            </div>
          </div>
        </div>
      </header>

      <div class="dashboard-content">
        <section class="dash-page active" id="overview">
          <div class="page-header">
            <div>
              <h2 class="page-title">Diversity.is Dashboard</h2>
              <p class="page-subtitle">A complete command center for users, operations, and AI-guided decisions.</p>
            </div>
            <a href="AddUser.php" class="btn btn-primary btn-sm"><i data-lucide="plus" class="w-4 h-4"></i> Add User</a>
          </div>

          <div class="stats-row">
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">Total Users</p><h3 class="dash-stat-value"><?= count($users) ?></h3></div><div class="dash-stat-icon icon-indigo"><i data-lucide="users" class="w-5 h-5 icon-gradient-primary"></i></div></div></div>
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">Active Profiles</p><h3 class="dash-stat-value"><?= $activeUsers ?></h3></div><div class="dash-stat-icon icon-emerald"><i data-lucide="user-check" class="w-5 h-5 icon-gradient-success"></i></div></div></div>
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">Admin Accounts</p><h3 class="dash-stat-value"><?= $adminUsers ?></h3></div><div class="dash-stat-icon icon-purple"><i data-lucide="shield" class="w-5 h-5 icon-gradient-purple"></i></div></div></div>
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">AI Suggestions</p><h3 class="dash-stat-value">12</h3></div><div class="dash-stat-icon icon-amber"><i data-lucide="bot" class="w-5 h-5 icon-gradient-warning"></i></div></div></div>
          </div>
        </section>

        <section class="dash-page" id="users">
          <div class="page-breadcrumbs"><span>Dashboard</span><i data-lucide="chevron-right" class="w-3 h-3"></i><span>User Management</span></div>
          <div class="page-header" style="margin-top: var(--space-xs);">
            <div><h2 class="page-title">User Management</h2></div>
            <a href="AddUser.php" class="btn btn-primary btn-sm"><i data-lucide="plus" class="w-4 h-4"></i> New User</a>
          </div>

          <div class="dash-card">
            <div class="table-wrap">
              <table class="dash-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Email</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($users) === 0): ?>
                    <tr><td colspan="6">No users found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                      <td><?= (int) $user->getId() ?></td>
                      <td><?= htmlspecialchars($user->getFullName()) ?></td>
                      <td><?= htmlspecialchars($user->getRole()) ?></td>
                      <td><?= (int) $user->getStatus() === 1 ? 'Active' : 'Inactive' ?></td>
                      <td><?= htmlspecialchars($user->getEmail()) ?></td>
                      <td style="text-align:right;">
                        <a href="EditUser.php?id=<?= (int) $user->getId() ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <a href="DeleteUser.php?id=<?= (int) $user->getId() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Delete this user?');">Delete</a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section class="dash-page" id="clients"><div class="page-header"><div><h2 class="page-title">Clients</h2><p class="page-subtitle">Portfolio quality, retention, and relationship health.</p></div></div></section>
        <section class="dash-page" id="employees"><div class="page-header"><div><h2 class="page-title">Employees</h2><p class="page-subtitle">Capacity, talent distribution, and team performance.</p></div></div></section>
        <section class="dash-page" id="products"><div class="page-header"><div><h2 class="page-title">Products</h2><p class="page-subtitle">Roadmap execution and release confidence.</p></div></div></section>
        <section class="dash-page" id="sales"><div class="page-header"><div><h2 class="page-title">Sales</h2><p class="page-subtitle">Pipeline visibility and conversion intelligence.</p></div></div></section>
        <section class="dash-page" id="suppliers"><div class="page-header"><div><h2 class="page-title">Suppliers</h2><p class="page-subtitle">Vendor reliability, procurement timing, and cost visibility.</p></div></div></section>
      </div>
    </main>
  </div>

  <script src="../assets/js/main.js"></script>
  <script>
    if (window.lucide) window.lucide.createIcons();
  </script>
</body>
</html>
