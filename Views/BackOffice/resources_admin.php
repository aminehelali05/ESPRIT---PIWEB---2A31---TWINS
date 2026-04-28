<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ResourceController.php');

if (!UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$resourceController = new ResourceController();
$resources = $resourceController->listResources();

$editRes = null;
if (isset($_GET['edit_id'])) {
    $editRes = $resourceController->getResourceById($_GET['edit_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Management — VoP Admin</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Resource Form Premium Overrides */
        .res-form-card {
            background: var(--b-surface);
            border: 1px solid var(--b-border);
            border-radius: var(--b-radius-md);
            box-shadow: var(--b-shadow-card);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.35s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .res-form-card:hover {
            box-shadow: var(--b-shadow-hover);
            border-color: var(--b-border-strong);
        }
        .res-form-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--b-text-main);
        }
        .res-input {
            background: var(--b-bg);
            border: 1px solid var(--b-border);
            color: var(--b-text-main);
            padding: 12px 16px;
            border-radius: var(--b-radius-sm);
            width: 100%;
            font-family: var(--font-family);
            font-size: 0.9rem;
            transition: all 0.25s ease;
        }
        .res-input:focus {
            outline: none;
            border-color: var(--b-accent);
            box-shadow: 0 0 0 3px var(--b-accent-glow);
        }
        .res-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--b-text-muted);
        }
        .res-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        .res-field {
            display: flex;
            flex-direction: column;
        }
        .res-field-full {
            grid-column: 1 / -1;
        }
        .res-submit {
            background: var(--b-accent);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 100px;
            font-family: var(--font-family);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 12px var(--b-accent-glow);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .res-submit:hover {
            background: var(--b-accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px var(--b-accent-glow);
        }
        .res-cancel {
            color: var(--b-text-muted);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            margin-left: 16px;
            transition: color 0.2s;
        }
        .res-cancel:hover { color: var(--b-text-main); }

        .flash-msg {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.88rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .flash-error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        .flash-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
        }
        
        .ai-generator-box {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
            border: 1px dashed var(--b-accent);
            border-radius: var(--b-radius-sm);
            padding: 1.5rem;
            margin-bottom: 20px;
            display: none;
        }
        .ai-generator-box.visible { display: block; }
        .ai-btn-sparkle {
            background: var(--b-accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .ai-btn-sparkle:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .ai-loading { display: none; margin-left: 10px; font-size: 0.8rem; color: var(--b-accent); font-weight: 600; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="page-title">
                    <h1>Resource <span style="color: var(--b-accent);">Control</span></h1>
                    <p>Manage planning, rules, and material resources.</p>
                </div>
            </header>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="flash-msg flash-error animate-enter">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i>
                    <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="flash-msg flash-success animate-enter">
                    <i data-lucide="check-circle-2" style="width: 18px; height: 18px;"></i>
                    <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>

            <!-- Resource Form -->
            <div class="res-form-card animate-enter">
                <h3 class="res-form-title">
                    <i data-lucide="<?= $editRes ? 'pencil' : 'plus-circle' ?>" style="width: 20px; height: 20px; color: var(--b-accent);"></i>
                    <?= $editRes ? 'Edit' : 'Add New' ?> Resource
                </h3>
                <form id="resourceAdminForm" action="../../index.php?action=<?= $editRes ? 'update_resource' : 'create_resource' ?>" method="POST" novalidate>
                    <?php if ($editRes): ?> <input type="hidden" name="id" value="<?= $editRes->getId() ?>"> <?php endif; ?>
                    <div class="res-grid">
                        <div class="res-field">
                            <label class="res-label">Title</label>
                            <input type="text" name="title" value="<?= $editRes ? htmlspecialchars($editRes->getTitle()) : '' ?>" class="res-input" placeholder="Enter resource title...">
                        </div>
                        <div class="res-field">
                            <label class="res-label">Type</label>
                            <select name="type" id="resTypeSelect" class="res-input">
                                <option value="planning" <?= $editRes && $editRes->getType() == 'planning' ? 'selected' : '' ?>>Planning</option>
                                <option value="regles" <?= $editRes && $editRes->getType() == 'regles' ? 'selected' : '' ?>>Rules</option>
                                <option value="materiel" <?= $editRes && $editRes->getType() == 'materiel' ? 'selected' : '' ?>>Material</option>
                            </select>
                        </div>
                        
                        <!-- AI Generator UI (Only for Planning) -->
                        <div class="res-field res-field-full ai-generator-box" id="aiBox">
                            <label class="res-label" style="color: var(--b-accent);"><i data-lucide="sparkles" class="w-3 h-3"></i> AI Planning Generator</label>
                            <p class="text-small" style="margin-bottom: 15px; opacity: 0.8;">Describe your project goals, and Gemini will generate a structured timeline for you.</p>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="aiProjectDesc" class="res-input" style="flex: 1;" placeholder="e.g. Build a landing page for a startup in 10 days">
                                <button type="button" class="ai-btn-sparkle" id="btnGenAI">
                                    <i data-lucide="zap"></i> Generate
                                </button>
                            </div>
                            <div id="aiLoading" class="ai-loading">Generating roadmap... <span class="loader-dots">...</span></div>
                        </div>
                        <div class="res-field res-field-full">
                            <label class="res-label">Description</label>
                            <textarea name="description" id="resDescription" class="res-input" rows="6" placeholder="Describe this resource..."><?= $editRes ? htmlspecialchars($editRes->getDescription()) : '' ?></textarea>
                        </div>
                        <div class="res-field">
                            <label class="res-label">Status</label>
                            <input type="text" name="status" value="<?= $editRes ? htmlspecialchars($editRes->getStatus()) : 'active' ?>" class="res-input" placeholder="active / inactive">
                        </div>
                        <div class="res-field" style="display: flex; align-items: flex-end; justify-content: flex-start;">
                            <div>
                                <button type="submit" class="res-submit"><?= $editRes ? 'Update Resource' : 'Create Resource' ?></button>
                                <?php if ($editRes): ?> <a href="resources_admin.php" class="res-cancel">Cancel</a> <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Resource Library -->
            <section class="card animate-enter" style="animation-delay: 0.1s;">
                <div class="section-head">
                    <h2><i data-lucide="archive" style="color: var(--b-accent); vertical-align: middle; margin-right: 8px; width: 20px; height: 20px;"></i> Library Overview</h2>
                    <span class="dm-kpi"><?= count($resources) ?> Resources</span>
                </div>

                <div class="table-container">
                    <table class="elegant-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resources as $res): ?>
                            <tr>
                                <td>
                                    <span class="pill pill-manager" style="text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.04em;">
                                        <?= htmlspecialchars($res['type']) ?>
                                    </span>
                                </td>
                                <td><strong style="color: var(--b-text-main);"><?= htmlspecialchars($res['title']) ?></strong></td>
                                <td>
                                    <?php if (strtolower($res['status']) == 'active'): ?>
                                        <span class="status st-active"><span class="st-dot"></span> Active</span>
                                    <?php else: ?>
                                        <span class="status st-offline"><span class="st-dot"></span> <?= htmlspecialchars($res['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="t-actions">
                                        <?php if ($res['user_id'] == ($sidebarUser['id'] ?? -1)): ?>
                                        <a href="resources_admin.php?edit_id=<?= $res['id'] ?>" class="t-btn" title="Edit">
                                            <i data-lucide="pencil"></i> Edit
                                        </a>
                                        <a href="#" class="t-btn t-btn-refuse" title="Delete"
                                           onclick="event.preventDefault(); confirmDeleteRes(<?= $res['id'] ?>);">
                                            <i data-lucide="trash-2"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-small" style="opacity: 0.5;">No access</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($resources)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 4rem 0; color: var(--b-text-light);">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                                    <p>No resources found.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script src="../../assets/js/event-resource-validation.js"></script>
    <script>
        lucide.createIcons();

        // AI Logic
        const resTypeSelect = document.getElementById('resTypeSelect');
        const aiBox = document.getElementById('aiBox');
        const btnGenAI = document.getElementById('btnGenAI');
        const aiProjectDesc = document.getElementById('aiProjectDesc');
        const resDescription = document.getElementById('resDescription');
        const aiLoading = document.getElementById('aiLoading');

        const toggleAIBox = () => {
            if (resTypeSelect.value === 'planning') {
                aiBox.classList.add('visible');
            } else {
                aiBox.classList.remove('visible');
            }
        };

        resTypeSelect.addEventListener('change', toggleAIBox);
        window.addEventListener('load', toggleAIBox);

        btnGenAI.addEventListener('click', async () => {
            const desc = aiProjectDesc.value.trim();
            if (!desc) {
                Swal.fire({ icon: 'warning', title: 'Please provide a project description.' });
                return;
            }

            btnGenAI.disabled = true;
            aiLoading.style.display = 'inline-block';

            try {
                const formData = new FormData();
                formData.append('description', desc);
                const resp = await fetch('../../index.php?action=generate_planning_ai', {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    let formatted = "### AI GENERATED PLANNING\n\n";
                    res.planning.forEach(step => {
                        formatted += `--- STEP ${step.ordre}: ${step.titre.toUpperCase()} ---\n`;
                        formatted += `Duration: ${step.durée_estimée_en_jours} days\n`;
                        formatted += `${step.description_courte}\n\n`;
                    });
                    resDescription.value = formatted;
                    Swal.fire({ icon: 'success', title: 'Planning Generated!', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                } else {
                    Swal.fire({ icon: 'error', title: 'AI Error', text: res.message });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Request Failed', text: 'Server error or invalid response.' });
            } finally {
                btnGenAI.disabled = false;
                aiLoading.style.display = 'none';
            }
        });

        function confirmDeleteRes(id) {
            Swal.fire({
                title: 'Delete this resource?',
                text: 'This action is permanent and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                background: '#ffffff',
                color: '#0f172a',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php?action=delete_resource&id=' + id;
                }
            });
        }
    </script>
</body>
</html>
