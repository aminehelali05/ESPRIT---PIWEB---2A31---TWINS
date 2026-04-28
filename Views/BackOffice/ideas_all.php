<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/IdeaController.php');

if (!UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

// Restore IdeaController
$ideaController = new IdeaController();
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';
$minScore = $_GET['minScore'] ?? '';

$extremes = $ideaController->getScoreExtremes();
$actualMin = (int)$extremes['min_score'];
$actualMax = (int)$extremes['max_score'];

$ideas = $ideaController->listAllIdeas($search, $sort, $minScore);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>All Ideas — Diversity.is Admin</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
      .badge-score {
          font-weight: bold;
          padding: 4px 10px;
          border-radius: 8px;
          background: rgba(139, 92, 246, 0.15);
          color: #a78bfa;
      }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'admin_sidebar.php'; ?>

        <main class="main-content" style="padding: 40px; overflow-y: auto;">
            <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
                <div>
                    <h1 class="text-h2">All Ideas & Contributions</h1>
                    <p class="text-small" style="color: #94a3b8;">Review platform ideas and moderate content</p>
                </div>
            </header>

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="bo-filters" style="margin-bottom: 20px;">
                <form action="" method="GET" style="display:flex; gap:10px; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by title/content..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="width:250px; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
                    
                    <input type="number" name="minScore" placeholder="Min Score..." 
                           min="<?= $actualMin ?>" max="<?= $actualMax ?>"
                           value="<?= htmlspecialchars($minScore) ?>" 
                           title="Score must be between <?= $actualMin ?> and <?= $actualMax ?>"
                           class="glass-input" style="width:150px; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
                    
                    <select name="sort" class="glass-input" style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: inherit;">
                        <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="score_desc" <?= $sort == 'score_desc' ? 'selected' : '' ?>>Highest Score</option>
                        <option value="score_asc" <?= $sort == 'score_asc' ? 'selected' : '' ?>>Lowest Score</option>
                    </select>

                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="ideas_all.php" class="btn btn-secondary btn-sm">Clear</a>
                </form>
            </div>

            <div class="bo-list" style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($ideas as $i): ?>
                <div class="card animate-enter" style="display:flex; justify-content:space-between; align-items:flex-start; padding:24px; position: relative; overflow: hidden;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <span class="status-badge" style="font-size: 0.7rem; background: rgba(var(--b-accent-rgb), 0.1); color: var(--b-accent); padding: 4px 12px; border-radius: 100px; font-weight: 700; text-transform: uppercase; border: 1px solid rgba(var(--b-accent-rgb), 0.2);">
                                <?= htmlspecialchars($i['status']) ?>
                            </span>
                            <span class="pill pill-manager" style="background: rgba(14, 165, 233, 0.1); color: #0284c7; font-size: 0.7rem;">
                                <i data-lucide="tag" style="width: 12px; height: 12px; vertical-align: middle; margin-right: 4px;"></i>
                                <?= htmlspecialchars($i['idea_type'] ?? 'Feature') ?>
                            </span>
                            <span style="font-size: 0.75rem; color: var(--b-text-light); margin-left: auto;">
                                <i data-lucide="calendar" style="width: 14px; height: 14px; vertical-align: middle; margin-right: 4px;"></i>
                                <?= date('M d, Y • H:i', strtotime($i['created_at'])) ?>
                            </span>
                        </div>
                        
                        <h3 style="margin:0 0 10px; font-size:1.25rem; color: var(--b-text-main); font-weight: 700; letter-spacing: -0.3px;">
                            <?= htmlspecialchars($i['title']) ?>
                        </h3>
                        
                        <p style="margin:0 0 16px; color: var(--b-text-muted); font-size: 0.95rem; line-height: 1.6; max-width: 900px;">
                            <?= htmlspecialchars($i['content']) ?>
                        </p>
                        
                        <div style="display:flex; gap:20px; align-items:center; padding-top: 16px; border-top: 1px solid var(--b-border);">
                            <div class="u-cell">
                                <div class="u-avatar" style="width: 28px; height: 28px; font-size: 0.7rem;">
                                    <?= strtoupper(substr($i['first_name'], 0, 1) . substr($i['last_name'], 0, 1)) ?>
                                </div>
                                <span class="u-name" style="font-size: 0.85rem;"><?= htmlspecialchars($i['first_name'] . ' ' . $i['last_name']) ?></span>
                            </div>
                            
                            <span style="font-size: 0.85rem; color: var(--b-text-muted);">
                                <i data-lucide="layers" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 6px; color: var(--b-text-light);"></i>
                                <?= htmlspecialchars(strlen($i['brainstorming_title']) > 40 ? substr($i['brainstorming_title'], 0, 40) . '...' : $i['brainstorming_title']) ?>
                            </span>
                            
                            <div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 0.8rem; color: var(--b-text-light); font-weight: 600;">SCORE</span>
                                <span class="badge-score" style="background: var(--b-accent-glow); color: var(--b-accent); padding: 4px 12px; border-radius: 8px; font-weight: 800; font-size: 1rem;">
                                    <?= (int)$i['score'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-left: 24px; padding-left: 24px; border-left: 1px solid var(--b-border);">
                        <a href="../../index.php?action=delete_idea&id=<?= $i['id'] ?>" class="t-btn t-btn-refuse" style="padding: 10px 16px; border-radius: 12px; background: rgba(239, 68, 68, 0.05);" onclick="return confirm('Delete this idea from the platform?')">
                            <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                            <span style="font-weight: 600;">Delete</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($ideas)): ?>
                    <p style="color:#94a3b8;">No ideas found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
