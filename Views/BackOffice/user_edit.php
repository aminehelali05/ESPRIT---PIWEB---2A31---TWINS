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

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide valid name and email.';
        } else {
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setEmail($email);
                $user->setPhone(trim($_POST['phone'] ?? ''));
                $user->setRole(trim($_POST['role'] ?? 'user'));
                $user->setStatus((int) ($_POST['status'] ?? 1));
                $user->setCountry(trim($_POST['country'] ?? ''));
                $user->setTitle(trim($_POST['title'] ?? ''));
                $user->setBio(trim($_POST['bio'] ?? ''));
                $user->setSkills(trim($_POST['skills'] ?? ''));
                $user->setBadge(trim($_POST['badge'] ?? ''));
                $user->setXp((int) ($_POST['xp'] ?? 0));
                $user->setAvatarUrl(trim($_POST['avatar_url'] ?? ''));
                $user->setIsBlocked((int) ($_POST['is_blocked'] ?? 0));

                $newPassword = trim($_POST['new_password'] ?? '');
                if ($newPassword !== '') {
                        if (strlen($newPassword) < 6) {
                                $error = 'New password must be at least 6 characters.';
                        } else {
                                $user->setPassword(password_hash($newPassword, PASSWORD_DEFAULT));
                        }
                }

                if ($error === '') {
                        $controller->updateUser($user, $id);
                        $message = 'User updated successfully.';
                        $user = $controller->getUserById($id);
                }
        }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/user-module.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="um-page" style="max-width:1000px;margin:0 auto;">
        <header class="um-topbar">
            <a class="um-brand" href="user_list.php"><span class="um-brand-dot">VoP</span> Edit User #<?= (int) $user->getId() ?></a>
            <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
        </header>

        <?php if ($error): ?><div class="um-alert um-alert-danger" style="margin-bottom:10px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="um-alert um-alert-success" style="margin-bottom:10px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <section class="um-card" style="padding:18px;">
            <form method="POST" class="um-form">
                <div class="um-grid-2">
                    <div><label>First Name *</label><input class="um-input" name="first_name" value="<?= htmlspecialchars($user->getFirstName()) ?>" required></div>
                    <div><label>Last Name *</label><input class="um-input" name="last_name" value="<?= htmlspecialchars($user->getLastName()) ?>" required></div>
                    <div><label>Email *</label><input class="um-input" type="email" name="email" value="<?= htmlspecialchars($user->getEmail()) ?>" required></div>
                    <div><label>Phone</label><input class="um-input" name="phone" value="<?= htmlspecialchars($user->getPhone()) ?>"></div>
                    <div><label>Role</label><select class="um-select" name="role"><option value="user" <?= $user->getRole() === 'user' ? 'selected' : '' ?>>User</option><option value="admin" <?= $user->getRole() === 'admin' ? 'selected' : '' ?>>Admin</option></select></div>
                    <div><label>Status</label><select class="um-select" name="status"><option value="1" <?= (int) $user->getStatus() === 1 ? 'selected' : '' ?>>Active</option><option value="0" <?= (int) $user->getStatus() === 0 ? 'selected' : '' ?>>Inactive</option></select></div>
                    <div><label>Country</label><input class="um-input" name="country" value="<?= htmlspecialchars($user->getCountry()) ?>"></div>
                    <div><label>Title</label><input class="um-input" name="title" value="<?= htmlspecialchars($user->getTitle()) ?>"></div>
                    <div><label>Badge</label><input class="um-input" name="badge" value="<?= htmlspecialchars($user->getBadge()) ?>"></div>
                    <div><label>XP</label><input class="um-input" type="number" name="xp" min="0" value="<?= (int) $user->getXp() ?>"></div>
                    <div><label>Blocked</label><select class="um-select" name="is_blocked"><option value="0" <?= (int) $user->getIsBlocked() === 0 ? 'selected' : '' ?>>No</option><option value="1" <?= (int) $user->getIsBlocked() === 1 ? 'selected' : '' ?>>Yes</option></select></div>
                    <div><label>Avatar URL</label><input class="um-input" name="avatar_url" value="<?= htmlspecialchars($user->getAvatarUrl()) ?>"></div>
                    <div style="grid-column:1/-1;"><label>Skills</label><input class="um-input" name="skills" value="<?= htmlspecialchars($user->getSkills()) ?>"></div>
                    <div style="grid-column:1/-1;"><label>Bio</label><textarea class="um-textarea" name="bio" rows="4"><?= htmlspecialchars($user->getBio()) ?></textarea></div>
                    <div style="grid-column:1/-1;"><label>New Password (optional)</label><input class="um-input" type="password" name="new_password"></div>
                </div>
                <div class="um-actions">
                    <a href="user_list.php" class="um-btn um-btn-soft">Back</a>
                    <button class="um-btn um-btn-primary" type="submit">Save</button>
                </div>
            </form>
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

