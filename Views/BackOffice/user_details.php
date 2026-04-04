<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
        header('Location: ../FrontOffice/login.php');
        exit;
}

$controller = new UserController();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
        header('Location: user_list.php');
        exit;
}

$user = $controller->getUserById($id);
if (!$user) {
        header('Location: user_list.php?msg=notfound');
        exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details</title>
    <link rel="stylesheet" href="../assets/css/user-module.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="um-page" style="max-width:1100px;margin:0 auto;">
        <header class="um-topbar">
            <a class="um-brand" href="user_list.php"><span class="um-brand-dot">VoP</span> User Details</a>
            <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
        </header>

        <section class="um-card um-profile-head tilt-hover">
            <img class="um-avatar" src="<?= htmlspecialchars($user->getAvatarUrl() ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($user->getFirstName())) ?>" alt="avatar">
            <div>
                <h1 style="margin:0;"><?= htmlspecialchars($user->getFullName()) ?></h1>
                <p style="color:var(--text-muted); margin:6px 0;"><?= htmlspecialchars($user->getTitle() ?: 'Member') ?></p>
                <div class="um-actions">
                    <span class="um-pill">Role: <?= htmlspecialchars($user->getRole()) ?></span>
                    <span class="um-pill">XP: <?= (int) $user->getXp() ?></span>
                    <span class="um-pill"><?= (int) $user->getIsBlocked() === 1 ? 'Blocked' : 'Active' ?></span>
                </div>
            </div>
            <div class="um-actions">
                <a class="um-btn um-btn-soft" href="user_edit.php?id=<?= (int) $user->getId() ?>">Edit</a>
                <a class="um-btn um-btn-primary" href="user_list.php">Back</a>
            </div>
        </section>

        <section class="um-mini-grid" style="margin-top:12px;">
            <article class="um-card um-mini-card">
                <h3 style="margin-top:0;">Account</h3>
                <p><strong>ID:</strong> #<?= (int) $user->getId() ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user->getEmail()) ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($user->getPhone() ?: '-') ?></p>
                <p><strong>Created:</strong> <?= htmlspecialchars($user->getCreatedAt() ?: '-') ?></p>
            </article>

            <article class="um-card um-mini-card">
                <h3 style="margin-top:0;">Profile</h3>
                <p><strong>Country:</strong> <?= htmlspecialchars($user->getCountry() ?: '-') ?></p>
                <p><strong>Badge:</strong> <?= htmlspecialchars($user->getBadge() ?: '-') ?></p>
                <p><strong>Last Seen:</strong> <?= htmlspecialchars($user->getLastSeen() ?: '-') ?></p>
                <p><strong>Face Enrolled:</strong> <?= (int) $user->getFaceEnrolled() === 1 ? 'Yes' : 'No' ?></p>
            </article>
        </section>

        <section class="um-card" style="padding:18px; margin-top:12px;">
            <h3 style="margin-top:0;">Bio</h3>
            <p style="color:var(--text-muted);"><?= nl2br(htmlspecialchars($user->getBio() ?: 'No bio available.')) ?></p>
            <h3>Skills</h3>
            <div class="um-actions">
                <?php foreach ($user->getSkillsArray() as $skill): ?>
                    <span class="um-pill"><?= htmlspecialchars(trim($skill)) ?></span>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <script src="../assets/js/user-module.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
<?php return; ?>

<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: user_list.php');
    exit;
}

$user = $controller->getUserById($id);
if (!$user) {
    header('Location: user_list.php?msg=notfound');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="um-container" style="max-width: 900px;">
        <div class="page-header" style="margin-bottom: 2rem;">
            <a href="user_list.php" class="um-btn um-btn-secondary" style="margin-bottom: 1rem;">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <h1 class="um-title">User Profile</h1>
            <p class="um-subtitle">Detailed information about <?= htmlspecialchars($user->getFullName()) ?></p>
        </div>

        <div class="um-card">
            <div class="um-profile-header">
                <div>
                    <img src="<?= $user->getAvatarUrl() ? htmlspecialchars($user->getAvatarUrl()) : 'https://ui-avatars.com/api/?name='.urlencode($user->getFullName()).'&background=random' ?>" class="um-avatar" alt="Avatar">
                </div>
                <div style="flex-grow: 1;">
                    <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;"><?= htmlspecialchars($user->getFullName()) ?></h2>
                    <div style="color: var(--text-muted); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem;">
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($user->getEmail()) ?></span>
                        <?php if($user->getPhone()): ?>
                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($user->getPhone()) ?></span>
                        <?php endif; ?>
                        <?php if($user->getCountry()): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user->getCountry()) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="um-badge um-badge-info"><?= htmlspecialchars(ucfirst($user->getRole())) ?></span>
                        <?php if($user->getIsBlocked() == 1): ?>
                            <span class="um-badge um-badge-danger">Blocked</span>
                        <?php else: ?>
                            <span class="um-badge um-badge-success">Active</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <a href="user_edit.php?id=<?= $user->getId() ?>" class="um-btn um-btn-primary">
                        <i class="fas fa-edit" style="margin-right: 0.5rem;"></i> Edit Profile
                    </a>
                </div>
            </div>

            <div class="um-grid-2" style="margin-top: 2rem;">
                <div>
                    <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Account Information</h3>
                    <div style="margin-bottom: 0.5rem;"><strong style="display:inline-block; width: 100px;">User ID:</strong> #<?= $user->getId() ?></div>
                    <div style="margin-bottom: 0.5rem;"><strong style="display:inline-block; width: 100px;">Joined:</strong> <?= $user->getCreatedAt() ? date('F j, Y, g:i a', strtotime($user->getCreatedAt())) : 'N/A' ?></div>
                    <div style="margin-bottom: 0.5rem;"><strong style="display:inline-block; width: 100px;">Last Seen:</strong> <?= $user->getLastSeen() ? date('F j, Y, g:i a', strtotime($user->getLastSeen())) : 'Never' ?></div>
                </div>
                <div>
                    <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Professional Details</h3>
                    <div style="margin-bottom: 0.5rem;"><strong style="display:inline-block; width: 100px;">Job Title:</strong> <?= htmlspecialchars($user->getTitle() ?: 'N/A') ?></div>
                    <div style="margin-bottom: 0.5rem;"><strong style="display:inline-block; width: 100px;">XP Points:</strong> <?= $user->getXp() ?></div>
                    <div style="margin-bottom: 0.5rem;"><strong style="display:inline-block; width: 100px;">Badge:</strong> <?= htmlspecialchars($user->getBadge() ?: 'None') ?></div>
                </div>
            </div>

            <?php if($user->getBio()): ?>
            <div style="margin-top: 2rem;">
                <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Biography</h3>
                <p style="color: var(--text-muted); line-height: 1.6;"><?= nl2br(htmlspecialchars($user->getBio())) ?></p>
            </div>
            <?php endif; ?>

            <?php if($user->getSkills()): ?>
            <div style="margin-top: 2rem;">
                <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Skills</h3>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php foreach($user->getSkillsArray() as $skill): ?>
                        <span class="um-badge" style="background:#e2e8f0; color:#475569;"><?= htmlspecialchars(trim($skill)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/js/user_modern.js"></script>
</body>
</html>
