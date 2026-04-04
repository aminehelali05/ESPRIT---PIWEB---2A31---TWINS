<?php
require __DIR__ . '/../RebuildUsers/user_edit.php';
return;

$controller = new UserController();
$error = '';

$id = (int) ($_GET['id'] ?? 0);
$user = null;
if ($id > 0) $user = $controller->getUserById($id);
if (!$user) { header('Location: user_list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $phone     = trim($_POST['phone'] ?? '');
    $role      = $_POST['role'] ?? 'user';
    $status    = (int) ($_POST['status'] ?? 0);
    $bio       = trim($_POST['bio'] ?? '');
    $country   = trim($_POST['country'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $skills    = trim($_POST['skills'] ?? '');
    $badge     = trim($_POST['badge'] ?? '');
    $avatarUrl = trim($_POST['avatar_url'] ?? '');
    $xp        = max(0, (int) ($_POST['xp'] ?? 0));
    $isBlocked = (int) ($_POST['is_blocked'] ?? 0);

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = 'First name, last name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($controller->emailExists($email, $id)) {
        $error = 'Email already exists for another user.';
    } else {
        $currentPassword = $user->getPassword();
        if ($password !== '') $currentPassword = password_hash($password, PASSWORD_DEFAULT);

        $updatedUser = new User($firstName, $lastName, $email, $currentPassword, $phone, $role, $status, $user->getCreatedAt());
        $updatedUser->setId($id);
        $updatedUser->setAvatarUrl($avatarUrl ?: null);
        $updatedUser->setBadge($badge ?: null);
        $updatedUser->setCountry($country ?: null);
        $updatedUser->setBio($bio ?: null);
        $updatedUser->setTitle($title ?: null);
        $updatedUser->setSkills($skills ?: null);
        $updatedUser->setXp($xp);
        $updatedUser->setIsBlocked($isBlocked);
        $updatedUser->setFaceDescriptor($user->getFaceDescriptor());
        $updatedUser->setFaceImagesPath($user->getFaceImagesPath());
        $updatedUser->setFaceEnrolled($user->getFaceEnrolled());
        $updatedUser->setFaceEnrolledAt($user->getFaceEnrolledAt());

        $controller->updateUser($updatedUser, $id);
        header('Location: user_list.php?success=updated');
        exit;
    }
}

$sessionName = $_SESSION['user_name'] ?? 'Admin User';
$initials = '';
foreach (explode(' ', $sessionName) as $chunk) { if ($chunk !== "") $initials .= strtoupper(substr($chunk, 0, 1)); }
$initials = substr($initials ?: 'AU', 0, 2);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User — Voices Of Peace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        ::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#3f3f46;border-radius:3px}html:not(.dark) ::-webkit-scrollbar-thumb{background:#d4d4d8}
        .glass{background:rgba(255,255,255,0.7);backdrop-filter:blur(12px);border-bottom:1px solid rgba(0,0,0,0.05)}.dark .glass{background:rgba(9,9,11,0.6);border-bottom:1px solid rgba(255,255,255,0.05)}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}.fade-in-up{animation:fadeInUp .5s cubic-bezier(.4,0,.2,1) forwards}
        .field-error{border-color:#ef4444!important;box-shadow:0 0 0 3px rgba(239,68,68,0.15)!important}
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}.shake{animation:shake .4s ease}
        .sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:40}.sidebar-backdrop.active{display:block}
        @media(max-width:1023px){.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}}
    </style>
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-400 font-sans antialiased overflow-hidden transition-colors duration-300">
    <div class="fixed inset-0 overflow-hidden -z-10 pointer-events-none opacity-40 dark:opacity-100">
        <div class="absolute top-[-10%] left-[20%] w-[500px] h-[500px] bg-indigo-500/10 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-10%] right-[10%] w-[600px] h-[600px] bg-emerald-500/10 rounded-full blur-[100px]"></div>
    </div>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="flex h-screen w-full">
        <!-- Sidebar -->
        <aside class="sidebar fixed inset-y-0 left-0 z-50 w-64 bg-white/80 dark:bg-zinc-950/50 border-r border-zinc-200 dark:border-zinc-800/50 backdrop-blur-xl transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 flex flex-col" id="sidebar">
            <div class="flex items-center justify-between h-16 px-6 border-b border-zinc-200 dark:border-zinc-800/50">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-gradient-to-tr from-indigo-600 to-emerald-500 rounded-lg flex items-center justify-center text-white shadow-lg shadow-indigo-500/20"><span class="font-bold text-xs">VoP</span></div>
                    <h1 class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Voices Of Peace</h1>
                </div>
                <button class="lg:hidden text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100" id="sidebarClose"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <div class="px-3 mb-2 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Administration</div>
                <a href="user_list.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="users" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i><span>Users List</span>
                </a>
                <a href="user_create.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="user-plus" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-emerald-400 transition-colors"></i><span>Add User</span>
                </a>
                <div class="px-3 mt-6 mb-2 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Front Office</div>
                <a href="../FrontOffice/profile.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="user-circle" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-amber-400 transition-colors"></i><span>My Profile</span>
                </a>
            </nav>
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800/50">
                <a href="../FrontOffice/auth.php" class="flex w-full items-center px-3 py-2 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="stroke-[1.5] w-4 h-4 mr-3"></i><span>Sign out</span>
                </a>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
            <header class="h-16 flex items-center justify-between px-6 border-b border-zinc-200 dark:border-zinc-800/50 glass z-20 sticky top-0">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100" id="sidebarToggle"><i data-lucide="menu" class="w-5 h-5"></i></button>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <a href="user_list.php" class="hover:text-zinc-900 dark:hover:text-zinc-200 transition-colors">Users</a>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        <span class="text-zinc-900 dark:text-zinc-100 font-medium">Edit</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="document.documentElement.classList.toggle('dark');" class="p-2 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/50 rounded-lg transition-all focus:outline-none">
                        <i data-lucide="sun" class="hidden dark:block stroke-[1.5] w-5 h-5"></i>
                        <i data-lucide="moon" class="block dark:hidden stroke-[1.5] w-5 h-5"></i>
                    </button>
                    <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-800 mx-1"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-indigo-500/20 to-emerald-500/20 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars($initials) ?></div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 hidden sm:block"><?= htmlspecialchars($sessionName) ?></span>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6 lg:p-8 scroll-smooth bg-zinc-50 dark:bg-zinc-950">
                <div class="max-w-4xl mx-auto fade-in-up">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Edit User</h2>
                            <p class="text-sm text-zinc-500 mt-1">Updating <strong class="text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getFullName()) ?></strong></p>
                        </div>
                        <div class="flex gap-3">
                            <a href="user_details.php?id=<?= $id ?>" class="px-4 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 transition-colors flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <i data-lucide="eye" class="w-4 h-4"></i> View
                            </a>
                            <a href="user_list.php" class="px-4 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 transition-colors flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                            </a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 flex items-center gap-3 text-rose-800 dark:text-rose-300">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-500 flex-shrink-0"></i>
                        <p class="text-sm font-medium"><?= htmlspecialchars($error) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl overflow-hidden backdrop-blur-sm shadow-sm">
                        <form method="POST" action="user_edit.php?id=<?= $id ?>" id="editForm" novalidate>
                            <!-- Basic Info -->
                            <div class="p-6 md:p-8 border-b border-zinc-200 dark:border-zinc-800/50">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-200 mb-6 flex items-center gap-2">
                                    <i data-lucide="user" class="w-4 h-4 text-indigo-500"></i> Basic Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">First Name <span class="text-rose-500">*</span></label>
                                        <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? $user->getFirstName()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                        <p class="text-xs text-rose-500 mt-1 hidden" data-error="first_name">Required</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Last Name <span class="text-rose-500">*</span></label>
                                        <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? $user->getLastName()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                        <p class="text-xs text-rose-500 mt-1 hidden" data-error="last_name">Required</p>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Email Address <span class="text-rose-500">*</span></label>
                                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user->getEmail()) ?>"
                                        class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    <p class="text-xs text-rose-500 mt-1 hidden" data-error="email">Valid email required</p>
                                </div>

                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">New Password <span class="text-xs font-normal text-zinc-500">(leave blank to keep current)</span></label>
                                    <input type="password" name="password"
                                        class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="••••••••">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Phone</label>
                                        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user->getPhone()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                    <div>
                                        <?php $currentRole = $_POST['role'] ?? $user->getRole(); ?>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Role</label>
                                        <select name="role" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                            <option value="user" <?= $currentRole === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="manager" <?= $currentRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                                        </select>
                                    </div>
                                    <div>
                                        <?php $currentStatus = (string)($_POST['status'] ?? $user->getStatus()); ?>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Status</label>
                                        <select name="status" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                            <option value="1" <?= $currentStatus === '1' ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= $currentStatus === '0' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div>
                                        <?php $currentBlocked = (string)($_POST['is_blocked'] ?? $user->getIsBlocked()); ?>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Blocked</label>
                                        <select name="is_blocked" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                            <option value="0" <?= $currentBlocked === '0' ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= $currentBlocked === '1' ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Profile Details -->
                            <div class="p-6 md:p-8 border-b border-zinc-200 dark:border-zinc-800/50">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-200 mb-6 flex items-center gap-2">
                                    <i data-lucide="id-card" class="w-4 h-4 text-emerald-500"></i> Profile Details
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Title</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $user->getTitle()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Country</label>
                                        <input type="text" name="country" value="<?= htmlspecialchars($_POST['country'] ?? $user->getCountry()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Bio</label>
                                    <textarea name="bio" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all resize-none"><?= htmlspecialchars($_POST['bio'] ?? $user->getBio()) ?></textarea>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Skills <span class="text-xs font-normal text-zinc-500">(comma-separated)</span></label>
                                        <input type="text" name="skills" value="<?= htmlspecialchars($_POST['skills'] ?? $user->getSkills()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Badge</label>
                                        <input type="text" name="badge" value="<?= htmlspecialchars($_POST['badge'] ?? $user->getBadge()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Avatar URL</label>
                                        <input type="url" name="avatar_url" value="<?= htmlspecialchars($_POST['avatar_url'] ?? $user->getAvatarUrl()) ?>" id="avatarUrlInput"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">XP Points</label>
                                        <input type="number" name="xp" min="0" value="<?= htmlspecialchars($_POST['xp'] ?? $user->getXp()) ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                    </div>
                                </div>
                                <!-- Avatar Preview -->
                                <?php $av = $_POST['avatar_url'] ?? $user->getAvatarUrl(); ?>
                                <?php if ($av): ?>
                                <div class="mt-4 flex items-center gap-4" id="avatarPreview">
                                    <img src="<?= htmlspecialchars($av) ?>" alt="Avatar" id="avatarImg" class="w-16 h-16 rounded-full object-cover border-2 border-indigo-500/30 shadow-lg" onerror="this.parentElement.style.display='none'">
                                    <span class="text-xs text-zinc-500">Current avatar</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="p-6 md:p-8 flex flex-col sm:flex-row justify-end gap-4">
                                <a href="user_list.php" class="px-5 py-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors text-center">Cancel</a>
                                <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-medium transition-all shadow-lg shadow-amber-500/20 hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                    <i data-lucide="save" class="w-4 h-4"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar'), backdrop = document.getElementById('sidebarBackdrop');
        document.getElementById('sidebarToggle')?.addEventListener('click',()=>{sidebar.classList.add('open');backdrop.classList.add('active')});
        document.getElementById('sidebarClose')?.addEventListener('click',()=>{sidebar.classList.remove('open');backdrop.classList.remove('active')});
        backdrop?.addEventListener('click',()=>{sidebar.classList.remove('open');backdrop.classList.remove('active')});

        // Client-side validation
        document.getElementById('editForm')?.addEventListener('submit', function(e) {
            let valid = true;
            ['first_name','last_name','email'].forEach(name => {
                const input = this.querySelector(`[name="${name}"]`);
                const err = this.querySelector(`[data-error="${name}"]`);
                if (!input) return;
                let ok = input.value.trim() !== '';
                if (name === 'email') ok = ok && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim());
                if (!ok) { valid = false; input.classList.add('field-error'); err?.classList.remove('hidden'); input.addEventListener('input',()=>{input.classList.remove('field-error');err?.classList.add('hidden')},{once:true}); }
                else { input.classList.remove('field-error'); err?.classList.add('hidden'); }
            });
            if (!valid) { e.preventDefault(); this.querySelector('.field-error')?.focus(); this.classList.add('shake'); setTimeout(()=>this.classList.remove('shake'),400); }
        });

        // Avatar preview update
        document.getElementById('avatarUrlInput')?.addEventListener('input', function() {
            let preview = document.getElementById('avatarPreview');
            if (!preview) {
                preview = document.createElement('div');
                preview.id = 'avatarPreview';
                preview.className = 'mt-4 flex items-center gap-4';
                preview.innerHTML = '<img src="" alt="Avatar" id="avatarImg" class="w-16 h-16 rounded-full object-cover border-2 border-indigo-500/30 shadow-lg"><span class="text-xs text-zinc-500">Avatar preview</span>';
                this.parentElement.parentElement.appendChild(preview);
            }
            const img = document.getElementById('avatarImg');
            if (this.value.trim()) { img.src = this.value.trim(); preview.style.display = 'flex'; img.onerror = () => { preview.style.display = 'none'; }; }
            else { preview.style.display = 'none'; }
        });
    </script>
</body>
</html>
