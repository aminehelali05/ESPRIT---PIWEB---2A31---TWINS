<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in or create your Diversity.is account to start collaborating with professionals.">
  <title>Sign In â€” Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <!-- Navbar -->
  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand">
        <div class="brand-icon">â¬¡</div>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php">Reviews</a>
        <a href="challenges.php">Challenges</a>
      </div>
      <div class="navbar-actions">
        <a href="addUser.php" class="btn btn-primary btn-sm">Create Account</a>
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
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <!-- Auth Section -->
  <section class="section auth-section">
    <div class="floating-shapes">
      <div class="floating-shape"></div>
      <div class="floating-shape"></div>
      <div class="floating-shape"></div>
    </div>

    <div class="container flex-center">
      <div class="auth-wrapper fade-in-section">
        <div class="auth-card-3d" id="auth-card">
          <!-- Front: Login -->
          <div class="auth-face auth-front glass-card">
            <div class="auth-header">
              <h1 class="text-h2">Welcome <span class="text-gradient">Back</span></h1>
              <p class="text-body-lg">Sign in to your account</p>
            </div>
            <form class="auth-form" id="login-form">
              <div class="form-group">
                <label class="form-label" for="login-email">Email</label>
                <input class="form-input" type="email" id="login-email" placeholder="Enter your email" autocomplete="email">
              </div>
              <div class="form-group">
                <label class="form-label" for="login-password">Password</label>
                <input class="form-input" type="password" id="login-password" placeholder="Enter your password" autocomplete="current-password">
              </div>
              <div class="auth-options">
                <label class="auth-checkbox">
                  <input type="checkbox" id="remember-me">
                  <span class="checkmark"></span>
                  Remember me
                </label>
                <a href="#" class="auth-link">Forgot password?</a>
              </div>
              <button type="submit" class="btn btn-primary btn-lg auth-submit">Sign In</button>
            </form>
            <div class="auth-divider">
              <span>or</span>
            </div>
            <div class="auth-socials">
              <button class="btn btn-secondary auth-social-btn" id="google-btn">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z"/></svg>
                Google
              </button>
              <button class="btn btn-secondary auth-social-btn" id="github-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                GitHub
              </button>
            </div>
            <p class="auth-toggle-text">
              Don't have an account? <a href="addUser.php" class="auth-link">Create one</a>
            </p>
          </div>

          <!-- Back: Register -->
          <div class="auth-face auth-back glass-card">
            <div class="auth-header">
              <h1 class="text-h2">Create <span class="text-gradient">Account</span></h1>
              <p class="text-body-lg">Join thousands of professionals</p>
            </div>
            <form class="auth-form" id="register-form">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="reg-first">First name</label>
                  <input class="form-input" type="text" id="reg-first" placeholder="John" autocomplete="given-name">
                </div>
                <div class="form-group">
                  <label class="form-label" for="reg-last">Last name</label>
                  <input class="form-input" type="text" id="reg-last" placeholder="Doe" autocomplete="family-name">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="reg-email">Email</label>
                <input class="form-input" type="email" id="reg-email" placeholder="john@example.com" autocomplete="email">
              </div>
              <div class="form-group">
                <label class="form-label" for="reg-password">Password</label>
                <input class="form-input" type="password" id="reg-password" placeholder="Min 8 characters" autocomplete="new-password">
                <div class="password-strength" id="password-strength">
                  <div class="strength-bar"></div>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-lg auth-submit">Create Account</button>
            </form>
            <p class="auth-toggle-text">
              Already have an account? <button class="auth-toggle-btn" id="show-login">Sign in</button>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/mouse-tracking.js"></script>
  <script src="../assets/js/auth.js"></script>
</body>
</html>


