<?php
require __DIR__ . '/../RebuildUsers/user_list.php';
return;

$controller = new UserController();
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$success = '';
$error = '';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $controller->deleteUser($id);
        $query = $search !== '' ? '&search=' . urlencode($search) : '';
        header('Location: user_list.php?success=deleted' . $query);
        exit;
    }
}

// Handle Block/Unblock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_block') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $targetUser = $controller->getUserById($id);
        if ($targetUser) {
            $targetUser->setIsBlocked($targetUser->getIsBlocked() ? 0 : 1);
            $controller->updateUser($targetUser, $id);
            header('Location: user_list.php?success=' . ($targetUser->getIsBlocked() ? 'blocked' : 'unblocked'));
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $map = [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted successfully.',
        'blocked' => 'User has been blocked.',
        'unblocked' => 'User has been unblocked.'
    ];
    $success = $map[$_GET['success']] ?? '';
}

$result = $controller->getUsersPage($page, 10, $search);
$users = $result['users'];
$pages = $result['pages'];
$currentPage = $result['page'];
$total = $result['total'];

// Stats
$totalUsers = $controller->countUsers();
$totalAdmins = $controller->countByRole('admin');
$totalManagers = $controller->countByRole('manager');

$sessionName = $_SESSION['user_name'] ?? 'Admin User';
$sessionEmail = $_SESSION['user_email'] ?? 'admin@voicesofpeace.org';
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
    <title>User Management — Voices Of Peace</title>
    <meta name="description" content="Manage platform users, roles, and access — Voices Of Peace Admin Dashboard">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { border: 'rgba(255,255,255,0.08)', surface: 'rgba(255,255,255,0.02)' } } }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #52525b; }
        html:not(.dark) ::-webkit-scrollbar-thumb { background: #d4d4d8; }
        html:not(.dark) ::-webkit-scrollbar-thumb:hover { background: #a1a1aa; }
        .glass { background: rgba(255,255,255,0.7); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .dark .glass { background: rgba(9,9,11,0.6); border-bottom: 1px solid rgba(255,255,255,0.05); }

        /* 3D Card hover */
        .card-3d { transition: transform 0.4s cubic-bezier(.25,.46,.45,.94), box-shadow 0.4s ease; transform-style: preserve-3d; }
        .card-3d:hover { transform: translateY(-6px) rotateX(2deg) rotateY(-2deg); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25); }

        /* Fade in animation */
        @keyframes fadeInUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s cubic-bezier(.4,0,.2,1) forwards; opacity: 0; }
        .fade-in-up:nth-child(1) { animation-delay: 0s; }
        .fade-in-up:nth-child(2) { animation-delay: 0.08s; }
        .fade-in-up:nth-child(3) { animation-delay: 0.16s; }
        .fade-in-up:nth-child(4) { animation-delay: 0.24s; }

        /* Row hover glow */
        .table-row-hover { transition: background 0.2s, transform 0.2s; }
        .table-row-hover:hover { transform: scale(1.003); }

        /* Pulse glow on badge */
        @keyframes pulseGlow { 0%,100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.3); } 50% { box-shadow: 0 0 0 6px rgba(99,102,241,0); } }
        .pulse-glow { animation: pulseGlow 2s infinite; }

        /* Sidebar mobile */
        .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; }
        .sidebar-backdrop.active { display: block; }
        @media (max-width: 1023px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
    </style>
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-400 font-sans antialiased selection:bg-indigo-500/30 overflow-hidden transition-colors duration-300">
    <!-- Background Glow -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none opacity-40 dark:opacity-100 transition-opacity">
        <div class="absolute top-[-10%] left-[20%] w-[500px] h-[500px] bg-indigo-500/10 dark:bg-indigo-900/10 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-10%] right-[10%] w-[600px] h-[600px] bg-emerald-500/10 dark:bg-emerald-900/10 rounded-full blur-[100px]"></div>
    </div>

    <!-- Sidebar Backdrop (mobile) -->
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
                <button class="lg:hidden text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100" id="sidebarClose">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <div class="px-3 mb-2 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Administration</div>
                <a href="user_list.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group active text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-white/5 border border-zinc-200 dark:border-zinc-800/50">
                    <i data-lucide="users" class="stroke-[1.5] w-4 h-4 mr-3 text-indigo-600 dark:text-indigo-400"></i>
                    <span>Users List</span>
                </a>
                <a href="user_create.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="user-plus" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors"></i>
                    <span>Add User</span>
                </a>

                <div class="px-3 mt-6 mb-2 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">Front Office</div>
                <a href="../FrontOffice/profile.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="user-circle" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors"></i>
                    <span>My Profile</span>
                </a>
                <a href="../FrontOffice/home.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg group text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-white/5 transition-all">
                    <i data-lucide="home" class="stroke-[1.5] w-4 h-4 mr-3 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors"></i>
                    <span>Home Page</span>
                </a>
            </nav>
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800/50">
                <a href="../FrontOffice/auth.php" class="flex w-full items-center px-3 py-2 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-colors">
                    <i data-lucide="log-out" class="stroke-[1.5] w-4 h-4 mr-3"></i>
                    <span>Sign out</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative transition-colors duration-300" id="mainContent">
            <!-- Header -->
            <header class="h-16 flex items-center justify-between px-6 border-b border-zinc-200 dark:border-zinc-800/50 glass z-20 sticky top-0 transition-colors duration-300">
                <div class="flex items-center gap-4 w-full max-w-xl">
                    <button class="lg:hidden text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100" id="sidebarToggle">
                        <i data-lucide="menu" class="stroke-[1.5] w-5 h-5"></i>
                    </button>
                    <form method="GET" class="relative group w-full" id="searchForm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="search" class="h-4 w-4 text-zinc-400 dark:text-zinc-600 group-focus-within:text-indigo-600 dark:group-focus-within:text-indigo-400 transition-colors"></i>
                        </div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="block w-full pl-10 pr-3 py-1.5 bg-zinc-100 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-lg leading-5 text-zinc-900 dark:text-zinc-200 placeholder-zinc-500 dark:placeholder-zinc-600 focus:outline-none focus:bg-white dark:focus:bg-zinc-900 focus:border-indigo-500/50 focus:ring-1 focus:ring-indigo-500/50 sm:text-sm transition-all" placeholder="Search users by name or email...">
                    </form>
                    <?php if ($search !== ''): ?>
                        <a href="user_list.php" class="text-sm font-medium text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 whitespace-nowrap transition-colors">Clear</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="document.documentElement.classList.toggle('dark');" class="p-2 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/50 rounded-lg transition-all focus:outline-none" id="themeToggle">
                        <i data-lucide="sun" class="hidden dark:block stroke-[1.5] w-5 h-5"></i>
                        <i data-lucide="moon" class="block dark:hidden stroke-[1.5] w-5 h-5"></i>
                    </button>
                    <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-800 mx-1"></div>
                    <div class="flex items-center gap-3 p-1 rounded-lg">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-indigo-500/20 to-emerald-500/20 border border-zinc-200 dark:border-zinc-700 overflow-hidden flex items-center justify-center text-xs font-bold text-indigo-600 dark:text-indigo-400">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="hidden sm:block text-left">
                            <span class="block text-xs font-medium text-zinc-700 dark:text-zinc-300"><?= htmlspecialchars($sessionName) ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="flex-1 overflow-y-auto p-6 lg:p-8 scroll-smooth bg-zinc-50 dark:bg-zinc-950 transition-colors duration-300">

                <!-- Page Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">User Management</h2>
                        <p class="text-sm text-zinc-500 mt-1">Manage platform users, roles, and access control.</p>
                    </div>
                    <a href="user_create.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium shadow-lg shadow-indigo-500/20 transition-all hover:-translate-y-0.5 flex items-center gap-2 w-fit">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Add New User
                    </a>
                </div>

                <!-- Success Message -->
                <?php if ($success): ?>
                <div class="mb-6 p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/50 flex items-center gap-3 text-emerald-800 dark:text-emerald-300 fade-in-up" id="successAlert">
                    <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 dark:text-emerald-400 flex-shrink-0"></i>
                    <p class="text-sm font-medium flex-1"><?= htmlspecialchars($success) ?></p>
                    <button onclick="this.parentElement.remove();" class="text-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-200 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="card-3d bg-white dark:bg-zinc-900/20 backdrop-blur-sm border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-5 hover:border-indigo-500/30 transition-colors shadow-sm dark:shadow-none fade-in-up">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Total Users</p>
                                <h3 class="text-2xl font-medium text-zinc-900 dark:text-zinc-100 mt-2 tracking-tight"><?= $totalUsers ?></h3>
                                <p class="text-xs text-emerald-500 mt-1 font-medium">Platform members</p>
                            </div>
                            <div class="p-2 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg border border-indigo-500/20">
                                <i data-lucide="users" class="stroke-[1.5] w-5 h-5"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-3d bg-white dark:bg-zinc-900/20 backdrop-blur-sm border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-5 hover:border-emerald-500/30 transition-colors shadow-sm dark:shadow-none fade-in-up">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Admins</p>
                                <h3 class="text-2xl font-medium text-zinc-900 dark:text-zinc-100 mt-2 tracking-tight"><?= $totalAdmins ?></h3>
                                <p class="text-xs text-emerald-500 mt-1 font-medium">Administrators</p>
                            </div>
                            <div class="p-2 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg border border-emerald-500/20">
                                <i data-lucide="shield" class="stroke-[1.5] w-5 h-5"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-3d bg-white dark:bg-zinc-900/20 backdrop-blur-sm border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-5 hover:border-purple-500/30 transition-colors shadow-sm dark:shadow-none fade-in-up">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Managers</p>
                                <h3 class="text-2xl font-medium text-zinc-900 dark:text-zinc-100 mt-2 tracking-tight"><?= $totalManagers ?></h3>
                                <p class="text-xs text-purple-500 mt-1 font-medium">Team leaders</p>
                            </div>
                            <div class="p-2 bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-lg border border-purple-500/20">
                                <i data-lucide="crown" class="stroke-[1.5] w-5 h-5"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-3d bg-white dark:bg-zinc-900/20 backdrop-blur-sm border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-5 hover:border-amber-500/30 transition-colors shadow-sm dark:shadow-none fade-in-up">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Regular Users</p>
                                <h3 class="text-2xl font-medium text-zinc-900 dark:text-zinc-100 mt-2 tracking-tight"><?= $totalUsers - $totalAdmins - $totalManagers ?></h3>
                                <p class="text-xs text-amber-500 mt-1 font-medium">Standard accounts</p>
                            </div>
                            <div class="p-2 bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-lg border border-amber-500/20">
                                <i data-lucide="user" class="stroke-[1.5] w-5 h-5"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-white dark:bg-zinc-900/20 backdrop-blur-sm border border-zinc-200 dark:border-zinc-800/60 rounded-xl overflow-hidden shadow-sm dark:shadow-none fade-in-up" style="animation-delay:0.3s;">
                    <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800/50 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-200">All Users</h3>
                            <p class="text-xs text-zinc-500 mt-0.5"><?= $total ?> total · Page <?= $currentPage ?> of <?= $pages ?></p>
                        </div>
                        <?php if ($search !== ''): ?>
                            <span class="text-xs px-2.5 py-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 rounded-lg border border-indigo-200 dark:border-indigo-800/50">
                                Search: "<?= htmlspecialchars($search) ?>"
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-zinc-700 dark:text-zinc-300" id="usersTable">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800/50">
                                <tr>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">User</th>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Role</th>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Status</th>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider hidden md:table-cell">Country</th>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider hidden lg:table-cell">XP</th>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider hidden lg:table-cell">Joined</th>
                                    <th class="py-3 px-5 text-xs font-semibold text-zinc-500 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/30">
                                <?php if (count($users) === 0): ?>
                                    <tr><td colspan="7" class="py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <i data-lucide="search-x" class="w-12 h-12 text-zinc-300 dark:text-zinc-700"></i>
                                            <p class="text-zinc-500 text-sm">No users found.</p>
                                            <?php if ($search !== ''): ?>
                                                <a href="user_list.php" class="text-indigo-600 dark:text-indigo-400 text-sm font-medium hover:underline">Clear search</a>
                                            <?php endif; ?>
                                        </div>
                                    </td></tr>
                                <?php else: foreach ($users as $user): ?>
                                    <tr class="table-row-hover hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                        <td class="py-4 px-5">
                                            <div class="flex items-center gap-3">
                                                <?php if ($user->getAvatarUrl()): ?>
                                                    <img src="<?= htmlspecialchars($user->getAvatarUrl()) ?>" alt="" class="w-10 h-10 rounded-full object-cover border-2 border-zinc-200 dark:border-zinc-700 flex-shrink-0">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500/80 to-emerald-500/80 flex flex-shrink-0 items-center justify-center text-white font-medium text-sm shadow-sm">
                                                        <?= htmlspecialchars($user->getInitials()) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 truncate flex items-center gap-2">
                                                        <?= htmlspecialchars($user->getFullName()) ?>
                                                        <?php if ((int)$user->getIsBlocked()): ?>
                                                            <span class="text-[10px] px-1.5 py-0.5 bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 rounded font-semibold">BLOCKED</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs text-zinc-500 truncate"><?= htmlspecialchars($user->getEmail()) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-5">
                                            <?php
                                            $roleColors = [
                                                'admin' => 'bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/50',
                                                'manager' => 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800/50',
                                                'user' => 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700',
                                            ];
                                            $role = strtolower($user->getRole() ?: 'user');
                                            $roleClass = $roleColors[$role] ?? $roleColors['user'];
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold capitalize border <?= $roleClass ?>">
                                                <?= htmlspecialchars($user->getRole() ?: 'User') ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-5">
                                            <?php if ((int)$user->getStatus() === 1): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-5 hidden md:table-cell text-zinc-500 text-xs">
                                            <?= htmlspecialchars($user->getCountry() ?: '—') ?>
                                        </td>
                                        <td class="py-4 px-5 hidden lg:table-cell">
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full h-1.5">
                                                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= min(100, (int)$user->getXp() / 5) ?>%"></div>
                                                </div>
                                                <span class="text-xs text-zinc-500 font-medium"><?= (int)$user->getXp() ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 hidden lg:table-cell text-zinc-500 text-xs">
                                            <?= htmlspecialchars(substr($user->getCreatedAt(), 0, 10) ?: 'N/A') ?>
                                        </td>
                                        <td class="py-4 px-5 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="user_details.php?id=<?= (int)$user->getId() ?>" class="p-1.5 text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors" title="View Details">
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                </a>
                                                <a href="user_edit.php?id=<?= (int)$user->getId() ?>" class="p-1.5 text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Edit">
                                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                                </a>
                                                <button onclick="confirmDelete(<?= (int)$user->getId() ?>, '<?= htmlspecialchars(addslashes($user->getFullName()), ENT_QUOTES) ?>')" class="p-1.5 text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition-colors" title="Delete">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pages > 1): ?>
                    <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-800/50 flex items-center justify-between">
                        <p class="text-xs text-zinc-500">Showing page <?= $currentPage ?> of <?= $pages ?></p>
                        <nav class="flex items-center gap-1">
                            <?php if ($currentPage > 1): ?>
                                <a href="user_list.php?p=<?= $currentPage - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="px-3 py-1.5 rounded-md text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/5 transition-colors">
                                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                </a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <a href="user_list.php?p=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors <?= $i === $currentPage ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/5' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($currentPage < $pages): ?>
                                <a href="user_list.php?p=<?= $currentPage + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="px-3 py-1.5 rounded-md text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/5 transition-colors">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteUserId">
    </form>

    <script>
        lucide.createIcons();

        // SweetAlert delete confirmation
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Delete User?',
                html: `Are you sure you want to delete <strong>${name}</strong>? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="mr-1">🗑️</i> Yes, delete',
                cancelButtonText: 'Cancel',
                customClass: { popup: 'swal2-popup-custom' },
                background: document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b',
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteUserId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Sidebar toggle (mobile)
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            sidebar.classList.add('open');
            backdrop.classList.add('active');
        });
        document.getElementById('sidebarClose')?.addEventListener('click', closeSidebar);
        backdrop?.addEventListener('click', closeSidebar);
        function closeSidebar() {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
        }

        // Auto-dismiss success alert
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.style.transition = 'opacity 0.5s, transform 0.5s';
                successAlert.style.opacity = '0';
                successAlert.style.transform = 'translateY(-8px)';
                setTimeout(() => successAlert.remove(), 500);
            }, 4000);
        }
    </script>
</body>
</html>
