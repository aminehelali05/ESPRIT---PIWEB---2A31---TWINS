<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include_once(__DIR__ . '/../../Controllers/AuthController.php');
include_once(__DIR__ . '/../../Controllers/UserController.php');

if (!AuthController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in to access your profile.';
  header('Location: auth.php');
  exit;
}

$sessionUser = AuthController::currentUser();
$userController = new UserController();
$userModel = $userController->getUserById((int) ($sessionUser['id'] ?? 0));

$displayFirstName = (string) ($userModel ? $userModel->getFirstName() : ($sessionUser['first_name'] ?? ''));
$displayLastName = (string) ($userModel ? $userModel->getLastName() : ($sessionUser['last_name'] ?? ''));
$displayName = trim($displayFirstName . ' ' . $displayLastName);
$displayName = $displayName !== '' ? $displayName : 'Member User';
$displayEmail = (string) ($userModel ? $userModel->getEmail() : ($sessionUser['email'] ?? ''));
$displayCountry = (string) ($userModel ? ($userModel->getCountry() ?? '') : '');
$displayTitle = (string) ($userModel ? ($userModel->getTitle() ?? '') : '');
$displayBio = (string) ($userModel ? ($userModel->getBio() ?? '') : '');
$displaySkills = (string) ($userModel ? ($userModel->getSkills() ?? '') : '');
$displayPhone = (string) ($userModel ? ($userModel->getPhone() ?? '') : '');
$displayAvatarUrl = (string) ($userModel ? ($userModel->getAvatarUrl() ?? '') : ($sessionUser['avatar_url'] ?? ''));
$displayRole = (string) ($userModel ? ($userModel->getRole() ?? 'user') : ($sessionUser['role'] ?? 'user'));
$displayStatus = (int) ($userModel ? ($userModel->getStatus() ?? 1) : 1);
$displayIsBlocked = (int) ($userModel ? ($userModel->getIsBlocked() ?? 0) : 0);
$displayBadge = (string) ($userModel ? ($userModel->getBadge() ?? '') : '');
$displayXp = (int) ($userModel ? ($userModel->getXp() ?? 0) : 0);
$displayFaceEnrolled = (int) ($userModel ? ($userModel->getFaceEnrolled() ?? 0) : 0);
$displayFaceImagesPath = (string) ($userModel ? ($userModel->getFaceImagesPath() ?? '') : '');
$displayFaceDescriptor = (string) ($userModel ? ($userModel->getFaceDescriptor() ?? '') : '');
$displayFaceEnrolledAt = (string) ($userModel ? ($userModel->getFaceEnrolledAt() ?? '') : '');
$displayLastSeen = (string) ($userModel ? ($userModel->getLastSeen() ?? '') : '');
$createdAt = (string) ($userModel ? ($userModel->getCreatedAt() ?? '') : '');
$joinedLabel = $createdAt !== '' ? date('M Y', strtotime($createdAt)) : 'Recently';
$initials = strtoupper(substr($displayFirstName ?: 'M', 0, 1) . substr($displayLastName ?: 'U', 0, 1));
$isOwner = true;
$reputationScore = 87;
$reputationGrowth = '+14% this month';
$profileLevel = 'Level 12 Collaborator';
$streakDays = 7;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Your professional profile on Diversity.is — manage your identity, skills, and activity.">
  <title>Profile — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/profile.css">
  <link rel="stylesheet" href="../../assets/css/profile-light.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body class="grid-dot-bg">
  <a class="skip-link" href="#profile-main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="container">
      <a href="home.php" class="navbar-brand" aria-label="Diversity home">
        <span class="brand-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </span>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Network</a>
        <a href="projects.php">Projects</a>
        <a href="challenges.php">Challenges</a>
        <a href="profile.php" class="active">Profile</a>
        <a href="../BackOffice/dashboard.php">Dashboard</a>
      </div>
      <div class="navbar-actions">
        <!-- Theme Toggle -->
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <!-- Profile Dropdown -->
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($initials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <main class="profile-main" id="profile-main-content" tabindex="-1">
    <div class="container">

      <!-- Profile Header Card -->
      <section class="profile-header glass-card fade-in-section" aria-labelledby="profile-name-heading">
        <div class="profile-cover-banner"></div>
        <div class="profile-header-glow"></div>
        <div class="profile-header-inner">
          <!-- Avatar -->
          <div class="profile-avatar-wrap">
            <div class="profile-avatar">
              <img src="<?= htmlspecialchars($displayAvatarUrl !== '' ? $displayAvatarUrl : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($displayName)) ?>" alt="Profile" id="profileAvatar">
              <button class="avatar-upload-btn" id="avatarUploadBtn" title="Change Photo">
                <i data-lucide="camera" class="w-5 h-5"></i>
              </button>
              <input type="file" id="avatarInput" accept="image/*" hidden>
            </div>
            <button class="avatar-random-btn" id="randomAvatarBtn" title="Random Avatar">
              <i data-lucide="dices" class="w-3.5 h-3.5"></i>
            </button>
            <div class="avatar-verified" title="Verified Account">
              <i data-lucide="badge-check" class="w-4 h-4"></i>
            </div>
          </div>

          <!-- Info -->
          <div class="profile-info">
            <div class="profile-info-top">
              <h1 class="profile-name" id="profile-name-heading"><?= htmlspecialchars($displayName) ?></h1>
              <span class="profile-badge badge-legend">
                <i data-lucide="crown" class="w-3 h-3"></i> Legend
              </span>
              <?php if ($isOwner): ?>
              <span class="profile-badge badge-owner" id="previewModeBadge">
                <i data-lucide="eye" class="w-3 h-3"></i> Owner View
              </span>
              <?php endif; ?>
            </div>
            <div class="profile-meta-row">
              <span class="profile-meta-item"><i data-lucide="mail" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($displayEmail) ?></span>
              <span class="profile-meta-dot"></span>
              <span class="profile-meta-item" id="profileLocationText"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($displayCountry !== '' ? $displayCountry : 'No country set') ?></span>
              <span class="profile-meta-dot"></span>
              <span class="profile-meta-item"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Joined <?= htmlspecialchars($joinedLabel) ?></span>
            </div>
            <div class="profile-tags">
              <span class="profile-tag" id="profileTitleTag"><i data-lucide="code-2" class="w-3 h-3"></i> <?= htmlspecialchars($displayTitle !== '' ? $displayTitle : 'Member') ?></span>
              <span class="profile-tag tag-accent"><i data-lucide="rocket" class="w-3 h-3"></i> Innovator</span>
              <span class="profile-tag tag-green"><i data-lucide="users" class="w-3 h-3"></i> Community Builder</span>
              <span class="profile-tag tag-open"><i data-lucide="badge-check" class="w-3 h-3"></i> Open to Collaborate</span>
            </div>
            <div class="profile-social-row">
              <a href="#" class="social-link-mini"><i data-lucide="linkedin" class="w-3.5 h-3.5"></i> LinkedIn</a>
              <a href="#" class="social-link-mini"><i data-lucide="github" class="w-3.5 h-3.5"></i> GitHub</a>
              <a href="#" class="social-link-mini"><i data-lucide="globe" class="w-3.5 h-3.5"></i> Portfolio</a>
            </div>
          </div>

          <div class="profile-reputation-core">
            <div class="profile-rep-ring" style="--progress: <?= (int) $reputationScore ?>;" aria-label="Reputation score <?= (int) $reputationScore ?> out of 100">
              <div class="profile-rep-inner">
                <strong><?= (int) $reputationScore ?></strong>
                <span>REP</span>
              </div>
            </div>
            <p class="rep-level"><?= htmlspecialchars($profileLevel) ?></p>
            <p class="rep-growth"><?= htmlspecialchars($reputationGrowth) ?> · 🔥 <?= (int) $streakDays ?> day streak</p>
          </div>

          <!-- Actions -->
          <div class="profile-actions">
            <button class="btn btn-primary btn-sm" id="editToggleBtn">
              <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Details
            </button>
            <button class="profile-action-icon" title="Export Data">
              <i data-lucide="download" class="w-4 h-4"></i>
            </button>
            <button class="profile-action-icon" id="shareProfileBtn" title="Share Profile">
              <i data-lucide="qr-code" class="w-4 h-4"></i>
            </button>
            <?php if ($isOwner): ?>
            <button class="profile-action-icon" id="togglePreviewBtn" title="Preview as public">
              <i data-lucide="switch-camera" class="w-4 h-4"></i>
            </button>
            <?php else: ?>
            <button class="btn btn-secondary btn-sm"><i data-lucide="send" class="w-3.5 h-3.5"></i> Message</button>
            <button class="btn btn-primary btn-sm"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Connect</button>
            <?php endif; ?>
            <button class="profile-action-icon action-danger" title="Sign Out">
              <i data-lucide="log-out" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
      </section>

      <div class="profile-top-grid">
        <section class="glass-card panel fade-in-section about-premium" id="about-section" aria-labelledby="about-positioning-title">
          <?php if ($isOwner): ?>
          <div class="owner-toolbar-floating">
            <button class="owner-tool" id="aiPolishBtn"><i data-lucide="sparkles" class="w-3.5 h-3.5"></i> AI Polish</button>
            <button class="owner-tool" id="editBioBtn"><i data-lucide="pencil-line" class="w-3.5 h-3.5"></i> Edit Bio</button>
            <button class="owner-tool" id="addVideoBtn"><i data-lucide="video" class="w-3.5 h-3.5"></i> Add Video</button>
          </div>
          <?php endif; ?>

          <div class="about-premium-grid">
            <div class="about-premium-left">
              <div class="panel-header about-header">
                <h3 class="panel-title" id="about-positioning-title">About & Positioning</h3>
                <span class="bio-rep-badge"><i data-lucide="medal" class="w-3.5 h-3.5"></i> Reputation Boosted</span>
              </div>

              <h2 class="about-hero-headline">Crafting scalable collaboration ecosystems with measurable impact.</h2>
              <p class="about-bio rich-bio" id="aboutBioText"><?= htmlspecialchars($displayBio !== '' ? $displayBio : 'No bio yet. Click Edit Details to add your biography.') ?></p>
              <button class="about-expand-btn" id="expandBioBtn"><i data-lucide="chevrons-down" class="w-3.5 h-3.5"></i> Expand Story</button>

              <div class="about-highlights premium-pills">
                <button class="about-pill" data-pill="experience" data-proof="Delivered 47 projects with high team retention."><i data-lucide="briefcase-business" class="w-3.5 h-3.5"></i><strong>Experience</strong><span>5+ years</span></button>
                <button class="about-pill" data-pill="passion" data-proof="Runs weekly knowledge circles to boost collective productivity."><i data-lucide="users" class="w-3.5 h-3.5"></i><strong>Passion</strong><span>Productive Teams</span></button>
                <button class="about-pill" data-pill="availability" data-proof="Open slots for 2 mentees this month."><i data-lucide="graduation-cap" class="w-3.5 h-3.5"></i><strong>Availability</strong><span>Open to Mentorship</span></button>
                <button class="about-pill" data-pill="focus" data-proof="Building AI-native collaboration UX patterns."><i data-lucide="sparkles" class="w-3.5 h-3.5"></i><strong>Current Focus</strong><span>AI + Collaboration UX</span></button>
              </div>

              <div class="about-actions">
                <?php if ($isOwner): ?>
                <button class="btn btn-gold btn-sm" id="requestReviewBtn"><i data-lucide="star" class="w-3.5 h-3.5"></i> Request Review <small>+80–150 REP estimated</small></button>
                <?php endif; ?>
                <button class="btn btn-secondary btn-sm" id="shareBioBtn"><i data-lucide="share-2" class="w-3.5 h-3.5"></i> Share Positioning</button>
              </div>
            </div>

            <div class="about-premium-right">
              <div class="pitch-video-card">
                <div class="video-header-row">
                  <h4>Pitch Video</h4>
                  <button class="video-mini-btn" id="pipVideoBtn"><i data-lucide="picture-in-picture-2" class="w-3.5 h-3.5"></i> PiP</button>
                </div>
                <div class="video-frame-wrap">
                  <video id="profilePitchVideo" class="pitch-video" poster="../../assets/images/heroes/profile-poster.svg" autoplay muted loop playsinline controls>
                    <source src="https://cdn.coverr.co/videos/coverr-programming-in-coffee-shop-1579/1080p.mp4" type="video/mp4">
                  </video>
                  <button class="video-overlay-play" id="videoOverlayPlay" aria-label="Play pitch video"><i data-lucide="play" class="w-5 h-5"></i></button>
                </div>
                <div class="video-meta-row">
                  <span><i data-lucide="clock-3" class="w-3 h-3"></i> 02:46</span>
                  <span><i data-lucide="list-video" class="w-3 h-3"></i> Chapters enabled</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <aside class="glass-card panel fade-in-section profile-insights-side premium-insights" aria-label="Profile insights">
          <div class="panel-header">
            <h3 class="panel-title">Visitor Insights</h3>
            <span class="insight-chip">Owner analytics</span>
          </div>

          <div class="insight-kpi-grid">
            <div class="insight-kpi">
              <span>Profile views today</span>
              <strong>29</strong>
              <em>↑12%</em>
            </div>
            <div class="insight-kpi">
              <span>Recruiter visits</span>
              <strong>6</strong>
              <em>Last 7 days</em>
            </div>
            <div class="insight-kpi">
              <span>Connection CTR</span>
              <strong>18%</strong>
              <em>Benchmark 9%</em>
            </div>
            <div class="insight-kpi">
              <span>Suggested edits</span>
              <strong>3</strong>
              <em>AI-powered</em>
            </div>
          </div>

          <div class="insight-spark premium-spark">
            <div class="spark-title">Reputation Growth · 30 days</div>
            <div class="spark-track spark-track-lg"><div class="spark-fill" id="repSparkFill"></div></div>
            <div class="spark-caption">+240 this month • Peak from project completion • Level up in 3 reviews</div>
          </div>

          <?php if ($isOwner): ?>
          <div class="suggested-edits-carousel" id="suggestedEditsCarousel">
            <div class="suggested-edit-card active" data-edit="cert">
              <h4>Add recent certificate</h4>
              <p>Show your latest verified achievement to improve trust and conversion.</p>
              <button class="apply-edit-btn" data-apply="certificate">Apply</button>
            </div>
            <div class="suggested-edit-card" data-edit="availability">
              <h4>Update availability</h4>
              <p>Switch availability to “open to collaborations” for better matching visibility.</p>
              <button class="apply-edit-btn" data-apply="availability">Apply</button>
            </div>
            <div class="suggested-edit-card" data-edit="project">
              <h4>Highlight AI project</h4>
              <p>Pin your strongest AI collaboration project in the top portfolio area.</p>
              <button class="apply-edit-btn" data-apply="project">Apply</button>
            </div>
          </div>
          <div class="carousel-controls">
            <button id="editPrevBtn"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
            <button id="editNextBtn"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
          </div>
          <?php endif; ?>
        </aside>
      </div>

      <!-- Content Grid -->
      <div class="profile-grid">

        <!-- Left Column -->
        <div class="profile-left">

          <!-- Engagement Stats -->
          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Engagement</h3>
              <button class="panel-action"><i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i></button>
            </div>
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-icon stat-icon-indigo"><i data-lucide="zap" class="w-4 h-4"></i></div>
                <div class="stat-value">47</div>
                <div class="stat-label">Projects</div>
              </div>
              <div class="stat-item">
                <div class="stat-icon stat-icon-cyan"><i data-lucide="git-pull-request" class="w-4 h-4"></i></div>
                <div class="stat-value">189</div>
                <div class="stat-label">Contributions</div>
              </div>
              <div class="stat-item">
                <div class="stat-icon stat-icon-emerald"><i data-lucide="star" class="w-4 h-4"></i></div>
                <div class="stat-value">4.9</div>
                <div class="stat-label">Rating</div>
              </div>
              <div class="stat-item">
                <div class="stat-icon stat-icon-amber"><i data-lucide="trophy" class="w-4 h-4"></i></div>
                <div class="stat-value">12</div>
                <div class="stat-label">Badges</div>
              </div>
            </div>
          </div>

          <!-- Skills Panel -->
          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Skills & Expertise</h3>
              <button class="panel-action" title="Add Skill"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
            </div>
            <div class="skills-tags">
              <span class="skill-tag">React <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">Node.js <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">TypeScript <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">Python <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">System Design <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">AWS <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
            </div>
            <div class="skills-cert-grid">
              <div class="skill-cert-card">
                <div>
                  <strong>React Advanced</strong>
                  <p>Level 5 · 42 endorsements</p>
                </div>
                <span class="cert-verified">Verified</span>
              </div>
              <div class="skill-cert-card">
                <div>
                  <strong>Cloud Architecture</strong>
                  <p>Level 4 · 23 endorsements</p>
                </div>
                <span class="cert-verified">Verified</span>
              </div>
            </div>
            <div class="skill-input-row">
              <input type="text" placeholder="Add a skill..." class="skill-input" id="skillInput">
            </div>
          </div>

          <!-- Connections Panel -->
          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Connections</h3>
            </div>
            <div class="connections-list">
              <div class="connection-item">
                <div class="connection-icon connection-discord"><i data-lucide="message-square" class="w-3.5 h-3.5"></i></div>
                <div class="connection-info">
                  <span class="connection-name">Discord</span>
                  <span class="connection-status">Connected</span>
                </div>
                <div class="connection-dot dot-active"></div>
              </div>
              <div class="connection-item">
                <div class="connection-icon connection-github"><i data-lucide="github" class="w-3.5 h-3.5"></i></div>
                <div class="connection-info">
                  <span class="connection-name">GitHub</span>
                  <span class="connection-status">Connected</span>
                </div>
                <div class="connection-dot dot-active"></div>
              </div>
              <div class="connection-item">
                <div class="connection-icon"><i data-lucide="linkedin" class="w-3.5 h-3.5"></i></div>
                <div class="connection-info">
                  <span class="connection-name">LinkedIn</span>
                  <span class="connection-status">Not connected</span>
                </div>
                <button class="connection-connect-btn">Connect</button>
              </div>
            </div>
          </div>

          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Achievements & Gamification</h3>
              <button class="panel-action"><i data-lucide="share-2" class="w-3.5 h-3.5"></i></button>
            </div>
            <div class="badge-wall">
              <span class="badge-chip">🏆 Team Player</span>
              <span class="badge-chip">⚡ Sprint Master</span>
              <span class="badge-chip">🧠 Mentor</span>
              <span class="badge-chip">🔥 7-Day Streak</span>
              <span class="badge-chip">✅ Top Reviewer</span>
            </div>
            <div class="next-level-box">
              <p>Progress to next level</p>
              <div class="spark-track"><div class="spark-fill" style="width:72%"></div></div>
              <small>+180 XP to reach Level 13</small>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="profile-right">

          <!-- AI Insight Card -->
          <div class="ai-insight-card glass-card fade-in-section">
            <div class="ai-insight-glow"></div>
            <div class="ai-insight-inner">
              <div class="ai-insight-header">
                <div class="ai-insight-title">
                  <i data-lucide="sparkles" class="w-4 h-4"></i>
                  <span>AI IMPACT ANALYSIS</span>
                </div>
                <span class="ai-insight-updated">Updated 2h ago</span>
              </div>
              <h3 class="ai-insight-headline">Top 5% Collaborator</h3>
              <p class="ai-insight-description">Your collaboration score is <strong>trending up 23%</strong> this month. You've positively impacted <strong>12 team projects</strong> and mentored <strong>3 new developers</strong>.</p>
              <div class="ai-insight-bar">
                <div class="ai-bar-labels">
                  <span>Impact Score</span>
                  <span>Top 5%</span>
                </div>
                <div class="ai-bar-track">
                  <div class="ai-bar-fill" id="aiBarFill"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="profile-tabs fade-in-section">
            <button class="profile-tab active" data-tab="settings"><i data-lucide="settings" class="w-4 h-4"></i> Settings</button>
            <button class="profile-tab" data-tab="history"><i data-lucide="clock" class="w-4 h-4"></i> History</button>
            <button class="profile-tab" data-tab="security"><i data-lucide="shield" class="w-4 h-4"></i> Security</button>
          </div>

          <!-- Settings Panel -->
          <div class="tab-content active" data-content="settings">
            <div class="settings-grid fade-in-section">
              <!-- Notifications -->
              <div class="glass-card panel">
                <h4 class="panel-sub-title"><i data-lucide="bell" class="w-4 h-4"></i> Notifications</h4>
                <p class="panel-sub-desc">Manage how you receive updates.</p>
                <div class="toggle-list">
                  <div class="toggle-row">
                    <span>Weekly Digest</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Project Alerts</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Marketing Emails</span>
                    <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                  </div>
                </div>
              </div>

              <!-- Privacy -->
              <div class="glass-card panel">
                <h4 class="panel-sub-title"><i data-lucide="eye" class="w-4 h-4"></i> Privacy</h4>
                <p class="panel-sub-desc">Control your data visibility.</p>
                <div class="toggle-list">
                  <div class="toggle-row">
                    <span>Public Profile</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Share Activity Status</span>
                    <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Allow AI Analysis</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="glass-card panel fade-in-section profile-reviews-mini">
            <div class="panel-header">
              <h3 class="panel-title">Reviews Snapshot</h3>
              <button class="panel-action" id="viewAllReviewsBtn"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></button>
            </div>
            <div class="rating-overview">
              <strong>4.9</strong>
              <span>Overall Rating · 128 reviews</span>
            </div>
            <div class="review-bars">
              <div><span>5★</span><div class="bar"><i style="width:84%"></i></div></div>
              <div><span>4★</span><div class="bar"><i style="width:12%"></i></div></div>
              <div><span>3★</span><div class="bar"><i style="width:4%"></i></div></div>
            </div>
          </div>

          <!-- History Panel -->
          <div class="tab-content" data-content="history">
            <div class="glass-card panel fade-in-section">
              <h4 class="panel-sub-title">Recent Activity</h4>
              <div class="activity-timeline">
                <div class="activity-item">
                  <div class="activity-dot dot-blue"></div>
                  <div class="activity-body">
                    <p>Updated profile settings</p>
                    <span>Just now · Web</span>
                  </div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-green"></div>
                  <div class="activity-body">
                    <p>Completed <strong>API Design Sprint</strong> challenge</p>
                    <span>2 hours ago · +300 XP</span>
                  </div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-purple"></div>
                  <div class="activity-body">
                    <p>Reviewed <strong>Sarah Kim's</strong> project</p>
                    <span>Yesterday, 14:00 · Review</span>
                  </div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-amber"></div>
                  <div class="activity-body">
                    <p>Earned <strong>Team Player</strong> badge</p>
                    <span>3 days ago · Achievement</span>
                  </div>
                </div>
              </div>
              <button class="panel-full-btn">
                <i data-lucide="history" class="w-3.5 h-3.5"></i> View Full History
              </button>
            </div>
          </div>

          <!-- Security Panel -->
          <div class="tab-content" data-content="security">
            <div class="glass-card panel fade-in-section">
              <h4 class="panel-sub-title"><i data-lucide="shield-check" class="w-4 h-4"></i> Security Settings</h4>
              <div class="toggle-list">
                <div class="toggle-row">
                  <span>Two-Factor Authentication</span>
                  <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                  <span>Login Notifications</span>
                  <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                  <span>Biometric Login</span>
                  <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                </div>
              </div>
              <div class="security-actions">
                <button class="btn btn-secondary btn-sm"><i data-lucide="key" class="w-3.5 h-3.5"></i> Change Password</button>
                <button class="btn btn-secondary btn-sm"><i data-lucide="smartphone" class="w-3.5 h-3.5"></i> Manage Devices</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Profile Modal (Dashboard Copy) -->
      <div class="db-modal-overlay" id="editModal">
        <div class="db-modal-card db-modal-card-compact profile-edit-modal">
          <div class="db-modal-head">
            <h3 id="profileModalTitle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8"></path><path d="M12 8v8"></path></svg> Edit Profile</h3>
            <button type="button" id="closeUserModal" class="db-icon-btn" aria-label="Close">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
            </button>
          </div>
          <form id="userForm" class="db-modal-grid">
            <input type="hidden" id="formId" name="id" value="<?= (int) ($sessionUser['id'] ?? 0) ?>">
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="8" r="5"></circle></svg>First Name</label><input id="formFirstName" name="first_name" value="<?= htmlspecialchars($displayFirstName) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="8" r="5"></circle></svg>Last Name</label><input id="formLastName" name="last_name" value="<?= htmlspecialchars($displayLastName) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"></path><path d="M4 7l8 6 8-6"></path></svg>Email</label><input id="formEmail" name="email" type="email" value="<?= htmlspecialchars($displayEmail) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>Password</label><input id="formPassword" name="password" type="password" placeholder="Required for create"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72l.45 3a2 2 0 0 1-.57 1.74L7.9 9.1a16 16 0 0 0 7 7l.64-1.09a2 2 0 0 1 1.74-.57l3 .45A2 2 0 0 1 22 16.92z"></path></svg>Phone</label><input id="formPhone" name="phone" value="<?= htmlspecialchars($displayPhone) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"></path></svg>Role</label>
              <select id="formRole" name="role">
                <option value="user" <?= strtolower($displayRole) === 'user' ? 'selected' : '' ?>>user</option>
                <option value="manager" <?= strtolower($displayRole) === 'manager' ? 'selected' : '' ?>>manager</option>
                <option value="admin" <?= strtolower($displayRole) === 'admin' ? 'selected' : '' ?>>admin</option>
                <option value="member" <?= strtolower($displayRole) === 'member' ? 'selected' : '' ?>>member</option>
              </select>
            </div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4"></path></svg>Status</label>
              <select id="formStatus" name="status">
                <option value="1" <?= $displayStatus === 1 ? 'selected' : '' ?>>active</option>
                <option value="0" <?= $displayStatus === 0 ? 'selected' : '' ?>>offline</option>
              </select>
            </div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>Is Blocked</label>
              <select id="formIsBlocked" name="is_blocked">
                <option value="0" <?= $displayIsBlocked === 0 ? 'selected' : '' ?>>No</option>
                <option value="1" <?= $displayIsBlocked === 1 ? 'selected' : '' ?>>Yes</option>
              </select>
            </div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="12" cy="11" r="3"></circle><path d="M8 18c1-2 2.5-3 4-3s3 1 4 3"></path></svg>Avatar URL</label><input id="formAvatarUrl" name="avatar_url" value="<?= htmlspecialchars($displayAvatarUrl) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 .9-4.5 4.3L17.6 20 12 17l-5.6 3 1.1-6.8L3 8.9 9 8z"></path></svg>Badge</label><input id="formBadge" name="badge" value="<?= htmlspecialchars($displayBadge) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11h18"></path><path d="M12 2a15.3 15.3 0 0 1 4 9 15.3 15.3 0 0 1-4 11 15.3 15.3 0 0 1-4-11 15.3 15.3 0 0 1 4-9z"></path><circle cx="12" cy="12" r="10"></circle></svg>Country</label><input id="formCountry" name="country" value="<?= htmlspecialchars($displayCountry) ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"></path></svg>Title</label><input id="formTitle" name="title" value="<?= htmlspecialchars($displayTitle) ?>"></div>
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
            <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>Skills</label><input id="formSkills" name="skills" value="<?= htmlspecialchars($displaySkills) ?>"></div>
            <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>Bio</label><textarea id="formBio" name="bio" rows="2"><?= htmlspecialchars($displayBio) ?></textarea></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"></path><path d="M17 5H9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8H7"></path></svg>XP</label><input id="formXp" name="xp" type="number" min="0" value="<?= (int) $displayXp ?>"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h6v6H4z"></path><path d="M14 4h6v6h-6z"></path><path d="M4 14h6v6H4z"></path><path d="M14 14h6v6h-6z"></path></svg>Face Enrolled</label>
              <select id="formFaceEnrolled" name="face_enrolled">
                <option value="0" <?= $displayFaceEnrolled === 0 ? 'selected' : '' ?>>No</option>
                <option value="1" <?= $displayFaceEnrolled === 1 ? 'selected' : '' ?>>Yes</option>
              </select>
            </div>
            <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9 6 9-6"></path><path d="M21 17H3"></path><path d="M3 7h18v10H3z"></path></svg>Face Images Path</label><input id="formFaceImagesPath" name="face_images_path" value="<?= htmlspecialchars($displayFaceImagesPath) ?>"></div>
            <div class="db-field db-field-full"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M7 12h10"></path><path d="M7 16h8"></path><path d="M7 8h10"></path></svg>Face Descriptor</label><textarea id="formFaceDescriptor" name="face_descriptor" rows="2"><?= htmlspecialchars($displayFaceDescriptor) ?></textarea></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>Face Enrolled At</label><input id="formFaceEnrolledAt" name="face_enrolled_at" value="<?= htmlspecialchars($displayFaceEnrolledAt) ?>" placeholder="Select date and time"></div>
            <div class="db-field"><label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>Last Seen</label><input id="formLastSeen" name="last_seen" value="<?= htmlspecialchars($displayLastSeen) ?>" placeholder="Select date and time"></div>

            <div class="db-modal-actions">
              <button type="button" class="db-btn db-btn-ghost" id="cancelUserModal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>Cancel</button>
              <button type="submit" class="db-btn db-btn-primary" id="saveUserBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>Save User</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>

  <button class="profile-fab" id="profileFab" aria-label="Quick profile actions">
    <i data-lucide="plus" class="w-5 h-5"></i>
  </button>
  <div class="profile-fab-menu" id="profileFabMenu">
    <button id="fabEditProfile"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Profile</button>
    <button><i data-lucide="folder-plus" class="w-4 h-4"></i> Add Project</button>
    <button><i data-lucide="badge-check" class="w-4 h-4"></i> Add Certificate</button>
  </div>

  <div class="profile-toast-stack" id="profileToastStack" aria-live="polite"></div>

  <div class="modal-overlay" id="aboutPillModal">
    <div class="modal glass-card">
      <div class="modal-header">
        <h3 id="aboutPillTitle">Highlight details</h3>
        <button class="modal-close" id="closeAboutPillModal"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
      <div class="modal-form">
        <p id="aboutPillBody" class="about-pill-modal-text"></p>
      </div>
    </div>
  </div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/globe-explorer.js"></script>
  <script src="../../assets/js/globe-3d-picker.js"></script>
  <script src="../../assets/js/profile.js"></script>
</body>
</html>
