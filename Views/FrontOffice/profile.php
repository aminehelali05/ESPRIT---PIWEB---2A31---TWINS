<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
}

$controller = new UserController();
$userId = (int) $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
        $payload = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'bio' => trim($_POST['bio'] ?? ''),
                'title' => trim($_POST['title'] ?? ''),
                'skills' => trim($_POST['skills'] ?? ''),
                'avatar_url' => trim($_POST['avatar_url'] ?? ''),
        ];

        if ($payload['first_name'] === '' || $payload['last_name'] === '' || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please fill valid name and email fields.';
        } else {
                if ($controller->updateProfile($userId, $payload)) {
                        $_SESSION['user_name'] = trim($payload['first_name'] . ' ' . $payload['last_name']);
                        $_SESSION['user_email'] = $payload['email'];
                        $message = 'Profile updated successfully.';
                } else {
                        $error = 'Could not save profile changes.';
                }
        }
}

$user = $controller->getUserById($userId);
if (!$user) {
        header('Location: login.php?logout=1');
        exit;
}

$skills = $user->getSkillsArray();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Voices of Peace</title>
    <link rel="stylesheet" href="../assets/css/user-module.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="um-page">
        <header class="um-topbar">
            <a class="um-brand" href="dashboard.php"><span class="um-brand-dot">VoP</span> Voices of Peace</a>
            <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
        </header>

        <?php if ($error): ?><div class="um-alert um-alert-danger" style="max-width:1200px;margin:0 auto 10px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="um-alert um-alert-success" style="max-width:1200px;margin:0 auto 10px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="um-profile-grid">
            <section class="um-card um-profile-head tilt-hover">
                <img class="um-avatar" src="<?= htmlspecialchars($user->getAvatarUrl() ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($user->getFirstName())) ?>" alt="avatar">
                <div>
                    <h1 style="margin:0;"><?= htmlspecialchars($user->getFullName()) ?></h1>
                    <p style="margin:6px 0; color:var(--text-muted);"><?= htmlspecialchars($user->getTitle() ?: 'Community Builder') ?> · <?= htmlspecialchars($user->getEmail()) ?></p>
                    <div class="um-actions">
                        <span class="um-pill"><?= htmlspecialchars($user->getCountry() ?: 'Global') ?></span>
                        <span class="um-pill"><?= htmlspecialchars(ucfirst($user->getRole())) ?></span>
                    </div>
                </div>
                <div class="um-actions">
                    <a class="um-btn um-btn-soft" href="dashboard.php">Dashboard</a>
                    <a class="um-btn um-btn-soft" href="auth.php?logout=1">Sign Out</a>
                </div>
            </section>

            <section class="um-mini-grid">
                <article class="um-card um-mini-card tilt-hover"><h3 style="margin:0 0 8px;">Reputation</h3><div class="um-progress"><span data-animate-progress data-progress="<?= min(100, (int) max(10, $user->getXp() % 101)) ?>"></span></div><p style="color:var(--text-muted)">XP: <?= (int) $user->getXp() ?></p></article>
                <article class="um-card um-mini-card tilt-hover"><h3 style="margin:0 0 8px;">Activity</h3><div class="um-progress"><span data-animate-progress data-progress="78"></span></div><p style="color:var(--text-muted)">Last seen: <?= htmlspecialchars($user->getLastSeen() ?: 'Recently') ?></p></article>
            </section>

            <section class="um-card" style="padding:18px;">
                <h2 style="margin-top:0;">Skills and Projects</h2>
                <div class="um-actions" style="margin:10px 0 18px;">
                    <?php if ($skills): foreach ($skills as $skill): ?>
                        <span class="um-pill"><?= htmlspecialchars(trim($skill)) ?></span>
                    <?php endforeach; else: ?>
                        <span class="um-pill">No skills added yet</span>
                    <?php endif; ?>
                </div>
                <div class="um-mini-grid">
                    <article class="um-card um-mini-card"><strong>Peace Mapper</strong><p style="color:var(--text-muted)">Geo-tagged local aid resources project.</p></article>
                    <article class="um-card um-mini-card"><strong>Dialogue Hub</strong><p style="color:var(--text-muted)">Community mediation collaboration workflow.</p></article>
                </div>
            </section>

            <section class="um-card" style="padding:18px;">
                <h2 style="margin-top:0;">Edit Profile</h2>
                <form method="POST" class="um-form">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="um-grid-2">
                        <div><label>First Name</label><input class="um-input" type="text" name="first_name" value="<?= htmlspecialchars($user->getFirstName()) ?>" required></div>
                        <div><label>Last Name</label><input class="um-input" type="text" name="last_name" value="<?= htmlspecialchars($user->getLastName()) ?>" required></div>
                        <div><label>Email</label><input class="um-input" type="email" name="email" value="<?= htmlspecialchars($user->getEmail()) ?>" required></div>
                        <div><label>Phone</label><input class="um-input" type="text" name="phone" value="<?= htmlspecialchars($user->getPhone()) ?>"></div>
                        <div><label>Country</label><input class="um-input" type="text" name="country" value="<?= htmlspecialchars($user->getCountry()) ?>"></div>
                        <div><label>Title</label><input class="um-input" type="text" name="title" value="<?= htmlspecialchars($user->getTitle()) ?>"></div>
                        <div style="grid-column:1/-1;"><label>Skills (comma separated)</label><input class="um-input" type="text" name="skills" value="<?= htmlspecialchars($user->getSkills()) ?>"></div>
                        <div style="grid-column:1/-1;"><label>Avatar URL</label><input class="um-input" type="text" name="avatar_url" value="<?= htmlspecialchars($user->getAvatarUrl()) ?>"></div>
                        <div style="grid-column:1/-1;"><label>Bio</label><textarea class="um-textarea" name="bio" rows="4"><?= htmlspecialchars($user->getBio()) ?></textarea></div>
                    </div>
                    <button class="um-btn um-btn-primary" type="submit">Save Changes</button>
                </form>
            </section>
        </div>
    </div>

    <script src="../assets/js/user-module.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
