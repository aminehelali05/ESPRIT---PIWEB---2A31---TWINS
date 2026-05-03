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

$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'score_desc';
$ideas = $ideaController->listIdeasByBrainstorming($bid, $sidebarUser['id'] ?? null, $search, $sort);
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

        <div class="section-header" style="text-align: left; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h2 class="text-h2" style="margin: 0;">Contributions <span style="color:#a78bfa;">(<?= count($ideas) ?>)</span></h2>
            <form action="" method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="id" value="<?= htmlspecialchars($bid) ?>">
                <input type="text" name="search" placeholder="Search ideas..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="padding: 6px 12px; border-radius: 8px;">
                <select name="sort" class="glass-input" style="padding: 6px 12px; border-radius: 8px;">
                    <option value="score_desc" <?= $sort == 'score_desc' ? 'selected' : '' ?>>Highest Score</option>
                    <option value="score_asc" <?= $sort == 'score_asc' ? 'selected' : '' ?>>Lowest Score</option>
                    <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            </form>
        </div>

        <div class="ideas-list">
            <?php foreach ($ideas as $idea): ?>
            <div class="glass-card idea-card" style="display: flex; gap: 16px;">
                <div class="idea-vote-column" style="display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 4px; min-width: 40px;">
                    <button class="vote-btn <?php echo ($idea['user_vote'] == 1) ? 'voted-up' : ''; ?>" data-idea="<?= $idea['id'] ?>" data-vote="1" style="background: none; border: none; cursor: pointer; color: <?= ($idea['user_vote'] == 1) ? '#f97316' : '#94a3b8' ?>; padding: 4px; transition: transform 0.2s;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    </button>
                    <span id="score-<?= $idea['id'] ?>" style="font-weight: bold; font-size: 1.1rem; color: #e2e8f0;"><?= (int)$idea['score'] ?></span>
                    <button class="vote-btn <?php echo ($idea['user_vote'] == -1) ? 'voted-down' : ''; ?>" data-idea="<?= $idea['id'] ?>" data-vote="-1" style="background: none; border: none; cursor: pointer; color: <?= ($idea['user_vote'] == -1) ? '#3b82f6' : '#94a3b8' ?>; padding: 4px; transition: transform 0.2s;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
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
                        <span class="status-badge" style="font-size: 0.65rem; background: rgba(59, 130, 246, 0.1); color: #60a5fa; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase;">
                            <?= htmlspecialchars($idea['status'] ?? 'PROPOSED') ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h3 style="margin: 0 0 8px; font-size: 1.15rem; color: #0f172a; letter-spacing: -0.01em;"><?= htmlspecialchars($idea['title'] ?? 'Untitled Idea') ?></h3>
                        <span style="font-size: 0.65rem; background: rgba(139, 92, 246, 0.1); color: #7c3aed; padding: 2px 8px; border-radius: 12px; font-weight: 600; text-transform: uppercase;"><?= htmlspecialchars($idea['type'] ?? 'Standard') ?></span>
                    </div>
                    <p style="margin: 0; color: #475569; line-height: 1.6; padding-bottom: 12px;"><?= htmlspecialchars($idea['content']) ?></p>
                    
                    <?php 
                        $up = (int)$idea['upvotes'];
                        $down = (int)$idea['downvotes'];
                        $tt = $up + $down;
                        $rt = $tt > 0 ? ($up / $tt) * 100 : 50;
                    ?>
                    <div style="width: 100%; height: 4px; background: #ef4444; border-radius: 4px; overflow: hidden; display: flex; margin-bottom: 4px;" title="Live Approval Ratio">
                        <div id="ratio-<?= $idea['id'] ?>" style="width: <?= $rt ?>%; height: 100%; background: #10b981; transition: width 0.4s ease-in-out;"></div>
                    </div>
                    <div style="text-align: right; font-size: 0.75rem; color: #94a3b8; font-weight: 600;">
                        <span id="pct-<?= $idea['id'] ?>"><?= round($rt) ?></span>% Approval
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
            <form id="ideaForm" action="../../index.php?action=add_idea" method="POST" novalidate>
                <input type="hidden" name="brainstorming_id" value="<?= $bid ?>">
                <div style="margin-bottom: 12px;">
                    <input type="text" name="title" id="ideaTitle" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;" placeholder="Titre de votre idée claire et concise..." required>
                    <div id="ideaTitleError" style="color: #ef4444; font-size: 0.85rem; margin-top: 4px; display: none;"></div>
                </div>
                
                <select name="type" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px; margin-bottom: 12px;" required>
                    <option value="Standard">Standard</option>
                    <option value="Feature Request">Feature Request</option>
                    <option value="Enhancement">Enhancement</option>
                    <option value="Bug Fix">Bug Fix</option>
                </select>

                <div style="background: rgba(139, 92, 246, 0.1); padding: 15px; border-radius: 12px; border: 1px dashed #a855f7; margin-bottom: 12px;">
                    <label style="color: #a78bfa; margin-bottom: 8px; display: block; font-weight: 600; font-size: 0.85rem;">✨ AI Idea Generator</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="aiIdeaPrompt" class="glass-input" placeholder="What is your idea about? e.g. Add dark mode" style="flex: 1; padding: 10px; border-radius: 8px;">
                        <button type="button" id="aiGenerateIdeaBtn" style="background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; white-space: nowrap; transition: all 0.2s ease;">
                            Generate
                        </button>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <textarea name="content" id="formContent" rows="4" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;" placeholder="Détaillez le fonctionnement de votre contribution ici..." required></textarea>
                    <div id="ideaContentError" style="color: #ef4444; font-size: 0.85rem; margin-top: 4px; display: none;"></div>
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
                    const pctEl = document.getElementById(`pct-${btn.dataset.idea}`);
                    if(pctEl) pctEl.innerText = Math.round(rt);
                    const scoreEl = document.getElementById(`score-${btn.dataset.idea}`);
                    if(scoreEl) scoreEl.innerText = result.stats.score;

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
                    const pctEl = document.getElementById(`pct-${stat.id}`);
                    if(pctEl) pctEl.innerText = Math.round(rt);
                    const scoreEl = document.getElementById(`score-${stat.id}`);
                    if(scoreEl) scoreEl.innerText = stat.score;
                }
            });
        } catch(e) {}
    }, 2500);

    // AI Idea Generation
    const aiIdeaBtn = document.getElementById('aiGenerateIdeaBtn');
    const aiIdeaPrompt = document.getElementById('aiIdeaPrompt');
    const ideaContentInput = document.getElementById('formContent');

    if (aiIdeaBtn) {
        aiIdeaBtn.addEventListener('click', async () => {
            const promptText = aiIdeaPrompt.value.trim();

            if (!promptText) {
                alert('Please enter a prompt describing your idea.');
                aiIdeaPrompt.focus();
                return;
            }
            
            const originalText = aiIdeaBtn.innerHTML;
            aiIdeaBtn.innerHTML = '✨ Generating...';
            aiIdeaBtn.style.opacity = '0.6';
            aiIdeaBtn.style.cursor = 'wait';
            aiIdeaBtn.disabled = true;

            try {
                const response = await fetch('../../index.php?action=generate_ai', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt: promptText, context: 'Idea Contribution' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    ideaContentInput.value = data.text;
                } else {
                    alert('⚠️ AI Generation failed: ' + (data.error || 'Unknown error'));
                }
            } catch(e) {
                console.error(e);
                alert('⚠️ AI generation failed. Check your network.');
            } finally {
                aiIdeaBtn.innerHTML = originalText;
                aiIdeaBtn.style.opacity = '1';
                aiIdeaBtn.style.cursor = 'pointer';
                aiIdeaBtn.disabled = false;
            }
        });
    }

    // Idea Form Validation
    const ideaForm = document.getElementById('ideaForm');
    const titleInput = document.getElementById('ideaTitle');
    const contentInput = document.getElementById('formContent');
    const titleError = document.getElementById('ideaTitleError');
    const contentError = document.getElementById('ideaContentError');

    if (ideaForm) {
        ideaForm.addEventListener('submit', function(e) {
            if (this.dataset.confirmed === 'true') return;

            let isValid = true;
            
            // Reset errors
            titleError.style.display = 'none';
            contentError.style.display = 'none';
            titleInput.style.borderColor = '';
            contentInput.style.borderColor = '';

            const titleVal = titleInput.value.trim();
            const contentVal = contentInput.value.trim();

            if (titleVal.length < 5) {
                titleError.textContent = 'Le titre doit comporter au moins 5 caractères.';
                titleError.style.display = 'block';
                titleInput.style.borderColor = '#ef4444';
                isValid = false;
            }

            if (contentVal.length < 15) {
                contentError.textContent = 'La description de l\'idée doit comporter au moins 15 caractères.';
                contentError.style.display = 'block';
                contentInput.style.borderColor = '#ef4444';
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                return;
            }

            // If valid, show SweetAlert
            e.preventDefault();
            Swal.fire({
                title: 'Post Contribution?',
                text: "Your creative idea will be shared with the community for voting.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '🚀 Post Idea!',
                cancelButtonText: 'Wait, let me rethink',
                confirmButtonColor: '#8b5cf6',
                cancelButtonColor: '#475569',
                background: '#1e293b',
                color: '#f8fafc'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.dataset.confirmed = 'true';
                    this.submit();
                }
            });
        });
    }
  </script>
</body>
</html>
