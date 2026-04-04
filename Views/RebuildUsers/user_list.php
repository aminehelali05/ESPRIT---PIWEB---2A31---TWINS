<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'delete') {
        $controller->deleteUser($id);
        header('Location: user_list.php?success=deleted');
        exit;
    }

    if ($id > 0 && $action === 'toggle_block') {
        $blocked = $controller->toggleBlock($id);
        $type = ((int)$blocked === 1) ? 'blocked' : 'unblocked';
        header('Location: user_list.php?success=' . $type);
        exit;
    }
}

$result = $controller->getUsersPage($page, $perPage, $search);
$users = $result['users'];
$total = $result['total'];
$pages = $result['pages'];
$currentPage = $result['page'];

$successMap = [
    'created' => 'User created successfully.',
    'updated' => 'User updated successfully.',
    'deleted' => 'User deleted successfully.',
    'blocked' => 'User has been blocked.',
    'unblocked' => 'User has been unblocked.',
];
$success = $successMap[$_GET['success'] ?? ''] ?? '';

$totalUsers = $controller->countUsers();
$totalAdmins = $controller->countByRole('admin');
$totalManagers = $controller->countByRole('manager');
$totalActive = $controller->countActiveUsers();

$sessionName = $_SESSION['user_name'] ?? 'Admin User';
$sessionInitials = strtoupper(substr($sessionName, 0, 1));
if (strpos($sessionName, ' ') !== false) {
    $parts = explode(' ', $sessionName);
    $sessionInitials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Voices Of Peace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user-system.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="user-system">
<div class="us-shell us-fade-up">
    <header class="us-topbar">
        <a class="us-brand" href="../FrontOffice/home.php"><span class="us-brand-dot">VoP</span> Voices Of Peace Dashboard</a>
        <div class="us-user-chip"><span class="us-user-avatar"><?= htmlspecialchars($sessionInitials) ?></span><?= htmlspecialchars($sessionName) ?></div>
    </header>

    <div class="us-grid">
        <aside class="us-sidebar">
            <div class="us-nav-title">Users</div>
            <a class="us-nav-link active" href="user_list.php"><i data-lucide="users"></i> Users List</a>
            <a class="us-nav-link" href="user_create.php"><i data-lucide="user-plus"></i> Create User</a>
            <div class="us-nav-title">Front Office</div>
            <a class="us-nav-link" href="../FrontOffice/profile.php"><i data-lucide="user-circle"></i> My Profile</a>
            <a class="us-nav-link" href="../FrontOffice/home.php"><i data-lucide="home"></i> Home</a>
        </aside>

        <main class="us-main">
            <section class="us-card us-card-3d">
                <div class="us-toolbar">
                    <div>
                        <h1 class="us-card-title">User Management</h1>
                        <p class="us-card-subtitle">Strictly rebuilt dashboard section with full CRUD actions and production-ready interactions.</p>
                    </div>
                    <a class="us-btn us-btn-primary" href="user_create.php"><i data-lucide="plus"></i> New User</a>
                </div>
            </section>

            <section class="us-stat-grid">
                <article class="us-stat us-card-3d"><h4>Total Users</h4><p><?= (int)$totalUsers ?></p></article>
                <article class="us-stat us-card-3d"><h4>Active</h4><p><?= (int)$totalActive ?></p></article>
                <article class="us-stat us-card-3d"><h4>Admins</h4><p><?= (int)$totalAdmins ?></p></article>
                <article class="us-stat us-card-3d"><h4>Managers</h4><p><?= (int)$totalManagers ?></p></article>
            </section>

            <section class="us-card us-card-3d">
                <div class="us-toolbar" style="margin-bottom:12px;">
                    <form method="get" class="us-filters">
                        <input class="us-input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email" style="min-width:260px;">
                        <button class="us-btn us-btn-ghost" type="submit"><i data-lucide="search"></i> Search</button>
                        <?php if ($search !== ''): ?>
                            <a class="us-btn us-btn-ghost" href="user_list.php">Clear</a>
                        <?php endif; ?>
                    </form>
                    <input id="liveSearch" class="us-input" placeholder="Instant filter table" style="min-width:220px;">
                </div>

                <div class="us-table-wrap">
                    <table class="us-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Country</th>
                                <th>XP</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($users) === 0): ?>
                            <tr><td colspan="6">No users found.</td></tr>
                        <?php else: foreach ($users as $user): ?>
                            <tr class="us-row-item">
                                <td>
                                    <strong><?= htmlspecialchars($user->getFullName()) ?></strong><br>
                                    <span style="color:#a1a1aa;font-size:12px;"><?= htmlspecialchars($user->getEmail()) ?></span>
                                </td>
                                <td><?= htmlspecialchars($user->getRole() ?: 'user') ?></td>
                                <td>
                                    <?php if ((int)$user->getIsBlocked() === 1): ?>
                                        <span class="us-badge blocked">Blocked</span>
                                    <?php elseif ((int)$user->getStatus() === 1): ?>
                                        <span class="us-badge active">Active</span>
                                    <?php else: ?>
                                        <span class="us-badge inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user->getCountry() ?: '-') ?></td>
                                <td><?= (int)$user->getXp() ?></td>
                                <td>
                                    <div class="us-row">
                                        <a class="us-btn us-btn-ghost" href="user_details.php?id=<?= (int)$user->getId() ?>">Details</a>
                                        <a class="us-btn us-btn-ghost" href="user_edit.php?id=<?= (int)$user->getId() ?>">Edit</a>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_block">
                                            <input type="hidden" name="id" value="<?= (int)$user->getId() ?>">
                                            <button class="us-btn us-btn-ghost" type="submit"><?= (int)$user->getIsBlocked() === 1 ? 'Unblock' : 'Block' ?></button>
                                        </form>
                                        <form method="post" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$user->getId() ?>">
                                            <button class="us-btn us-btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                <div class="us-row" style="margin-top:14px;">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <a class="us-btn <?= $i === $currentPage ? 'us-btn-primary' : 'us-btn-ghost' ?>" href="user_list.php?p=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<div class="us-toast" data-message="<?= htmlspecialchars($success) ?>"></div>
<script src="../assets/js/user-system.js"></script>
<script>
  if (window.lucide) { window.lucide.createIcons(); }
  UserSystem.initDeleteConfirm('.delete-form', 'Delete this user permanently?');
  UserSystem.initToasts();
  UserSystem.initLiveSearch('#liveSearch', '.us-row-item');
</script>
</body>
</html>
