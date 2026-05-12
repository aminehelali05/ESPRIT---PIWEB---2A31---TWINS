<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarDisplayName = $sidebarDisplayName !== '' ? $sidebarDisplayName : 'Guest User';
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$isAdminSidebar = strtolower(trim((string) ($sidebarUser['email'] ?? ''))) === 'admin@diversity.is';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Give and receive meaningful feedback on Diversity.is.">
  <title>Reviews & Ratings — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/reviews.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="auth.php">Sign In</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php" class="active">Reviews</a>
        <a href="challenges.php">Challenges</a>
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
            <div class="nav-avatar">AD</div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong>Admin User</strong>
              <span>admin@diversity.is</span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <main class="profile-main" id="main-content" tabindex="-1">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card" aria-label="Quick profile and navigation">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          <div>
            <h4><?= htmlspecialchars($sidebarDisplayName) ?></h4>
            <p>Level 12</p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg><span>Home Feed</span></a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg><span>Social</span></a>
          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg><span>Profile</span></a>
          <a href="skills.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg><span>Skills & Certificates</span></a>
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg><span>Projects & Collaborators</span></a>
          <a class="active" href="reviews.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l2.8 5.7 6.3.9-4.6 4.4 1.1 6.3L12 17.3 6.4 20.3l1.1-6.3L2.9 9.6l6.3-.9L12 3z" /></svg><span>Reviews & Ratings</span></a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg><span>Challenges & Daily Quiz</span></a>
          <?php if ($isAdminSidebar): ?><a href="../BackOffice/dashboard.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/><path d="M14 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6z"/><path d="M4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2z"/><path d="M14 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2z"/></svg><span>User Management</span></a><?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong>84</strong></div>
          <div class="mini-score"><span>Daily Streak</span><strong>7 days</strong></div>
        </div>
      </aside>

      <section class="profile-content-area">

  <section class="section-half">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:center">
        <span class="section-tag">Feedback</span>
        <h1 class="text-h1">Reviews & <span class="text-gradient">Ratings</span></h1>
        <p class="text-body-lg">Share and receive meaningful feedback to grow professionally.</p>
      </div>
    </div>
  </section>

  <!-- Rating Overview -->
  <section class="rating-overview-section">
    <div class="container">
      <div class="rating-overview glass-card fade-in-section">
        <div class="rating-big">
          <span class="rating-number">4.9</span>
          <div class="rating-stars-display" data-rating="4.9">
            <span class="star filled">★</span>
            <span class="star filled">★</span>
            <span class="star filled">★</span>
            <span class="star filled">★</span>
            <span class="star half-filled">★</span>
          </div>
          <p class="text-small">Based on 127 reviews</p>
        </div>
        <div class="rating-breakdown">
          <div class="rating-row"><span class="text-small">5 ★</span><div class="rating-bar-track"><div class="rating-bar-fill" style="width:82%"></div></div><span class="text-small">104</span></div>
          <div class="rating-row"><span class="text-small">4 ★</span><div class="rating-bar-track"><div class="rating-bar-fill" style="width:12%"></div></div><span class="text-small">15</span></div>
          <div class="rating-row"><span class="text-small">3 ★</span><div class="rating-bar-track"><div class="rating-bar-fill" style="width:4%"></div></div><span class="text-small">5</span></div>
          <div class="rating-row"><span class="text-small">2 ★</span><div class="rating-bar-track"><div class="rating-bar-fill" style="width:2%"></div></div><span class="text-small">2</span></div>
          <div class="rating-row"><span class="text-small">1 ★</span><div class="rating-bar-track"><div class="rating-bar-fill" style="width:1%"></div></div><span class="text-small">1</span></div>
        </div>
            </div>
          </div>
        </section>

  <!-- Write Review -->
  <section class="write-review-section">
    <div class="container">
      <div class="write-review glass-card fade-in-section">
        <h3 class="text-h3">Write a Review</h3>
        <div class="interactive-stars" id="interactive-stars">
          <span class="star-input" data-value="1">★</span>
          <span class="star-input" data-value="2">★</span>
          <span class="star-input" data-value="3">★</span>
          <span class="star-input" data-value="4">★</span>
          <span class="star-input" data-value="5">★</span>
        </div>
        <p class="rating-text text-small" id="rating-text">Select a rating</p>
        <textarea class="form-input review-textarea" placeholder="Share your experience..." rows="4" id="review-textarea"></textarea>
        <div style="display:flex;justify-content:flex-end">
          <button class="btn btn-primary" id="submit-review">Submit Review</button>
        </div>
            </div>
          </div>
        </section>

  <!-- Reviews List -->
  <section class="reviews-list-section">
    <div class="container">
      <h2 class="text-h2 fade-in-section" style="margin-bottom:var(--space-2xl)">Recent <span class="text-gradient">Reviews</span></h2>
      
      <div class="reviews-list fade-in-section">
        <div class="review-card glass-card stagger-item">
          <div class="review-header">
            <div class="avatar" style="width:40px;height:40px;font-size:0.85rem;background:linear-gradient(135deg,#A855F7,#6366F1)"><span>SK</span></div>
            <div>
              <p class="review-author">Sarah Kim</p>
              <div class="review-stars">★★★★★</div>
            </div>
            <span class="text-small" style="margin-left:auto">3 days ago</span>
          </div>
          <p class="review-text">Incredible developer to work with! John delivered the AI dashboard ahead of schedule and exceeded all expectations. His code quality and documentation are top-notch.</p>
          <div class="review-helpful">
            <button class="helpful-btn" id="helpful-1"><i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> Helpful (12)</button>
          </div>
        </div>

        <div class="review-card glass-card stagger-item">
          <div class="review-header">
            <div class="avatar" style="width:40px;height:40px;font-size:0.85rem;background:linear-gradient(135deg,#22C55E,#22D3EE)"><span>MR</span></div>
            <div>
              <p class="review-author">Mike Rodriguez</p>
              <div class="review-stars">★★★★★</div>
            </div>
            <span class="text-small" style="margin-left:auto">1 week ago</span>
          </div>
          <p class="review-text">Great collaborator and problem solver. We tackled a complex microservice architecture together, and John's insights on caching and performance were invaluable.</p>
          <div class="review-helpful">
            <button class="helpful-btn" id="helpful-2"><i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> Helpful (8)</button>
          </div>
        </div>

        <div class="review-card glass-card stagger-item">
          <div class="review-header">
            <div class="avatar" style="width:40px;height:40px;font-size:0.85rem;background:linear-gradient(135deg,#F59E0B,#F43F5E)"><span>AJ</span></div>
            <div>
              <p class="review-author">Anna Johnson</p>
              <div class="review-stars">★★★★<span style="color:rgba(255,255,255,0.2)">★</span></div>
            </div>
            <span class="text-small" style="margin-left:auto">2 weeks ago</span>
          </div>
          <p class="review-text">Very knowledgeable in full-stack development. Communication was excellent throughout the project. Would love to collaborate again in the future!</p>
          <div class="review-helpful">
            <button class="helpful-btn" id="helpful-3"><i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> Helpful (5)</button>
          </div>
        </div>

        <div class="review-card glass-card stagger-item">
          <div class="review-header">
            <div class="avatar" style="width:40px;height:40px;font-size:0.85rem;background:linear-gradient(135deg,#6366F1,#22D3EE)"><span>LP</span></div>
            <div>
              <p class="review-author">Lisa Park</p>
              <div class="review-stars">★★★★★</div>
            </div>
            <span class="text-small" style="margin-left:auto">3 weeks ago</span>
          </div>
          <p class="review-text">Outstanding attention to detail. John transformed our product requirements into a pixel-perfect implementation. His mentoring sessions were incredibly valuable.</p>
          <div class="review-helpful">
            <button class="helpful-btn" id="helpful-4"><i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> Helpful (15)</button>
          </div>
        </div>
            </div>
          </div>
  </section>

  <div style="height:var(--space-4xl)"></div>

      </section>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/reviews.js"></script>
</body>
</html>
