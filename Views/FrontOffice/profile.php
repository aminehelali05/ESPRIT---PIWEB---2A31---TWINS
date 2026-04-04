<?php
require_once __DIR__ . '/profile_rebuilt.php';
exit;
__halt_compiler();

$userController = new UserController();
$error = '';
$success = '';
$editingUser = null;

// CRUD Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && !empty($_POST['id'])) {
        $userController->deleteUser((int) $_POST['id']);
        header('Location: profile.php?success=deleted#crud');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $error = 'First name, last name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            if ($action === 'update') {
                $current = $userController->getUserById($id);
                if (!$current) {
                    $error = 'User not found.';
                } elseif ($userController->emailExists($email, $id)) {
                    $error = 'Email already used by another account.';
                } else {
                    $hashedPassword = $current->getPassword();
                    if ($password !== '') $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updated = new User($firstName, $lastName, $email, $hashedPassword, $phone, $current->getRole(), $current->getStatus(), $current->getCreatedAt());
                    $userController->updateUser($updated, $id);
                    header('Location: profile.php?success=updated#crud');
                    exit;
                }
            } else {
                if ($password === '') {
                    $error = 'Password is required for account creation.';
                } elseif ($userController->emailExists($email)) {
                    $error = 'Email already exists.';
                } else {
                    $newUser = new User($firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $phone, 'user', 1, date('Y-m-d H:i:s'));
                    $userController->addUser($newUser);
                    header('Location: profile.php?success=created#crud');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editingUser = $userController->getUserById((int) $_GET['edit']);
}

if (isset($_GET['success'])) {
    $msgs = ['created' => 'Account created successfully.', 'updated' => 'Profile updated successfully.', 'deleted' => 'User deleted successfully.'];
    $success = $msgs[$_GET['success']] ?? '';
}

$users = $userController->listUsers();

// Session-based profile or first user
$sessionUserId = $_SESSION['user_id'] ?? null;
$profileUser = null;
if ($sessionUserId) {
    $profileUser = $userController->getUserById($sessionUserId);
}
if (!$profileUser && count($users) > 0) {
    $profileUser = $users[0];
}
$profileName = $profileUser ? $profileUser->getFullName() : 'Guest User';
$profileEmail = $profileUser ? $profileUser->getEmail() : 'guest@diversity.is';
$profileRole = $profileUser ? $profileUser->getRole() : 'user';
$profileCreated = $profileUser ? $profileUser->getCreatedAt() : date('Y-m-d');
$profilePhone = $profileUser ? $profileUser->getPhone() : '';
$profileInitials = '';
foreach (explode(' ', $profileName) as $w) { $profileInitials .= strtoupper(substr($w, 0, 1)); }
$profileInitials = substr($profileInitials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Your Diversity.is profile — manage identity, skills, and community impact.">
  <title>Profile — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .profile-hero {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 1rem;
      padding: 2rem;
      display: flex;
      align-items: center;
      gap: 2rem;
      margin-bottom: 1.5rem;
      position: relative;
    }
    [data-theme="light"] .profile-hero { background: #fff; border-color: rgba(0,0,0,0.06); box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .profile-hero-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--color-accent), var(--color-accent-secondary));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 2.2rem;
      font-weight: 700;
      flex-shrink: 0;
      position: relative;
    }
    .avatar-badge {
      position: absolute;
      bottom: 4px;
      right: 4px;
      width: 24px;
      height: 24px;
      background: rgba(34,211,238,0.9);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid var(--color-bg-primary);
    }
    .profile-hero-info h2 {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    .profile-hero-meta {
      display: flex;
      align-items: center;
      gap: 1rem;
      color: var(--color-text-secondary);
      font-size: 0.85rem;
      margin-bottom: 0.75rem;
    }
    .profile-hero-meta span { display: flex; align-items: center; gap: 4px; }
    .profile-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .profile-badge {
      padding: 4px 14px;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 600;
    }
    .badge-primary { background: rgba(99,102,241,0.12); color: #818CF8; border: 1px solid rgba(99,102,241,0.25); }
    .badge-success { background: rgba(34,197,94,0.12); color: #86EFAC; border: 1px solid rgba(34,197,94,0.25); }
    .profile-hero-actions {
      position: absolute;
      top: 1.5rem;
      right: 1.5rem;
      display: flex;
      gap: 0.5rem;
    }
    .hero-action-btn {
      width: 38px;
      height: 38px;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255,255,255,0.08);
      background: transparent;
      color: var(--color-text-secondary);
      cursor: pointer;
      transition: all 0.2s;
    }
    [data-theme="light"] .hero-action-btn { border-color: rgba(0,0,0,0.08); }
    .hero-action-btn:hover { background: rgba(99,102,241,0.1); color: var(--color-accent); border-color: rgba(99,102,241,0.2); }
    .hero-action-btn.outline {
      border-radius: 999px;
      padding: 0 1rem;
      width: auto;
      gap: 6px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    .profile-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 1.5rem; margin-bottom: 1.5rem; }
    @media (max-width: 800px) { .profile-grid { grid-template-columns: 1fr; } .profile-hero { flex-direction: column; text-align: center; } }
    .panel {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 0.75rem;
      padding: 1.5rem;
    }
    [data-theme="light"] .panel { background: #fff; border-color: rgba(0,0,0,0.06); box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .panel-head h4 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-secondary); font-weight: 600; }
    .panel-head a { font-size: 0.72rem; color: var(--color-text-secondary); text-decoration: none; }
    .engagement-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .engagement-card {
      padding: 1.25rem;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.04);
      border-radius: 0.5rem;
    }
    [data-theme="light"] .engagement-card { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.04); }
    .engagement-card p.num { font-size: 2rem; font-weight: 700; margin-top: 0.5rem; }
    .engagement-card p.label { font-size: 0.72rem; color: var(--color-text-secondary); }
    .ai-panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }
    .ai-panel-header h4 { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-accent-secondary); font-weight: 600; }
    .ai-panel-header span { font-size: 0.7rem; color: var(--color-text-secondary); }
    .ai-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem; }
    .ai-desc { font-size: 0.85rem; color: var(--color-text-secondary); line-height: 1.5; }
    .impact-bar { margin-top: 1.25rem; }
    .impact-bar-header { display: flex; justify-content: space-between; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-secondary); margin-bottom: 0.5rem; }
    .impact-track { height: 6px; background: rgba(255,255,255,0.06); border-radius: 3px; overflow: hidden; }
    [data-theme="light"] .impact-track { background: rgba(0,0,0,0.06); }
    .impact-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #22C55E, #A855F7, #6366F1); }
    .skill-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; }
    .skill-tag {
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 0.72rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.06);
      color: var(--color-text-primary);
    }
    [data-theme="light"] .skill-tag { background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.06); }
    .skill-tag .x { cursor: pointer; color: var(--color-text-secondary); margin-left: 4px; }
    .tabs { display: flex; gap: 2rem; border-bottom: 1px solid rgba(255,255,255,0.06); margin-bottom: 1.5rem; }
    [data-theme="light"] .tabs { border-color: rgba(0,0,0,0.06); }
    .tab { font-size: 0.85rem; padding-bottom: 0.75rem; color: var(--color-text-secondary); cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
    .tab.active { color: var(--color-text-primary); border-color: var(--color-accent); }
    .tab:hover:not(.active) { color: var(--color-text-primary); }
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 700px) { .settings-grid { grid-template-columns: 1fr; } }
    .setting-card {
      padding: 1.25rem;
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 0.5rem;
    }
    [data-theme="light"] .setting-card { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.06); }
    .setting-card h5 { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; }
    .setting-card p { font-size: 0.78rem; color: var(--color-text-secondary); }
    .alert-bar {
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      font-size: 0.82rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    .alert-success { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.25); color: #86EFAC; }
    .alert-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); color: #FCA5A5; }
    [data-theme="light"] .alert-success { color: #16A34A; }
    [data-theme="light"] .alert-error { color: #DC2626; }
    .crud-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 800px) { .crud-grid { grid-template-columns: 1fr; } }
    .form-label-sm { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 0.25rem; color: var(--color-text-secondary); }
    .form-ctrl {
      width: 100%;
      padding: 0.6rem 0.85rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 0.5rem;
      color: var(--color-text-primary);
      font-size: 0.85rem;
      font-family: inherit;
      transition: all 0.2s;
    }
    [data-theme="light"] .form-ctrl { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.08); }
    .form-ctrl:focus { outline: none; border-color: var(--color-accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
    .user-cell { display: flex; align-items: center; gap: 0.75rem; }
    .user-avatar-sm {
      width: 30px; height: 30px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.55rem; font-weight: 700; color: white; flex-shrink: 0;
    }
    .role-pill {
      display: inline-flex; padding: 2px 8px; border-radius: 999px;
      font-size: 0.6rem; font-weight: 600; text-transform: capitalize;
    }
    .role-admin { background: rgba(168,85,247,0.12); color: #C084FC; }
    .role-manager { background: rgba(245,158,11,0.12); color: #FBBF24; }
    .role-user { background: rgba(99,102,241,0.12); color: #818CF8; }
    .action-sm {
      width: 28px; height: 28px; border-radius: 0.25rem;
      display: inline-flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.06); background: transparent;
      color: var(--color-text-secondary); cursor: pointer; transition: all 0.2s;
      text-decoration: none;
    }
    .action-sm:hover { background: rgba(99,102,241,0.1); color: var(--color-accent); }
    .action-sm.danger:hover { background: rgba(239,68,68,0.1); color: #EF4444; }
  </style>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php">Reviews</a>
        <a href="challenges.php">Challenges</a>
      </div>
      <div class="navbar-actions">
        <a href="auth.php" class="btn btn-primary btn-sm">Sign In</a>
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu"><div class="nav-avatar"><?= $profileInitials ?></div></button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header"><strong><?= htmlspecialchars($profileName) ?></strong><span><?= htmlspecialchars($profileEmail) ?></span></div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/UserDashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <main class="profile-main" style="padding-top:96px;">
    <div class="container">

      <!-- ===== PROFILE HERO ===== -->
      <div class="profile-hero fade-in-section">
        <div class="profile-hero-avatar">
          <?= $profileInitials ?>
          <div class="avatar-badge"><i data-lucide="camera" class="w-3 h-3" style="color:#0B0F1A;"></i></div>
        </div>
        <div class="profile-hero-info">
          <h2><?= htmlspecialchars($profileName) ?></h2>
          <div class="profile-hero-meta">
            <span><i data-lucide="mail" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($profileEmail) ?></span>
            <span>•</span>
            <span><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Joined <?= date('M Y', strtotime($profileCreated)) ?></span>
          </div>
          <div class="profile-badges">
            <span class="profile-badge badge-primary">Community Builder</span>
            <span class="profile-badge badge-success"><?= ucfirst($profileRole) ?></span>
          </div>
        </div>
        <div class="profile-hero-actions">
          <button class="hero-action-btn outline"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i> Edit Details</button>
          <button class="hero-action-btn"><i data-lucide="download" class="w-4 h-4"></i></button>
          <button class="hero-action-btn"><i data-lucide="log-out" class="w-4 h-4"></i></button>
        </div>
      </div>

      <!-- ===== ENGAGEMENT + AI IMPACT ===== -->
      <div class="profile-grid fade-in-section">
        <div>
          <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-head"><h4>Engagement</h4><a href="#">View All</a></div>
            <div class="engagement-cards">
              <div class="engagement-card">
                <i data-lucide="heart" class="w-4 h-4" style="color:var(--color-text-secondary);"></i>
                <p class="num"><?= count($users) ?></p>
                <p class="label">Actions Joined</p>
              </div>
              <div class="engagement-card">
                <i data-lucide="box" class="w-4 h-4" style="color:var(--color-text-secondary);"></i>
                <p class="num"><?= max(0, count($users)-1) ?></p>
                <p class="label">Resources Shared</p>
              </div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-head"><h4>Skills</h4><a href="#" style="font-size: 1rem;">+</a></div>
            <div class="skill-tags">
              <span class="skill-tag">Conflict Resolution <span class="x">×</span></span>
              <span class="skill-tag">Translation <span class="x">×</span></span>
              <span class="skill-tag">Logistics <span class="x">×</span></span>
            </div>
            <input type="text" class="form-ctrl" placeholder="Add a skill..." style="margin-top: 0.75rem;">
          </div>
        </div>

        <div class="panel">
          <div class="ai-panel-header">
            <h4><i data-lucide="sparkles" class="w-4 h-4"></i> AI Impact Analysis</h4>
            <span>Updated 2h ago</span>
          </div>
          <h3 class="ai-title">Community Catalyst</h3>
          <p class="ai-desc">Your collaboration score is trending up. You've impacted <strong><?= count($users) ?> local communities</strong> recently.</p>
          <div class="impact-bar">
            <div class="impact-bar-header"><span>Impact Score</span><span>Top 15%</span></div>
            <div class="impact-track"><div class="impact-fill" style="width: 85%;"></div></div>
          </div>
        </div>
      </div>

      <!-- ===== SETTINGS TABS ===== -->
      <div class="fade-in-section" style="margin-bottom: 2rem;">
        <div class="tabs">
          <div class="tab active">Settings</div>
          <div class="tab">History</div>
          <div class="tab">Security</div>
        </div>
        <div class="settings-grid">
          <div class="setting-card">
            <h5><i data-lucide="bell" class="w-4 h-4"></i> Notifications</h5>
            <p>Manage your email and push notification preferences.</p>
          </div>
          <div class="setting-card">
            <h5><i data-lucide="lock" class="w-4 h-4"></i> Privacy</h5>
            <p>Control who can see your profile and activity data.</p>
          </div>
          <div class="setting-card">
            <h5><i data-lucide="globe" class="w-4 h-4"></i> Language</h5>
            <p>Change your display language and regional settings.</p>
          </div>
          <div class="setting-card">
            <h5><i data-lucide="palette" class="w-4 h-4"></i> Appearance</h5>
            <p>Toggle dark/light mode and customize accent colors.</p>
          </div>
        </div>
      </div>

      <!-- ===== CRUD SECTION ===== -->
      <div id="crud" class="fade-in-section">
        <h3 style="font-size:1.1rem; font-weight:600; margin-bottom:1rem;">User Management</h3>

        <?php if ($success): ?>
          <div class="alert-bar alert-success"><i data-lucide="check-circle" class="w-4 h-4"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert-bar alert-error"><i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="crud-grid">
          <div class="panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
              <h4 style="font-size:0.85rem; font-weight:600;">Users (<?= count($users) ?>)</h4>
            </div>
            <div class="table-responsive" style="overflow-x:auto;">
              <table class="table table-hover align-middle" style="width:100%; border-collapse:collapse;">
                <thead>
                  <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
                    <th style="padding:8px; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--color-text-secondary);">User</th>
                    <th style="padding:8px; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--color-text-secondary);">Role</th>
                    <th style="padding:8px; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:var(--color-text-secondary); text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($users) === 0): ?>
                    <tr><td colspan="3" style="padding:1.5rem; text-align:center; color:var(--color-text-secondary); font-size:0.85rem;">No users found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($users as $u):
                      $ini = strtoupper(substr($u->getFirstName(),0,1) . substr($u->getLastName(),0,1));
                      $cls = ['#6366F1','#22C55E','#F59E0B','#EF4444','#A855F7','#22D3EE','#EC4899'];
                      $rc = 'role-' . strtolower($u->getRole());
                    ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                      <td style="padding:8px;">
                        <div class="user-cell">
                          <div class="user-avatar-sm" style="background:<?= $cls[$u->getId() % count($cls)] ?>;"><?= $ini ?></div>
                          <div><strong style="font-size:0.82rem;"><?= htmlspecialchars($u->getFullName()) ?></strong><br><span style="font-size:0.7rem; color:var(--color-text-secondary);"><?= htmlspecialchars($u->getEmail()) ?></span></div>
                        </div>
                      </td>
                      <td style="padding:8px;"><span class="role-pill <?= $rc ?>"><?= htmlspecialchars($u->getRole()) ?></span></td>
                      <td style="padding:8px; text-align:right;">
                        <div style="display:flex; gap:4px; justify-content:flex-end;">
                          <a href="profile.php?edit=<?= (int) $u->getId() ?>#crud" class="action-sm"><i data-lucide="pencil" class="w-3 h-3"></i></a>
                          <form method="POST" action="profile.php#crud" style="margin:0;" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $u->getId() ?>">
                            <button type="submit" class="action-sm danger"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="panel">
            <h4 style="font-size:0.85rem; font-weight:600; margin-bottom:1rem;">
              <i data-lucide="<?= $editingUser ? 'edit-3' : 'user-plus' ?>" class="w-4 h-4" style="display:inline; vertical-align:middle;"></i>
              <?= $editingUser ? 'Edit User' : 'Create Account' ?>
            </h4>
            <form method="POST" action="profile.php#crud">
              <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
              <?php if ($editingUser): ?><input type="hidden" name="id" value="<?= (int) $editingUser->getId() ?>"><?php endif; ?>
              <div class="form-group" style="margin-bottom:0.75rem;"><label class="form-label-sm">First name</label><input class="form-ctrl" name="first_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getFirstName() : ($_POST['first_name'] ?? '')) ?>"></div>
              <div class="form-group" style="margin-bottom:0.75rem;"><label class="form-label-sm">Last name</label><input class="form-ctrl" name="last_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getLastName() : ($_POST['last_name'] ?? '')) ?>"></div>
              <div class="form-group" style="margin-bottom:0.75rem;"><label class="form-label-sm">Email</label><input class="form-ctrl" name="email" type="email" required value="<?= htmlspecialchars($editingUser ? $editingUser->getEmail() : ($_POST['email'] ?? '')) ?>"></div>
              <div class="form-group" style="margin-bottom:0.75rem;"><label class="form-label-sm">Password <?= $editingUser ? '(optional)' : '' ?></label><input class="form-ctrl" name="password" type="password" <?= $editingUser ? '' : 'required' ?>></div>
              <div class="form-group" style="margin-bottom:0.75rem;"><label class="form-label-sm">Phone</label><input class="form-ctrl" name="phone" value="<?= htmlspecialchars($editingUser ? $editingUser->getPhone() : ($_POST['phone'] ?? '')) ?>"></div>
              <div style="display:flex; gap:8px; margin-top:0.75rem;">
                <button class="btn btn-primary btn-sm" type="submit"><?= $editingUser ? 'Save' : 'Create' ?></button>
                <?php if ($editingUser): ?><a href="profile.php#crud" class="btn btn-secondary btn-sm">Cancel</a><?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>

    </div>
  </main>

  <script src="../assets/js/main.js"></script>
  <script>
    if (window.lucide) lucide.createIcons();
  </script>
</body>
</html>
