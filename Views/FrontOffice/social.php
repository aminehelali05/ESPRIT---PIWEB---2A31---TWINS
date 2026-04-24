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
  <meta name="description" content="Diversity.is Social Feed — Share updates, discuss ideas, and engage with the community.">
  <title>Social Feed — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/social.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg with-global-left-sidebar">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="auth.php">Sign In</a>
        <a href="social.php" class="active">Feed</a>
        <a href="JobOffer.php">Job Offers</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php">Contracts</a>
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
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

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
          <a class="active" href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg><span>Social</span></a>
          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg><span>Profile</span></a>
          <a href="JobOffer.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg><span>Job Offers</span></a>
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg><span>Projects & Collaborators</span></a>
          <a href="reviews.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l2.8 5.7 6.3.9-4.6 4.4 1.1 6.3L12 17.3 6.4 20.3l1.1-6.3L2.9 9.6l6.3-.9L12 3z" /></svg><span>Contracts</span></a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg><span>Challenges & Daily Quiz</span></a>
          <?php if ($isAdminSidebar): ?><a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/><path d="M14 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6z"/><path d="M4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2z"/><path d="M14 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2z"/></svg><span>Dashboard</span></a><?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong>84</strong></div>
          <div class="mini-score"><span>Daily Streak</span><strong>7 days</strong></div>
        </div>
      </aside>

      <section class="profile-content-area">

  <section class="section-half">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:left;margin-bottom:var(--space-2xl)">
        <span class="section-tag">Community</span>
        <h1 class="text-h1">Social <span class="text-gradient">Feed</span></h1>
        <p class="text-body-lg">See what the community is sharing.</p>
      </div>
    </div>
  </section>

  <section class="feed-section">
    <div class="container">
      <div class="feed-layout">
        <!-- Feed Column -->
        <div class="feed-main">
          <!-- Create Post -->
          <div class="create-post glass-card fade-in-section">
            <div class="create-post-row">
              <div class="avatar"><span>JD</span></div>
              <input class="form-input create-post-input" placeholder="Share something with the community..." id="post-input">
            </div>
            <div class="create-post-actions">
              <button class="btn btn-sm btn-secondary"><i data-lucide="image" class="w-3.5 h-3.5"></i> Media</button>
              <button class="btn btn-sm btn-secondary"><i data-lucide="paperclip" class="w-3.5 h-3.5"></i> File</button>
              <button class="btn btn-sm btn-primary" id="post-btn">Post</button>
            </div>
          </div>

          <!-- Post Cards -->
          <div class="post-card glass-card fade-in-section stagger-item" id="post-1">
            <div class="post-header">
              <div class="avatar" style="background:linear-gradient(135deg,#A855F7,#6366F1)"><span>SK</span></div>
              <div>
                <p class="post-author">Sarah Kim</p>
                <p class="text-small">UX Designer • 2 hours ago</p>
              </div>
            </div>
            <div class="post-body">
              <p>Just finished designing the new dashboard UI for our collaboration platform! Using a glassmorphism approach with depth layers. What do you think?</p>
              <div class="post-media">
                <div class="post-media-placeholder">
                  <span><i data-lucide="image" class="w-4 h-4"></i> Dashboard Preview</span>
                </div>
              </div>
            </div>
            <div class="post-stats">
              <span><i data-lucide="heart" class="w-3 h-3"></i> 48 likes</span>
              <span class="post-comment-toggle" data-post="1"><i data-lucide="message-circle" class="w-3 h-3"></i> 12 comments</span>
              <span><i data-lucide="repeat" class="w-3 h-3"></i> 5 shares</span>
            </div>
            <div class="post-actions">
              <button class="post-action-btn" data-action="like">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Like
              </button>
              <button class="post-action-btn post-comment-toggle" data-post="1">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Comment
              </button>
              <button class="post-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                Share
              </button>
            </div>
            <!-- Expandable Comments -->
            <div class="comments-section" id="comments-1">
              <div class="comment-item">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem;background:linear-gradient(135deg,#22C55E,#22D3EE)"><span>MR</span></div>
                <div class="comment-body">
                  <p class="comment-author">Mike Rodriguez</p>
                  <p class="comment-text">This looks incredible! The blur effects are so smooth. Would love to see the interaction states.</p>
                  <p class="text-small">1 hour ago • <i data-lucide="heart" class="w-2.5 h-2.5"></i> 5</p>
                </div>
              </div>
              <div class="comment-item">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem;background:linear-gradient(135deg,#F59E0B,#F43F5E)"><span>AJ</span></div>
                <div class="comment-body">
                  <p class="comment-author">Anna Johnson</p>
                  <p class="comment-text">Beautiful work! The glass cards really stand out against that dark background</p>
                  <p class="text-small">45 min ago • <i data-lucide="heart" class="w-2.5 h-2.5"></i> 3</p>
                </div>
              </div>
              <div class="comment-input-row">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem"><span>JD</span></div>
                <input class="form-input" placeholder="Write a comment..." style="flex:1;padding:0.6rem 1rem;font-size:0.85rem">
              </div>
            </div>
          </div>

          <div class="post-card glass-card fade-in-section stagger-item" id="post-2">
            <div class="post-header">
              <div class="avatar" style="background:linear-gradient(135deg,#22C55E,#22D3EE)"><span>MR</span></div>
              <div>
                <p class="post-author">Mike Rodriguez</p>
                <p class="text-small">Backend Developer • 5 hours ago</p>
              </div>
            </div>
            <div class="post-body">
              <p>Just deployed a new microservice that reduced API latency by 40%! Key takeaways: use connection pooling, implement caching at the edge, and profile everything. Happy to share the architecture if anyone's interested.</p>
            </div>
            <div class="post-stats">
              <span><i data-lucide="heart" class="w-3 h-3"></i> 92 likes</span>
              <span class="post-comment-toggle" data-post="2"><i data-lucide="message-circle" class="w-3 h-3"></i> 24 comments</span>
              <span><i data-lucide="repeat" class="w-3 h-3"></i> 18 shares</span>
            </div>
            <div class="post-actions">
              <button class="post-action-btn" data-action="like">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Like
              </button>
              <button class="post-action-btn post-comment-toggle" data-post="2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Comment
              </button>
              <button class="post-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                Share
              </button>
            </div>
            <div class="comments-section" id="comments-2">
              <div class="comment-item">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem;background:linear-gradient(135deg,#A855F7,#6366F1)"><span>SK</span></div>
                <div class="comment-body">
                  <p class="comment-author">Sarah Kim</p>
                  <p class="comment-text">40% improvement is insane! Would love to see the architecture diagram.</p>
                  <p class="text-small">3 hours ago • <i data-lucide="heart" class="w-2.5 h-2.5"></i> 8</p>
                </div>
              </div>
              <div class="comment-input-row">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem"><span>JD</span></div>
                <input class="form-input" placeholder="Write a comment..." style="flex:1;padding:0.6rem 1rem;font-size:0.85rem">
              </div>
            </div>
          </div>

          <div class="post-card glass-card fade-in-section stagger-item" id="post-3">
            <div class="post-header">
              <div class="avatar" style="background:linear-gradient(135deg,#F59E0B,#F43F5E)"><span>AJ</span></div>
              <div>
                <p class="post-author">Anna Johnson</p>
                <p class="text-small">Data Scientist • 8 hours ago</p>
              </div>
            </div>
            <div class="post-body">
              <p>Finished my analysis on remote collaboration patterns. Key finding: teams using asynchronous communication tools are 35% more productive than those relying solely on real-time messaging. Full report in my latest project!</p>
            </div>
            <div class="post-stats">
              <span><i data-lucide="heart" class="w-3 h-3"></i> 67 likes</span>
              <span class="post-comment-toggle" data-post="3"><i data-lucide="message-circle" class="w-3 h-3"></i> 15 comments</span>
              <span><i data-lucide="repeat" class="w-3 h-3"></i> 31 shares</span>
            </div>
            <div class="post-actions">
              <button class="post-action-btn" data-action="like">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Like
              </button>
              <button class="post-action-btn post-comment-toggle" data-post="3">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Comment
              </button>
              <button class="post-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                Share
              </button>
            </div>
            <div class="comments-section" id="comments-3">
              <div class="comment-input-row">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem"><span>JD</span></div>
                <input class="form-input" placeholder="Write a comment..." style="flex:1;padding:0.6rem 1rem;font-size:0.85rem">
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <aside class="feed-sidebar">
          <div class="glass-card sidebar-card fade-in-section">
            <h3 class="panel-title">Trending Topics</h3>
            <div class="trending-list">
              <div class="trending-item"><span class="trending-hash">#</span><div><p class="trending-topic">WebDevelopment</p><p class="text-small">1.2k posts</p></div></div>
              <div class="trending-item"><span class="trending-hash">#</span><div><p class="trending-topic">AITools</p><p class="text-small">890 posts</p></div></div>
              <div class="trending-item"><span class="trending-hash">#</span><div><p class="trending-topic">RemoteWork</p><p class="text-small">654 posts</p></div></div>
              <div class="trending-item"><span class="trending-hash">#</span><div><p class="trending-topic">OpenSource</p><p class="text-small">512 posts</p></div></div>
            </div>
          </div>
          <div class="glass-card sidebar-card fade-in-section">
            <h3 class="panel-title">Suggested Connections</h3>
            <div class="suggestion-list">
              <div class="suggestion-item">
                <div class="avatar" style="width:36px;height:36px;font-size:0.75rem;background:linear-gradient(135deg,#6366F1,#A855F7)"><span>LP</span></div>
                <div><p class="suggestion-name">Lisa Park</p><p class="text-small">Product Manager</p></div>
                <button class="btn btn-sm btn-secondary">+</button>
              </div>
              <div class="suggestion-item">
                <div class="avatar" style="width:36px;height:36px;font-size:0.75rem;background:linear-gradient(135deg,#22D3EE,#22C55E)"><span>TC</span></div>
                <div><p class="suggestion-name">Tom Chen</p><p class="text-small">DevOps Engineer</p></div>
                <button class="btn btn-sm btn-secondary">+</button>
              </div>
              <div class="suggestion-item">
                <div class="avatar" style="width:36px;height:36px;font-size:0.75rem;background:linear-gradient(135deg,#F43F5E,#F59E0B)"><span>EM</span></div>
                <div><p class="suggestion-name">Eva Martin</p><p class="text-small">ML Engineer</p></div>
                <button class="btn btn-sm btn-secondary">+</button>
              </div>
            </div>
          </div>
        </aside>
            </div>
          </div>
      </div>
    </div>
  </section>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/social.js"></script>
      </section>
    </div>
  </main>
</body>
</html>


