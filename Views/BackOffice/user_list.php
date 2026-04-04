<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../FrontOffice/login.php');
    exit;
}

$controller = new UserController();
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $controller->deleteUser($id);
        header('Location: user_list.php?msg=deleted');
        exit;
    }

    if ($action === 'toggle_block' && $id > 0) {
        $controller->toggleBlock($id);
        header('Location: user_list.php?msg=updated');
        exit;
    }
}

$result = $controller->getUsersPage($page, 10, $search);
$users = $result['users'];
$totalUsers = $controller->countUsers();
$activeUsers = $controller->countActiveUsers();
$adminUsers = $controller->countByRole('admin');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>
  <link rel="stylesheet" href="../assets/css/user-module.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="um-page">
    <div class="um-dashboard">
      <aside class="um-card um-sidebar">
        <div class="um-brand" style="margin-bottom:14px;"><span class="um-brand-dot">VoP</span> Admin</div>
        <a class="um-nav-link active" href="user_list.php"><i data-lucide="users" class="w-4 h-4"></i> Users</a>
        <a class="um-nav-link" href="../FrontOffice/dashboard.php"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Main Dashboard</a>
        <a class="um-nav-link" href="../FrontOffice/profile.php"><i data-lucide="user-round" class="w-4 h-4"></i> Profile</a>
      </aside>

      <main class="um-main">
        <header class="um-topbar um-card" style="padding:10px 14px; margin:0;">
          <div>
            <strong>User Management</strong>
            <div style="color:var(--text-muted); font-size:.86rem;">Migrated structure from Projet-2Aref dashboard + strict MVC CRUD</div>
          </div>
          <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
        </header>

        <section class="um-stats">
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $totalUsers ?></h3><p>Total users</p></article>
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $activeUsers ?></h3><p>Active</p></article>
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $adminUsers ?></h3><p>Admins</p></article>
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $result['pages'] ?></h3><p>Pages</p></article>
        </section>

        <section class="um-card" style="padding:16px;">
          <form method="GET" class="um-actions" style="justify-content:space-between; align-items:center;">
            <div class="um-actions" style="flex:1;">
              <input class="um-input" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email" style="max-width:380px;">
              <button class="um-btn um-btn-soft" type="submit">Filter</button>
            </div>
            <a class="um-btn um-btn-primary" href="user_create.php">Create User</a>
          </form>

          <div class="um-table-wrap" style="margin-top:14px;">
            <table class="um-table">
              <thead>
                <tr>
                  <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Country</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if (!$users): ?>
                <tr><td colspan="7" style="color:var(--text-muted);">No users found.</td></tr>
              <?php else: foreach ($users as $user): ?>
                <tr>
                  <td>#<?= (int) $user->getId() ?></td>
                  <td><?= htmlspecialchars($user->getFullName()) ?></td>
                  <td><?= htmlspecialchars($user->getEmail()) ?></td>
                  <td><span class="um-pill"><?= htmlspecialchars($user->getRole()) ?></span></td>
                  <td>
                    <?php if ((int) $user->getIsBlocked() === 1): ?>
                      <span class="um-pill um-pill-danger">Blocked</span>
                    <?php else: ?>
                      <span class="um-pill um-pill-success">Active</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($user->getCountry() ?: '-') ?></td>
                  <td>
                    <div class="um-actions">
                      <a class="um-btn-icon" href="user_details.php?id=<?= (int) $user->getId() ?>" title="Details"><i data-lucide="eye" class="w-4 h-4"></i></a>
                      <a class="um-btn-icon" href="user_edit.php?id=<?= (int) $user->getId() ?>" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                      <form method="POST" data-confirm-action="Delete this user?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $user->getId() ?>">
                        <button class="um-btn-icon" type="submit" title="Delete"><i data-lucide="trash" class="w-4 h-4"></i></button>
                      </form>
                      <form method="POST">
                        <input type="hidden" name="action" value="toggle_block">
                        <input type="hidden" name="id" value="<?= (int) $user->getId() ?>">
                        <button class="um-btn-icon" type="submit" title="Toggle Block"><i data-lucide="shield-ban" class="w-4 h-4"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script src="../assets/js/user-module.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
<?php return; ?>

<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$result = $userController->getUsersPage(1, 100); // just fetch all for dashboard
$users = $result['users'];

