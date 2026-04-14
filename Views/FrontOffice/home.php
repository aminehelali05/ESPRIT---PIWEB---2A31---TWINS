<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');

if (!UserController::isAuthenticated()) {
    $_SESSION['flash_error'] = 'Please sign in to access Home.';
    header('Location: auth.php');
    exit;
}

$currentUser = UserController::currentUser();
$firstName = trim((string) ($currentUser['first_name'] ?? 'Member'));
$lastName = trim((string) ($currentUser['last_name'] ?? ''));
$displayName = trim($firstName . ' ' . $lastName);
$displayName = $displayName !== '' ? $displayName : 'Member';
$displayEmail = (string) ($currentUser['email'] ?? 'member@diversity.is');
$isAdminSidebar = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$navInitials = strtoupper(substr($firstName, 0, 1) . substr($lastName !== '' ? $lastName : 'M', 0, 1));

$reputationScore = 84;
$level = 'Level 12';
$streak = 7;
$dailyXp = 25;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is Home — social discovery, opportunities, and daily collaboration engagement hub.">
  <title>Home — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
</head>
<body class="grid-dot-bg home-page-body">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="container">
      <a href="home.php" class="navbar-brand" aria-label="Diversity home">
        <span class="brand-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </span>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav" aria-hidden="true"></div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($navInitials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> My Profile</a>
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/><path d="M14 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6z"/><path d="M4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2z"/><path d="M14 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2z"/></svg> Dashboard</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <main class="home-hub" id="main-content" tabindex="-1">
    <div class="home-grid container">
      <aside class="home-left glass-card" aria-label="Quick profile and navigation">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($navInitials) ?></div>
          <div>
            <h4><?= htmlspecialchars($displayName) ?></h4>
            <p><?= htmlspecialchars($level) ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a class="active" href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg> Home Feed</a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg> Social</a>
          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg> Profile</a>
          <a href="jobOffer.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg> Job Offers</a>
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg> Projects & Collaborators</a>
          <a href="contracts.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg> Contracts</a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg> Challenges & Daily Quiz</a>
          <?php if ($isAdminSidebar): ?>
            <div style="margin: 12px 0 6px 12px; font-size: 0.7rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.08em;">BackOffice</div>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Dashboard</a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score">
            <span>Reputation</span>
            <strong><?= $reputationScore ?></strong>
          </div>
          <div class="mini-score">
            <span>Daily Streak 🔥</span>
            <strong><?= $streak ?> days</strong>
          </div>
        </div>
      </aside>

      <section class="home-main" aria-label="Home feed content">
        <article class="home-hero-card glass-card fade-in-section" aria-labelledby="home-hero-title">
          <div class="home-hero-left">
            <p class="hero-greeting">Good morning, <?= htmlspecialchars($firstName) ?> 👋</p>
            <h1 id="home-hero-title">Welcome back to your collaboration hub</h1>
            <p class="hero-sub">Stay connected, discover opportunities, and earn XP through daily collaboration moments.</p>
            <div class="hero-points" style="display:flex;gap:10px;flex-wrap:wrap;margin:8px 0 2px;">
              <span class="profile-tag" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/></svg>
                Smart Insights
              </span>
              <span class="profile-tag" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8l9-5 9 5-9 5-9-5z"/><path d="M21 12l-9 5-9-5"/></svg>
                Premium Collaboration
              </span>
              <span class="profile-tag" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Reputation Growth
              </span>
            </div>
            <div class="hero-cta-row">
              <a href="challenges.php" class="btn btn-primary btn-sm">Take Daily Quiz (+<?= (int) $dailyXp ?> XP)</a>
              <a href="../BackOffice/dashboardUser.php" class="btn btn-secondary btn-sm">Open Dashboard Insights</a>
            </div>
          </div>
          <div class="home-hero-right">
            <div class="reputation-ring" style="--progress: <?= (int) $reputationScore ?>;">
              <div class="ring-inner"><strong><?= (int) $reputationScore ?></strong><span>REP</span></div>
            </div>
            <p class="ring-label"><?= htmlspecialchars($level) ?> • <?= (int) $streak ?>-day streak</p>
          </div>
        </article>

        <article class="composer-card glass-card fade-in-section" aria-label="Post composer">
          <div class="composer-top">
            <div class="left-avatar small"><?= htmlspecialchars($navInitials) ?></div>
            <button class="composer-input" id="openComposerBtn">What’s happening in your projects today?</button>
          </div>
          <div class="composer-actions">
            <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg> Attach Project</button>
            <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg> Tag Skill</button>
            <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg> Share Certificate</button>
            <button class="primary" id="quickPublishBtn"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> Publish</button>
          </div>
        </article>

        <div class="feed-headline-row fade-in-section" role="region" aria-labelledby="main-feed-title">
          <h2 id="main-feed-title">Main Social Feed</h2>
          <div class="feed-filters">
            <button class="active">For You</button>
            <button>Projects</button>
            <button>Challenges</button>
            <button>Certificates</button>
          </div>
        </div>

        <div class="feed-list" id="feedList" aria-live="polite">
          <article class="feed-card glass-card fade-in-section">
            <div class="feed-user-row">
              <img src="https://api.dicebear.com/7.x/initials/svg?seed=Sarah+Kim" alt="Sarah Kim">
              <div>
                <h4>Sarah Kim <span class="rep-pill">REP 91</span></h4>
                <p>Community Manager • 22 min ago</p>
              </div>
            </div>
            <p class="feed-content">Just completed sprint retrospective for <strong>Project Atlas</strong>. We reduced delivery delays by <strong>31%</strong> and opened 2 new collaboration spots.</p>
            <div class="feed-cover" style="height:240px; background:linear-gradient(135deg, #a5b4fc, #818cf8); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; letter-spacing:1px;">Sprint Analytics Visualization</div>
            <div class="feed-meta"><span>124 likes</span><span>18 comments</span><span>9 shares</span></div>
            <div class="feed-actions">
              <button class="react-btn"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.514" /></svg> Like</button>
              <button class="comment-btn"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg> Comment</button>
              <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg> Share</button>
              <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg> Save</button>
              <button class="skill-boost"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" /></svg> Skill Boost</button>
            </div>
            <div class="comment-preview">Leo Santos: “Great execution. Interested to collaborate for next phase.”</div>
          </article>

          <article class="feed-card glass-card fade-in-section">
            <div class="feed-user-row">
              <img src="https://api.dicebear.com/7.x/initials/svg?seed=Marc+D" alt="Marc D">
              <div>
                <h4>Marc D. <span class="rep-pill">REP 76</span></h4>
                <p>Operations Lead • 1 hour ago</p>
              </div>
            </div>
            <p class="feed-content">Daily challenge result: our team reached <strong>#2</strong> on sustainability leaderboard. Looking for a front-end collaborator for the next challenge build.</p>
            <div class="post-tags"><span>#challenge</span><span>#frontend</span><span>#leadership</span></div>
            <div class="feed-meta"><span>89 likes</span><span>12 comments</span><span>5 shares</span></div>
            <div class="feed-actions">
              <button class="react-btn"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.514" /></svg> Like</button>
              <button class="comment-btn"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg> Comment</button>
              <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg> Share</button>
              <button><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg> Save</button>
              <button class="skill-boost"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" /></svg> Skill Boost</button>
            </div>
            <div class="comment-preview">Amina Bennett: “Count me in for collaboration.”</div>
          </article>

          <article class="activity-highlight glass-card fade-in-section">
            <h3>Recent Network Activity</h3>
            <ul>
              <li><strong>Sarah</strong> completed Project Atlas and earned <span>+120 REP</span></li>
              <li><strong>Leo</strong> shared a certificate in Product Strategy <span>+60 XP</span></li>
              <li><strong>Maya</strong> joined today’s challenge leaderboard <span>+35 XP</span></li>
            </ul>
            <a href="../BackOffice/dashboardUser.php">See all in Dashboard</a>
          </article>
        </div>
      </section>

      <aside class="home-right" aria-label="Right widgets">
        <article class="widget-card glass-card fade-in-section">
          <h3>People You May Know</h3>
          <div class="people-list">
            <div class="people-item"><img src="https://api.dicebear.com/7.x/initials/svg?seed=Lina+O" alt="Lina"><div><strong>Lina O.</strong><p>Product Lead • 4 mutual skills</p></div><button>Connect</button></div>
            <div class="people-item"><img src="https://api.dicebear.com/7.x/initials/svg?seed=Noah+Kim" alt="Noah"><div><strong>Noah Kim</strong><p>DevOps • 2 mutual connections</p></div><button>Connect</button></div>
            <div class="people-item"><img src="https://api.dicebear.com/7.x/initials/svg?seed=Rania+K" alt="Rania"><div><strong>Rania K.</strong><p>Community Builder • Skill match 87%</p></div><button>Connect</button></div>
          </div>
        </article>

        <article class="widget-card glass-card fade-in-section">
          <h3>Trending Collaborations</h3>
          <div class="project-mini-card">
            <img src="../../assets/images/projects/project-thumb.svg" alt="GreenOps project thumbnail" style="width:40px;height:40px;border-radius:10px;object-fit:cover;">
            <div><strong>GreenOps Hub</strong><p>Looking for collaborators • Skill match: UI/UX, JS</p><button>Join as Collaborator</button></div>
          </div>
          <div class="project-mini-card">
            <img src="../../assets/images/challenges/challenge-card.svg" alt="TalentBridge challenge thumbnail" style="width:40px;height:40px;border-radius:10px;object-fit:cover;">
            <div><strong>TalentBridge</strong><p>Open role: Front-end Engineer</p><button>Apply Role</button></div>
          </div>
        </article>

        <article class="widget-card glass-card fade-in-section">
          <h3>Challenges & Leaderboard</h3>
          <div class="challenge-progress">
            <div class="small-ring" style="--progress:72;"><span>72%</span></div>
            <div><strong>Eco Impact Sprint</strong><p>3 days left • Rank #6 in network</p></div>
          </div>
          <ol class="mini-leaderboard">
            <li><span>1</span> Sarah Kim <strong>1490 XP</strong></li>
            <li><span>2</span> Marc D. <strong>1380 XP</strong></li>
            <li><span>3</span> Amina Bennett <strong>1315 XP</strong></li>
          </ol>
          <a href="challenges.php" class="btn btn-secondary btn-sm widget-btn">Start Daily Quiz</a>
        </article>

        <article class="widget-card glass-card fade-in-section">
          <h3>Skill Spotlight</h3>
          <div class="skills-pill-list">
            <span>Prompt Engineering</span>
            <span>Team Leadership</span>
            <span>Frontend Architecture</span>
            <span>Data Storytelling</span>
          </div>
          <button class="btn btn-primary btn-sm widget-btn">Add Suggested Certificate (+40 XP)</button>
        </article>
      </aside>
    </div>
  </main>

  <button class="home-fab" id="homeFab" type="button" aria-label="Open quick actions" aria-controls="fabMenu" aria-expanded="false"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4" /></svg></button>
  <div class="fab-menu" id="fabMenu" aria-label="Quick actions menu">
    <a href="#"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg> Create Post</a>
    <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg> Create Project</a>
    <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg> Create Challenge</a>
  </div>

  <nav class="mobile-tabbar" aria-label="Mobile navigation">
    <a class="active" href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg><span>Home</span></a>
    <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg><span>Network</span></a>
    <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg><span>Projects</span></a>
    <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg><span>Challenges</span></a>
    <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg><span>Me</span></a>
  </nav>

  <div class="home-toast-stack" id="homeToastStack" aria-live="polite"></div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/home.js"></script>
</body>
</html>