$userController = new UserController();
$userId = $_GET['id'] ?? null;

if (!$userId) {
    die("User ID not provided.");
}

$user = $userController->getUserById($userId);
if (!$user) {
    die("User not found.");
}

// Handle Form Submission
$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = htmlspecialchars($_POST['first_name']);
    $lastName  = htmlspecialchars($_POST['last_name']);
    $email     = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $role      = htmlspecialchars($_POST['role']);
    $isBlocked = isset($_POST['is_blocked']) ? 1 : 0;
    
    $bio       = htmlspecialchars($_POST['bio']);
    $title     = htmlspecialchars($_POST['title']);
    $phone     = htmlspecialchars($_POST['phone']);
    $country   = htmlspecialchars($_POST['country']);
    $skills    = htmlspecialchars($_POST['skills']);

    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = "Name and Email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setIsBlocked($isBlocked);
        
        $user->setBio($bio);
        $user->setTitle($title);
        $user->setPhone($phone);
        $user->setCountry($country);
        $user->setSkills($skills);

        if ($userController->updateUser($user)) {
            $message = "Profile successfully updated.";
        } else {
            $error = "Error updating user in database.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit User — Dashboard Focus</title>
  
  <!-- Typography Profiles -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    /* Strict Light Mode Design System (from precise instructions) */
    :root {
        --color-primary: #0A66C2;
        --color-primary-hover: #084c91;
        --color-accent: #00C853;
        --color-bg: #F5F7FA;
        --color-card: #FFFFFF;
        --color-text-primary: #1A1A1A;
        --color-text-secondary: #6B7280;
        --color-border: #E5E7EB;
        --color-danger: #ef4444;
        
        --font-heading: 'Inter', sans-serif;
        --font-body: 'Satoshi', sans-serif;
        
        --radius-card: 16px;
        --radius-input: 8px;
        
        --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        background-color: var(--color-bg);
        color: var(--color-text-primary);
        font-family: var(--font-body);
        overflow-x: hidden;
    }

    h1, h2, h3 { font-family: var(--font-heading); font-weight: 600; }

    /* Dashboard Layout */
    .dash-layout {
        display: flex; min-height: 100vh;
    }
    
    /* Sidebar Stub for visual context */
    .sidebar { width: 260px; background: var(--color-card); border-right: 1px solid var(--color-border); padding: 24px; display:none; }
    @media (min-width: 1024px) { .sidebar { display:block; } }
    .sidebar-logo { font-family: var(--font-heading); font-size: 1.2rem; font-weight: 700; color: var(--color-primary); display:flex; align-items:center; gap:8px; margin-bottom: 40px;}
    .nav-link { display:flex; align-items:center; gap:12px; padding: 12px 16px; color: var(--color-text-secondary); text-decoration:none; font-weight:500; border-radius: 8px; transition: var(--transition-base); margin-bottom: 8px;}
    .nav-link.active { background: #eff6ff; color: var(--color-primary); }
    .nav-link:hover { background: var(--color-bg); color: var(--color-text-primary); }

    .main-content { flex: 1; padding: 32px 48px; max-width: 1200px; margin: 0 auto; }
    
    /* Header */
    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom: 32px; }
    .page-title { font-size: 1.5rem; }
    .page-subtitle { color: var(--color-text-secondary); font-size: 0.95rem; margin-top: 4px; }
    
    /* Alerts */
    .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; display:flex; align-items:center; gap:8px; animation: slideIn 0.3s ease; }
    .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .alert-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* Forms */
    .saas-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-card);
        padding: 32px;
        box-shadow: var(--shadow-soft);
        margin-bottom: 24px;
        transition: var(--transition-base);
    }
    .saas-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    
    .form-grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
    @media (min-width: 768px) { .form-grid { grid-template-columns: 1fr 1fr; } }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .full-width { grid-column: 1 / -1; }
    
    label { font-weight: 500; font-size: 0.9rem; color: var(--color-text-primary); }
    
    input[type="text"], input[type="email"], select, textarea {
        width: 100%; padding: 12px 16px;
        background: #fff; border: 1px solid var(--color-border);
        border-radius: var(--radius-input); font-family: var(--font-body);
        font-size: 0.95rem; color: var(--color-text-primary);
        transition: var(--transition-base);
        outline: none;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(10,102,194,0.15); }
    
    /* Toggle Switch */
    .toggle-wrap { display:flex; align-items:center; gap: 12px; cursor:pointer; }
    .toggle-switch {
        position: relative; width: 44px; height: 24px; background: var(--color-border);
        border-radius: 24px; transition: var(--transition-base);
    }
    .toggle-switch::after {
        content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px;
        background: #fff; border-radius: 50%; transition: var(--transition-base);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    input[type="checkbox"] { display:none; }
    input[type="checkbox"]:checked + .toggle-switch { background: var(--color-danger); }
    input[type="checkbox"]:checked + .toggle-switch::after { transform: translateX(20px); }

    /* Avatar Edit */
    .avatar-edit-wrap { display:flex; align-items:center; gap: 24px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--color-border); }
    .avatar-preview {
        width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
        border: 2px solid var(--color-border);
    }
    .btn-upload { background: var(--color-bg); color: var(--color-text-primary); border: 1px solid var(--color-border); padding: 8px 16px; border-radius: var(--radius-input); font-weight:500; font-size: 0.9rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition: var(--transition-base);}
    .btn-upload:hover { background: #e5e7eb; }

    /* Action Buttons */
    .actions-footer { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--color-border); }
    .btn { padding: 12px 24px; border-radius: var(--radius-input); font-family: var(--font-heading); font-weight: 500; font-size: 1rem; cursor:pointer; transition: var(--transition-base); border: none; display:inline-flex; align-items:center; justify-content:center; gap:8px;}
    .btn-primary { background: var(--color-primary); color: #fff; shadow: 0 4px 6px rgba(10,102,194,0.2); }
    .btn-primary:hover { background: var(--color-primary-hover); transform: translateY(-1px); box-shadow: 0 6px 12px rgba(10,102,194,0.25); }
    .btn-secondary { background: #fff; color: var(--color-text-secondary); border: 1px solid var(--color-border); }
    .btn-secondary:hover { background: var(--color-bg); color: var(--color-text-primary); }

    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>

  <div class="dash-layout">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo"><i data-lucide="hexagon"></i> Diversity.is</div>
        <nav>
            <a href="user_list.php" class="nav-link"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview</a>
            <a href="user_list.php" class="nav-link active"><i data-lucide="users" class="w-4 h-4"></i> User Management</a>
            <a href="#" class="nav-link"><i data-lucide="settings" class="w-4 h-4"></i> System Config</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit Profile</h1>
                <p class="page-subtitle">Update identity parameters and administrative rights for #<?= $user->getId() ?>.</p>
            </div>
            <a href="user_list.php" class="btn btn-secondary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Back to List</a>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><i data-lucide="check-circle"></i> <?= $message ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><i data-lucide="alert-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="user_edit.php?id=<?= $user->getId() ?>" id="editForm">
            <div class="saas-card">
                
                <div class="avatar-edit-wrap">
                    <img src="<?= $user->getAvatarUrl() ? htmlspecialchars($user->getAvatarUrl()) : 'https://ui-avatars.com/api/?name='.urlencode($user->getFullName()).'&background=0A66C2&color=fff' ?>" class="avatar-preview" alt="Avatar">
                    <div>
                        <h3 style="margin-bottom: 8px; font-size: 1.1rem;">Profile Picture</h3>
                        <div style="display:flex; gap:12px;">
                            <button type="button" class="btn-upload"><i data-lucide="upload" class="w-4 h-4"></i> Change Photo</button>
                            <button type="button" class="btn-upload" style="color:var(--color-danger);"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                </div>

                <h3 style="margin-bottom: 24px; font-size: 1.1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">Personal Details</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($user->getFirstName()) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($user->getLastName()) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user->getEmail()) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user->getPhone()) ?>">
                    </div>
                    <div class="form-group">
                        <label>Headline / Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($user->getTitle()) ?>" placeholder="e.g. Senior Software Engineer">
                    </div>
                    <div class="form-group">
                        <label>Country / Location</label>
                        <input type="text" name="country" value="<?= htmlspecialchars($user->getCountry()) ?>">
                    </div>
                    <div class="form-group full-width">
                        <label>Biography</label>
                        <textarea name="bio" rows="4"><?= htmlspecialchars($user->getBio()) ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Skills (comma separated)</label>
                        <input type="text" name="skills" value="<?= htmlspecialchars($user->getSkills()) ?>" placeholder="e.g. React, PHP, UI/UX">
                    </div>
                </div>
            </div>

            <div class="saas-card">
                <h3 style="margin-bottom: 24px; font-size: 1.1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;"><i data-lucide="shield" class="w-4 h-4" style="display:inline; margin-right:8px; position:relative; top:2px; color:var(--color-primary);"></i>Administrative Access</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>System Role</label>
                        <select name="role">
                            <option value="user" <?= $user->getRole() == 'user' ? 'selected' : '' ?>>User (Standard Access)</option>
                            <option value="admin" <?= $user->getRole() == 'admin' ? 'selected' : '' ?>>Admin (Full Access)</option>
                        </select>
                        <p style="font-size:0.8rem; color:var(--color-text-secondary); margin-top:4px;">Determines system privileges.</p>
                    </div>
                    
                    <div class="form-group" style="padding-top: 24px;">
                        <label class="toggle-wrap">
                            <input type="checkbox" name="is_blocked" <?= $user->getIsBlocked() ? 'checked' : '' ?>>
                            <div class="toggle-switch"></div>
                            <div>
                                <span style="display:block; font-weight:600; color:var(--color-text-primary);">Block Account Access</span>
                                <span style="font-size:0.8rem; color:var(--color-text-secondary); font-weight:400;">Instantly revokes login privileges.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="actions-footer">
                    <a href="../FrontOffice/profile.php?id=<?= $user->getId() ?>" class="btn btn-secondary" target="_blank"><i data-lucide="external-link" class="w-4 h-4"></i> View Live Profile</a>
                    <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save Settings</button>
                </div>
            </div>
        </form>

    </main>
  </div>

  <script>
    lucide.createIcons();
    
    // Add simple form submission animation to btn
    document.getElementById('editForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4" style="animation: spin 1s linear infinite;"></i> Saving...';
        lucide.createIcons();
    });
  </script>
  <style>
    @keyframes spin { 100% { transform: rotate(360deg); } }
  </style>
</body>
</html>
