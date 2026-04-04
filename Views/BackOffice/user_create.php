<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
        header('Location: ../FrontOffice/login.php');
        exit;
}

$controller = new UserController();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
                $error = 'Please provide valid required fields.';
        } elseif ($controller->emailExists($email)) {
                $error = 'Email already exists.';
        } else {
                $user = new User(
                        $firstName,
                        $lastName,
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                        trim($_POST['phone'] ?? ''),
                        trim($_POST['role'] ?? 'user'),
                        (int) ($_POST['status'] ?? 1),
                        date('Y-m-d H:i:s')
                );
                $user->setCountry(trim($_POST['country'] ?? ''));
                $user->setTitle(trim($_POST['title'] ?? ''));
                $user->setBio(trim($_POST['bio'] ?? ''));
                $user->setSkills(trim($_POST['skills'] ?? ''));
                $user->setBadge(trim($_POST['badge'] ?? ''));
                $user->setXp((int) ($_POST['xp'] ?? 0));
                $user->setAvatarUrl(trim($_POST['avatar_url'] ?? ''));

                $id = $controller->addUser($user);
                if ($id) {
                        header('Location: user_list.php?msg=created');
                        exit;
                }
                $error = 'Failed to create user.';
        }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
    <link rel="stylesheet" href="../assets/css/user-module.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="um-page" style="max-width:1000px;margin:0 auto;">
        <header class="um-topbar">
            <a class="um-brand" href="user_list.php"><span class="um-brand-dot">VoP</span> Create User</a>
            <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
        </header>

        <?php if ($error): ?><div class="um-alert um-alert-danger" style="margin-bottom:10px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <section class="um-card" style="padding:18px;">
            <form method="POST" class="um-form">
                <div class="um-grid-2">
                    <div><label>First Name *</label><input class="um-input" name="first_name" required></div>
                    <div><label>Last Name *</label><input class="um-input" name="last_name" required></div>
                    <div><label>Email *</label><input class="um-input" type="email" name="email" required></div>
                    <div><label>Password *</label><input class="um-input" type="password" name="password" required minlength="6"></div>
                    <div><label>Phone</label><input class="um-input" name="phone"></div>
                    <div><label>Country</label><input class="um-input" name="country"></div>
                    <div><label>Role</label><select class="um-select" name="role"><option value="user">User</option><option value="admin">Admin</option></select></div>
                    <div><label>Status</label><select class="um-select" name="status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    <div><label>Title</label><input class="um-input" name="title"></div>
                    <div><label>Badge</label><input class="um-input" name="badge"></div>
                    <div><label>XP</label><input class="um-input" type="number" name="xp" min="0" value="0"></div>
                    <div><label>Avatar URL</label><input class="um-input" name="avatar_url"></div>
                    <div style="grid-column:1/-1;"><label>Skills</label><input class="um-input" name="skills" placeholder="Comma separated"></div>
                    <div style="grid-column:1/-1;"><label>Bio</label><textarea class="um-textarea" name="bio" rows="4"></textarea></div>
                </div>
                <div class="um-actions">
                    <a href="user_list.php" class="um-btn um-btn-soft">Cancel</a>
                    <button class="um-btn um-btn-primary" type="submit">Create User</button>
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

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $controller = new UserController();
    
    // Check if email exists
    if ($controller->emailExists($_POST['email'])) {
        $error = "This email is already registered.";
    } else {
        $user = new User(
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $_POST['phone'] ?? '',
            $_POST['role'] ?? 'user',
            $_POST['status'] ?? 1
        );
        $user->setCountry($_POST['country'] ?? '');
        $user->setTitle($_POST['title'] ?? '');
        
        $controller->addUser($user);
        header('Location: user_list.php?msg=added');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New User</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="um-container" style="max-width: 800px;">
        <div class="page-header" style="margin-bottom: 2rem;">
            <a href="user_list.php" class="um-btn um-btn-secondary" style="margin-bottom: 1rem;">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <h1 class="um-title">Create New User</h1>
            <p class="um-subtitle">Fill out the details to onboard a new member.</p>
        </div>

        <?php if($error): ?>
            <div style="background: var(--danger-color); color: white; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="um-card">
            <form action="" method="POST">
                <div class="um-grid-2">
                    <div class="um-form-group">
                        <label class="um-label">First Name *</label>
                        <input type="text" name="first_name" class="um-input" required placeholder="John">
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Last Name *</label>
                        <input type="text" name="last_name" class="um-input" required placeholder="Doe">
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Email Address *</label>
                        <input type="email" name="email" class="um-input" required placeholder="john@example.com">
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Phone Number</label>
                        <input type="text" name="phone" class="um-input" placeholder="+1 234 567 8900">
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Password *</label>
                        <input type="password" name="password" class="um-input" required placeholder="••••••••">
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Role</label>
                        <select name="role" class="um-input">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Job Title</label>
                        <input type="text" name="title" class="um-input" placeholder="e.g. Developer">
                    </div>
                    <div class="um-form-group">
                        <label class="um-label">Country</label>
                        <input type="text" name="country" class="um-input" placeholder="e.g. USA">
                    </div>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <a href="user_list.php" class="um-btn um-btn-secondary">Cancel</a>
                    <button type="submit" class="um-btn um-btn-primary um-btn-submit">
                        <i class="fas fa-save" style="margin-right: 0.5rem;"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="../assets/js/user_modern.js"></script>
</body>
</html>
