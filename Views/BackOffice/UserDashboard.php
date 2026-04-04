<?php
header('Location: user_list.php');
exit;
__halt_compiler();

// --- Handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && !empty($_POST['id'])) {
        $userController->deleteUser((int) $_POST['id']);
        header('Location: UserDashboard.php?success=deleted');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($firstName === '' || $lastName === '' || $email === '') {
            $error = 'First name, last name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            if ($action === 'update') {
                $existingUser = $userController->getUserById($id);
                if (!$existingUser) {
                    $error = 'User not found.';
                } elseif ($userController->emailExists($email, $id)) {
                    $error = 'Email already used by another user.';
                } else {
                    $hashedPassword = $existingUser->getPassword();
                    if ($password !== '') {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $updated = new User($firstName, $lastName, $email, $hashedPassword, $phone, $role, $status, $existingUser->getCreatedAt());
                    $userController->updateUser($updated, $id);
                    header('Location: UserDashboard.php?success=updated');
                    exit;
                }
            } else {
                if ($password === '') {
                    $error = 'Password is required for new users.';
                } elseif ($userController->emailExists($email)) {
                    $error = 'Email already exists.';
                } else {
                    $newUser = new User($firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $role, $status, date('Y-m-d H:i:s'));
                    $userController->addUser($newUser);
                    header('Location: UserDashboard.php?success=created');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editingUser = $userController->getUserById((int) $_GET['edit']);
}

if (isset($_GET['success'])) {
    $msgs = ['created' => 'User created successfully.', 'updated' => 'User updated successfully.', 'deleted' => 'User deleted successfully.'];
    $success = $msgs[$_GET['success']] ?? '';
}

$users = $userController->listUsers();
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => (int) $u->getStatus() === 1));
$adminUsers = count(array_filter($users, fn($u) => strtolower($u->getRole()) === 'admin'));
$managerUsers = count(array_filter($users, fn($u) => strtolower($u->getRole()) === 'manager'));

// Session user info
$sessionName = $_SESSION['user_name'] ?? 'Admin';
$sessionEmail = $_SESSION['user_email'] ?? 'admin@diversity.is';
$sessionInitials = '';
foreach (explode(' ', $sessionName) as $word) { $sessionInitials .= strtoupper(substr($word, 0, 1)); }
$sessionInitials = substr($sessionInitials, 0, 2);

