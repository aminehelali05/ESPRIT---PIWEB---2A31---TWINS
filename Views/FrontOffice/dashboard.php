<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$controller = new UserController();
$totalUsers = $controller->countUsers();
$activeUsers = $controller->countActiveUsers();
$adminUsers = $controller->countByRole('admin');
$currentUser = $controller->getUserById((int) $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Voices of Peace</title>
  <link rel="stylesheet" href="../assets/css/user-module.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="um-page">
    <div class="um-dashboard">
      <aside class="um-card um-sidebar">
        <div class="um-brand" style="margin-bottom:14px;"><span class="um-brand-dot">VoP</span> Dashboard</div>
        <a class="um-nav-link active" href="dashboard.php"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview</a>
        <a class="um-nav-link" href="profile.php"><i data-lucide="user-round" class="w-4 h-4"></i> Profile</a>
        <a class="um-nav-link" href="../BackOffice/user_list.php"><i data-lucide="users" class="w-4 h-4"></i> User CRUD</a>
        <a class="um-nav-link" href="home.php"><i data-lucide="house" class="w-4 h-4"></i> Home</a>
      </aside>

      <main class="um-main">
        <header class="um-topbar um-card" style="padding:10px 14px; margin:0;">
          <div>
            <strong><?= htmlspecialchars($currentUser ? $currentUser->getFullName() : 'User') ?></strong>
            <div style="color:var(--text-muted); font-size:.86rem;">Modern SaaS control center migrated from Projet-2Aref</div>
          </div>
          <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
        </header>

        <section class="um-stats">
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $totalUsers ?></h3><p>Total users</p></article>
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $activeUsers ?></h3><p>Active accounts</p></article>
          <article class="um-card um-stat tilt-hover"><h3><?= (int) $adminUsers ?></h3><p>Admin accounts</p></article>
          <article class="um-card um-stat tilt-hover"><h3>94%</h3><p>System health</p></article>
        </section>

        <section class="um-card" style="padding:18px;">
          <h2 style="margin-top:0;">Quick Navigation</h2>
          <div class="um-actions" style="margin-top:10px;">
            <a class="um-btn um-btn-primary" href="profile.php">My Profile</a>
            <a class="um-btn um-btn-soft" href="../BackOffice/user_create.php">Create User</a>
            <a class="um-btn um-btn-soft" href="../BackOffice/user_list.php">Open User List</a>
          </div>
        </section>

        <section class="um-card" style="padding:18px;">
          <h2 style="margin-top:0;">Progress Snapshot</h2>
          <div style="display:grid; gap:12px; margin-top:12px;">
            <div>
              <div style="display:flex; justify-content:space-between;"><span>Onboarding Completion</span><span>88%</span></div>
              <div class="um-progress"><span data-animate-progress data-progress="88"></span></div>
            </div>
            <div>
              <div style="display:flex; justify-content:space-between;"><span>Community Engagement</span><span>73%</span></div>
              <div class="um-progress"><span data-animate-progress data-progress="73"></span></div>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script src="../assets/js/user-module.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>