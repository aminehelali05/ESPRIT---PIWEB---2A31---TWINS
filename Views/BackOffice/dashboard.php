<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/AuthController.php');

if (!AuthController::isAuthenticated()) {
    $_SESSION['flash_error'] = 'Please sign in to access the dashboard.';
    header('Location: ../FrontOffice/auth.php?mode=login');
    exit;
}

$user = AuthController::currentUser() ?? [];
$displayName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$displayName = $displayName !== '' ? $displayName : (string) ($user['username'] ?? 'Member User');
$displayEmail = (string) ($user['email'] ?? '');
$initials = strtoupper(substr((string) ($user['first_name'] ?? 'M'), 0, 1) . substr((string) ($user['last_name'] ?? 'U'), 0, 1));

// Premium, Minimal SVGs for Modules
$moduleShortcuts = [
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'label' => 'Users CRUD', 'kpi' => '132 Total', 'desc' => 'Manage accounts, roles, & access levels.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>', 
        'label' => 'Social feed', 'kpi' => '24 New', 'desc' => 'Moderate discussions and comments.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>', 
        'label' => 'Skills & Certs', 'kpi' => '12 Pending', 'desc' => 'Review and validate user skill sets.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>', 
        'label' => 'Projects', 'kpi' => '7 Active', 'desc' => 'Monitor platform-wide collaborations.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>', 
        'label' => 'Reviews', 'kpi' => '4.9 Avg', 'desc' => 'Analyze reputation metrics & feedback.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>', 
        'label' => 'Challenges', 'kpi' => 'Daily Live', 'desc' => 'Configure daily quizzes and sprints.'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VoP Backend Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body data-current-user-id="<?= (int) ($user['id'] ?? 0) ?>">
    <div class="app-container">
        
        <!-- Elegant Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">VoP</div>
                <div class="brand-text">Voices Of Peace</div>
            </div>

            <nav class="nav-menu">
                <a href="#overview" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Overview
                </a>
                <a href="#users" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    User
                </a>
                <a href="#modules" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    All Modules
                </a>
                <a href="#settings" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings
                </a>
                <a href="../FrontOffice/home.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" x2="19" y1="12" y2="12"></line><polyline points="12 5 5 12 12 19"></polyline></svg>
                    Exit Backend
                </a>
            </nav>

            <div class="user-widget">
                <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($displayName) ?></strong>
                    <span>System Admin</span>
                </div>
                <a href="../../index.php?action=logout" class="logout-icon" aria-label="Sign out">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                </a>
            </div>
        </aside>

        <!-- Main Content View -->
        <main class="main-content">
            
            <header class="top-header animate-enter">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Mon, April 7 — Welcome to your workspace.</p>
                </div>
                <div class="header-actions">
                    <div class="search-bar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search system...">
                    </div>
                </div>
            </header>

            <div class="bento-grid">
                
                <!-- KPI 1 -->
                <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.05s;">
                    <div>
                        <div class="kpi-h">Total Active Users</div>
                        <div class="kpi-v" id="kpiTotalUsers" data-counter="0">0</div>
                        <div class="kpi-badge">+12.5% this month</div>
                    </div>
                    <div class="icon-box icon-blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>

                <!-- KPI 2 -->
                <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.08s;">
                    <div>
                        <div class="kpi-h">Platform Reputation</div>
                        <div class="kpi-v" id="kpiAdmins" data-counter="0">0</div>
                        <div class="kpi-badge" style="color: #6366f1; background: rgba(99,102,241,0.1);">Stable growth</div>
                    </div>
                    <div class="icon-box icon-indigo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                </div>

                <!-- KPI 3 -->
                <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.11s;">
                    <div>
                        <div class="kpi-h">Completed Projects</div>
                        <div class="kpi-v" id="kpiNewThisMonth" data-counter="0">0</div>
                        <div class="kpi-badge" style="color: #8b5cf6; background: rgba(139,92,246,0.1);">+4 this week</div>
                    </div>
                    <div class="icon-box icon-purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                </div>

                <!-- Main User Table -->
                <div class="card span-8 animate-enter" style="animation-delay: 0.15s;" id="users">
                    <div class="section-head">
                        <h2>Users Management</h2>
                        <div class="section-actions">
                            <button class="btn-primary" id="addUserBtn" style="padding: 6px 14px; font-size: 0.8rem;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                                Add User
                            </button>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="elegant-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Feed side column -->
                <div class="card span-4 animate-enter" style="animation-delay: 0.18s;">
                    <div class="section-head">
                        <h2>Activity Feed</h2>
                    </div>
                    <div class="feed-list">
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                            <div class="f-content"><p>Skill Verified</p><span>Sarah approved "Full-Stack Dev"</span></div>
                            <div class="f-time">2m</div>
                        </div>
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg></div>
                            <div class="f-content"><p>Report Filed</p><span>Automated system flagged comment</span></div>
                            <div class="f-time">1h</div>
                        </div>
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                            <div class="f-content"><p>New Enrollment</p><span>Alex joined the workspace</span></div>
                            <div class="f-time">3h</div>
                        </div>
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></div>
                            <div class="f-content"><p>Global Post</p><span>Admin published an announcement</span></div>
                            <div class="f-time">5h</div>
                        </div>
                    </div>
                </div>

                <!-- Module Access Grid -->
                <div class="card span-12 animate-enter" style="animation-delay: 0.2s;" id="modules">
                    <div class="section-head">
                        <h2>System Modules</h2>
                        <span style="font-size:0.8rem; color:var(--b-text-muted);">Quick access to all CRUD panels</span>
                    </div>
                    <div class="module-list" style="grid-template-columns: repeat(3, 1fr);">
                        <?php foreach ($moduleShortcuts as $mod): ?>
                            <a href="#" class="dash-module">
                                <div class="dm-head">
                                    <div class="dm-icon"><?= $mod['svg'] ?></div>
                                    <span class="dm-kpi"><?= $mod['kpi'] ?></span>
                                </div>
                                <div>
                                    <h3 class="dm-title"><?= $mod['label'] ?></h3>
                                    <p class="dm-desc"><?= $mod['desc'] ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div class="db-modal-overlay" id="userModal">
        <div class="db-modal-card db-modal-card-compact">
            <div class="db-modal-head">
                <h3 id="userModalTitle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8"></path><path d="M12 8v8"></path></svg> Create User</h3>
                <button type="button" id="closeUserModal" class="db-icon-btn" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
                </button>
            </div>
            <form id="userForm" class="db-modal-grid">
                <input type="hidden" id="formId" name="id">
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="8" r="5"></circle></svg>First Name</label><input id="formFirstName" name="first_name"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="8" r="5"></circle></svg>Last Name</label><input id="formLastName" name="last_name"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"></path><path d="M4 7l8 6 8-6"></path></svg>Email</label><input id="formEmail" name="email" type="email"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>Password</label><input id="formPassword" name="password" type="password" placeholder="Required for create"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72l.45 3a2 2 0 0 1-.57 1.74L7.9 9.1a16 16 0 0 0 7 7l.64-1.09a2 2 0 0 1 1.74-.57l3 .45A2 2 0 0 1 22 16.92z"></path></svg>Phone</label><input id="formPhone" name="phone"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"></path></svg>Role</label>
                    <select id="formRole" name="role">
                        <option value="user">user</option>
                        <option value="manager">manager</option>
                        <option value="admin">admin</option>
                        <option value="member">member</option>
                    </select>
                </div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4"></path></svg>Status</label>
                    <select id="formStatus" name="status">
                        <option value="1">active</option>
                        <option value="0">offline</option>
                    </select>
                </div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>Is Blocked</label>
                    <select id="formIsBlocked" name="is_blocked">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="12" cy="11" r="3"></circle><path d="M8 18c1-2 2.5-3 4-3s3 1 4 3"></path></svg>Avatar URL</label><input id="formAvatarUrl" name="avatar_url"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 .9-4.5 4.3L17.6 20 12 17l-5.6 3 1.1-6.8L3 8.9 9 8z"></path></svg>Badge</label><input id="formBadge" name="badge"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11h18"></path><path d="M12 2a15.3 15.3 0 0 1 4 9 15.3 15.3 0 0 1-4 11 15.3 15.3 0 0 1-4-11 15.3 15.3 0 0 1 4-9z"></path><circle cx="12" cy="12" r="10"></circle></svg>Country</label><input id="formCountry" name="country"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"></path></svg>Title</label><input id="formTitle" name="title"></div>
                <div class="db-field db-field-full db-map-tools">
                    <label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M3 12h18"></path><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>Globe Explorer</label>
                    <div class="db-map-actions">
                        <button type="button" class="db-btn db-btn-ghost" id="openGlobeBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M3 12h18"></path></svg>Open Globe Explorer</button>
                        <button type="button" class="db-btn db-btn-ghost" id="openGlobe3DBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline><polyline points="7.5 19.79 7.5 14.6 3 12"></polyline><polyline points="21 12 16.5 14.6 16.5 19.79"></polyline><polyline points="12 22.08 12 16.8"></polyline><polyline points="12 16.8 16.5 14.6"></polyline><polyline points="12 16.8 7.5 14.6"></polyline></svg>Choose from 3D Globe</button>
                        <button type="button" class="db-btn db-btn-primary" id="applyGlobeLocationBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>Use Picked Location</button>
                    </div>
                    <input id="formMapAddress" class="db-map-address" placeholder="Selected location will appear here" readonly>
                    <input id="formLatitude" type="hidden">
                    <input id="formLongitude" type="hidden">
                </div>
                <div class="db-field db-field-full db-globe-wrap" id="dbGlobeWrap">
                    <div id="userGlobeMap" class="db-globe-map"></div>
                </div>
                <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>Skills</label><input id="formSkills" name="skills"></div>
                <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>Bio</label><textarea id="formBio" name="bio" rows="2"></textarea></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"></path><path d="M17 5H9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8H7"></path></svg>XP</label><input id="formXp" name="xp" type="number" min="0" value="0"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h6v6H4z"></path><path d="M14 4h6v6h-6z"></path><path d="M4 14h6v6H4z"></path><path d="M14 14h6v6h-6z"></path></svg>Face Enrolled</label>
                    <select id="formFaceEnrolled" name="face_enrolled">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9 6 9-6"></path><path d="M21 17H3"></path><path d="M3 7h18v10H3z"></path></svg>Face Images Path</label><input id="formFaceImagesPath" name="face_images_path"></div>
                <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M7 12h10"></path><path d="M7 16h8"></path><path d="M7 8h10"></path></svg>Face Descriptor</label><textarea id="formFaceDescriptor" name="face_descriptor" rows="2"></textarea></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>Face Enrolled At</label><input id="formFaceEnrolledAt" name="face_enrolled_at" placeholder="Select date and time"></div>
                <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>Last Seen</label><input id="formLastSeen" name="last_seen" placeholder="Select date and time"></div>

                <div class="db-modal-actions">
                    <button type="button" class="db-btn db-btn-ghost" id="cancelUserModal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>Cancel</button>
                    <button type="submit" class="db-btn db-btn-primary" id="saveUserBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>Save User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Minimal JS execution -->
    <script src="../../assets/js/globe-explorer.js"></script>
    <script src="../../assets/js/globe-3d-picker.js"></script>
    <script src="../../assets/js/backoffice-dashboard.js"></script>
</body>
</html>
