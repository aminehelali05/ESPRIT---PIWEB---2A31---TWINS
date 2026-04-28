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
$sort = $_GET['sort'] ?? 'newest';
$filters = [
    'status' => 'ACCEPTE' // Users only see accepted brainstormings
];
$brainstormings = $brainstormingController->listBrainstormings($search, $filters, $sort);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Brainstorming — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <link rel="stylesheet" href="../../assets/css/user-form.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
  <style>
      body {
          background: #f1f5f9;
      }
      .profile-main {
          background: #f1f5f9;
      }
      .profile-content-area {
          background: #ffffff;
          border-radius: 30px;
          padding: 40px;
          margin-top: 20px;
          box-shadow: 0 10px 50px rgba(0, 0, 0, 0.04);
          min-height: 80vh;
      }
      .section-header {
          margin-bottom: 40px;
          border-bottom: 1px solid #f1f5f9;
          padding-bottom: 30px;
      }
      .brainstorm-accordion {
          display: flex;
          flex-direction: column;
          gap: 16px;
      }
      .brainstorm-card {
          animation: fadeInUp 0.6s cubic-bezier(0.23, 1, 0.32, 1) both;
          background: linear-gradient(to right, #fcfdfe, #f8fafc);
          border-radius: 20px;
          border: 1px solid #eef2f6;
          border-left: 5px solid var(--color-accent);
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
          transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
          overflow: hidden;
          cursor: pointer;
          position: relative;
      }
      .brainstorm-card::before {
          content: '';
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: linear-gradient(45deg, transparent, rgba(var(--color-accent-rgb), 0.03), transparent);
          transform: translateX(-100%);
          transition: transform 0.6s;
      }
      .brainstorm-card:hover {
          transform: translateX(5px);
          box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
          border-color: rgba(var(--color-accent-rgb), 0.2);
          background: #ffffff;
      }
      .brainstorm-card:hover::before {
          transform: translateX(100%);
      }
      .card-header {
          padding: 24px 30px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          z-index: 2;
          position: relative;
      }
      .header-content {
          display: flex;
          align-items: center;
          gap: 20px;
          flex: 1;
      }
      .card-title {
          font-size: 1.1rem;
          font-weight: 600;
          color: #334155;
          margin: 0;
          transition: all 0.3s;
      }
      .header-tag {
          font-size: 0.65rem;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          padding: 4px 10px;
          border-radius: 8px;
          background: rgba(var(--color-accent-rgb), 0.08);
          color: var(--color-accent);
          white-space: nowrap;
      }
      .toggle-icon-wrapper {
          width: 32px;
          height: 32px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 50%;
          background: #f1f5f9;
          transition: all 0.4s;
      }
      .toggle-icon {
          width: 16px;
          height: 16px;
          color: #64748b;
          transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      .card-body {
          max-height: 0;
          opacity: 0;
          transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
          padding: 0 30px;
          background: #ffffff;
          transform: translateY(-10px);
      }
      .brainstorm-card.expanded {
          border-color: var(--color-accent);
          box-shadow: 0 20px 40px rgba(var(--color-accent-rgb), 0.12);
          margin: 8px 0;
          transform: scale(1.01);
          background: #ffffff;
      }
      .brainstorm-card.expanded .card-title {
          color: var(--color-accent);
          font-size: 1.2rem;
      }
      .brainstorm-card.expanded .toggle-icon-wrapper {
          background: var(--color-accent);
      }
      .brainstorm-card.expanded .toggle-icon {
          transform: rotate(180deg);
          color: #ffffff;
      }
      .brainstorm-card.expanded .card-body {
          max-height: 600px;
          opacity: 1;
          padding: 10px 30px 40px 30px;
          border-top: 1px dashed #e2e8f0;
          transform: translateY(0);
      }
      @keyframes fadeInUp {
          from { opacity: 0; transform: translateY(30px); }
          to { opacity: 1; transform: translateY(0); }
      }
      .brainstorm-card:nth-child(1) { animation-delay: 0.1s; }
      .brainstorm-card:nth-child(2) { animation-delay: 0.15s; }
      .brainstorm-card:nth-child(3) { animation-delay: 0.2s; }
      .brainstorm-card:nth-child(4) { animation-delay: 0.25s; }
      .brainstorm-card:nth-child(5) { animation-delay: 0.3s; }

      .card-desc {
          color: #475569;
          font-size: 1.05rem;
          line-height: 1.8;
          margin-bottom: 30px;
          padding: 10px 0;
      }
      .card-footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding-top: 25px;
          border-top: 1px solid #f1f5f9;
      }
      .meta-group {
          display: flex;
          gap: 24px;
      }
      .meta-item {
          display: flex;
          align-items: center;
          gap: 8px;
          color: #94a3b8;
          font-size: 0.85rem;
      }
      .contribute-btn {
          background: var(--color-accent);
          color: white;
          padding: 14px 32px;
          border-radius: 16px;
          font-weight: 600;
          text-decoration: none;
          transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
          display: flex;
          align-items: center;
          gap: 12px;
          box-shadow: 0 8px 20px rgba(var(--color-accent-rgb), 0.3);
      }
      .contribute-btn:hover {
          transform: scale(1.05) translateY(-2px);
          box-shadow: 0 12px 30px rgba(var(--color-accent-rgb), 0.4);
      }
      .premium-toolbar {
          background: #f8fafc !important;
          border: 1px solid #e2e8f0 !important;
          box-shadow: none !important;
          border-radius: 18px !important;
          margin-bottom: 30px !important;
      }
      .glass-input {
          background: #ffffff !important;
          border: 1px solid #e2e8f0 !important;
          color: #1e293b !important;
          border-radius: 14px !important;
      }
      .btn-primary {
          background: var(--color-accent) !important;
          border: none !important;
          border-radius: 14px !important;
          font-weight: 600 !important;
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
          <a href="social.php"><i data-lucide="users"></i><span>Social Feed</span></a>
          <a class="active" href="brainstormings.php"><i data-lucide="lightbulb"></i><span>Brainstorming</span></a>
          <a href="events.php"><i data-lucide="calendar"></i><span>Events</span></a>
          <a href="resources.php"><i data-lucide="library"></i><span>Resources</span></a>
          <a href="profile.php"><i data-lucide="user"></i><span>Profile</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="section-header">
          <span class="section-tag">Collaborate</span>
          <h1 class="text-h1">Innovation <span class="text-gradient">Hub</span></h1>
          <p class="text-body-lg">Join active brainstorming sessions and contribute your creative ideas.</p>
        </div>

        <div class="projects-toolbar premium-toolbar">
          <form action="" method="GET" class="toolbar-filters" style="display: flex; gap: 12px; flex: 1;">
            <div style="position: relative; flex: 1;">
                <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: rgba(255,255,255,0.4);"></i>
                <input type="text" name="search" placeholder="Search sessions..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="padding-left: 38px; width: 100%;">
            </div>
            
            <div style="position: relative; width: 180px;">
                <i data-lucide="filter" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: rgba(255,255,255,0.4);"></i>
                <select name="sort" class="glass-input" style="padding-left: 38px; width: 100%; cursor: pointer;" onchange="this.form.submit()">
                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="title_az" <?= $sort == 'title_az' ? 'selected' : '' ?>>Title A-Z</option>
                    <option value="title_za" <?= $sort == 'title_za' ? 'selected' : '' ?>>Title Z-A</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary" style="padding: 0 20px;">Search</button>
          </form>
          <button type="button" class="btn btn-primary" id="openLaunchModalBtn" style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
            <i data-lucide="plus-circle" style="width: 18px; height: 18px;"></i> Launch Session
          </button>
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

        <div class="brainstorm-accordion">
          <?php foreach ($brainstormings as $b): ?>
          <div class="brainstorm-card" onclick="this.classList.toggle('expanded')">
            <div class="card-header">
              <div class="header-content">
                <span class="header-tag"><?= htmlspecialchars($b['topic'] ?? 'General') ?></span>
                <h3 class="card-title"><?= htmlspecialchars($b['title']) ?></h3>
              </div>
              <div class="toggle-icon-wrapper">
                <i data-lucide="chevron-down" class="toggle-icon"></i>
              </div>
            </div>
            <div class="card-body">
              <p class="card-desc"><?= htmlspecialchars($b['description']) ?></p>
              
              <div class="card-footer">
                <div class="meta-group">
                  <div class="meta-item">
                    <i data-lucide="user" style="width: 14px;"></i>
                    <span>By <?= htmlspecialchars($b['first_name']) ?></span>
                  </div>
                  <div class="meta-item">
                    <i data-lucide="clock" style="width: 14px;"></i>
                    <span><?= date('M d, Y', strtotime($b['created_at'])) ?></span>
                  </div>
                </div>
                
                <a href="brainstorming_details.php?id=<?= $b['id'] ?>" class="contribute-btn" onclick="event.stopPropagation()">
                  <span>Contribute Now</span>
                  <i data-lucide="arrow-right" style="width: 18px;"></i>
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php if (empty($brainstormings)): ?>
            <div class="glass-card" style="padding: 60px; text-align: center; background: white; border-radius: 16px;">
                <i data-lucide="lightbulb-off" style="width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px;"></i>
                <p class="text-body-lg" style="color: #64748b;">No active brainstorming sessions found.</p>
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

            <div class="uf-group uf-span-2">
              <label class="uf-label" for="formTopic">Category & Topic</label>
              <select class="uf-input uf-select" id="formTopic" name="topic" required>
                <option value="">Select a category...</option>
                <option value="Technology">Technology</option>
                <option value="Design">Design</option>
                <option value="Business">Business</option>
                <option value="Marketing">Marketing</option>
                <option value="Other">Other</option>
              </select>
            </div>
            
            <div style="display: flex; justify-content: flex-end; width: 100%; margin-bottom: -10px; position: relative; z-index: 50;" class="uf-span-2">
                <button type="button" id="aiSuggestBtn" style="font-size: 0.75rem; background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%); color: white; border: none; padding: 6px 15px; border-radius: 12px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 10px rgba(168, 85, 247, 0.4); pointer-events: auto;">
                    ✨ AI Write For Me
                </button>
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

  <script src="../../assets/js/main.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/brainstorming-validation.js?v=<?= time() ?>"></script>
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

    // Magic AI Implementation
    const aiBtn = document.getElementById('aiSuggestBtn');
    const titleInput = document.getElementById('formTitle');
    const descInput = document.getElementById('formDescription');

    if(aiBtn) {
        const showDescError = (msg) => {
            if (window.BrainstormingValidation) {
                window.BrainstormingValidation.validateField(descInput, false);
            }
            const group = descInput.closest('.uf-group') || descInput.parentElement;
            group.classList.add('has-error');
            let errorSpan = group.querySelector('.field-error');
            if (!errorSpan) {
                errorSpan = document.createElement('span');
                errorSpan.className = 'field-error';
                group.appendChild(errorSpan);
            }
            errorSpan.textContent = msg;
            descInput.style.borderColor = 'rgba(225, 29, 72, 0.55)';
        };

        aiBtn.addEventListener('click', async () => {
            const topic = titleInput.value.trim();

            // First: validate the title using the existing engine
            if (window.BrainstormingValidation) {
                const isTitleValid = window.BrainstormingValidation.validateField(titleInput, false);
                if (!isTitleValid) {
                    // Show title's error message in the description area instead of alert()
                    const titleError = titleInput.closest('.uf-group')?.querySelector('.field-error')?.textContent
                                       || 'Please fix the Session Title before using AI.';
                    showDescError('⚠️ Fix the title first: ' + titleError);
                    titleInput.focus();
                    return;
                }
            }

            if (!topic) {
                showDescError('⚠️ Please enter a Session Title so the AI knows what to write about.');
                titleInput.focus();
                return;
            }
            
            const originalText = aiBtn.innerHTML;
            aiBtn.innerHTML = '✨ Generating...';
            aiBtn.style.opacity = '0.6';
            aiBtn.style.cursor = 'wait';
            aiBtn.disabled = true;

            try {
                // Local Smart Generator (100% Reliable, 0 API constraints)
                await new Promise(resolve => setTimeout(resolve, 600)); // Fake realistic thinking time

                const cleanTopic = topic.charAt(0).toLowerCase() + topic.slice(1);
                const templates = [
                    "This session is dedicated to exploring innovative solutions around " + cleanTopic + ". We aim to gather out-of-the-box ideas, discuss potential roadblocks, and identify actionable solutions to drive this initiative forward.",
                    "Join us to rethink our approach to " + cleanTopic + ". We are looking for creative contributions, fresh perspectives, and collaborative strategies to maximize our impact in this area.",
                    "A collaborative brainstorming session to map out the challenges and opportunities surrounding " + cleanTopic + ". Let's generate disruptive concepts and build a strong foundation for success.",
                    "We're opening the floor to tackle " + cleanTopic + ". Bring your most ambitious ideas as we aim to redesign our methodology, overcome current limitations, and achieve breakthrough results."
                ];
                
                const aiText = templates[Math.floor(Math.random() * templates.length)];
                descInput.value = aiText;
                descInput.parentElement.classList.add('has-value');
                
            } catch(e) {
                alert("AI generation failed. Check your network.");
            } finally {
                aiBtn.innerHTML = originalText;
                aiBtn.style.opacity = '1';
                aiBtn.style.cursor = 'pointer';
                aiBtn.disabled = false;
            }
        });
    }
  </script>
</body>
</html>
