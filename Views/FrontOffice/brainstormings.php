<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/BrainstormingController.php');

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarDisplayName = $sidebarDisplayName !== '' ? $sidebarDisplayName : 'Guest User';
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$isAdminSidebar = UserController::isAdmin();

$brainstormingController = new BrainstormingController();
$search = $_GET['search'] ?? '';
$filters = [
    'status' => 'ACCEPTE' // Users only see accepted brainstormings
];
$brainstormings = $brainstormingController->listBrainstormings($search, $filters);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Brainstorming — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <link rel="stylesheet" href="../../assets/css/user-form.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
      .brainstorm-card {
          border-left: 4px solid #8b5cf6;
      }
      .idea-count {
          font-size: 0.75rem;
          background: rgba(139, 92, 246, 0.2);
          color: #a78bfa;
          padding: 2px 8px;
          border-radius: 10px;
          margin-top: 10px;
          display: inline-block;
      }
  </style>
</head>
<body class="grid-dot-bg">
  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Feed</a>
        <a href="projects.php">Projects</a>
        <a href="events.php">Events</a>
        <a href="brainstormings.php" class="active">Brainstorming</a>
      </div>
      <div class="navbar-actions">
        <div class="nav-profile">
          <button class="nav-profile-btn">
            <div class="nav-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          </button>
          <div class="nav-dropdown">
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user"></i> Profile</a>
            <?php if ($isAdminSidebar): ?>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard"></i> Dashboard</a>
            <?php endif; ?>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out"></i> Log Out</a>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card">
        <nav class="left-nav">
          <a href="home.php"><i data-lucide="home"></i><span>Home Feed</span></a>
          <a href="social.php"><i data-lucide="users"></i><span>Social</span></a>
          <a href="brainstormings.php" class="active"><i data-lucide="lightbulb"></i><span>Brainstorming</span></a>
          <a href="events.php"><i data-lucide="calendar"></i><span>Events</span></a>
          <a href="resources.php"><i data-lucide="library"></i><span>Resources</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="section-header">
          <span class="section-tag">Collaborate</span>
          <h1 class="text-h1">Innovation <span class="text-gradient">Hub</span></h1>
          <p class="text-body-lg">Join active brainstorming sessions and contribute your creative ideas.</p>
        </div>

        <div class="projects-toolbar">
          <form action="" method="GET" class="toolbar-filters" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search sessions..." value="<?= htmlspecialchars($search) ?>" class="glass-input">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
          </form>
          <button type="button" class="btn btn-primary btn-sm" id="openLaunchModalBtn">+ Launch Session</button>
        </div>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 12px; margin: 20px 0;">
                <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 15px; border-radius: 12px; margin: 20px 0;">
                <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-3">
          <?php foreach ($brainstormings as $b): ?>
          <div class="project-card glass-card brainstorm-card">
            <h3 class="project-title"><?= htmlspecialchars($b['title']) ?></h3>
            <p class="text-small project-desc"><?= htmlspecialchars(substr($b['description'], 0, 120)) ?>...</p>
            <div class="project-meta">
              <span class="text-small"><i data-lucide="user"></i> By <?= htmlspecialchars($b['first_name']) ?></span>
              <span class="text-small"><i data-lucide="clock"></i> <?= date('M d', strtotime($b['created_at'])) ?></span>
            </div>
            <div style="margin-top: 15px;">
                <a href="brainstorming_details.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%;">View & Contribute</a>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($brainstormings)): ?>
            <div class="glass-card" style="grid-column: span 3; padding: 40px; text-align: center;">
                <p class="text-body-lg">No active brainstorming sessions found.</p>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <!-- Launch Session Modal -->
  <div class="uf-overlay" id="launchModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="uf-card" style="max-width: 600px;">
      <div class="uf-header">
        <div class="uf-header-left">
          <p class="uf-title" id="modalTitle">Launch Brainstorming</p>
          <p class="uf-subtitle">Start a new session to gather innovative ideas from the community.</p>
        </div>
        <button type="button" id="closeLaunchModal" class="uf-close" aria-label="Close">
          <i data-lucide="x"></i>
        </button>
      </div>

      <div class="uf-body">
        <form id="brainstormingForm" action="../../index.php?action=create_brainstorming" method="POST" autocomplete="off">
          <div class="uf-grid">
            <div class="uf-group uf-span-2">
              <label class="uf-label" for="formTitle">Session Title</label>
              <input class="uf-input" id="formTitle" name="title" type="text" placeholder="What are we brainstorming about?" required>
            </div>
            
            <div class="uf-group uf-span-2 is-textarea">
              <label class="uf-label" for="formDescription">Description & Goals</label>
              <textarea class="uf-input uf-textarea" id="formDescription" name="description" rows="5" placeholder="Describe the challenges and what kind of ideas you're looking for..." required></textarea>
            </div>
          </div>

          <div style="margin-top: 30px; display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Submit for Validation</button>
            <button type="button" class="btn btn-secondary" id="cancelLaunchModal" style="flex: 1;">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/brainstorming-validation.js"></script>
  <script>
    lucide.createIcons();

    const launchModal = document.getElementById('launchModal');
    const openBtn = document.getElementById('openLaunchModalBtn');
    const closeBtn = document.getElementById('closeLaunchModal');
    const cancelBtn = document.getElementById('cancelLaunchModal');

    const toggleModal = (show) => {
        if (show) {
            launchModal.classList.add('open');
            document.body.style.overflow = 'hidden';
        } else {
            launchModal.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    openBtn?.addEventListener('click', () => toggleModal(true));
    [closeBtn, cancelBtn].forEach(b => b?.addEventListener('click', () => toggleModal(false)));

    // Handle input focus/blur for floating label effect if needed
    document.querySelectorAll('.uf-input').forEach(input => {
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                input.parentElement.classList.add('has-value');
            } else {
                input.parentElement.classList.remove('has-value');
            }
        });
    });
  </script>
</body>
</html>
