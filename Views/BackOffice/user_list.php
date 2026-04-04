<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $controller->deleteUser($id);
        $query = $search !== '' ? '&search=' . urlencode($search) : '';
        header('Location: user_list.php?success=deleted' . $query);
        exit;
    }
}

if (isset($_GET['success'])) {
    $map = [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted successfully.'
    ];
    $success = $map[$_GET['success']] ?? '';
}

$result = $controller->getUsersPage($page, 8, $search);
$users = $result['users'];
$pages = $result['pages'];
$currentPage = $result['page'];
$total = $result['total'];

$sessionName = $_SESSION['user_name'] ?? 'Admin User';
$sessionEmail = $_SESSION['user_email'] ?? 'admin@diversity.is';
$initials = '';
foreach (explode(' ', $sessionName) as $chunk) {
    $initials .= strtoupper(substr($chunk, 0, 1));
}
$initials = substr($initials ?: 'AU', 0, 2);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User List - Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .module-panel { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1rem; }
    [data-theme="light"] .module-panel { background: #fff; border-color: rgba(0,0,0,0.08); }
    .alert-bar { padding: .75rem 1rem; border-radius: 10px; margin-bottom: 1rem; font-size: .85rem; }
    .alert-success { background: rgba(34,197,94,.12); color: #86efac; border: 1px solid rgba(34,197,94,.25); }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { text-align: left; padding: .75rem; border-bottom: 1px solid rgba(255,255,255,.06); font-size: .85rem; }
    [data-theme="light"] .table th, [data-theme="light"] .table td { border-bottom-color: rgba(0,0,0,.06); }
    .role-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .7rem; font-weight: 600; }
    .role-admin { color: #c084fc; background: rgba(168,85,247,.16); }
    .role-manager { color: #fbbf24; background: rgba(251,191,36,.16); }
    .role-user { color: #818cf8; background: rgba(129,140,248,.16); }
    .status-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .7rem; }
    .status-active { color: #86efac; background: rgba(34,197,94,.16); }
    .status-off { color: #cbd5e1; background: rgba(148,163,184,.16); }
    .action-row { display: flex; gap: .5rem; }
    .action-btn { border: 1px solid rgba(255,255,255,.08); color: var(--color-text-secondary); background: transparent; border-radius: 8px; padding: .35rem .5rem; cursor: pointer; text-decoration: none; }
    .action-btn:hover { border-color: rgba(99,102,241,.45); color: var(--color-accent); }
    .danger:hover { border-color: rgba(239,68,68,.45); color: #ef4444; }
    .top-actions { display: flex; gap: .75rem; align-items: center; }
    .search-input { width: 260px; max-width: 100%; }
    .pagination { display: flex; gap: .5rem; margin-top: 1rem; }
    .pagination a { padding: .35rem .6rem; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; text-decoration: none; color: var(--color-text-secondary); }
    .pagination a.active { border-color: rgba(99,102,241,.6); color: var(--color-accent); }
    .header-profile { display: flex; align-items: center; gap: .6rem; }
    .header-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--color-accent), var(--color-accent-secondary)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; }
  </style>
</head>
<body class="dashboard-body">
  <div class="dashboard-layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header"><div class="sidebar-brand"><div class="brand-icon">⬡</div><span class="sidebar-brand-text">Diversity.is</span></div></div>
      <nav class="sidebar-nav">
        <div class="sidebar-section-label">BackOffice</div>
        <a class="sidebar-link active" href="user_list.php"><i data-lucide="users"></i> Users</a>
        <a class="sidebar-link" href="user_form.php"><i data-lucide="user-plus"></i> Add User</a>
        <a class="sidebar-link" href="../FrontOffice/profile.php"><i data-lucide="user-circle"></i> My Profile</a>
      </nav>
      <div class="sidebar-footer"><a class="sidebar-link sidebar-logout" href="../FrontOffice/auth.php"><i data-lucide="log-out"></i> Logout</a></div>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header">
        <div class="header-left"><h3 style="font-size:.95rem;">User Management</h3></div>
        <div class="header-right">
          <button class="theme-toggle" aria-label="Toggle theme">
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          </button>
          <div class="header-profile"><div class="header-avatar"><?= htmlspecialchars($initials) ?></div><div><div style="font-size:.8rem; font-weight:600;"><?= htmlspecialchars($sessionName) ?></div><div style="font-size:.68rem; color:var(--color-text-secondary);"><?= htmlspecialchars($sessionEmail) ?></div></div></div>
        </div>
      </header>

      <section class="dashboard-content" style="padding:1.2rem; overflow:auto;">
        <?php if ($success): ?>
          <div class="alert-bar alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="module-panel">
          <form method="get" class="top-actions" style="justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap;">
            <div style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;">
              <input class="search-input form-input" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email">
              <button type="submit" class="btn btn-outline btn-sm">Search</button>
              <?php if ($search !== ''): ?><a class="btn btn-ghost btn-sm" href="user_list.php">Clear</a><?php endif; ?>
            </div>
            <div style="display:flex; gap:.6rem; align-items:center;">
              <span style="font-size:.8rem; color:var(--color-text-secondary);">Total: <?= (int) $total ?></span>
              <a href="user_form.php" class="btn btn-primary btn-sm">Add User</a>
            </div>
          </form>

          <div style="overflow:auto;">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if (count($users) === 0): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--color-text-secondary);">No users found.</td></tr>
              <?php else: foreach ($users as $user):
                $role = strtolower($user->getRole() ?: 'user');
                $roleClass = in_array($role, ['admin', 'manager'], true) ? 'role-' . $role : 'role-user';
                $statusClass = (int) $user->getStatus() === 1 ? 'status-active' : 'status-off';
              ?>
                <tr>
                  <td><?= (int) $user->getId() ?></td>
                  <td><?= htmlspecialchars($user->getFullName()) ?></td>
                  <td><?= htmlspecialchars($user->getEmail()) ?></td>
                  <td><span class="role-pill <?= $roleClass ?>"><?= htmlspecialchars($role) ?></span></td>
                  <td><span class="status-pill <?= $statusClass ?>"><?= (int) $user->getStatus() === 1 ? 'Active' : 'Inactive' ?></span></td>
                  <td style="text-align:right;">
                    <div class="action-row" style="justify-content:flex-end;">
                      <a class="action-btn" href="user_edit.php?id=<?= (int) $user->getId() ?>">Edit</a>
                      <form method="post" onsubmit="return confirm('Delete this user?');" style="margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $user->getId() ?>">
                        <button class="action-btn danger" type="submit">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($pages > 1): ?>
            <div class="pagination">
              <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="<?= $i === $currentPage ? 'active' : '' ?>" href="user_list.php?p=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>
  <script src="../assets/js/dashboard.js"></script>
  <script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
