<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage collaborative projects and connect with team members on Diversity.is.">
  <title>Projects â€” Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/projects.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">â¬¡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php" class="active">Projects</a>
        <a href="reviews.php">Reviews</a>
        <a href="challenges.php">Challenges</a>
      </div>
      <div class="navbar-actions">
        <a href="auth.php" class="btn btn-primary btn-sm">Sign In</a>
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
            <a href="../BackOffice/user_list.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <section class="section-half" style="padding-top:100px">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:left">
        <span class="section-tag">Workspace</span>
        <h1 class="text-h1">Projects & <span class="text-gradient">Collaborators</span></h1>
        <p class="text-body-lg">Manage your projects and connect with your team.</p>
      </div>
    </div>
  </section>

  <!-- Projects Grid -->
  <section class="projects-grid-section">
    <div class="container">
      <div class="projects-toolbar fade-in-section">
        <div class="toolbar-filters">
          <button class="filter-btn active">All</button>
          <button class="filter-btn">Active</button>
          <button class="filter-btn">Completed</button>
          <button class="filter-btn">Archived</button>
        </div>
        <button class="btn btn-primary btn-sm">+ New Project</button>
      </div>

      <div class="grid grid-3 fade-in-section">
        <div class="project-card glass-card tilt-card stagger-item">
          <div class="project-status status-active">Active</div>
          <h3 class="project-title">AI Dashboard Platform</h3>
          <p class="text-small project-desc">Real-time analytics dashboard powered by machine learning for enterprise clients.</p>
          <div class="project-progress">
            <div class="progress-header">
              <span class="text-small">Progress</span>
              <span class="text-small" style="color:var(--color-accent-secondary)">78%</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill" style="width:78%;background:var(--color-accent-secondary)"></div>
            </div>
          </div>
          <div class="project-meta">
            <div class="project-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#6366F1,#A855F7)"><span>JD</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22C55E,#22D3EE);margin-left:-8px"><span>SK</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#F59E0B,#F43F5E);margin-left:-8px"><span>MR</span></div>
              <span class="text-small" style="margin-left:4px">+2</span>
            </div>
            <span class="text-small">Due: Apr 15</span>
          </div>
        </div>

        <div class="project-card glass-card tilt-card stagger-item">
          <div class="project-status status-active">Active</div>
          <h3 class="project-title">E-Commerce Redesign</h3>
          <p class="text-small project-desc">Complete UI/UX overhaul for a major retail platform with mobile-first approach.</p>
          <div class="project-progress">
            <div class="progress-header">
              <span class="text-small">Progress</span>
              <span class="text-small" style="color:var(--color-accent)">45%</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill" style="width:45%;background:var(--color-accent)"></div>
            </div>
          </div>
          <div class="project-meta">
            <div class="project-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#A855F7,#6366F1)"><span>LP</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22D3EE,#22C55E);margin-left:-8px"><span>TC</span></div>
            </div>
            <span class="text-small">Due: May 1</span>
          </div>
        </div>

        <div class="project-card glass-card tilt-card stagger-item">
          <div class="project-status status-completed">Completed</div>
          <h3 class="project-title">API Gateway v2</h3>
          <p class="text-small project-desc">Microservice gateway with rate limiting, caching, and real-time monitoring.</p>
          <div class="project-progress">
            <div class="progress-header">
              <span class="text-small">Progress</span>
              <span class="text-small" style="color:#22C55E">100%</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill" style="width:100%;background:#22C55E"></div>
            </div>
          </div>
          <div class="project-meta">
            <div class="project-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#22C55E,#22D3EE)"><span>MR</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#6366F1,#A855F7);margin-left:-8px"><span>JD</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#F43F5E,#F59E0B);margin-left:-8px"><span>AJ</span></div>
            </div>
            <span class="text-small">Completed</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Project Detail / Timeline -->
  <section class="section project-detail-section">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:left;margin-bottom:var(--space-2xl)">
        <span class="section-tag">Timeline</span>
        <h2 class="text-h2">AI Dashboard <span class="text-gradient">Roadmap</span></h2>
      </div>

      <div class="project-timeline fade-in-section">
        <div class="timeline-line"></div>
        
        <div class="timeline-item stagger-item">
          <div class="timeline-dot dot-completed"></div>
          <div class="timeline-content glass-card">
            <div class="timeline-date text-small">Jan 15, 2026</div>
            <h4>Project Kickoff</h4>
            <p class="text-small">Initial team meeting, requirements gathering, and architecture planning.</p>
            <div class="timeline-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#6366F1,#A855F7)"><span>JD</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22C55E,#22D3EE);margin-left:-6px"><span>SK</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22D3EE,#22C55E);margin-left:-6px"><span>MR</span></div>
            </div>
          </div>
        </div>

        <div class="timeline-item stagger-item">
          <div class="timeline-dot dot-completed"></div>
          <div class="timeline-content glass-card">
            <div class="timeline-date text-small">Feb 10, 2026</div>
            <h4>UI Design Complete</h4>
            <p class="text-small">Finalized all wireframes and high-fidelity mockups in Figma.</p>
            <div class="timeline-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#A855F7,#6366F1)"><span>SK</span></div>
            </div>
          </div>
        </div>

        <div class="timeline-item stagger-item">
          <div class="timeline-dot dot-active"></div>
          <div class="timeline-content glass-card timeline-active">
            <div class="timeline-date text-small">Mar 20, 2026</div>
            <h4>Backend Integration</h4>
            <p class="text-small">Connecting frontend to ML models and real-time data streams.</p>
            <div class="timeline-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#6366F1,#A855F7)"><span>JD</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22C55E,#22D3EE);margin-left:-6px"><span>MR</span></div>
            </div>
          </div>
        </div>

        <div class="timeline-item stagger-item">
          <div class="timeline-dot"></div>
          <div class="timeline-content glass-card">
            <div class="timeline-date text-small">Apr 15, 2026</div>
            <h4>Launch & Deploy</h4>
            <p class="text-small">Final testing, staging deployment, and production launch.</p>
            <div class="timeline-avatars">
              <div class="avatar mini" style="background:linear-gradient(135deg,#6366F1,#A855F7)"><span>JD</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22C55E,#22D3EE);margin-left:-6px"><span>SK</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#22D3EE,#22C55E);margin-left:-6px"><span>MR</span></div>
              <div class="avatar mini" style="background:linear-gradient(135deg,#F59E0B,#F43F5E);margin-left:-6px"><span>AJ</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/mouse-tracking.js"></script>
  <script src="../assets/js/projects.js"></script>
</body>
</html>



