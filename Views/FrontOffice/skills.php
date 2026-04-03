<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Showcase your skills and certificates on Diversity.is.">
  <title>Skills & Certificates â€” Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/skills.css">
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
        <a href="skills.php" class="active">Skills</a>
        <a href="projects.php">Projects</a>
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
            <a href="../BackOffice/ListUsers.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <!-- Skills Hero -->
  <section class="section-half" style="padding-top:100px">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:center">
        <span class="section-tag">Expertise</span>
        <h1 class="text-h1">Skills & <span class="text-gradient">Certificates</span></h1>
        <p class="text-body-lg">Track your expertise and earn recognition for your achievements.</p>
      </div>
    </div>
  </section>

  <!-- Skills Grid -->
  <section class="skills-section">
    <div class="container">
      <h2 class="text-h2 fade-in-section" style="margin-bottom:var(--space-2xl)">Skill <span class="text-gradient">Cloud</span></h2>
      <div class="skills-3d-grid fade-in-section" id="skills-grid">
        <div class="skill-float-item tilt-card stagger-item" data-depth="1">
          <div class="skill-icon"><i data-lucide="atom" class="w-5 h-5"></i></div>
          <span>React</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="2">
          <div class="skill-icon"><i data-lucide="hexagon" class="w-5 h-5"></i></div>
          <span>Node.js</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="1">
          <div class="skill-icon"><i data-lucide="terminal" class="w-5 h-5"></i></div>
          <span>Python</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="3">
          <div class="skill-icon"><i data-lucide="file-code-2" class="w-5 h-5"></i></div>
          <span>TypeScript</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="2">
          <div class="skill-icon"><i data-lucide="cloud" class="w-5 h-5"></i></div>
          <span>AWS</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="1">
          <div class="skill-icon"><i data-lucide="container" class="w-5 h-5"></i></div>
          <span>Docker</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="3">
          <div class="skill-icon"><i data-lucide="database" class="w-5 h-5"></i></div>
          <span>PostgreSQL</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="2">
          <div class="skill-icon"><i data-lucide="git-branch" class="w-5 h-5"></i></div>
          <span>GraphQL</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="1">
          <div class="skill-icon"><i data-lucide="pen-tool" class="w-5 h-5"></i></div>
          <span>Figma</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="2">
          <div class="skill-icon"><i data-lucide="flame" class="w-5 h-5"></i></div>
          <span>Firebase</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="3">
          <div class="skill-icon"><i data-lucide="zap" class="w-5 h-5"></i></div>
          <span>Next.js</span>
        </div>
        <div class="skill-float-item tilt-card stagger-item" data-depth="1">
          <div class="skill-icon"><i data-lucide="brain" class="w-5 h-5"></i></div>
          <span>TensorFlow</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Certificates Carousel -->
  <section class="section certs-section">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:center">
        <span class="section-tag">Recognition</span>
        <h2 class="text-h1">Certificate <span class="text-gradient">Showcase</span></h2>
        <p class="text-body-lg">Professional certifications earned through excellence.</p>
      </div>

      <div class="carousel-wrapper fade-in-section">
        <div class="carousel-3d" id="cert-carousel">
          <div class="cert-card glass-card" data-index="0">
            <div class="cert-badge"><i data-lucide="trophy" class="w-7 h-7"></i></div>
            <h3 class="cert-title">AWS Solutions Architect</h3>
            <p class="cert-issuer">Amazon Web Services</p>
            <p class="cert-date text-small">Issued: Jan 2025</p>
            <div class="cert-skills">
              <span class="tag">Cloud</span>
              <span class="tag">Architecture</span>
              <span class="tag">DevOps</span>
            </div>
            <div class="cert-id text-small">ID: AWS-SA-2025-1234</div>
          </div>
          <div class="cert-card glass-card" data-index="1">
            <div class="cert-badge"><i data-lucide="graduation-cap" class="w-7 h-7"></i></div>
            <h3 class="cert-title">Google Cloud Professional</h3>
            <p class="cert-issuer">Google Cloud</p>
            <p class="cert-date text-small">Issued: Oct 2024</p>
            <div class="cert-skills">
              <span class="tag">GCP</span>
              <span class="tag">ML</span>
              <span class="tag">BigQuery</span>
            </div>
            <div class="cert-id text-small">ID: GCP-PRO-2024-5678</div>
          </div>
          <div class="cert-card glass-card" data-index="2">
            <div class="cert-badge"><i data-lucide="star" class="w-7 h-7"></i></div>
            <h3 class="cert-title">Meta Frontend Developer</h3>
            <p class="cert-issuer">Meta</p>
            <p class="cert-date text-small">Issued: Jul 2024</p>
            <div class="cert-skills">
              <span class="tag">React</span>
              <span class="tag">Testing</span>
              <span class="tag">UX</span>
            </div>
            <div class="cert-id text-small">ID: META-FE-2024-9012</div>
          </div>
          <div class="cert-card glass-card" data-index="3">
            <div class="cert-badge"><i data-lucide="shield" class="w-7 h-7"></i></div>
            <h3 class="cert-title">CompTIA Security+</h3>
            <p class="cert-issuer">CompTIA</p>
            <p class="cert-date text-small">Issued: Mar 2024</p>
            <div class="cert-skills">
              <span class="tag">Security</span>
              <span class="tag">Network</span>
              <span class="tag">Compliance</span>
            </div>
            <div class="cert-id text-small">ID: COMP-SEC-2024-3456</div>
          </div>
          <div class="cert-card glass-card" data-index="4">
            <div class="cert-badge"><i data-lucide="cpu" class="w-7 h-7"></i></div>
            <h3 class="cert-title">Deep Learning Specialization</h3>
            <p class="cert-issuer">deeplearning.ai</p>
            <p class="cert-date text-small">Issued: Dec 2023</p>
            <div class="cert-skills">
              <span class="tag">Neural Nets</span>
              <span class="tag">CNN</span>
              <span class="tag">RNN</span>
            </div>
            <div class="cert-id text-small">ID: DL-SPEC-2023-7890</div>
          </div>
        </div>
        <div class="carousel-controls">
          <button class="carousel-btn" id="carousel-prev">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <div class="carousel-dots" id="carousel-dots"></div>
          <button class="carousel-btn" id="carousel-next">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/mouse-tracking.js"></script>
  <script src="../assets/js/skills.js"></script>
</body>
</html>