// Current page from ?page= or default
$currentPage = $_GET['page'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is — Admin Dashboard with full CRUD user management.">
  <title>Dashboard — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    /* ======= RICH DASHBOARD INLINE STYLES ======= */
    .module-panel {
      background: rgba(255,255,255,0.04);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 0.75rem;
      padding: 1.5rem;
      transition: border-color 0.2s;
    }
    [data-theme="light"] .module-panel {
      background: #fff;
      border-color: rgba(0,0,0,0.06);
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .module-panel:hover { border-color: rgba(99,102,241,0.2); }
    .module-rich-grid {
      display: grid;
      gap: 1.5rem;
      margin-top: 1.5rem;
    }
    .alert-bar {
      padding: 0.85rem 1.25rem;
      border-radius: 0.5rem;
      font-size: 0.85rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    .alert-success {
      background: rgba(34,197,94,0.08);
      border: 1px solid rgba(34,197,94,0.25);
      color: #86EFAC;
    }
    [data-theme="light"] .alert-success { color: #16A34A; }
    .alert-error {
      background: rgba(239,68,68,0.08);
      border: 1px solid rgba(239,68,68,0.25);
      color: #FCA5A5;
    }
    [data-theme="light"] .alert-error { color: #DC2626; }
    .user-cell {
      display: flex;
      header('Location: user_list.php');
      exit;
                <p style="font-size: 0.82rem; color: var(--color-text-secondary);">Your involvement over the last 6 months</p>
                <div style="margin-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                  <div style="text-align:center; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="font-size: 1.5rem; font-weight: 700;"><?= $activeUsers ?></p>
                    <p style="font-size: 0.7rem; color: var(--color-text-secondary);">Active Users</p>
                  </div>
                  <div style="text-align:center; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: 0.5rem; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="font-size: 1.5rem; font-weight: 700;"><?= $managerUsers ?></p>
                    <p style="font-size: 0.7rem; color: var(--color-text-secondary);">Managers</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <!-- ===== USERS PAGE ===== -->
        <?php if ($currentPage === 'users' || $currentPage === 'add-user'): ?>
        <section>
          <?php if ($success): ?>
            <div class="alert-bar alert-success"><i data-lucide="check-circle" class="w-4 h-4"></i> <?= htmlspecialchars($success) ?></div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert-bar alert-error"><i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <!-- AI Insight Banner -->
          <div class="ai-insight-card">
            <div class="ai-insight-icon"><i data-lucide="brain" class="w-5 h-5"></i></div>
            <div class="ai-insight-text">
              <h4>AI User Analysis</h4>
              <p>You have <strong><?= $totalUsers ?></strong> total users — <strong><?= $activeUsers ?></strong> active, <strong><?= $adminUsers ?></strong> admins, <strong><?= $managerUsers ?></strong> managers. <?= ($totalUsers - $activeUsers) > 0 ? ($totalUsers - $activeUsers) . ' inactive accounts may need review.' : 'All accounts are active.' ?></p>
            </div>
          </div>

          <div class="module-rich-grid" style="grid-template-columns: 1.3fr 0.7fr; align-items:start;">
            <!-- Users Table -->
            <article class="module-panel">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
                <h3 style="font-size: 1rem; font-weight: 600;">Users List</h3>
                <a href="UserDashboard.php?page=users" class="btn btn-primary btn-sm"><i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh</a>
              </div>
              <div class="table-wrap">
                <table class="dash-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>User</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Created</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($totalUsers === 0): ?>
                      <tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--color-text-secondary);">No users found. Create your first user.</td></tr>
                    <?php else: ?>
                      <?php foreach ($users as $user):
                        $initials = strtoupper(substr($user->getFirstName(),0,1) . substr($user->getLastName(),0,1));
                        $colors = ['#6366F1','#22C55E','#F59E0B','#EF4444','#A855F7','#22D3EE','#EC4899'];
                        $bgColor = $colors[$user->getId() % count($colors)];
                        $roleClass = 'role-' . strtolower($user->getRole());
                      ?>
                      <tr>
                        <td style="color:var(--color-text-secondary);"><?= (int) $user->getId() ?></td>
                        <td>
                          <div class="user-cell">
                            <div class="user-avatar-sm" style="background:<?= $bgColor ?>;"><?= $initials ?></div>
                            <div class="user-info-cell">
                              <strong><?= htmlspecialchars($user->getFullName()) ?></strong>
                              <span><?= htmlspecialchars($user->getEmail()) ?></span>
                            </div>
                          </div>
                        </td>
                        <td><span class="role-pill <?= $roleClass ?>"><?= htmlspecialchars($user->getRole()) ?></span></td>
                        <td><span class="status-dot <?= (int) $user->getStatus() === 1 ? 'status-active' : 'status-inactive' ?>"><?= (int) $user->getStatus() === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td style="font-size:0.75rem; color:var(--color-text-secondary);"><?= htmlspecialchars($user->getCreatedAt()) ?></td>
                        <td>
                          <div class="action-btns">
                            <a class="action-btn" href="UserDashboard.php?page=users&edit=<?= (int) $user->getId() ?>" title="Edit">
                              <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </a>
                            <form method="POST" action="UserDashboard.php?page=users" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int) $user->getId() ?>">
                              <button type="submit" class="action-btn danger" title="Delete">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                              </button>
                            </form>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <!-- Create/Edit Form -->
            <article class="module-panel">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">
                <i data-lucide="<?= $editingUser ? 'edit-3' : 'user-plus' ?>" class="w-4 h-4" style="display:inline; vertical-align:middle;"></i>
                <?= $editingUser ? 'Edit User #' . (int) $editingUser->getId() : 'Create New User' ?>
              </h3>
              <form method="POST" action="UserDashboard.php?page=users">
                <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
                <?php if ($editingUser): ?><input type="hidden" name="id" value="<?= (int) $editingUser->getId() ?>"><?php endif; ?>

                <div class="form-row-2">
                  <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getFirstName() : ($_POST['first_name'] ?? '')) ?>">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getLastName() : ($_POST['last_name'] ?? '')) ?>">
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($editingUser ? $editingUser->getEmail() : ($_POST['email'] ?? '')) ?>">
                </div>

                <div class="form-group">
                  <label class="form-label">Password <?= $editingUser ? '(leave empty to keep)' : '' ?></label>
                  <input type="password" class="form-control" name="password" <?= $editingUser ? '' : 'required' ?>>
                </div>

                <div class="form-group">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($editingUser ? $editingUser->getPhone() : ($_POST['phone'] ?? '')) ?>">
                </div>

                <div class="form-row-2">
                  <div class="form-group">
                    <label class="form-label">Role</label>
                    <?php $selectedRole = strtolower($editingUser ? $editingUser->getRole() : ($_POST['role'] ?? 'user')); ?>
                    <select class="form-control" name="role">
                      <option value="user" <?= $selectedRole === 'user' ? 'selected' : '' ?>>User</option>
                      <option value="manager" <?= $selectedRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                      <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Status</label>
                    <?php $checkedStatus = $editingUser ? ((int) $editingUser->getStatus() === 1) : (!isset($_POST['action']) || isset($_POST['status'])); ?>
                    <div style="display:flex; align-items:center; gap:8px; margin-top:0.35rem;">
                      <input type="checkbox" id="status" name="status" <?= $checkedStatus ? 'checked' : '' ?>>
                      <label for="status" style="font-size:0.82rem; margin:0; cursor:pointer;">Active</label>
                    </div>
                  </div>
                </div>

                <div style="display:flex; gap:8px; margin-top:0.5rem;">
                  <button type="submit" class="btn btn-primary btn-sm"><?= $editingUser ? 'Update User' : 'Create User' ?></button>
                  <?php if ($editingUser): ?>
                    <a href="UserDashboard.php?page=users" class="btn btn-secondary btn-sm">Cancel</a>
                  <?php endif; ?>
                </div>
              </form>
            </article>
          </div>
        </section>
        <?php endif; ?>

      </div>
    </main>
  </div>

  <script>
    // Init Lucide
    if (window.lucide) lucide.createIcons();

    // Theme toggle
    const themeBtn = document.querySelector('.theme-toggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme') || 'dark';
        html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        localStorage.setItem('diversity-theme', current === 'dark' ? 'light' : 'dark');
      });
    }
    // Restore theme
    const savedTheme = localStorage.getItem('diversity-theme');
    if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);

    // Mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const headerToggle = document.getElementById('headerToggle');
    if (headerToggle && sidebar) {
      headerToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }
  </script>
</body>
</html>
