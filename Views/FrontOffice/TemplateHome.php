<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Professional Collaboration Platform â€” Build, collaborate, and grow with a modern team workspace.">
  <title>Diversity.is â€” Professional Collaboration Platform</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/home.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">

  <!-- Gradient Mesh Background -->
  <canvas id="gradient-canvas"></canvas>

  <!-- Navbar -->
  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="TemplateHome.php" class="navbar-brand">
        <div class="brand-icon">â¬¡</div>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="TemplateHome.php" class="active">Home</a>
        <a href="auth.php">Sign In</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php">Reviews</a>
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
            <a href="../BackOffice/DashboardTemplate.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
  </nav>

  <!-- ===== HERO SECTION ===== -->
  <section class="section hero" id="hero">
    <div class="floating-shapes">
      <div class="floating-shape" data-parallax="-0.15"></div>
      <div class="floating-shape" data-parallax="-0.25"></div>
      <div class="floating-shape" data-parallax="-0.1"></div>
    </div>
    
    <!-- 3D Floating Abstract Shapes -->
    <div class="hero-shapes" id="hero-shapes">
      <div class="hero-shape shape-1" data-parallax="-0.2">
        <div class="shape-inner"></div>
      </div>
      <div class="hero-shape shape-2" data-parallax="-0.35">
        <div class="shape-inner"></div>
      </div>
      <div class="hero-shape shape-3" data-parallax="-0.15">
        <div class="shape-inner"></div>
      </div>
      <div class="hero-shape shape-4" data-parallax="-0.3">
        <div class="shape-inner"></div>
      </div>
    </div>

    <div class="container hero-content">
      <div class="hero-badge fade-in-section">
        <span class="badge-dot"></span>
        Next-Gen Collaboration
      </div>
      <h1 class="text-hero hero-title fade-in-section">
        Build.<br>
        <span class="text-gradient">Collaborate.</span><br>
        Grow.
      </h1>
      <p class="text-body-lg hero-subtitle fade-in-section">
        A unified platform where professionals connect, share skills,<br class="hide-mobile">
        manage projects, and grow together.
      </p>
      <div class="hero-actions fade-in-section">
        <a href="auth.php" class="btn btn-primary btn-lg">
          Get Started
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
        <a href="#modules" class="btn btn-secondary btn-lg">
          Explore Modules
        </a>
      </div>

      <!-- Stats -->
      <div class="hero-stats fade-in-section">
        <div class="stat-item">
          <span class="stat-number" data-target="12000">0</span><span class="stat-suffix">+</span>
          <span class="stat-label">Professionals</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="3500">0</span><span class="stat-suffix">+</span>
          <span class="stat-label">Projects</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-number" data-target="850">0</span><span class="stat-suffix">+</span>
          <span class="stat-label">Skills</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== MODULES OVERVIEW ===== -->
  <section class="section section-modules" id="modules">
    <div class="container">
      <div class="section-header fade-in-section">
        <span class="section-tag">Platform Modules</span>
        <h2 class="text-h1">Everything you need,<br><span class="text-gradient">in one place.</span></h2>
        <p class="text-body-lg">Six powerful modules designed to supercharge your professional journey.</p>
      </div>

      <div class="modules-grid grid grid-3 fade-in-section">
        <!-- Module 1: Auth & Profile -->
        <a href="auth.php" class="module-card glass-card tilt-card stagger-item" id="module-auth">
          <div class="module-icon">
            <svg class="icon-gradient-primary" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <h3 class="module-title">User Profiles</h3>
          <p class="module-desc">Create your professional identity with rich profiles, skills showcasing, and certificate management.</p>
          <div class="module-arrow">â†’</div>
        </a>

        <!-- Module 2: Social -->
        <a href="social.php" class="module-card glass-card tilt-card stagger-item" id="module-social">
          <div class="module-icon module-icon-cyan">
            <svg class="icon-gradient-primary" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          </div>
          <h3 class="module-title">Social Feed</h3>
          <p class="module-desc">Share updates, discuss ideas, and engage with the community through posts and comments.</p>
          <div class="module-arrow">â†’</div>
        </a>

        <!-- Module 3: Skills -->
        <a href="skills.php" class="module-card glass-card tilt-card stagger-item" id="module-skills">
          <div class="module-icon module-icon-purple">
            <svg class="icon-gradient-purple" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <h3 class="module-title">Skills & Certs</h3>
          <p class="module-desc">Showcase your expertise, earn certificates, and track your professional growth over time.</p>
          <div class="module-arrow">â†’</div>
        </a>

        <!-- Module 4: Projects -->
        <a href="projects.php" class="module-card glass-card tilt-card stagger-item" id="module-projects">
          <div class="module-icon module-icon-green">
            <svg class="icon-gradient-success" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          </div>
          <h3 class="module-title">Projects</h3>
          <p class="module-desc">Manage collaborative projects, track timelines, and coordinate your team effortlessly.</p>
          <div class="module-arrow">â†’</div>
        </a>

        <!-- Module 5: Reviews -->
        <a href="reviews.php" class="module-card glass-card tilt-card stagger-item" id="module-reviews">
          <div class="module-icon module-icon-amber">
            <svg class="icon-gradient-warning" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
          </div>
          <h3 class="module-title">Reviews</h3>
          <p class="module-desc">Give and receive meaningful feedback with an interactive rating and review system.</p>
          <div class="module-arrow">â†’</div>
        </a>

        <!-- Module 6: Challenges -->
        <a href="challenges.php" class="module-card glass-card tilt-card stagger-item" id="module-challenges">
          <div class="module-icon module-icon-rose">
            <svg class="icon-gradient-danger" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          </div>
          <h3 class="module-title">Challenges</h3>
          <p class="module-desc">Test your knowledge with daily quizzes and coding challenges to level up your skills.</p>
          <div class="module-arrow">â†’</div>
        </a>
      </div>
    </div>
  </section>

  <section class="section section-value" id="value">
    <div class="container">
      <div class="section-header fade-in-section">
        <span class="section-tag">Why Diversity.is</span>
        <h2 class="text-h1">Built for modern teams,<br><span class="text-gradient">designed for real impact.</span></h2>
        <p class="text-body-lg">A complete experience that combines collaboration, growth, and execution in one beautiful workspace.</p>
      </div>
      <div class="value-grid grid grid-3 fade-in-section">
        <article class="value-card glass-card tilt-card stagger-item">
          <i data-lucide="layers" class="w-6 h-6 icon-gradient-primary"></i>
          <h3>Unified Workspace</h3>
          <p>Profiles, projects, reviews, social collaboration, and dashboard intelligence in one place.</p>
        </article>
        <article class="value-card glass-card tilt-card stagger-item">
          <i data-lucide="sparkles" class="w-6 h-6 icon-gradient-purple"></i>
          <h3>AI-Ready Workflows</h3>
          <p>Assistive insights and smart suggestions to help teams decide faster and execute better.</p>
        </article>
        <article class="value-card glass-card tilt-card stagger-item">
          <i data-lucide="shield-check" class="w-6 h-6 icon-gradient-success"></i>
          <h3>Trust and Quality</h3>
          <p>Role-aware operations, transparent progress, and measurable outcomes across every module.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="section section-testimonials" id="testimonials">
    <div class="container">
      <div class="section-header fade-in-section">
        <span class="section-tag">Community Voices</span>
        <h2 class="text-h1">Loved by creators,<br><span class="text-gradient">teams, and managers.</span></h2>
      </div>
      <div class="testimonials-grid grid grid-3 fade-in-section">
        <article class="testimonial-card glass-card stagger-item">
          <p>â€œDiversity.is helped us reduce onboarding chaos and made project collaboration feel effortless.â€</p>
          <h4>Lina O.</h4>
          <span>Product Lead</span>
        </article>
        <article class="testimonial-card glass-card stagger-item">
          <p>â€œThe dashboard is clean, powerful, and fast. We finally have visibility without complexity.â€</p>
          <h4>Marc D.</h4>
          <span>Operations Manager</span>
        </article>
        <article class="testimonial-card glass-card stagger-item">
          <p>â€œOur team engagement jumped in two weeks thanks to the social + skills ecosystem.â€</p>
          <h4>Rania K.</h4>
          <span>Community Builder</span>
        </article>
      </div>
    </div>
  </section>

  <section class="section section-faq" id="faq">
    <div class="container">
      <div class="section-header fade-in-section">
        <span class="section-tag">FAQ</span>
        <h2 class="text-h1">Everything clear,<br><span class="text-gradient">before you even start.</span></h2>
      </div>
      <div class="faq-list fade-in-section">
        <details class="faq-item glass-card" open>
          <summary>Is Diversity.is suitable for small and large teams?</summary>
          <p>Yes. The platform scales from solo creators to multi-team organizations with modular workflows.</p>
        </details>
        <details class="faq-item glass-card">
          <summary>Can I use dashboard features without complex setup?</summary>
          <p>Absolutely. The dashboard is designed for immediate productivity with clear actions and guided insights.</p>
        </details>
        <details class="faq-item glass-card">
          <summary>Do I need to pay to start?</summary>
          <p>No. You can start for free and grow as your team and needs expand.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- ===== CTA SECTION ===== -->
  <section class="section section-cta" id="cta">
    <div class="floating-shapes">
      <div class="floating-shape"></div>
      <div class="floating-shape"></div>
    </div>
    <div class="container flex-center">
      <div class="cta-card glass-card fade-in-section">
        <div class="cta-glow"></div>
        <span class="section-tag">Ready to start?</span>
        <h2 class="text-h1">Join the <span class="text-gradient">Platform</span></h2>
        <p class="text-body-lg">Connect with thousands of professionals, showcase your talent, and accelerate your career.</p>
        <div class="cta-actions">
          <a href="auth.php" class="btn btn-primary btn-lg">Create Free Account</a>
          <a href="profile.php" class="btn btn-secondary btn-lg">View Demo Profile</a>
        </div>
        <div class="cta-features">
          <div class="cta-feature">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22D3EE" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Free forever
          </div>
          <div class="cta-feature">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22D3EE" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            No credit card
          </div>
          <div class="cta-feature">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#22D3EE" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Instant access
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="navbar-brand">
            <div class="brand-icon">â¬¡</div>
            Diversity.is
          </div>
          <p class="text-small">Professional collaboration reimagined.</p>
        </div>
        <div class="footer-links">
          <a href="auth.php">Sign In</a>
          <a href="social.php">Feed</a>
          <a href="skills.php">Skills</a>
          <a href="projects.php">Projects</a>
          <a href="reviews.php">Reviews</a>
          <a href="challenges.php">Challenges</a>
        </div>
      </div>
      <div class="divider"></div>
      <p class="text-small footer-copy">&copy; 2026 Diversity.is. All rights reserved.</p>
    </div>
  </footer>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/mouse-tracking.js"></script>
  <script src="../assets/js/home.js"></script>
</body>
</html>