<?php return; ?>

<?php
require_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
// Default user ID for display (active session simulation)
$userId = 2;
$user = $userController->getUserById($userId);

if (!$user) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="en" class="antialiased dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Voices of Peace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-container {
            z-index: 99999 !important;
        }

        body {
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Dark Mode Core Theme */
        .dark body {
            background-color: #09090b;
            background-image:
                radial-gradient(circle at 50% 0%, rgba(120, 119, 198, 0.1) 0%, transparent 60%),
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
        }

        /* Light Mode Core Theme */
        body:not(.dark) {
            background-color: #f8fafc;
            background-image:
                radial-gradient(circle at 50% 0%, rgba(120, 119, 198, 0.04) 0%, transparent 60%),
                linear-gradient(rgba(0, 0, 0, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
        }

        /* Glass Panel Toggle */
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            color: #111827;
            transition: all 0.3s ease;
        }

        .dark .glass-panel {
            background: rgba(20, 20, 22, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            color: inherit;
        }

        .glass-panel:hover {
            border-color: rgba(0, 0, 0, 0.12);
        }

        .dark .glass-panel:hover {
            border-color: rgba(255, 255, 255, 0.12);
        }

        /* Inputs */
        .input-field {
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #111827;
            transition: all 0.2s ease;
        }

        .dark .input-field {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e4e4e7;
        }

        .input-field:focus {
            outline: none;
            border-color: #AEE1F9;
            box-shadow: 0 0 0 1px rgba(174, 225, 249, 0.5);
        }

        .dark .input-field:focus {
            box-shadow: 0 0 0 1px rgba(174, 225, 249, 0.2);
        }

        /* Toggle Switches */
        .toggle-checkbox {
            appearance: none;
            display: none;
        }

        .toggle-label {
            position: relative;
            display: block;
            width: 36px;
            height: 20px;
            background-color: #e2e8f0;
            border-radius: 9999px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .dark .toggle-label {
            background-color: #27272a;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toggle-label::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 14px;
            height: 14px;
            background-color: #ffffff;
            border-radius: 50%;
            transition: transform 0.3s ease, background-color 0.3s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .dark .toggle-label::after {
            background-color: #71717a;
            box-shadow: none;
        }

        .toggle-checkbox:checked+.toggle-label {
            background-color: rgba(174, 225, 249, 0.5);
            border-color: rgba(174, 225, 249, 0.8);
        }

        .dark .toggle-checkbox:checked+.toggle-label {
            background-color: rgba(174, 225, 249, 0.15);
            border-color: rgba(174, 225, 249, 0.3);
        }

        .toggle-checkbox:checked+.toggle-label::after {
            transform: translateX(16px);
            background-color: #0ea5e9;
        }

        .dark .toggle-checkbox:checked+.toggle-label::after {
            background-color: #AEE1F9;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #27272a;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: #3f3f46;
        }
    </style>
</head>

<body class="text-zinc-600 dark:text-zinc-300 min-h-screen selection:bg-[#AEE1F9] selection:text-zinc-900 transition-colors duration-300">

    <!-- Navbar -->
    <nav class="fixed top-0 w-full z-50 border-b border-black/5 dark:border-white/5 bg-white/80 dark:bg-[#09090b]/80 backdrop-blur-md transition-colors">
        <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2 group cursor-default">
                <div class="w-6 h-6 rounded bg-gradient-to-tr from-[#AEE1F9] to-[#C3E6CB] flex items-center justify-center text-zinc-900 shadow-lg shadow-cyan-500/20">
                    <span class="font-bold text-xs tracking-tighter">VoP</span>
                </div>
                <span class="font-semibold text-sm tracking-tight text-zinc-900 dark:text-zinc-100 group-hover:text-zinc-600 dark:group-hover:text-white transition-colors">Voices of <span class="text-zinc-400 dark:text-zinc-500">Peace</span></span>
            </div>
            
            <div class="flex gap-4 items-center">
                <button onclick="document.documentElement.classList.toggle('dark')" class="p-2 text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                    <i data-lucide="moon" class="block dark:hidden w-4 h-4"></i>
                    <i data-lucide="sun" class="hidden dark:block w-4 h-4"></i>
                </button>
                <a href="../vue/home.html" class="text-xs font-medium text-zinc-600 dark:text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200 transition-colors flex items-center gap-1.5">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i>
                    <span>Back to Hub</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-20 px-6">
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Profile Header -->
            <div class="glass-panel rounded-2xl p-1 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-96 h-96 bg-[#AEE1F9] opacity-[0.08] dark:opacity-[0.03] blur-[80px] rounded-full pointer-events-none -translate-y-1/2 translate-x-1/2"></div>

                <div class="bg-black/5 dark:bg-zinc-900/40 rounded-xl p-6 md:p-8 relative z-10 transition-colors">
                    <div class="flex flex-col md:flex-row gap-8 items-start md:items-center">

                        <!-- Avatar Section -->
                        <div class="relative group shrink-0 mx-auto md:mx-0">
                            <div class="w-24 h-24 md:w-28 md:h-28 rounded-2xl overflow-hidden ring-1 ring-black/10 dark:ring-white/10 shadow-2xl relative bg-zinc-200 dark:bg-zinc-800">
                                <img id="profileAvatar" src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= htmlspecialchars($user->getFirstName()) ?>" alt="Profile" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            </div>
                            <div class="absolute -bottom-2 -right-2 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 p-1.5 rounded-lg shadow-sm" title="Biometric ID Verified">
                                <div class="text-[#059669] dark:text-[#C3E6CB]">
                                    <i data-lucide="scan-face" class="w-4 h-4"></i>
                                </div>
                            </div>

                            <button id="uploadAvatarBtn" class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-2xl cursor-pointer backdrop-blur-[2px]">
                                <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                            </button>
                            <input type="file" id="avatarInput" accept="image/*" class="hidden">

                            <button id="randomAvatarBtn" class="absolute -top-2 -right-2 bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 p-1.5 rounded-full shadow-lg transition-colors" title="Generate Random Avatar">
                                <i data-lucide="dices" class="w-3.5 h-3.5 text-zinc-600 dark:text-zinc-300"></i>
                            </button>
                        </div>

                        <!-- Info Section -->
                        <div class="flex-1 text-center md:text-left space-y-3">
                            <div>
                                <div class="flex items-center justify-center md:justify-start gap-3">
                                    <h1 id="profileName" class="text-2xl md:text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                                        <?= htmlspecialchars($user->getFullName()) ?>
                                    </h1>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-200 border border-amber-500/20 uppercase tracking-wider items-center gap-1">
                                        Legend
                                    </span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start gap-4 mt-1 text-xs text-zinc-500 font-medium">
                                    <span class="flex items-center gap-1 text-zinc-600 dark:text-zinc-400">
                                        <i data-lucide="mail" class="w-3 h-3"></i>
                                        <span id="displayEmail"><?= htmlspecialchars($user->getEmail()) ?></span>
                                    </span>
                                    <span class="w-1 h-1 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="phone" class="w-3 h-3"></i>
                                        <?= htmlspecialchars($user->getPhoneNumber() ?? 'No Phone Set') ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap justify-center md:justify-start gap-2">
                                <span class="px-2.5 py-1 rounded-md bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5 text-[11px] font-medium text-zinc-700 dark:text-zinc-300">Community Builder</span>
                                <span class="px-2.5 py-1 rounded-md bg-[#0ea5e9]/10 border border-[#0ea5e9]/20 text-[11px] font-medium text-[#0ea5e9] dark:text-[#AEE1F9]">Peace Maker</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 w-full md:w-auto justify-center">
                            <button id="editToggleBtn" class="h-9 px-4 rounded-lg bg-zinc-900 dark:bg-white text-white dark:text-zinc-950 text-xs font-semibold hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors shadow-lg flex items-center gap-2">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Edit Details
                            </button>
                            <button id="exportDataBtn" class="h-9 w-9 flex items-center justify-center rounded-lg border border-black/10 dark:border-white/10 text-zinc-600 dark:text-zinc-400 hover:bg-black/5 dark:hover:bg-white/5 transition-colors" title="Export Data">
                                <i data-lucide="download" class="w-4 h-4"></i>
                            </button>
                            <a href="../auth/login.html" class="h-9 w-9 flex items-center justify-center rounded-lg border border-red-500/20 text-red-500 dark:text-red-400 hover:bg-red-500/10 transition-colors" title="Sign Out">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left Column (4/12) -->
                <div class="lg:col-span-4 space-y-6">

                    <!-- Stats Card -->
                    <div class="glass-panel p-5 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Engagement</h3>
                            <button class="text-[10px] text-[#0ea5e9] dark:text-[#AEE1F9] hover:underline">View All</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-3 rounded-lg bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.07] transition-colors cursor-pointer group">
                                <div class="text-zinc-500 mb-1 group-hover:text-[#0ea5e9] dark:group-hover:text-[#AEE1F9] transition-colors"><i data-lucide="heart-handshake" class="w-4 h-4"></i></div>
                                <div class="text-xl font-semibold text-zinc-900 dark:text-white tracking-tight">12</div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Actions Joined</div>
                            </div>
                            <div class="p-3 rounded-lg bg-black/5 dark:bg-white/5 border border-black/5 dark:border-white/5 hover:bg-black/10 dark:hover:bg-white/[0.07] transition-colors cursor-pointer group">
                                <div class="text-zinc-500 mb-1 group-hover:text-[#059669] dark:group-hover:text-[#C3E6CB] transition-colors"><i data-lucide="package" class="w-4 h-4"></i></div>
                                <div class="text-xl font-semibold text-zinc-900 dark:text-white tracking-tight">8</div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Resources Shared</div>
                            </div>
                        </div>
                    </div>

                    <!-- Skills & Expertise -->
                    <div class="glass-panel p-5 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Skills</h3>
                            <button class="text-zinc-500 hover:text-zinc-900 dark:hover:text-white"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-2 py-1 rounded text-[11px] bg-zinc-200 text-zinc-700 border border-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700 flex items-center gap-1">
                                Conflict Resolution <button class="hover:text-red-500"><i data-lucide="x" class="w-2 h-2"></i></button>
                            </span>
                            <span class="px-2 py-1 rounded text-[11px] bg-zinc-200 text-zinc-700 border border-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700 flex items-center gap-1">
                                Translation <button class="hover:text-red-500"><i data-lucide="x" class="w-2 h-2"></i></button>
                            </span>
                            <span class="px-2 py-1 rounded text-[11px] bg-zinc-200 text-zinc-700 border border-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700 flex items-center gap-1">
                                Logistics <button class="hover:text-red-500"><i data-lucide="x" class="w-2 h-2"></i></button>
                            </span>
                        </div>
                        <div class="mt-4 pt-4 border-t border-black/10 dark:border-white/5">
                            <input type="text" placeholder="Add a skill..." class="w-full bg-transparent text-[11px] text-zinc-900 dark:text-zinc-300 focus:outline-none placeholder:text-zinc-400 dark:placeholder:text-zinc-600">
                        </div>
                    </div>

                    <!-- Connected Accounts -->
                    <div class="glass-panel p-5 rounded-xl">
                        <h3 class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-4">Connections</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded bg-[#5865F2]/10 flex items-center justify-center text-[#5865F2]">
                                        <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Discord</span>
                                        <span class="text-[10px] text-zinc-500">Connected</span>
                                    </div>
                                </div>
                                <div class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.4)]"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded bg-black/5 dark:bg-white/5 flex items-center justify-center text-zinc-500 dark:text-zinc-400">
                                        <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Wallet</span>
                                        <span class="text-[10px] text-zinc-500">Not connected</span>
                                    </div>
                                </div>
                                <button class="text-[10px] px-2 py-1 rounded bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 text-zinc-600 dark:text-zinc-400 transition-colors">Connect</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (8/12) -->
                <div class="lg:col-span-8 space-y-6">

                    <!-- AI Insight Card -->
                    <div class="glass-panel p-6 rounded-xl relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-gradient-to-br from-[#AEE1F9] to-[#C3E6CB] opacity-30 dark:opacity-10 blur-[40px] group-hover:opacity-40 dark:group-hover:opacity-20 transition-opacity duration-700"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2 text-[#0ea5e9] dark:text-[#AEE1F9]">
                                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                                    <span class="text-xs font-semibold tracking-wide">AI IMPACT ANALYSIS</span>
                                </div>
                                <span class="text-[10px] text-zinc-500">Updated 2h ago</span>
                            </div>
                            <h3 class="text-lg font-medium text-zinc-900 dark:text-white mb-2 tracking-tight">Community Catalyst</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed max-w-lg mb-6">
                                Your collaboration score is trending up. You've impacted <strong class="text-zinc-900 dark:text-zinc-200">3 local communities</strong> recently.
                            </p>
                            <div class="space-y-2">
                                <div class="flex justify-between text-[10px] font-medium text-zinc-500 uppercase tracking-wider">
                                    <span>Impact Score</span>
                                    <span>Top 15%</span>
                                </div>
                                <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-[#0ea5e9] via-[#10b981] to-[#0ea5e9] dark:from-[#AEE1F9] dark:via-[#C3E6CB] dark:to-[#AEE1F9] w-[85%] rounded-full animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive Tabs -->
                    <div class="border-b border-black/10 dark:border-white/5">
                        <div class="flex gap-6">
                            <button class="pb-3 text-sm font-medium text-zinc-900 dark:text-white border-b-2 border-[#0ea5e9] dark:border-[#AEE1F9]">Settings</button>
                            <button class="pb-3 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-300 transition-colors">History</button>
                            <button class="pb-3 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-300 transition-colors">Security</button>
                        </div>
                    </div>

                    <!-- Settings Controls -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Notifications -->
                        <div class="glass-panel p-5 rounded-xl flex flex-col justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-1 flex items-center gap-2">
                                    <i data-lucide="bell" class="w-4 h-4 text-zinc-500"></i> Notifications
                                </h4>
                                <p class="text-[11px] text-zinc-500 mb-4">Manage how you receive updates.</p>

                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">Weekly Digest</span>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="toggle1" class="toggle-checkbox" checked>
                                            <label for="toggle1" class="toggle-label"></label>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">Project Alerts</span>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="toggle2" class="toggle-checkbox" checked>
                                            <label for="toggle2" class="toggle-label"></label>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">Marketing Emails</span>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="toggle3" class="toggle-checkbox">
                                            <label for="toggle3" class="toggle-label"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Privacy -->
                        <div class="glass-panel p-5 rounded-xl flex flex-col justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-1 flex items-center gap-2">
                                    <i data-lucide="shield" class="w-4 h-4 text-zinc-500"></i> Privacy
                                </h4>
                                <p class="text-[11px] text-zinc-500 mb-4">Control your data visibility.</p>

                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">Public Profile</span>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="toggle4" class="toggle-checkbox" checked>
                                            <label for="toggle4" class="toggle-label"></label>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">Share Activity Status</span>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="toggle5" class="toggle-checkbox">
                                            <label for="toggle5" class="toggle-label"></label>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300">Allow AI Analysis</span>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="toggle6" class="toggle-checkbox" checked>
                                            <label for="toggle6" class="toggle-label"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form (Overlay) -->
                    <div id="editFormSection" class="glass-panel p-6 rounded-xl hidden border border-yellow-500/10 bg-white/95 dark:bg-zinc-900/95 fixed inset-0 z-[60] m-auto max-w-lg h-fit shadow-2xl">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Edit Profile Details</h3>
                            <button id="closeEditBtn" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-white">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <form id="profileForm" class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">First Name</label>
                                    <input type="text" id="editFirstName" value="<?= htmlspecialchars($user->getFirstName()) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="First name">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Last Name</label>
                                    <input type="text" id="editLastName" value="<?= htmlspecialchars($user->getLastName()) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="Last name">
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Email</label>
                                <input type="email" id="editEmail" value="<?= htmlspecialchars($user->getEmail()) ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="user@example.com">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-[10px] font-medium text-zinc-500 uppercase tracking-wider">Phone</label>
                                <input type="text" id="editPhone" value="<?= htmlspecialchars($user->getPhoneNumber() ?? '') ?>" class="input-field w-full px-3 py-2 rounded-lg text-sm" placeholder="+1 234 567 8900">
                            </div>

                            <div class="pt-2 flex justify-end gap-2">
                                <button type="button" id="cancelEditBtn" class="px-4 py-2 border border-black/10 dark:border-white/10 text-zinc-700 dark:text-zinc-300 text-xs font-semibold rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition-colors">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Required Scripts -->
    <script>
        lucide.createIcons();

        const editToggleBtn = document.getElementById('editToggleBtn');
        const closeEditBtn = document.getElementById('closeEditBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        const editFormSection = document.getElementById('editFormSection');

        function toggleModal() {
            editFormSection.classList.toggle('hidden');
        }

        editToggleBtn.addEventListener('click', toggleModal);
        closeEditBtn.addEventListener('click', toggleModal);
        cancelEditBtn.addEventListener('click', toggleModal);

        window.addEventListener('click', (e) => {
            if (e.target === editFormSection) {
                toggleModal();
            }
        });
    </script>
</body>
</html>
