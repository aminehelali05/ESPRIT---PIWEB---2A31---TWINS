<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/BrainstormingController.php');
include_once(__DIR__ . '/../../Controllers/IdeaController.php');

$bid = $_GET['id'] ?? null;
if (!$bid) {
    header('Location: brainstormings.php');
    exit;
}

$brainstormingController = new BrainstormingController();
$ideaController = new IdeaController();

$session = $brainstormingController->getBrainstormingById($bid);
if (!$session || $session->getStatus() !== 'ACCEPTE' && !UserController::isAdmin()) {
    header('Location: brainstormings.php');
    exit;
}

$creator = (new UserController())->getUserById($session->getUserId());
$ideas = $ideaController->listIdeasByBrainstorming($bid);

$sidebarUser = UserController::currentUser() ?? [];
$sidebarInitials = strtoupper(substr($sidebarUser['first_name'] ?? 'G', 0, 1) . substr($sidebarUser['last_name'] ?? 'U', 0, 1));
$isOwner = !empty($sidebarUser) && $sidebarUser['id'] == $session->getUserId();
$isAdmin = UserController::isAdmin();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($session->getTitle()) ?> — Innovation Hub</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
      .idea-card {
          padding: 20px;
          margin-bottom: 20px;
          border-left: 2px solid rgba(139, 92, 246, 0.4);
      }
      .creator-info {
          display: flex;
          align-items: center;
          gap: 12px;
          margin-bottom: 20px;
      }
      .creator-avatar {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: #8b5cf6;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 600;
          color: white;
      }
  </style>
</head>
<body class="grid-dot-bg">
  <nav class="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card">
        <nav class="left-nav">
          <a href="home.php"><i data-lucide="home"></i><span>Home Feed</span></a>
          <a href="brainstormings.php" class="active"><i data-lucide="lightbulb"></i><span>Brainstorming</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="glass-card" style="padding: 40px; margin-bottom: 40px;">
          <div class="creator-info">
              <div class="creator-avatar">
                  <?php if ($creator->getAvatarUrl()): ?>
                    <img src="<?= htmlspecialchars($creator->getAvatarUrl()) ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                  <?php else: ?>
                    <?= strtoupper(substr($creator->getFirstName(),0,1) . substr($creator->getLastName(),0,1)) ?>
                  <?php endif; ?>
              </div>
              <div>
                  <h4 style="margin:0;"><?= htmlspecialchars($creator->getFirstName() . ' ' . $creator->getLastName()) ?></h4>
                  <small style="color:#94a3b8;">Session Host</small>
              </div>
          </div>
          
          <h1 class="text-h1" style="margin-bottom: 20px;"><?= htmlspecialchars($session->getTitle()) ?></h1>
          <p class="text-body-lg" style="white-space: pre-wrap;"><?= htmlspecialchars($session->getDescription()) ?></p>
          
          <?php if ($isOwner || $isAdmin): ?>
          <div style="margin-top: 30px; display: flex; gap: 15px;">
              <a href="brainstorming_edit.php?id=<?= $session->getId() ?>" class="btn btn-secondary btn-sm"><i data-lucide="edit" class="w-4 h-4"></i> Edit</a>
              <a href="../../index.php?action=delete_brainstorming&id=<?= $session->getId() ?>" class="btn btn-secondary btn-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);" onclick="return confirm('Are you sure you want to delete this session?')"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</a>
          </div>
          <?php endif; ?>
        </div>

        <div class="section-header" style="text-align: left;">
            <h2 class="text-h2">Contributions <span style="color:#a78bfa;">(<?= count($ideas) ?>)</span></h2>
        </div>

        <div class="ideas-list">
            <?php foreach ($ideas as $idea): ?>
            <div class="glass-card idea-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                    <strong><?= htmlspecialchars($idea['first_name'] . ' ' . $idea['last_name']) ?></strong>
                    <small style="color:#94a3b8;"><?= date('M d, H:i', strtotime($idea['created_at'])) ?></small>
                </div>
                <p style="margin: 0;"><?= htmlspecialchars($idea['content']) ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($ideas)): ?>
                <p style="color:#94a3b8; font-style: italic;">No ideas contributed yet. Be the first!</p>
            <?php endif; ?>
        </div>

        <div class="glass-card" style="padding: 30px; margin-top: 40px;">
            <h3 style="margin-bottom: 20px;">Add your <span style="color:#a78bfa;">Idea</span></h3>
            <form id="ideaForm" action="../../index.php?action=add_idea" method="POST">
                <input type="hidden" name="brainstorming_id" value="<?= $bid ?>">
                <textarea name="content" id="formContent" rows="4" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px; margin-bottom: 20px;" placeholder="Type your innovative contribution here..."></textarea>
                <button type="submit" class="btn btn-primary">Post Contribution</button>
            </form>
        </div>
      </section>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/brainstorming-validation.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
