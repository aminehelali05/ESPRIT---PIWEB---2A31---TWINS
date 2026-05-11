<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarDisplayName = $sidebarDisplayName !== '' ? $sidebarDisplayName : 'Guest User';
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$isAdminSidebar = strtolower(trim((string) ($sidebarUser['email'] ?? ''))) === 'admin@diversity.is';

$eventController = new EventController();
$search = $_GET['search'] ?? '';
$filters = [
    'status' => 'ACCEPTE', // Users only see accepted events by default
    'category' => $_GET['category'] ?? ''
];
$events = $eventController->listEvents($search, $filters);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Community events and meetups on Diversity.is.">
  <title>Events — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="auth.php">Sign In</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="events.php" class="active">Events</a>
        <a href="resources.php">Resources</a>
      </div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($sidebarDisplayName) ?></strong>
              <span><?= htmlspecialchars($sidebarUser['email'] ?? '') ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <?php if ($isAdminSidebar): ?>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <?php endif; ?>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <main class="profile-main" id="main-content" tabindex="-1">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          <div>
            <h4><?= htmlspecialchars($sidebarDisplayName) ?></h4>
            <p><?= htmlspecialchars($sidebarUser['role'] ?? 'User') ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><i data-lucide="home" class="w-4 h-4"></i><span>Home Feed</span></a>
          <a href="social.php"><i data-lucide="users" class="w-4 h-4"></i><span>Social</span></a>
          <a href="profile.php"><i data-lucide="user" class="w-4 h-4"></i><span>Profile</span></a>
          <a href="events.php" class="active"><i data-lucide="calendar" class="w-4 h-4"></i><span>Events</span></a>
          <a href="resources.php"><i data-lucide="library" class="w-4 h-4"></i><span>Resources</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="section-header fade-in-section">
          <span class="section-tag">Explore</span>
          <h1 class="text-h1">Community <span class="text-gradient">Events</span></h1>
          <p class="text-body-lg">Discover and join events tailored for freelancers and professionals.</p>
        </div>

        <div class="projects-toolbar fade-in-section">
          <form action="" method="GET" class="toolbar-filters" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="padding: 8px 12px; border-radius: 8px;">
            <select name="category" class="glass-input" style="padding: 8px 12px; border-radius: 8px;">
              <option value="">All Categories</option>
              <option value="Workshop" <?= $filters['category'] == 'Workshop' ? 'selected' : '' ?>>Workshop</option>
              <option value="Networking" <?= $filters['category'] == 'Networking' ? 'selected' : '' ?>>Networking</option>
              <option value="Webinar" <?= $filters['category'] == 'Webinar' ? 'selected' : '' ?>>Webinar</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
          </form>
          <a href="event_create.php" class="btn btn-primary btn-sm">+ Suggest Event</a>
        </div>

        <div class="grid grid-3 fade-in-section">
          <?php foreach ($events as $event): ?>
          <div class="project-card glass-card tilt-card">
            <div class="project-status status-active"><?= htmlspecialchars($event['category']) ?></div>
            <h3 class="project-title"><?= htmlspecialchars($event['title']) ?></h3>
            <p class="text-small project-desc"><?= htmlspecialchars(substr($event['description'], 0, 100)) ?>...</p>
            <div class="project-meta">
              <span class="text-small"><i data-lucide="calendar" class="w-3 h-3"></i> <?= date('M d, Y', strtotime($event['event_date'])) ?></span>
              <span class="text-small"><i data-lucide="map-pin" class="w-3 h-3"></i> <?= htmlspecialchars($event['location']) ?></span>
            </div>
            <div style="margin-top: 15px;">
                <a href="event_details.php?id=<?= $event['id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%;">View Details</a>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($events)): ?>
            <p class="text-body-lg">No events found matching your criteria.</p>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
