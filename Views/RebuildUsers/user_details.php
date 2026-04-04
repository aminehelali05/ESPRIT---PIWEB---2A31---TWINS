<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$id = (int)($_GET['id'] ?? 0);
$user = $id > 0 ? $controller->getUserById($id) : null;
if (!$user) { header('Location: user_list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_block') {
    $controller->toggleBlock($id);
    header('Location: user_details.php?id=' . $id);
    exit;
}

$skills = $user->getSkillsArray();
$level = floor((int)$user->getXp() / 100) + 1;
$progress = min(100, ((int)$user->getXp()) % 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details | Voices Of Peace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user-system.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="user-system">
<div class="us-shell us-fade-up">
    <header class="us-topbar">
        <a class="us-brand" href="user_list.php"><span class="us-brand-dot">VoP</span> User Details</a>
        <div class="us-row">
            <a class="us-btn us-btn-ghost" href="user_edit.php?id=<?= $id ?>">Edit</a>
            <a class="us-btn us-btn-ghost" href="user_list.php">Back</a>
        </div>
    </header>

    <div class="us-main">
        <section class="us-card us-card-3d">
            <div class="us-row" style="justify-content:space-between; width:100%; align-items:flex-start;">
                <div class="us-row" style="align-items:center;">
                    <div class="us-user-avatar" style="width:62px; height:62px; font-size:20px;"><?= htmlspecialchars($user->getInitials()) ?></div>
                    <div>
                        <h1 class="us-card-title" style="font-size:22px;"><?= htmlspecialchars($user->getFullName()) ?></h1>
                        <p class="us-card-subtitle"><?= htmlspecialchars($user->getEmail()) ?></p>
                        <div class="us-row" style="margin-top:8px;">
                            <span class="us-badge <?= (int)$user->getIsBlocked() ? 'blocked' : ((int)$user->getStatus() ? 'active' : 'inactive') ?>">
                              <?= (int)$user->getIsBlocked() ? 'Blocked' : ((int)$user->getStatus() ? 'Active' : 'Inactive') ?>
                            </span>
                            <span class="us-badge active" style="background:rgba(174,225,249,.12); border-color:rgba(174,225,249,.35); color:#bae6fd;">
                              <?= htmlspecialchars($user->getRole() ?: 'user') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="toggle_block">
                    <button class="us-btn <?= (int)$user->getIsBlocked() ? 'us-btn-primary' : 'us-btn-danger' ?>" type="submit">
                        <?= (int)$user->getIsBlocked() ? 'Unblock User' : 'Block User' ?>
                    </button>
                </form>
            </div>
        </section>

        <section class="us-stat-grid">
            <article class="us-stat us-card-3d"><h4>XP</h4><p><?= (int)$user->getXp() ?></p></article>
            <article class="us-stat us-card-3d"><h4>Level</h4><p><?= (int)$level ?></p></article>
            <article class="us-stat us-card-3d"><h4>Status</h4><p><?= (int)$user->getStatus() ? 'Online' : 'Offline' ?></p></article>
            <article class="us-stat us-card-3d"><h4>Progress</h4><p><?= (int)$progress ?>%</p></article>
        </section>

        <section class="us-card us-card-3d">
            <h2 class="us-card-title">Profile Information</h2>
            <div class="us-form-grid" style="margin-top:10px;">
                <div class="us-field"><span class="us-label">Phone</span><div class="us-input"><?= htmlspecialchars($user->getPhone() ?: '-') ?></div></div>
                <div class="us-field"><span class="us-label">Country</span><div class="us-input"><?= htmlspecialchars($user->getCountry() ?: '-') ?></div></div>
                <div class="us-field"><span class="us-label">Title</span><div class="us-input"><?= htmlspecialchars($user->getTitle() ?: '-') ?></div></div>
                <div class="us-field"><span class="us-label">Badge</span><div class="us-input"><?= htmlspecialchars($user->getBadge() ?: '-') ?></div></div>
                <div class="us-field full"><span class="us-label">Bio</span><div class="us-input" style="min-height:84px;"><?= nl2br(htmlspecialchars($user->getBio() ?: 'No biography provided.')) ?></div></div>
                <div class="us-field full"><span class="us-label">Skills</span><div class="us-row"><?php if (count($skills)): foreach ($skills as $skill): ?><span class="us-badge active" style="background:rgba(195,230,203,.12); border-color:rgba(195,230,203,.33); color:#bbf7d0;"><?= htmlspecialchars(trim($skill)) ?></span><?php endforeach; else: ?><span class="us-card-subtitle">No skills listed.</span><?php endif; ?></div></div>
            </div>
        </section>

        <section class="us-card us-card-3d">
            <div class="us-row">
                <a class="us-btn us-btn-primary" href="user_edit.php?id=<?= $id ?>"><i data-lucide="pencil"></i> Edit User</a>
                <form method="post" action="user_list.php" class="delete-form" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="us-btn us-btn-danger" type="submit"><i data-lucide="trash-2"></i> Delete User</button>
                </form>
            </div>
        </section>
    </div>
</div>

<script src="../assets/js/user-system.js"></script>
<script>
  if (window.lucide) { window.lucide.createIcons(); }
  UserSystem.initDeleteConfirm('.delete-form', 'This will delete the user permanently. Continue?');
</script>
</body>
</html>
