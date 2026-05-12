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
    <title>Resource Management — Admin Diversity</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .admin-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; overflow: hidden; }
        .admin-table th, .admin-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; }
        .admin-table th { background: rgba(30, 41, 59, 0.8); font-weight: 600; font-size: 0.8rem; }
        .form-card { background: rgba(30, 41, 59, 0.5); padding: 30px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 40px; }
        .admin-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 12px; border-radius: 10px; width: 100%; margin-bottom: 15px; }
        .admin-label { display: block; margin-bottom: 8px; font-size: 0.9rem; color: #94a3b8; }
        .btn-submit { background: #38bdf8; color: #020617; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body style="background: #020617; color: #e2e8f0; font-family: Inter, sans-serif;">
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <aside class="sidebar" style="width: 260px; border-right: 1px solid rgba(255,255,255,0.1); padding: 20px;">
            <div class="brand" style="margin-bottom: 40px;">
                <h2 style="color: #38bdf8;">VoP Admin</h2>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item" style="display: flex; align-items: center; gap: 10px; padding: 12px; color: #94a3b8; text-decoration: none;">
                    <i data-lucide="layout-dashboard"></i> Overview
                </a>
                <a href="events_admin.php" class="nav-item" style="display: flex; align-items: center; gap: 10px; padding: 12px; color: #94a3b8; text-decoration: none;">
                    <i data-lucide="calendar"></i> Events
                </a>
                <a href="resources_admin.php" class="nav-item active" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(56, 189, 248, 0.1); color: #38bdf8; text-decoration: none; border-radius: 8px;">
                    <i data-lucide="library"></i> Resources
                </a>
            </nav>
        </aside>

        <main class="main-content" style="flex: 1; padding: 40px;">
            <header style="margin-bottom: 40px;">
                <h1 style="font-size: 2rem;">Resource <span style="color: #38bdf8;">Control</span></h1>
                <p style="color: #94a3b8;">Manage planning, rules, and material resources.</p>
            </header>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #22c55e; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <h3 style="margin-bottom: 20px;"><?= $editRes ? 'Edit' : 'Add New' ?> Resource</h3>
                <form id="resourceAdminForm" action="../../index.php?action=<?= $editRes ? 'update_resource' : 'create_resource' ?>" method="POST">
                    <?php if ($editRes): ?> <input type="hidden" name="id" value="<?= $editRes->getId() ?>"> <?php endif; ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label class="admin-label">Title</label>
                            <input type="text" name="title" value="<?= $editRes ? htmlspecialchars($editRes->getTitle()) : '' ?>" class="admin-input">
                        </div>
                        <div>
                            <label class="admin-label">Type</label>
                            <select name="type" class="admin-input">
                                <option value="planning" <?= $editRes && $editRes->getType() == 'planning' ? 'selected' : '' ?>>Planning</option>
                                <option value="regles" <?= $editRes && $editRes->getType() == 'regles' ? 'selected' : '' ?>>Rules</option>
                                <option value="materiel" <?= $editRes && $editRes->getType() == 'materiel' ? 'selected' : '' ?>>Material</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="admin-label">Description</label>
                        <textarea name="description" class="admin-input" rows="4"><?= $editRes ? htmlspecialchars($editRes->getDescription()) : '' ?></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                         <div>
                            <label class="admin-label">Status</label>
                            <input type="text" name="status" value="<?= $editRes ? htmlspecialchars($editRes->getStatus()) : 'active' ?>" class="admin-input">
                        </div>
                        <div style="display: flex; align-items: flex-end; padding-bottom: 15px;">
                            <button type="submit" class="btn-submit"><?= $editRes ? 'Update Resource' : 'Create Resource' ?></button>
                            <?php if ($editRes): ?> <a href="resources_admin.php" style="margin-left: 15px; color: #94a3b8;">Cancel</a> <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <section>
                <h2 style="margin-bottom: 20px;">Library Overview</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $res): ?>
                        <tr>
                            <td><span style="color: #38bdf8; text-transform: uppercase; font-size: 0.7rem;"><?= htmlspecialchars($res['type']) ?></span></td>
                            <td><strong><?= htmlspecialchars($res['title']) ?></strong></td>
                            <td><?= htmlspecialchars($res['status']) ?></td>
                            <td>
                                <a href="resources_admin.php?edit_id=<?= $res['id'] ?>" style="color: #38bdf8; margin-right: 15px; text-decoration: none;">Edit</a>
                                <a href="../../index.php?action=delete_resource&id=<?= $res['id'] ?>" style="color: #ef4444; text-decoration: none;" onclick="return confirm('Delete this resource?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($resources)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8;">No resources found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <script src="../../assets/js/event-resource-validation.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
