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

$sidebarUser = UserController::currentUser() ?? [];
$sidebarInitials = strtoupper(substr($sidebarUser['first_name'] ?? 'G', 0, 1) . substr($sidebarUser['last_name'] ?? 'U', 0, 1));
$isOwner = !empty($sidebarUser) && $sidebarUser['id'] == $session->getUserId();
$isAdmin = UserController::isAdmin();

$ideas = $ideaController->listIdeasByBrainstorming($bid, $sidebarUser['id'] ?? null);
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
        </div>

        <div class="section-header" style="text-align: left;">
            <h2 class="text-h2">Contributions <span style="color:#a78bfa;">(<?= count($ideas) ?>)</span></h2>
        </div>

        <div class="ideas-list">
            <?php foreach ($ideas as $idea): ?>
            <div class="glass-card idea-card" style="display: flex; gap: 16px;">
                <div class="idea-vote-column" style="display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 2px; min-width: 45px;">
                    <button class="vote-btn <?php echo ($idea['user_vote'] == 1) ? 'voted-up' : ''; ?>" data-idea="<?= $idea['id'] ?>" data-vote="1" style="background: none; border: none; cursor: pointer; color: <?= ($idea['user_vote'] == 1) ? '#f97316' : '#94a3b8' ?>; padding: 2px; transition: transform 0.2s;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    </button>
                    <span id="score-<?= $idea['id'] ?>" style="font-weight: 700; color: #1e293b; font-size: 1rem;"><?= (int)$idea['upvotes'] - (int)$idea['downvotes'] ?></span>
                    <button class="vote-btn <?php echo ($idea['user_vote'] == -1) ? 'voted-down' : ''; ?>" data-idea="<?= $idea['id'] ?>" data-vote="-1" style="background: none; border: none; cursor: pointer; color: <?= ($idea['user_vote'] == -1) ? '#3b82f6' : '#94a3b8' ?>; padding: 2px; transition: transform 0.2s;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                    </button>
                </div>
                
                <div class="idea-content-column" style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="creator-avatar" style="width: 24px; height: 24px; font-size: 10px;">
                                    <?= strtoupper(substr($idea['first_name'],0,1) . substr($idea['last_name'],0,1)) ?>
                                </div>
                                <small style="color:#94a3b8;">
                                    <strong><?= htmlspecialchars($idea['first_name'] . ' ' . $idea['last_name']) ?></strong> 
                                    <span style="color: #f59e0b; font-weight: 600;" title="User Karma">★ <?= (int)$idea['karma'] ?></span>
                                    • <?= date('M d, H:i', strtotime($idea['created_at'])) ?>
                                </small>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="status-badge" style="font-size: 0.65rem; background: rgba(59, 130, 246, 0.1); color: #60a5fa; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase;">
                                    <?= htmlspecialchars($idea['status'] ?? 'PROPOSED') ?>
                                </span>
                                <?php if(!empty($idea['idea_type'])): ?>
                                <span class="status-badge" style="font-size: 0.65rem; background: rgba(139, 92, 246, 0.1); color: #a78bfa; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase;">
                                    <?= htmlspecialchars($idea['idea_type']) ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (($sidebarUser['id'] ?? null) == $idea['user_id'] || $isAdmin): ?>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <a href="idea_edit.php?id=<?= $idea['id'] ?>" style="color: var(--color-accent); transition: opacity 0.2s;" title="Edit Idea">
                                            <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                        </a>
                                        <a href="../../index.php?action=delete_idea_fo&id=<?= $idea['id'] ?>&bid=<?= $bid ?>" style="color: #e11d48; transition: opacity 0.2s;" title="Delete Idea" onclick="return confirm('Delete this idea?')">
                                            <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <h3 style="margin: 0 0 8px; font-size: 1.15rem; color: #0f172a; letter-spacing: -0.01em;"><?= htmlspecialchars($idea['title'] ?? 'Untitled Idea') ?></h3>
                    <p style="margin: 0; color: #475569; line-height: 1.6; padding-bottom: 12px;"><?= htmlspecialchars($idea['content']) ?></p>
                    
                    <?php 
                        $up = (int)$idea['upvotes'];
                        $down = (int)$idea['downvotes'];
                        $tt = $up + $down;
                        $rt = $tt > 0 ? ($up / $tt) * 100 : 50;
                    ?>
                    <div style="width: 100%; height: 6px; background: #fee2e2; border-radius: 4px; overflow: hidden; display: flex; margin-bottom: 4px;" title="Live Approval Ratio">
                        <div id="ratio-<?= $idea['id'] ?>" style="width: <?= $rt ?>%; height: 100%; background: #10b981; transition: width 0.4s ease-in-out;"></div>
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <small id="percentage-<?= $idea['id'] ?>" style="font-size: 0.75rem; color: #64748b; font-weight: 600;"><?= round($rt) ?>% Approval</small>
                    </div>
                </div>
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
                
                <div style="position: relative; margin-bottom: 20px;">
                    <i data-lucide="type" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; color: #94a3b8;"></i>
                    <input type="text" name="title" id="ideaTitle" class="glass-input" style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px;" placeholder="Titre de votre idée claire et concise...">
                    <div id="error-ideaTitle" style="color: #e11d48; font-size: 0.75rem; margin-top: 5px; display: none;">Title is required.</div>
                </div>

                <div style="position: relative; margin-bottom: 20px;">
                    <i data-lucide="tag" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; color: #94a3b8;"></i>
                    <select name="type" id="ideaType" class="glass-input" style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px; appearance: none; cursor: pointer;">
                        <option value="" disabled selected>Sélectionnez le type d'idée...</option>
                        <option value="Feature">Feature (Nouvelle fonctionnalité)</option>
                        <option value="Amélioration">Amélioration (Optimisation)</option>
                        <option value="Bug">Correction (Bug/Problème)</option>
                        <option value="Recherche">Recherche (Exploration)</option>
                    </select>
                    <i data-lucide="chevron-down" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 16px; color: #94a3b8; pointer-events: none;"></i>
                    <div id="error-ideaType" style="color: #e11d48; font-size: 0.75rem; margin-top: 5px; display: none;">Please select a type.</div>
                </div>

                <div style="position: relative; margin-bottom: 20px;">
                    <i data-lucide="align-left" style="position: absolute; left: 12px; top: 15px; width: 18px; color: #94a3b8;"></i>
                    <textarea name="content" id="ideaContent" rows="4" class="glass-input" style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px;" placeholder="Détaillez le fonctionnement de votre contribution ici..."></textarea>
                    <div id="error-ideaContent" style="color: #e11d48; font-size: 0.75rem; margin-top: 5px; display: none;">Description is required.</div>
                </div>

                <button type="submit" class="btn btn-primary">Post Contribution</button>
            </form>
        </div>
      </section>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/brainstorming-validation.js"></script>
  <script>
    lucide.createIcons();
    
    document.querySelectorAll('.vote-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const formData = new FormData();
            formData.append('idea_id', btn.dataset.idea);
            formData.append('vote_type', btn.dataset.vote);
            
            try {
                const response = await fetch('../../index.php?action=vote_idea', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    const container = btn.closest('.idea-vote-column');
                    
                    const tt = parseInt(result.stats.upvotes) + parseInt(result.stats.downvotes);
                    const rt = tt > 0 ? (parseInt(result.stats.upvotes) / tt) * 100 : 50;
                    const rBar = document.getElementById(`ratio-${btn.dataset.idea}`);
                    if(rBar) rBar.style.width = rt + '%';

                    const scoreEl = document.getElementById(`score-${btn.dataset.idea}`);
                    if(scoreEl) scoreEl.innerText = parseInt(result.stats.upvotes) - parseInt(result.stats.downvotes);
                    
                    const percEl = document.getElementById(`percentage-${btn.dataset.idea}`);
                    if(percEl) percEl.innerText = Math.round(rt) + '% Approval';

                    const isUp = btn.dataset.vote === "1";
                    if (btn.classList.contains(isUp ? 'voted-up' : 'voted-down')) {
                        btn.classList.remove(isUp ? 'voted-up' : 'voted-down');
                        btn.style.color = '#94a3b8';
                    } else {
                        container.querySelectorAll('.vote-btn').forEach(b => {
                            b.style.color = '#94a3b8';
                            b.classList.remove('voted-up', 'voted-down');
                        });
                        btn.classList.add(isUp ? 'voted-up' : 'voted-down');
                        btn.style.color = isUp ? '#f97316' : '#3b82f6';
                        btn.style.transform = "scale(1.2)";
                        setTimeout(() => btn.style.transform = "scale(1)", 150);
                    }
                } else {
                    alert(result.message || "Please log in to vote.");
                }
            } catch (err) {
                console.error(err);
            }
        });
    });

    // AJAX Polling Live Concept
    setInterval(async () => {
        try {
            const res = await fetch(`../../index.php?action=poll_ideas_stats&bid=<?= $bid ?>`);
            if (!res.ok) return;
            const stats = await res.json();
            stats.forEach(stat => {
                const rEl = document.getElementById(`ratio-${stat.id}`);
                if (rEl) {
                    const tt = parseInt(stat.upvotes) + parseInt(stat.downvotes);
                    const rt = tt > 0 ? (parseInt(stat.upvotes) / tt) * 100 : 50;
                    rEl.style.width = rt + '%';

                    const sEl = document.getElementById(`score-${stat.id}`);
                    if (sEl) sEl.innerText = parseInt(stat.upvotes) - parseInt(stat.downvotes);
                    
                    const pEl = document.getElementById(`percentage-${stat.id}`);
                    if (pEl) pEl.innerText = Math.round(rt) + '% Approval';
                }
            });
        } catch(e) {}
    // Manual Validation for Contribution Form (No HTML5 bubbles)
    document.getElementById('ideaForm').addEventListener('submit', function(e) {
        let isValid = true;
        const title = document.getElementById('ideaTitle');
        const type = document.getElementById('ideaType');
        const content = document.getElementById('ideaContent');

        if (title.value.trim() === "") {
            document.getElementById('error-ideaTitle').style.display = 'block';
            title.style.borderColor = '#e11d48';
            isValid = false;
        } else {
            document.getElementById('error-ideaTitle').style.display = 'none';
            title.style.borderColor = '';
        }

        if (type.value === "") {
            document.getElementById('error-ideaType').style.display = 'block';
            type.style.borderColor = '#e11d48';
            isValid = false;
        } else {
            document.getElementById('error-ideaType').style.display = 'none';
            type.style.borderColor = '';
        }

        if (content.value.trim() === "") {
            document.getElementById('error-ideaContent').style.display = 'block';
            content.style.borderColor = '#e11d48';
            isValid = false;
        } else {
            document.getElementById('error-ideaContent').style.display = 'none';
            content.style.borderColor = '';
        }

        if (!isValid) {
            e.preventDefault();
        }
    });
  </script>
</body>
</html>
