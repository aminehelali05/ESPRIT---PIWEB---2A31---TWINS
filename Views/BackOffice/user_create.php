<?php
require __DIR__ . '/../RebuildUsers/user_create.php';
return;

$controller = new UserController();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $phone     = trim($_POST['phone'] ?? '');
    $role      = $_POST['role'] ?? 'user';
    $status    = (int) ($_POST['status'] ?? 1);
    $bio       = trim($_POST['bio'] ?? '');
    $country   = trim($_POST['country'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $skills    = trim($_POST['skills'] ?? '');
    $badge     = trim($_POST['badge'] ?? '');
    $avatarUrl = trim($_POST['avatar_url'] ?? '');
    $xp        = max(0, (int) ($_POST['xp'] ?? 0));

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields (First Name, Last Name, Email, Password).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($controller->emailExists($email)) {
        $error = 'This email address is already registered.';
    } else {
        $user = new User(
            $firstName, $lastName, $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone, $role, $status, date('Y-m-d H:i:s')
        );
        $user->setAvatarUrl($avatarUrl ?: null);
        $user->setBadge($badge ?: null);
        $user->setCountry($country ?: null);
        $user->setBio($bio ?: null);
        $user->setTitle($title ?: null);
        $user->setSkills($skills ?: null);
        $user->setXp($xp);

        $result = $controller->addUser($user);
        if ($result !== false) {
            header('Location: user_list.php?success=created');
            exit;
        } else {
            $error = 'Failed to create user. Please try again.';
        }
    }
}

$sessionName = $_SESSION['user_name'] ?? 'Admin User';
$initials = '';
foreach (explode(' ', $sessionName) as $chunk) {
    if ($chunk !== "") $initials .= strtoupper(substr($chunk, 0, 1));
}
$initials = substr($initials ?: 'AU', 0, 2);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User — Voices Of Peace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } } }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 3px; }
        html:not(.dark) ::-webkit-scrollbar-thumb { background: #d4d4d8; }
        .glass { background: rgba(255,255,255,0.7); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .dark .glass { background: rgba(9,9,11,0.6); border-bottom: 1px solid rgba(255,255,255,0.05); }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s cubic-bezier(.4,0,.2,1) forwards; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
        .shake { animation: shake 0.4s ease; }
        .field-error { border-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239,68,68,0.15) !important; }
        .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; }
        .sidebar-backdrop.active { display: block; }
        @media (max-width: 1023px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
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
                    <div class="w-8 h-8 bg-gradient-to-tr from-indigo-600 to-emerald-500 rounded-lg flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                        <span class="font-bold text-xs">VoP</span>
                    </div>
                    <h1 class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Voices Of Peace</h1>
                </div>
                <button class="lg:hidden text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100" id="sidebarClose"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <div class="px-3 mb-2 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Administration</div>
                <a href="user_list.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="users" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors"></i><span>Users List</span>
                </a>
                <a href="user_create.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group active text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-white/5 border border-zinc-200 dark:border-zinc-800/50">
                    <i data-lucide="user-plus" class="stroke-[1.5] w-4 h-4 mr-3 text-emerald-600 dark:text-emerald-400"></i><span>Add User</span>
                </a>
                <div class="px-3 mt-6 mb-2 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Front Office</div>
                <a href="../FrontOffice/profile.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="user-circle" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-amber-400 transition-colors"></i><span>My Profile</span>
                </a>
                <a href="../FrontOffice/home.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="home" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-blue-400 transition-colors"></i><span>Home Page</span>
                </a>
            </nav>
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800/50">
                <a href="../FrontOffice/auth.php" class="flex w-full items-center px-3 py-2 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="stroke-[1.5] w-4 h-4 mr-3"></i><span>Sign out</span>
                </a>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative transition-colors duration-300">
            <!-- Header -->
            <header class="h-16 flex items-center justify-between px-6 border-b border-zinc-200 dark:border-zinc-800/50 glass z-20 sticky top-0">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100" id="sidebarToggle"><i data-lucide="menu" class="w-5 h-5"></i></button>
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <a href="user_list.php" class="hover:text-zinc-900 dark:hover:text-zinc-200 transition-colors">Users</a>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        <span class="text-zinc-900 dark:text-zinc-100 font-medium">Create</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="document.documentElement.classList.toggle('dark');" class="p-2 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/50 rounded-lg transition-all focus:outline-none">
                        <i data-lucide="sun" class="hidden dark:block stroke-[1.5] w-5 h-5"></i>
                        <i data-lucide="moon" class="block dark:hidden stroke-[1.5] w-5 h-5"></i>
                    </button>
                    <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-800 mx-1"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-indigo-500/20 to-emerald-500/20 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 hidden sm:block"><?= htmlspecialchars($sessionName) ?></span>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto p-6 lg:p-8 scroll-smooth bg-zinc-50 dark:bg-zinc-950">
                <div class="max-w-4xl mx-auto fade-in-up">
                    <!-- Page Header -->
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Create New User</h2>
                            <p class="text-sm text-zinc-500 mt-1">Add a new member to the platform with all profile details.</p>
                        </div>
                        <a href="user_list.php" class="px-4 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 transition-colors flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to List
                        </a>
                    </div>

                    <!-- Error Message -->
                    <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 flex items-center gap-3 text-rose-800 dark:text-rose-300">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-500 dark:text-rose-400 flex-shrink-0"></i>
                        <p class="text-sm font-medium"><?= htmlspecialchars($error) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Form Card -->
                    <div class="bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl overflow-hidden backdrop-blur-sm shadow-sm">
                        <form method="POST" action="user_create.php" id="createForm" novalidate>
                            <!-- Section: Basic Info -->
                            <div class="p-6 md:p-8 border-b border-zinc-200 dark:border-zinc-800/50">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-200 mb-1 flex items-center gap-2">
                                    <i data-lucide="user" class="w-4 h-4 text-indigo-500"></i> Basic Information
                                </h3>
                                <p class="text-xs text-zinc-500 mb-6">Required fields are marked with *</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">First Name <span class="text-rose-500">*</span></label>
                                        <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all" placeholder="John" data-validate="required">
                                        <p class="text-xs text-rose-500 mt-1 hidden" data-error="first_name">First name is required</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Last Name <span class="text-rose-500">*</span></label>
                                        <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all" placeholder="Doe" data-validate="required">
                                        <p class="text-xs text-rose-500 mt-1 hidden" data-error="last_name">Last name is required</p>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Email Address <span class="text-rose-500">*</span></label>
                                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                        class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all" placeholder="john@example.com" data-validate="email">
                                    <p class="text-xs text-rose-500 mt-1 hidden" data-error="email">Please enter a valid email address</p>
                                </div>

                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Password <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="password" required id="passwordField"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all pr-12" placeholder="Min 6 characters" data-validate="password">
                                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                                            <i data-lucide="eye" class="w-4 h-4" id="eyeIcon"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-rose-500 mt-1 hidden" data-error="password">Password must be at least 6 characters</p>
                                    <!-- Password strength bar -->
                                    <div class="mt-2 flex gap-1" id="strengthBar">
                                        <div class="h-1 flex-1 rounded bg-zinc-200 dark:bg-zinc-800 transition-colors" id="str1"></div>
                                        <div class="h-1 flex-1 rounded bg-zinc-200 dark:bg-zinc-800 transition-colors" id="str2"></div>
                                        <div class="h-1 flex-1 rounded bg-zinc-200 dark:bg-zinc-800 transition-colors" id="str3"></div>
                                        <div class="h-1 flex-1 rounded bg-zinc-200 dark:bg-zinc-800 transition-colors" id="str4"></div>
                                    </div>
                                    <p class="text-xs text-zinc-500 mt-1" id="strengthText"></p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Phone</label>
                                        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="+216 XX XXX XXX">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Role</label>
                                        <select name="role" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                            <option value="user" <?= ($_POST['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Status</label>
                                        <select name="status" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all">
                                            <option value="1" <?= (string)($_POST['status'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= (string)($_POST['status'] ?? '1') === '0' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Profile Details -->
                            <div class="p-6 md:p-8 border-b border-zinc-200 dark:border-zinc-800/50">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-200 mb-1 flex items-center gap-2">
                                    <i data-lucide="id-card" class="w-4 h-4 text-emerald-500"></i> Profile Details
                                </h3>
                                <p class="text-xs text-zinc-500 mb-6">Optional profile information</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Title / Role Name</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="e.g. Community Manager">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Country</label>
                                        <input type="text" name="country" value="<?= htmlspecialchars($_POST['country'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="e.g. Tunisia">
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Bio</label>
                                    <textarea name="bio" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all resize-none" placeholder="A short description about this user..."><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Skills <span class="text-xs font-normal text-zinc-500">(comma-separated)</span></label>
                                        <input type="text" name="skills" value="<?= htmlspecialchars($_POST['skills'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="PHP, MySQL, JavaScript">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Badge</label>
                                        <input type="text" name="badge" value="<?= htmlspecialchars($_POST['badge'] ?? '') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="e.g. Peace Maker">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Avatar URL</label>
                                        <input type="url" name="avatar_url" value="<?= htmlspecialchars($_POST['avatar_url'] ?? '') ?>" id="avatarUrlInput"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="https://example.com/avatar.jpg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Starting XP</label>
                                        <input type="number" name="xp" min="0" max="99999" value="<?= htmlspecialchars($_POST['xp'] ?? '0') ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-black/20 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition-all" placeholder="0">
                                    </div>
                                </div>

                                <!-- Avatar Preview -->
                                <div class="mt-4 flex items-center gap-4" id="avatarPreview" style="display:none;">
                                    <img src="" alt="Avatar Preview" id="avatarImg" class="w-16 h-16 rounded-full object-cover border-2 border-indigo-500/30 shadow-lg">
                                    <span class="text-xs text-zinc-500">Avatar preview</span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="p-6 md:p-8 flex flex-col sm:flex-row justify-end gap-4">
                                <button type="reset" class="px-5 py-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Reset Form</button>
                                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition-all shadow-lg shadow-indigo-500/20 hover:-translate-y-0.5 flex items-center justify-center gap-2" id="submitBtn">
                                    <i data-lucide="save" class="w-4 h-4"></i> Create Account
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
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        document.getElementById('sidebarToggle')?.addEventListener('click', () => { sidebar.classList.add('open'); backdrop.classList.add('active'); });
        document.getElementById('sidebarClose')?.addEventListener('click', () => { sidebar.classList.remove('open'); backdrop.classList.remove('active'); });
        backdrop?.addEventListener('click', () => { sidebar.classList.remove('open'); backdrop.classList.remove('active'); });

        // Toggle password visibility
        function togglePassword() {
            const f = document.getElementById('passwordField');
            f.type = f.type === 'password' ? 'text' : 'password';
        }

        // Password strength indicator
        const pwField = document.getElementById('passwordField');
        pwField?.addEventListener('input', function() {
            const v = this.value;
            let strength = 0;
            if (v.length >= 6) strength++;
            if (v.length >= 10) strength++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) strength++;
            if (/[0-9]/.test(v) && /[^A-Za-z0-9]/.test(v)) strength++;

            const colors = ['bg-rose-500', 'bg-amber-500', 'bg-yellow-500', 'bg-emerald-500'];
            const labels = ['Weak', 'Fair', 'Good', 'Strong'];
            for (let i = 1; i <= 4; i++) {
                const bar = document.getElementById('str' + i);
                bar.className = 'h-1 flex-1 rounded transition-colors ' + (i <= strength ? colors[strength - 1] : 'bg-zinc-200 dark:bg-zinc-800');
            }
            document.getElementById('strengthText').textContent = v.length > 0 ? labels[strength - 1] || '' : '';
        });

        // Avatar preview
        document.getElementById('avatarUrlInput')?.addEventListener('input', function() {
            const preview = document.getElementById('avatarPreview');
            const img = document.getElementById('avatarImg');
            if (this.value.trim()) {
                img.src = this.value.trim();
                preview.style.display = 'flex';
                img.onerror = () => { preview.style.display = 'none'; };
            } else {
                preview.style.display = 'none';
            }
        });

        // Client-side validation
        document.getElementById('createForm')?.addEventListener('submit', function(e) {
            let valid = true;
            const fields = { first_name: 'required', last_name: 'required', email: 'email', password: 'password' };

            Object.entries(fields).forEach(([name, type]) => {
                const input = this.querySelector(`[name="${name}"]`);
                const errorEl = this.querySelector(`[data-error="${name}"]`);
                if (!input) return;

                let isValid = true;
                const val = input.value.trim();

                if (type === 'required' && val === '') isValid = false;
                if (type === 'email' && (val === '' || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val))) isValid = false;
                if (type === 'password' && val.length < 6) isValid = false;

                if (!isValid) {
                    valid = false;
                    input.classList.add('field-error');
                    errorEl?.classList.remove('hidden');
                    input.addEventListener('input', () => {
                        input.classList.remove('field-error');
                        errorEl?.classList.add('hidden');
                    }, { once: true });
                } else {
                    input.classList.remove('field-error');
                    errorEl?.classList.add('hidden');
                }
            });

            if (!valid) {
                e.preventDefault();
                this.querySelector('.field-error')?.focus();
                this.classList.add('shake');
                setTimeout(() => this.classList.remove('shake'), 400);
            }
        });
    </script>
</body>
</html>
