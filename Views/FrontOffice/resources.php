<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ResourceController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$isAdminSidebar = strtolower(trim((string) ($sidebarUser['email'] ?? ''))) === 'admin@diversity.is';

$resourceController = new ResourceController();
$eventController = new EventController();
$typeFilter = $_GET['type'] ?? null;
$resources = $resourceController->listResources($typeFilter);
$myEvents = $eventController->listEvents('', ['status' => 'ACCEPTE']); // You can filter by user if needed, but let's show all accepted ones for now

$editRes = null;
if (isset($_GET['edit_id'])) {
    $editRes = $resourceController->getResourceById($_GET['edit_id']);
    // Security check: only owner or admin can edit
    if ($editRes && !($editRes->getUserId() == ($sidebarUser['id'] ?? -1) || UserController::isAdmin())) {
        $editRes = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resources — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .res-form-glass {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2.5rem;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
    }
    .form-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;
    }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-group-full { grid-column: span 2; }
    .input-glass {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(var(--color-accent-rgb), 0.2);
        padding: 12px 16px;
        border-radius: 12px;
        font-family: inherit;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }
    .input-glass:focus {
        outline: none;
        border-color: var(--color-accent);
        box-shadow: 0 0 0 4px rgba(var(--color-accent-rgb), 0.1);
        background: white;
    }
    .label-premium {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-text-secondary);
    }
    .ai-box-premium {
        background: linear-gradient(135deg, rgba(var(--color-accent-rgb), 0.05), rgba(var(--color-accent-secondary-rgb), 0.05));
        border: 1px dashed var(--color-accent);
        border-radius: 16px;
        padding: 1.5rem;
        margin: 15px 0;
        display: none;
    }
    .ai-box-premium.visible { display: block; }
    .btn-sparkle {
        background: var(--color-accent);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 100px;
        font-weight: 600;
        display: flex; align-items: center; gap: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-sparkle:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(var(--color-accent-rgb), 0.4); }
    
    .card-actions {
        display: flex; gap: 10px; margin-top: 15px; padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    .action-btn {
        padding: 8px; border-radius: 8px; border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s; background: rgba(0,0,0,0.03); color: var(--color-text-secondary);
    }
    .action-btn:hover { background: rgba(var(--color-accent-rgb), 0.1); color: var(--color-accent); }
    .action-btn.btn-delete:hover { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .flash-toast {
        position: fixed; top: 20px; right: 20px; z-index: 1000;
        padding: 15px 25px; border-radius: 12px; color: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        display: flex; align-items: center; gap: 10px;
        animation: slideIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    /* Fix for some missing cards styles */
    .project-card { position: relative; overflow: hidden; }
    .project-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        background: var(--color-accent); opacity: 0.3;
    }
  </style>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash-toast" style="background: #10b981;" id="flashSuccess">
        <i data-lucide="check-circle"></i> <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
    </div>
    <script>setTimeout(() => document.getElementById('flashSuccess').remove(), 4000);</script>
  <?php endif; ?>

  <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="flash-toast" style="background: #ef4444;" id="flashError">
        <i data-lucide="alert-circle"></i> <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
    </div>
    <script>setTimeout(() => document.getElementById('flashError').remove(), 4000);</script>
  <?php endif; ?>

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

      </div>
      <div class="navbar-actions">
        <div class="user-profile-nav" onclick="location.href='profile.php'">
          <div class="nav-avatar"><?= $sidebarInitials ?></div>
          <span><?= htmlspecialchars($sidebarDisplayName) ?></span>
        </div>
      </div>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card">
        <nav class="left-nav">
          <a href="home.php"><i data-lucide="home" class="w-4 h-4"></i><span>Home Feed</span></a>
          <a href="social.php"><i data-lucide="users" class="w-4 h-4"></i><span>Social Feed</span></a>
          <a href="brainstormings.php"><i data-lucide="lightbulb" class="w-4 h-4"></i><span>Brainstorming</span></a>
          <a href="events.php"><i data-lucide="calendar" class="w-4 h-4"></i><span>Events</span></a>
          <a href="resources.php" class="active"><i data-lucide="library" class="w-4 h-4"></i><span>Resources</span></a>
          <a href="profile.php"><i data-lucide="user" class="w-4 h-4"></i><span>Profile</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="section-header fade-in-section">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span class="section-tag">Library</span>
                    <h1 class="text-h1">Platform <span class="text-gradient">Resources</span></h1>
                    <p class="text-body-lg">Essential planning tools, community rules, and shared materials.</p>
                </div>
                <?php if (UserController::isAuthenticated()): ?>
                    <button class="btn btn-primary" onclick="toggleResourceForm()">
                        <i data-lucide="plus" style="width: 18px; margin-right: 8px;"></i> Contribute
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Suggest Resource Form -->
        <div id="resourceFormContainer" class="fade-in-section" style="<?= $editRes ? '' : 'display: none;' ?>">
            <div class="res-form-glass">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 class="text-h3" style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="<?= $editRes ? 'pencil' : 'sparkles' ?>" style="color: var(--color-accent); width: 24px;"></i>
                        <?= $editRes ? 'Edit Resource' : 'Contribute New Resource' ?>
                    </h2>
                    <button class="action-btn" onclick="toggleResourceForm()"><i data-lucide="x"></i></button>
                </div>

                <form action="../../index.php?action=<?= $editRes ? 'update_resource' : 'create_resource' ?>" method="POST">
                    <?php if ($editRes): ?> <input type="hidden" name="id" value="<?= $editRes->getId() ?>"> <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="label-premium">Resource Title</label>
                            <input type="text" name="title" required class="input-glass" placeholder="e.g. Freelance Survival Guide" value="<?= $editRes ? htmlspecialchars($editRes->getTitle()) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="label-premium">Category</label>
                            <select name="type" id="resTypeSelect" class="input-glass">
                                <option value="planning" <?= ($editRes && $editRes->getType() == 'planning') ? 'selected' : '' ?>>Planning / Roadmap</option>
                                <option value="regles" <?= ($editRes && $editRes->getType() == 'regles') ? 'selected' : '' ?>>Community Rules</option>
                                <option value="materiel" <?= ($editRes && $editRes->getType() == 'materiel') ? 'selected' : '' ?>>Shared Material</option>
                            </select>
                        </div>
                        <div class="form-group-full">
                            <label class="label-premium">Associated Event</label>
                            <select name="event_id" class="input-glass">
                                <option value="">-- No Event Association (Global Resource) --</option>
                                <?php foreach ($myEvents as $ev): ?>
                                    <option value="<?= $ev['id'] ?>" <?= ($editRes && $editRes->getEventId() == $ev['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ev['title']) ?> (<?= date('M j', strtotime($ev['start_date'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- AI Box -->
                        <div class="form-group-full ai-box-premium" id="aiBox">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: var(--color-accent);">
                                <i data-lucide="zap" style="width: 16px;"></i>
                                <span style="font-weight: 700; font-size: 0.75rem; text-transform: uppercase;">Gemini AI Generator</span>
                            </div>
                            <p class="text-small" style="margin-bottom: 15px;">Enter your project goals and AI will draft a structured timeline for you.</p>
                            <div style="display: flex; gap: 12px;">
                                <input type="text" id="aiPrompt" style="flex: 1;" class="input-glass" placeholder="e.g. Launch a SaaS MVP in 30 days">
                                <button type="button" class="btn-sparkle" id="btnRunAI">
                                    <i data-lucide="wand-2" style="width: 16px;"></i> Generate
                                </button>
                            </div>
                            <div id="aiWait" style="display: none; margin-top: 10px; font-size: 0.8rem; color: var(--color-accent);">
                                Thinking... <span class="loader-dots"></span>
                            </div>
                        </div>

                        <div class="form-group-full">
                            <label class="label-premium">Content / Description</label>
                            <textarea name="description" id="resDesc" rows="8" required class="input-glass" placeholder="Detailed content of the resource..."><?= $editRes ? htmlspecialchars($editRes->getDescription()) : '' ?></textarea>
                        </div>

                        <div class="form-group-full" style="display: flex; gap: 15px; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                                <?= $editRes ? 'Update Changes' : 'Publish Resource' ?>
                            </button>
                            <?php if ($editRes): ?>
                                <a href="resources.php" class="btn btn-secondary" style="padding: 12px 30px; text-decoration: none;">Cancel</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" onclick="toggleResourceForm()" style="padding: 12px 30px;">Discard</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
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
            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php
                    $icon = 'book-open';
                    if ($res['type'] == 'planning') $icon = 'calendar-range';
                    if ($res['type'] == 'regles') $icon = 'shield-alert';
                    if ($res['type'] == 'materiel') $icon = 'package';
                    ?>
                    <div style="padding: 12px; border-radius: 12px; background: rgba(var(--color-accent-rgb), 0.1); color: var(--color-accent);">
                        <i data-lucide="<?= $icon ?>" style="width: 20px;"></i>
                    </div>
                    <h3 class="text-h3" style="margin: 0; font-size: 1.1rem;"><?= htmlspecialchars($res['title']) ?></h3>
                </div>
                <span class="text-small" style="background: rgba(var(--color-accent-rgb), 0.05); padding: 4px 10px; border-radius: 6px; font-weight: 600; text-transform: uppercase;">
                    <?= htmlspecialchars($res['type']) ?>
                </span>
            </div>
            
            <p class="text-body" style="font-size: 0.95rem; line-height: 1.6; opacity: 0.8; height: 80px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                <?= htmlspecialchars($res['description']) ?>
            </p>

            <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div class="text-small" style="color: var(--color-text-secondary); display: flex; align-items: center; gap: 5px;">
                    <i data-lucide="clock" style="width: 14px;"></i> <?= date('M j, Y', strtotime($res['created_at'])) ?>
                </div>
                
                <?php if ($res['user_id'] == ($sidebarUser['id'] ?? -1)): ?>
                    <div class="card-actions" style="margin: 0; border: none; padding: 0;">
                        <a href="resources.php?edit_id=<?= $res['id'] ?>" class="action-btn" title="Edit"><i data-lucide="pencil" style="width: 14px;"></i></a>
                        <button class="action-btn btn-delete" title="Delete" onclick="confirmDelete(<?= $res['id'] ?>)"><i data-lucide="trash-2" style="width: 14px;"></i></button>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm" style="border-radius: 8px;">Read Full</button>
                <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php if (empty($resources)): ?>
            <div class="form-group-full glass-card" style="padding: 4rem; text-align: center; grid-column: span 2;">
                <div style="margin-bottom: 20px; opacity: 0.2;"><i data-lucide="library" style="width: 64px; height: 64px;"></i></div>
                <h3 class="text-h3">No resources found</h3>
                <p class="text-body">Be the first to contribute helpful material to the platform.</p>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script>
    lucide.createIcons();

    function toggleResourceForm() {
        const container = document.getElementById('resourceFormContainer');
        if (container.style.display === 'none') {
            container.style.display = 'block';
            container.scrollIntoView({ behavior: 'smooth' });
        } else {
            container.style.display = 'none';
        }
    }

    // AI Logic
    const resType = document.getElementById('resTypeSelect');
    const aiBox = document.getElementById('aiBox');
    const promptIn = document.getElementById('aiPrompt');
    const btnAI = document.getElementById('btnRunAI');
    const waitMsg = document.getElementById('aiWait');
    const descArea = document.getElementById('resDesc');

    const checkType = () => {
        if (resType.value === 'planning') {
            aiBox.classList.add('visible');
        } else {
            aiBox.classList.remove('visible');
        }
    };
    resType.addEventListener('change', checkType);
    window.addEventListener('load', checkType);

    btnAI.addEventListener('click', async () => {
        const val = promptIn.value.trim();
        if (!val) {
            Swal.fire({ icon: 'warning', title: 'Need a prompt!', text: 'Tell me what you want to generate.' });
            return;
        }

        btnAI.disabled = true;
        waitMsg.style.display = 'block';

        try {
            const formData = new FormData();
            formData.append('description', val);
            const resp = await fetch('../../index.php?action=generate_planning_ai', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();

            if (res.success) {
                let text = "### AI GENERATED ROADMAP\n\n";
                res.planning.forEach(s => {
                    text += `* [${s.ordre}] ${s.titre} (${s.durée_estimée_en_jours} days)\n  ${s.description_courte}\n\n`;
                });
                descArea.value = text;
                Swal.fire({ icon: 'success', title: 'Generated!', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            } else {
                throw new Error(res.message);
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'AI Error', text: e.message || 'Could not connect to AI service.' });
        } finally {
            btnAI.disabled = false;
            waitMsg.style.display = 'none';
        }
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Remove resource?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../../index.php?action=delete_resource&id=' + id;
            }
        });
    }
  </script>
</body>
</html>
