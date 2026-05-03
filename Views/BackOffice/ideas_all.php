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
$bid = $_GET['bid'] ?? null;

$extremes = $ideaController->getScoreExtremes();
$actualMin = (int)$extremes['min_score'];
$actualMax = (int)$extremes['max_score'];

$ideas = $ideaController->listAllIdeas($search, $sort, $minScore, $bid);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
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

        <main class="main-content" style="padding: 40px; overflow-y: auto; background: #f8fafc; color: #0f172a;">
            <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 30px;">
                <div>
                    <h1 class="text-h2" style="color: #0f172a;">All Ideas & Contributions</h1>
                    <p class="text-small" style="color: #475569;">Review platform ideas and moderate content</p>
                </div>
            </header>

            <?php if (isset($_SESSION['flash_success'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>

            <?php if ($bid): ?>
                <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); color: #a78bfa; padding: 12px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <span><i data-lucide="filter" style="width:16px; height:16px; vertical-align:middle; margin-right:8px;"></i> Showing ideas for Brainstorming Session <strong>#<?= htmlspecialchars($bid) ?></strong></span>
                    <a href="ideas_all.php" style="color: #94a3b8; text-decoration: none; font-size: 0.8rem; background: rgba(255,255,255,0.05); padding: 4px 12px; border-radius: 20px;">Clear Filter</a>
                </div>
            <?php endif; ?>
            
            <div class="bo-filters" style="margin-bottom: 20px;">
                <form action="" method="GET" style="display:flex; gap:10px; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by title/content..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="width:250px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a;">
                    
                    <input type="number" name="minScore" placeholder="Min Score..." 
                           min="<?= $actualMin ?>" max="<?= $actualMax ?>"
                           value="<?= htmlspecialchars($minScore) ?>" 
                           title="Score must be between <?= $actualMin ?> and <?= $actualMax ?>"
                           class="glass-input" style="width:150px; padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a;">
                    
                    <select name="sort" class="glass-input" style="padding: 8px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a;">
                        <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="score_desc" <?= $sort == 'score_desc' ? 'selected' : '' ?>>Highest Score</option>
                        <option value="score_asc" <?= $sort == 'score_asc' ? 'selected' : '' ?>>Lowest Score</option>
                    </select>

                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="ideas_all.php" class="btn btn-secondary btn-sm">Clear</a>
                </form>
            </div>

            <div class="bo-list" style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($ideas as $i): ?>
                <div class="bo-card" style="display:flex; justify-content:space-between; align-items:flex-start; padding:20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <span class="status-badge" style="font-size: 0.65rem; background: rgba(59, 130, 246, 0.1); color: #2563eb; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase;">
                                <?= htmlspecialchars($i['status']) ?>
                            </span>
                            <span class="text-small" style="color: #64748b; font-size: 0.75rem;"><?= date('M d, Y H:i', strtotime($i['created_at'])) ?></span>
                        </div>
                        <h3 style="margin:0 0 8px; font-size:1.1rem; color: #1e293b;"><?= htmlspecialchars($i['title']) ?></h3>
                        <p style="margin:0 0 10px; color:#475569; font-size:0.9rem; line-height:1.5;"><?= htmlspecialchars($i['content']) ?></p>
                        <div style="display:flex; gap:15px; align-items:center; font-size:0.8rem; color:#64748b;">
                            <span><i data-lucide="user" class="w-4 h-4" style="vertical-align:middle; margin-right:4px;"></i><?= htmlspecialchars($i['first_name'] . ' ' . $i['last_name']) ?></span>
                            <span><i data-lucide="folder" class="w-4 h-4" style="vertical-align:middle; margin-right:4px;"></i><?= htmlspecialchars(substr($i['brainstorming_title'], 0, 30)) ?>...</span>
                            <span class="badge-score" style="background: rgba(139, 92, 246, 0.1); color: #7c3aed;"><i data-lucide="arrow-up" class="w-4 h-4" style="vertical-align:middle;"></i> <?= (int)$i['score'] ?></span>
                        </div>
                    </div>
                    <div>
                        <a href="../../index.php?action=delete_idea&id=<?= $i['id'] ?>" class="btn btn-secondary btn-sm" style="color:#ef4444; border-color:rgba(239, 68, 68, 0.2);" onclick="return confirm('Delete this idea from the platform?')">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
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
