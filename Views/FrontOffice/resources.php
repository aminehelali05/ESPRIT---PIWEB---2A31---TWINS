<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ResourceController.php');

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$isAdminSidebar = strtolower(trim((string) ($sidebarUser['email'] ?? ''))) === 'admin@diversity.is';

$resourceController = new ResourceController();
$typeFilter = $_GET['type'] ?? null;
$resources = $resourceController->listResources($typeFilter);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resources — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="brainstormings.php">Brainstorming</a>
        <a href="events.php">Events</a>
        <a href="resources.php" class="active">Resources</a>
      </div>
      <!-- Navbar actions ... same as before -->
    </div>
  </nav>

  <main class="profile-main">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card">
        <nav class="left-nav">
          <a href="home.php"><i data-lucide="home" class="w-4 h-4"></i><span>Home Feed</span></a>
          <a href="social.php"><i data-lucide="users" class="w-4 h-4"></i><span>Social</span></a>
          <a href="profile.php"><i data-lucide="user" class="w-4 h-4"></i><span>Profile</span></a>
          <a href="brainstormings.php"><i data-lucide="lightbulb" class="w-4 h-4"></i><span>Brainstorming</span></a>
          <a href="events.php"><i data-lucide="calendar" class="w-4 h-4"></i><span>Events</span></a>
          <a href="resources.php" class="active"><i data-lucide="library" class="w-4 h-4"></i><span>Resources</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="section-header fade-in-section">
            <span class="section-tag">Library</span>
            <h1 class="text-h1">Platform <span class="text-gradient">Resources</span></h1>
            <p class="text-body-lg">Essential planning tools, community rules, and shared materials.</p>
        </div>

        <div class="projects-toolbar fade-in-section">
          <div class="toolbar-filters">
            <a href="resources.php" class="filter-btn <?= !$typeFilter ? 'active' : '' ?>">All</a>
            <a href="resources.php?type=planning" class="filter-btn <?= $typeFilter == 'planning' ? 'active' : '' ?>">Plannings</a>
            <a href="resources.php?type=regles" class="filter-btn <?= $typeFilter == 'regles' ? 'active' : '' ?>">Rules</a>
            <a href="resources.php?type=materiel" class="filter-btn <?= $typeFilter == 'materiel' ? 'active' : '' ?>">Material</a>
          </div>
        </div>

        <div class="grid grid-2 fade-in-section">
          <?php foreach ($resources as $res): ?>
          <div class="project-card glass-card">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                <?php
                $icon = 'book-open';
                if ($res['type'] == 'planning') $icon = 'calendar-range';
                if ($res['type'] == 'regles') $icon = 'shield-alert';
                if ($res['type'] == 'materiel') $icon = 'package';
                ?>
                <div style="padding: 10px; border-radius: 10px; background: rgba(var(--color-accent-rgb), 0.1); color: var(--color-accent);">
                    <i data-lucide="<?= $icon ?>"></i>
                </div>
                <h3 class="text-h3" style="margin: 0;"><?= htmlspecialchars($res['title']) ?></h3>
            </div>
            <p class="text-body"><?= htmlspecialchars($res['description']) ?></p>
            <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <span class="text-small" style="text-transform: uppercase; color: var(--color-accent-secondary);"><?= htmlspecialchars($res['type']) ?></span>
                <button class="btn btn-secondary btn-sm">Read More</button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($resources)): ?>
            <p class="text-body-lg">No resources available at the moment.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
