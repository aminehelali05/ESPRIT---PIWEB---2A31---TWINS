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
$sort = $_GET['sort'] ?? 'date_desc';
$filters = [
    'status' => 'ACCEPTE' // Users only see accepted brainstormings
];
$brainstormings = $brainstormingController->listBrainstormings($search, $filters, null, null, $sort);
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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
      .brainstorming-modal-container {
          background: rgba(248, 250, 252, 0.95);
          backdrop-filter: blur(12px);
          border-radius: 24px;
          padding: 80px;
          box-shadow: 0 20px 40px -10px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
          border: 1px solid rgba(255,255,255,0.1);
          margin-top: 30px;
      }
      .brainstorm-card {
          background: #ffffff;
          border: 1px solid #e2e8f0;
          border-radius: 16px;
          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
          transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
          overflow: hidden;
          position: relative;
      }
      .brainstorm-card::before {
          content: '';
          position: absolute;
          left: 0;
          top: 0;
          bottom: 0;
          width: 4px;
          background: linear-gradient(180deg, #a855f7 0%, #3b82f6 100%);
          opacity: 0;
          transition: opacity 0.4s ease;
      }
      .brainstorm-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
          border-color: #cbd5e1;
      }
      .brainstorm-card:hover::before {
          opacity: 1;
      }
      .bc-header {
          padding: 24px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          cursor: pointer;
          background: #ffffff;
          transition: background 0.3s ease;
      }
      .bc-header:hover {
          background: #f8fafc;
      }
      .bc-header h3 {
          margin: 0;
          color: #0f172a;
          font-size: 1.25rem;
          font-weight: 700;
          letter-spacing: -0.01em;
          transition: color 0.3s ease;
      }
      .brainstorm-card:hover .bc-header h3 {
          color: #7c3aed;
      }
      .bc-icon-wrapper {
          width: 32px;
          height: 32px;
          border-radius: 50%;
          background: #f1f5f9;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.3s ease;
          color: #64748b;
      }
      .brainstorm-card:hover .bc-icon-wrapper {
          background: #ede9fe;
          color: #7c3aed;
      }
      .btn-contribute {
          display: block;
          width: 100%;
          text-align: center;
          background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%);
          color: white;
          padding: 12px;
          border-radius: 10px;
          text-decoration: none;
          font-weight: 600;
          transition: all 0.3s ease;
          box-shadow: 0 4px 15px -3px rgba(168, 85, 247, 0.4);
      }
      .btn-contribute:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 20px -3px rgba(168, 85, 247, 0.6);
          filter: brightness(1.1);
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

        <div class="brainstorming-modal-container">
        <div class="projects-toolbar" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
          <form action="" method="GET" class="toolbar-filters" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
            <input type="text" name="search" placeholder="Search sessions..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b; flex: 1; min-width: 200px; padding: 7px 12px; border-radius: 8px; font-size: 0.9rem;">
            <select name="sort" class="glass-input" style="padding: 7px 12px; border-radius: 8px; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b; font-weight: 500; font-size: 0.9rem;">
                <option value="date_desc" <?= $sort == 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="date_asc" <?= $sort == 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                <option value="title_asc" <?= $sort == 'title_asc' ? 'selected' : '' ?>>Title A-Z</option>
                <option value="title_desc" <?= $sort == 'title_desc' ? 'selected' : '' ?>>Title Z-A</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm" style="background: #e2e8f0; color: #1e293b; font-weight: 600; padding: 7px 15px; border-radius: 8px; font-size: 0.85rem;">Search</button>
          </form>
          <button type="button" class="btn btn-primary btn-sm" id="openLaunchModalBtn" style="background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%); color: white; border: none; padding: 7px 18px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 15px -3px rgba(168, 85, 247, 0.4); transition: transform 0.2s ease; font-size: 0.85rem;">+ Launch Session</button>
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

        <div style="display: flex; flex-direction: column; gap: 15px;">
          <?php foreach ($brainstormings as $b): ?>
          <div class="brainstorm-card">
            <div class="bc-header">
                <h3><?= htmlspecialchars($b['title']) ?></h3>
                <div class="bc-icon-wrapper">
                    <i data-lucide="chevron-down" style="color: inherit; transition: transform 0.3s ease;"></i>
                </div>
            </div>
            <div class="bc-body" style="display: none; padding: 0 24px 24px 24px; background: #ffffff; border-top: 1px solid #f1f5f9;">
                <p class="text-small project-desc" style="color: #475569; margin-top: 15px; margin-bottom: 20px; line-height: 1.7; font-size: 0.95rem;"><?= htmlspecialchars(substr($b['description'], 0, 120)) ?>...</p>
                <div class="project-meta" style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
                  <span class="text-small" style="background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 12px; font-weight: 600; font-size: 0.8rem; display: flex; align-items: center;"><i data-lucide="tag" style="width: 14px; height: 14px; margin-right: 6px;"></i><?= htmlspecialchars($b['topic'] ?? 'General') ?></span>
                  <span class="text-small" style="color: #64748b; font-size: 0.85rem; display: flex; align-items: center;"><i data-lucide="user" style="width: 16px; margin-right: 6px;"></i> By <?= htmlspecialchars($b['first_name']) ?></span>
                  <span class="text-small" style="color: #64748b; font-size: 0.85rem; display: flex; align-items: center;"><i data-lucide="clock" style="width: 16px; margin-right: 6px;"></i> <?= date('M d', strtotime($b['created_at'])) ?></span>
                </div>
                <div style="margin-top: 15px;">
                    <a href="brainstorming_details.php?id=<?= $b['id'] ?>" class="btn-contribute">View & Contribute</a>
                </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($brainstormings)): ?>
            <div style="padding: 40px; text-align: center; color: #64748b; background: #ffffff; border-radius: 12px; border: 1px dashed #cbd5e1;">
                <p class="text-body-lg" style="margin: 0;">No active brainstorming sessions found.</p>
            </div>
          <?php endif; ?>
        </div>
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

            <div class="uf-group uf-span-1">
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

            <div class="uf-group uf-span-1">
              <label class="uf-label" for="formType">Session Type</label>
              <select class="uf-input uf-select" id="formType" name="type" required>
                <option value="Technical">Technical</option>
                <option value="Creative">Creative</option>
                <option value="Strategic">Strategic</option>
                <option value="General" selected>General</option>
              </select>
            </div>
            
            <div class="uf-group uf-span-2" style="background: rgba(139, 92, 246, 0.05); padding: 15px; border-radius: 12px; border: 1px dashed #a855f7;">
              <label class="uf-label" style="color: #7c3aed;">✨ AI Assistant</label>
              <div style="display: flex; gap: 10px;">
                  <input type="text" id="aiPromptInput" class="uf-input" placeholder="What should the AI write about? e.g. Renewable energy solutions" style="flex: 1; padding: 10px; border-radius: 8px;">
                  <button type="button" id="aiSuggestBtn" style="background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 10px rgba(168, 85, 247, 0.4); white-space: nowrap; transition: all 0.2s ease;">
                      Generate
                  </button>
              </div>
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

    // Handle accordion toggle for brainstorm cards
    document.querySelectorAll('.bc-header').forEach(header => {
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const icon = header.querySelector('i');
            if (body.style.display === 'none') {
                body.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            } else {
                body.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        });
    });

    // Magic AI Implementation
    const aiBtn = document.getElementById('aiSuggestBtn');
    const promptInput = document.getElementById('aiPromptInput');
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
            const promptText = promptInput.value.trim();

            if (!promptText) {
                alert('Please enter a prompt so the AI knows what to write about.');
                promptInput.focus();
                return;
            }
            
            const originalText = aiBtn.innerHTML;
            aiBtn.innerHTML = '✨ Generating...';
            aiBtn.style.opacity = '0.6';
            aiBtn.style.cursor = 'wait';
            aiBtn.disabled = true;

            try {
                const response = await fetch('../../index.php?action=generate_ai', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt: promptText, context: 'Brainstorming Session Description' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    descInput.value = data.text;
                    descInput.parentElement.classList.add('has-value');
                    descInput.style.borderColor = ''; // clear error
                    // trigger validation re-check if needed
                    descInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    showDescError('⚠️ AI Generation failed: ' + (data.error || 'Unknown error'));
                }
            } catch(e) {
                console.error(e);
                showDescError('⚠️ AI generation failed. Check your network.');
            } finally {
                aiBtn.innerHTML = originalText;
                aiBtn.style.opacity = '1';
                aiBtn.style.cursor = 'pointer';
                aiBtn.disabled = false;
            }
        });
    }

    // SweetAlert Confirmation for Session Launch
    document.getElementById('brainstormingForm')?.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return;
        if (this.dataset.confirmed === 'true') return;

        e.preventDefault();
        Swal.fire({
            title: 'Launch Session?',
            text: "Your session will be submitted for validation. Once approved, others can contribute!",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '✅ Submit for Validation',
            cancelButtonText: 'Not yet',
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#475569',
            background: '#ffffff',
            color: '#1e293b'
        }).then((result) => {
            if (result.isConfirmed) {
                this.dataset.confirmed = 'true';
                this.submit();
            }
        });
    });
  </script>
</body>
</html>