// Quick Stats
$totalUsers = $userController->countUsers();
$activeUsers = $userController->countActiveUsers();
$adminUsers = $userController->countByRole('admin');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Advanced 3D & Animation Scripts -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.0/vanilla-tilt.min.js"></script>
  <style>
    /* Extreme Premium UI Upgrades */
    body {
        background: #0f172a;
        overflow-x: hidden;
    }
    #particles-js {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: -1;
    }
    .dash-stat-card, .dash-card, .kpi-mini {
        background: rgba(30, 41, 59, 0.6) !important;
        backdrop-filter: blur(16px) saturate(180%);
        -webkit-backdrop-filter: blur(16px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.5) !important;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
        transform-style: preserve-3d;
    }
    .dash-stat-card:hover, .kpi-mini:hover {
        box-shadow: 0 25px 45px -5px rgba(99, 102, 241, 0.3) !important;
        border-color: rgba(99, 102, 241, 0.5) !important;
    }
    .dash-grid-2 .dash-card:hover {
        box-shadow: 0 25px 45px -5px rgba(236, 72, 153, 0.2) !important;
    }
    h2.page-title {
        background: linear-gradient(to right, #818cf8, #34d399);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0px 5px 15px rgba(52, 211, 153, 0.2);
    }
    .dash-table tbody tr {
        transition: all 0.3s ease;
        background: rgba(255,255,255,0.02);
    }
    .dash-table tbody tr:hover {
        background: rgba(99, 102, 241, 0.1) !important;
        transform: scale(1.01);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        z-index: 10;
        position: relative;
    }
    .sidebar {
        background: rgba(15, 23, 42, 0.8) !important;
        backdrop-filter: blur(20px);
        border-right: 1px solid rgba(255,255,255,0.05);
    }
    .dashboard-header {
        background: rgba(15, 23, 42, 0.6) !important;
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    td img, td .badge-pill, .table-action-btn {
        transition: all 0.3s ease;
    }
    td img:hover {
        transform: scale(1.2) rotate(10deg);
        box-shadow: 0 0 15px rgba(99,102,241,0.8);
    }
    .table-action-btn:hover {
        transform: translateY(-3px) scale(1.2);
        color: #f472b6 !important;
    }
    .floating-element {
        animation: float 6s ease-in-out infinite;
    }
    @keyframes float {
        0% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(1deg); }
        100% { transform: translateY(0px) rotate(0deg); }
    }
  </style>
</head>
<body class="grid-dot-bg dashboard-body">
  <div id="particles-js"></div>
  <div class="dashboard-layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <div class="brand-icon">⬡</div>
          <span class="sidebar-brand-text">Diversity.is</span>
        </div>
        <button class="sidebar-close" id="sidebarClose"><i data-lucide="x" class="w-5 h-5"></i></button>
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
        <a href="../FrontOffice/auth.html" class="sidebar-link sidebar-logout"><i data-lucide="log-out" class="w-4 h-4"></i><span>Sign Out</span></a>
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
              <a href="../../index.php" class="nav-dropdown-item"><i data-lucide="home" class="w-4 h-4"></i> Back to Home</a>
              <a href="../FrontOffice/auth.html" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
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
            <button class="btn btn-primary btn-sm" onclick="document.querySelector('[data-page=\'users\']').click(); document.getElementById('openUserModalBtn').click();"><i data-lucide="plus" class="w-4 h-4"></i> Add User</button>
          </div>

          <div class="stats-row">
            <div class="dash-stat-card floating-element" data-tilt data-tilt-max="15" data-tilt-speed="400" data-tilt-glare="true" data-tilt-max-glare="0.2">
              <div class="dash-stat-top" style="transform: translateZ(30px);">
                <div>
                  <p class="dash-stat-label">Total Users</p>
                  <h3 class="dash-stat-value"><?= $totalUsers ?></h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="trending-up" class="w-3.5 h-3.5"></i> Real-time CRUD synced</p>
                </div>
                <div class="dash-stat-icon icon-indigo" style="transform: translateZ(40px); background: linear-gradient(135deg, #6366f1, #a855f7); color:white; box-shadow: 0 0 20px rgba(99,102,241,0.5);"><i data-lucide="users" class="w-5 h-5"></i></div>
              </div>
            </div>
            <div class="dash-stat-card floating-element" style="animation-delay: 1s;" data-tilt data-tilt-max="15" data-tilt-speed="400" data-tilt-glare="true" data-tilt-max-glare="0.2">
              <div class="dash-stat-top" style="transform: translateZ(30px);">
                <div>
                  <p class="dash-stat-label">Active Profiles</p>
                  <h3 class="dash-stat-value"><?= $activeUsers ?></h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="activity" class="w-3.5 h-3.5"></i> Online and productive</p>
                </div>
                <div class="dash-stat-icon icon-emerald" style="transform: translateZ(40px); background: linear-gradient(135deg, #10b981, #3b82f6); color:white; box-shadow: 0 0 20px rgba(16,185,129,0.5);"><i data-lucide="user-check" class="w-5 h-5"></i></div>
              </div>
            </div>
            <div class="dash-stat-card floating-element" style="animation-delay: 2s;" data-tilt data-tilt-max="15" data-tilt-speed="400" data-tilt-glare="true" data-tilt-max-glare="0.2">
              <div class="dash-stat-top" style="transform: translateZ(30px);">
                <div>
                  <p class="dash-stat-label">Admin Accounts</p>
                  <h3 class="dash-stat-value"><?= $adminUsers ?></h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Governance and control</p>
                </div>
                <div class="dash-stat-icon icon-purple" style="transform: translateZ(40px); background: linear-gradient(135deg, #d946ef, #f43f5e); color:white; box-shadow: 0 0 20px rgba(217,70,239,0.5);"><i data-lucide="shield" class="w-5 h-5"></i></div>
              </div>
            </div>
            <div class="dash-stat-card floating-element" style="animation-delay: 3s;" data-tilt data-tilt-max="15" data-tilt-speed="400" data-tilt-glare="true" data-tilt-max-glare="0.2">
              <div class="dash-stat-top" style="transform: translateZ(30px);">
                <div>
                  <p class="dash-stat-label">AI Suggestions</p>
                  <h3 class="dash-stat-value" id="overviewSuggestions" data-target="12">12</h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Ready to apply</p>
                </div>
                <div class="dash-stat-icon icon-amber" style="transform: translateZ(40px); background: linear-gradient(135deg, #f59e0b, #ed8936); color:white; box-shadow: 0 0 20px rgba(245,158,11,0.5);"><i data-lucide="bot" class="w-5 h-5"></i></div>
              </div>
            </div>
          </div>

          <div class="dash-grid-2">
            <div class="dash-card">
              <h3 class="dash-card-title">Live Activity</h3>
              <div class="dash-activity-list" id="activityList">
                <div style="padding: 1rem; color: var(--text-muted);">Loaded dynamically...</div>
              </div>
            </div>
            <div class="dash-card">
              <h3 class="dash-card-title">Quick Actions</h3>
              <div class="quick-actions-grid">
                <button class="quick-action-btn" onclick="document.querySelector('[data-page=\'users\']').click(); document.getElementById('openUserModalBtn').click();">
                  <div class="quick-action-icon icon-indigo"><i data-lucide="user-plus" class="w-5 h-5 icon-gradient-primary"></i></div>
                  <span>Create User</span>
                </button>
                <button class="quick-action-btn">
                  <div class="quick-action-icon icon-emerald"><i data-lucide="brain-circuit" class="w-5 h-5 icon-gradient-success"></i></div>
                  <span>AI Summary</span>
                </button>
                <button class="quick-action-btn">
                  <div class="quick-action-icon icon-amber"><i data-lucide="line-chart" class="w-5 h-5 icon-gradient-warning"></i></div>
                  <span>Sales View</span>
                </button>
                <button class="quick-action-btn">
                  <div class="quick-action-icon icon-purple"><i data-lucide="truck" class="w-5 h-5 icon-gradient-purple"></i></div>
                  <span>Suppliers</span>
                </button>
              </div>
            </div>
          </div>
        </section>

        <section class="dash-page" id="users">
          <div class="page-breadcrumbs"><span>Dashboard</span><i data-lucide="chevron-right" class="w-3 h-3"></i><span>User Management</span></div>
          <div class="page-header" style="margin-top: var(--space-xs);">
            <div>
              <h2 class="page-title">User Management</h2>
            </div>
            <a href="user_create.php" class="btn btn-primary btn-sm" id="openUserModalBtn"><i data-lucide="plus" class="w-4 h-4"></i> New User</a>
          </div>

          <div class="kpi-mini-grid">
            <div class="kpi-mini"><span>Total users</span><strong><?= $totalUsers ?></strong></div>
            <div class="kpi-mini"><span>Active now</span><strong><?= $activeUsers ?></strong></div>
            <div class="kpi-mini"><span>Admins</span><strong><?= $adminUsers ?></strong></div>
            <div class="kpi-mini"><span>New this month</span><strong>8</strong></div>
          </div>

          <div class="dash-card">
            <div class="filters-row">
              <div class="filter-group form-group">
                <label>Search</label>
                <div class="input-with-icon">
                  <i data-lucide="search" class="w-4 h-4 text-secondary"></i>
                  <input type="text" class="form-control" id="usersSearchInput" placeholder="Name, email, location...">
                </div>
              </div>
              <div class="filter-group form-group" style="min-width: 170px;">
                <label>Role</label>
                <select class="form-control" id="usersRoleFilter">
                  <option value="all">All roles</option>
                  <option value="admin">Admin</option>
                  <option value="manager">Manager</option>
                  <option value="member">Member</option>
                  <option value="user">User</option>
                </select>
              </div>
              <div class="filter-group form-group" style="min-width: 170px;">
                <label>Status</label>
                <select class="form-control" id="usersStatusFilter">
                  <option value="all">All status</option>
                  <option value="active">Active</option>
                  <option value="offline">Offline</option>
                  <option value="blocked">Blocked</option>
                </select>
              </div>
            </div>

            <div class="table-wrap">
              <table class="dash-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Last Active</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody id="usersTableBody">
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td>#<?= $u->getId() ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <?php if($u->getAvatarUrl()): ?>
                                    <img src="<?= htmlspecialchars($u->getAvatarUrl()) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--surface-2); display:flex; align-items:center; justify-content:center; font-weight:600; font-size: 0.8rem;">
                                      <?= htmlspecialchars($u->getInitials()) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($u->getFullName()) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($u->getEmail()) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-pill"><?= htmlspecialchars(ucfirst($u->getRole())) ?></span></td>
                        <td>
                            <?php if($u->getIsBlocked() == 1): ?>
                                <span class="badge-pill" style="background: rgba(239, 68, 68, 0.1); color: var(--color-danger);"><span style="display:inline-block; width:6px; height:6px; background:var(--color-danger); border-radius:50%; margin-right:6px;"></span> Blocked</span>
                            <?php else: ?>
                                <span class="badge-pill" style="background: rgba(16, 185, 129, 0.1); color: var(--color-success);"><span style="display:inline-block; width:6px; height:6px; background:var(--color-success); border-radius:50%; margin-right:6px;"></span> Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($u->getCountry() ?: 'N/A') ?></td>
                        <td><?= $u->getLastSeen() ? date('M j, Y H:i', strtotime($u->getLastSeen())) : 'Never' ?></td>
                        <td style="text-align:right;">
                            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                                <a class="table-action-btn table-action-edit" href="user_edit.php?id=<?= $u->getId() ?>" title="Edit"><i class="lucide-icon" data-lucide="edit-2" style="width:16px;height:16px"></i></a>
                                <form method="POST" action="user_list.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="delete_user_id" value="<?= $u->getId() ?>">
                                    <button type="submit" class="table-action-btn table-action-delete" title="Delete"><i class="lucide-icon" data-lucide="trash-2" style="width:16px;height:16px"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
              </table>
              <?php if(empty($users)): ?>
              <div class="empty-state" id="usersEmptyState">
                <i data-lucide="users" class="w-5 h-5"></i>
                <p>No users match this filter set.</p>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <!-- Additional UI components remain as they were in HTML template -->
        </section>

      </div>
    </main>
  </div>

  <script src="../assets/js/main.js"></script>
  <!-- <script src="../assets/js/dashboard.js"></script> Commented out if it handles raw JS fetching that overrides our PHP -->
  <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
  <script>
    lucide.createIcons();
    particlesJS('particles-js', {
      "particles": {
        "number": {"value": 50, "density": {"enable": true, "value_area": 1000}},
        "color": {"value": ["#6366f1", "#10b981", "#ec4899"]},
        "shape": {"type": "circle"},
        "opacity": {"value": 0.4, "random": true, "anim": {"enable": true, "speed": 1, "opacity_min": 0.1, "sync": false}},
        "size": {"value": 3, "random": true, "anim": {"enable": true, "speed": 2, "size_min": 0.1, "sync": false}},
        "line_linked": {"enable": true, "distance": 150, "color": "#cbd5e1", "opacity": 0.1, "width": 1},
        "move": {"enable": true, "speed": 1, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false}
      },
      "interactivity": {
        "detect_on": "window",
        "events": {"onhover": {"enable": true, "mode": "bubble"}, "onclick": {"enable": true, "mode": "repulse"}, "resize": true},
        "modes": {"bubble": {"distance": 200, "size": 6, "duration": 2, "opacity": 0.8, "speed": 3}, "repulse": {"distance": 200, "duration": 0.4}}
      },
      "retina_detect": true
    });

    // basic tab switching for dashboard
    document.querySelectorAll('.sidebar-link[data-page]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            document.querySelectorAll('.dash-page').forEach(p => p.classList.remove('active'));
            document.getElementById(link.getAttribute('data-page')).classList.add('active');
        });
    });
  </script>
</body>
</html>
