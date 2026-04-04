<?php
require __DIR__ . '/../RebuildUsers/user_details.php';
return;

$controller = new UserController();
$id = (int) ($_GET['id'] ?? 0);
$user = null;
if ($id > 0) $user = $controller->getUserById($id);
if (!$user) { header('Location: user_list.php'); exit; }

// Handle block/unblock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_block') {
    $user->setIsBlocked($user->getIsBlocked() ? 0 : 1);
    $controller->updateUser($user, $id);
    header('Location: user_details.php?id=' . $id);
    exit;
}

$sessionName = $_SESSION['user_name'] ?? 'Admin User';
$initials = '';
foreach (explode(' ', $sessionName) as $chunk) { if ($chunk !== "") $initials .= strtoupper(substr($chunk, 0, 1)); }
$initials = substr($initials ?: 'AU', 0, 2);

$skillsArr = $user->getSkillsArray();
$xpPercent = min(100, (int)$user->getXp() / 5);
$level = floor((int)$user->getXp() / 100) + 1;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user->getFullName()) ?> — User Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        ::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#3f3f46;border-radius:3px}html:not(.dark) ::-webkit-scrollbar-thumb{background:#d4d4d8}
        .glass{background:rgba(255,255,255,0.7);backdrop-filter:blur(12px);border-bottom:1px solid rgba(0,0,0,0.05)}.dark .glass{background:rgba(9,9,11,0.6);border-bottom:1px solid rgba(255,255,255,0.05)}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}.fade-in-up{animation:fadeInUp .5s cubic-bezier(.4,0,.2,1) forwards;opacity:0}
        .fade-in-up:nth-child(1){animation-delay:0s}.fade-in-up:nth-child(2){animation-delay:.1s}.fade-in-up:nth-child(3){animation-delay:.2s}
        @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
        .xp-bar-fill{background:linear-gradient(90deg,#6366f1,#8b5cf6,#6366f1);background-size:200% 100%;animation:shimmer 2s ease-in-out infinite}
        .card-3d{transition:transform .4s cubic-bezier(.25,.46,.45,.94),box-shadow .4s ease;transform-style:preserve-3d}
        .card-3d:hover{transform:translateY(-4px) rotateX(1deg);box-shadow:0 16px 32px -8px rgba(0,0,0,0.2)}
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
                        <span class="text-zinc-900 dark:text-zinc-100 font-medium"><?= htmlspecialchars($user->getFullName()) ?></span>
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
                <div class="max-w-5xl mx-auto">
                    <!-- Action Bar -->
                    <div class="flex items-center justify-between mb-8 fade-in-up">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">User Profile</h2>
                            <p class="text-sm text-zinc-500 mt-1">Detailed view for ID #<?= $id ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="user_edit.php?id=<?= $id ?>" class="px-4 py-2 bg-amber-600/10 text-amber-600 dark:text-amber-400 border border-amber-600/20 rounded-lg text-sm font-medium hover:bg-amber-600/20 transition-colors flex items-center gap-2">
                                <i data-lucide="edit-2" class="w-4 h-4"></i> Edit
                            </a>
                            <button onclick="confirmDelete()" class="px-4 py-2 bg-rose-600/10 text-rose-600 dark:text-rose-400 border border-rose-600/20 rounded-lg text-sm font-medium hover:bg-rose-600/20 transition-colors flex items-center gap-2">
                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                            </button>
                            <a href="user_list.php" class="px-4 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800/50 transition-colors flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left: Profile Card -->
                        <div class="lg:col-span-1 space-y-6">
                            <!-- Avatar Card -->
                            <div class="card-3d bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl overflow-hidden backdrop-blur-sm shadow-sm fade-in-up">
                                <!-- Cover gradient -->
                                <div class="h-24 bg-gradient-to-br from-indigo-600 via-purple-600 to-emerald-500 relative">
                                    <div class="absolute inset-0 bg-black/10"></div>
                                </div>
                                <div class="px-6 pb-6 -mt-12 flex flex-col items-center">
                                    <?php if ($user->getAvatarUrl()): ?>
                                        <img src="<?= htmlspecialchars($user->getAvatarUrl()) ?>" alt="<?= htmlspecialchars($user->getFullName()) ?>" class="w-24 h-24 rounded-full object-cover border-4 border-white dark:border-zinc-900 shadow-xl mb-4" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500/80 to-emerald-500/80 border-4 border-white dark:border-zinc-900 shadow-xl mb-4 items-center justify-center text-3xl font-bold text-white" style="display:none">
                                            <?= htmlspecialchars($user->getInitials()) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500/80 to-emerald-500/80 border-4 border-white dark:border-zinc-900 shadow-xl mb-4 flex items-center justify-center text-3xl font-bold text-white">
                                            <?= htmlspecialchars($user->getInitials()) ?>
                                        </div>
                                    <?php endif; ?>

                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 text-center">
                                        <?= htmlspecialchars($user->getFullName()) ?>
                                    </h3>
                                    <?php if ($user->getTitle()): ?>
                                        <p class="text-sm text-zinc-500 text-center mt-0.5"><?= htmlspecialchars($user->getTitle()) ?></p>
                                    <?php endif; ?>

                                    <div class="flex items-center gap-2 mt-3">
                                        <?php
                                        $roleColors = ['admin'=>'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400','manager'=>'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400','user'=>'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300'];
                                        $role = strtolower($user->getRole() ?: 'user');
                                        ?>
                                        <span class="capitalize px-2.5 py-1 rounded-md text-xs font-semibold <?= $roleColors[$role] ?? $roleColors['user'] ?>">
                                            <?= htmlspecialchars($user->getRole() ?: 'User') ?>
                                        </span>
                                        <?php if ((int)$user->getStatus() === 1): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactive
                                            </span>
                                        <?php endif; ?>
                                        <?php if ((int)$user->getIsBlocked()): ?>
                                            <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400">BLOCKED</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($user->getBadge()): ?>
                                        <div class="mt-3 px-3 py-1.5 bg-gradient-to-r from-amber-500/10 to-yellow-500/10 border border-amber-500/20 rounded-full text-xs font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                            <i data-lucide="award" class="w-3 h-3"></i> <?= htmlspecialchars($user->getBadge()) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Block/Unblock -->
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border transition-colors <?= (int)$user->getIsBlocked() ? 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20' : 'border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20' ?> font-medium flex items-center gap-1.5">
                                            <i data-lucide="<?= (int)$user->getIsBlocked() ? 'unlock' : 'lock' ?>" class="w-3 h-3"></i>
                                            <?= (int)$user->getIsBlocked() ? 'Unblock User' : 'Block User' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- XP & Level Card -->
                            <div class="card-3d bg-gradient-to-br from-indigo-600 to-purple-700 rounded-xl p-6 text-white shadow-lg relative overflow-hidden fade-in-up">
                                <div class="relative z-10">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm border border-white/30">
                                            <i data-lucide="medal" class="w-6 h-6 text-yellow-300"></i>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium text-indigo-200 uppercase tracking-wider">Level <?= $level ?></p>
                                            <h3 class="text-2xl font-bold"><?= (int)$user->getXp() ?> XP</h3>
                                        </div>
                                    </div>
                                    <div class="w-full bg-white/20 rounded-full h-2">
                                        <div class="xp-bar-fill h-2 rounded-full" style="width: <?= $xpPercent ?>%"></div>
                                    </div>
                                    <p class="text-xs text-indigo-200 mt-2">Next level at <?= ($level * 100) ?> XP</p>
                                </div>
                                <div class="absolute -bottom-6 -right-6 text-white/10">
                                    <i data-lucide="star" class="w-32 h-32"></i>
                                </div>
                            </div>

                            <!-- Skills Tags -->
                            <?php if (!empty($skillsArr)): ?>
                            <div class="card-3d bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-6 backdrop-blur-sm shadow-sm fade-in-up">
                                <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-indigo-500"></i> Skills
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($skillsArr as $skill): ?>
                                        <span class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 rounded-lg text-xs font-medium border border-indigo-200 dark:border-indigo-800/50 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors cursor-default">
                                            <?= htmlspecialchars(trim($skill)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Details -->
                        <div class="lg:col-span-2 space-y-6">
                            <!-- Bio -->
                            <?php if ($user->getBio()): ?>
                            <div class="card-3d bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-6 backdrop-blur-sm shadow-sm fade-in-up">
                                <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-emerald-500"></i> Bio
                                </h4>
                                <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed"><?= nl2br(htmlspecialchars($user->getBio())) ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Profile Information -->
                            <div class="card-3d bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-6 backdrop-blur-sm shadow-sm fade-in-up">
                                <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mb-6 flex items-center gap-2">
                                    <i data-lucide="id-card" class="w-3.5 h-3.5 text-indigo-500"></i> Profile Information
                                </h4>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-8">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="user" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Full Name</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getFullName()) ?></dd>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="mail" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Email</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getEmail()) ?></dd>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="phone" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Phone</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getPhone() ?: '—') ?></dd>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="map-pin" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Country</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getCountry() ?: '—') ?></dd>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="calendar" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Joined</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getCreatedAt() ?? 'Unknown') ?></dd>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="clock" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Last Seen</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getLastSeen() ?? 'Never') ?></dd>
                                        </div>
                                    </div>
                                </dl>
                            </div>

                            <!-- Security / Face Recognition -->
                            <div class="card-3d bg-white dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-6 backdrop-blur-sm shadow-sm fade-in-up">
                                <h4 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider mb-6 flex items-center gap-2">
                                    <i data-lucide="shield" class="w-3.5 h-3.5 text-amber-500"></i> Security & Access
                                </h4>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-8">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="scan-face" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Face Recognition</dt>
                                            <dd class="text-sm font-semibold <?= (int)$user->getFaceEnrolled() ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500' ?>">
                                                <?= (int)$user->getFaceEnrolled() ? '✓ Enrolled' : '✗ Not enrolled' ?>
                                            </dd>
                                        </div>
                                    </div>
                                    <?php if ($user->getFaceEnrolledAt()): ?>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="calendar-check" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Enrolled At</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getFaceEnrolledAt()) ?></dd>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="ban" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Block Status</dt>
                                            <dd class="text-sm font-semibold <?= (int)$user->getIsBlocked() ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                                                <?= (int)$user->getIsBlocked() ? '🚫 Blocked' : '✓ Not blocked' ?>
                                            </dd>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex-shrink-0">
                                            <i data-lucide="refresh-cw" class="w-4 h-4 text-zinc-500"></i>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-zinc-500 mb-0.5">Last Updated</dt>
                                            <dd class="text-sm font-semibold text-zinc-900 dark:text-zinc-100"><?= htmlspecialchars($user->getUpdatedAt() ?? '—') ?></dd>
                                        </div>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" method="POST" action="user_list.php" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
    </form>

    <script>
        lucide.createIcons();

        // Sidebar
        const sidebar=document.getElementById('sidebar'),backdrop=document.getElementById('sidebarBackdrop');
        document.getElementById('sidebarToggle')?.addEventListener('click',()=>{sidebar.classList.add('open');backdrop.classList.add('active')});
        document.getElementById('sidebarClose')?.addEventListener('click',()=>{sidebar.classList.remove('open');backdrop.classList.remove('active')});
        backdrop?.addEventListener('click',()=>{sidebar.classList.remove('open');backdrop.classList.remove('active')});

        // Delete confirmation
        function confirmDelete() {
            Swal.fire({
                title: 'Delete this user?',
                html: 'Are you sure you want to delete <strong><?= htmlspecialchars(addslashes($user->getFullName())) ?></strong>? This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '🗑️ Yes, delete',
                cancelButtonText: 'Cancel',
                background: document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b',
            }).then((r) => { if (r.isConfirmed) document.getElementById('deleteForm').submit(); });
        }
    </script>
</body>
</html>
