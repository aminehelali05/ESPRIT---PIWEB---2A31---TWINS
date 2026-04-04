<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include_once(__DIR__ . '/../../Controllers/AuthController.php');

if (AuthController::isAuthenticated()) {
  header('Location: ../BackOffice/dashboard.php');
  exit;
}

$mode = isset($_GET['mode']) && strtolower((string)$_GET['mode']) === 'register' ? 'register' : 'login';
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in or create your account to start collaborating with professionals.">
  <title>Login | Professional Networking & Collaboration Platform</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg auth-classic">
  <div class="auth-bg" aria-hidden="true">
    <div class="auth-gradient-orb orb-1"></div>
    <div class="auth-gradient-orb orb-2"></div>
    <div class="auth-gradient-orb orb-3"></div>
    <canvas id="auth-network-canvas"></canvas>
  </div>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand">
        <div class="brand-icon">⬡</div>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="auth.php?mode=login" class="active">Sign In</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
      </div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <section class="section auth-section">
    <div class="container flex-center">
      <div class="auth-wrapper fade-in-section">
        <?php if ($flashError): ?>
          <div class="glass-card auth-flash auth-flash-error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
          <div class="glass-card auth-flash auth-flash-success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <div class="auth-card-3d <?= $mode === 'register' ? 'flipped' : '' ?>" id="auth-card">
          <div class="auth-face auth-front glass-card">
            <div class="auth-brand-lockup">
              <div class="auth-brand-icon"><i data-lucide="zap"></i></div>
              <h1>Welcome Back</h1>
              <p>Sign in to continue your journey</p>
            </div>

            <form class="auth-form" id="login-form" method="post" action="../../route.php?action=login">
              <div class="form-group">
                <label class="form-label" for="login-email">Email</label>
                <div class="input-with-icon">
                  <i data-lucide="mail"></i>
                  <input class="form-input" type="text" id="login-email" name="email" placeholder="you@example.com" autocomplete="username" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="login-password">Password</label>
                <div class="input-with-icon">
                  <i data-lucide="lock"></i>
                  <input class="form-input" type="password" id="login-password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                  <button type="button" class="password-toggle" data-toggle-password="login-password" aria-label="Toggle password">
                    <i data-lucide="eye"></i>
                  </button>
                </div>
              </div>

              <div class="auth-options">
                <label class="auth-checkbox">
                  <input type="checkbox" id="remember-me" name="remember_me">
                  <span class="checkmark"></span>
                  Remember me
                </label>
                <a href="../../auth/forgot_password.html" class="auth-link">Forgot password?</a>
              </div>

              <div class="auth-human-check" id="human-check">
                <div class="human-check-left">
                  <span class="human-check-icon"><i data-lucide="shield-check"></i></span>
                  <span>Verify you are human</span>
                </div>
                <span class="human-check-dot"></span>
              </div>

              <button type="submit" class="btn btn-primary btn-lg auth-submit">
                <span class="btn-label">Sign In</span>
                <span class="btn-loading"><span class="auth-spinner"></span>Signing in...</span>
              </button>

              <div class="auth-divider"><span>Or continue with</span></div>

              <button type="button" class="btn btn-secondary auth-face-btn" id="face-login-btn">
                <i data-lucide="scan-face"></i>
                Login with Face Recognition
              </button>

              <button type="button" class="btn btn-secondary auth-demo-btn" id="demo-login-btn">Demo Login (admin/admin)</button>
            </form>

            <p class="auth-toggle-text">
              Don't have an account?
              <button class="auth-toggle-btn" id="show-register" type="button">Create Account</button>
            </p>
          </div>

          <div class="auth-face auth-back glass-card">
            <div class="auth-brand-lockup auth-brand-lockup-register">
              <h1>Join the Community</h1>
              <p>Start your journey of making an impact today</p>
            </div>

            <form class="auth-form" id="register-form" method="post" action="../../route.php?action=register">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="reg-first">First Name</label>
                  <input class="form-input" type="text" id="reg-first" name="first_name" placeholder="John" autocomplete="given-name" required>
                </div>
                <div class="form-group">
                  <label class="form-label" for="reg-last">Last Name</label>
                  <input class="form-input" type="text" id="reg-last" name="last_name" placeholder="Doe" autocomplete="family-name" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="reg-email">Email</label>
                <div class="input-with-icon">
                  <i data-lucide="mail"></i>
                  <input class="form-input" type="email" id="reg-email" name="email" placeholder="john@example.com" autocomplete="email" required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="reg-password">Password</label>
                <div class="input-with-icon">
                  <i data-lucide="lock"></i>
                  <input class="form-input" type="password" id="reg-password" name="password" placeholder="Create a strong password" autocomplete="new-password" minlength="6" required>
                  <button type="button" class="password-toggle" data-toggle-password="reg-password" aria-label="Toggle password">
                    <i data-lucide="eye"></i>
                  </button>
                </div>
                <div class="password-strength" id="password-strength"><div class="strength-bar"></div></div>
              </div>

              <div class="form-group">
                <label class="form-label" for="confirm-password">Confirm Password</label>
                <div class="input-with-icon">
                  <i data-lucide="check-circle-2"></i>
                  <input class="form-input" type="password" id="confirm-password" placeholder="••••••••" autocomplete="new-password" required>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-lg auth-submit auth-submit-register">
                <span class="btn-label">Create Account</span>
                <span class="btn-loading"><span class="auth-spinner"></span>Creating account...</span>
              </button>
            </form>

            <p class="auth-toggle-text">
              Already have an account?
              <button class="auth-toggle-btn" id="show-login" type="button">Sign In</button>
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div id="auth-toast" class="auth-toast" aria-live="polite" role="status"></div>
  <div id="serverFlash" data-error="<?= htmlspecialchars((string)$flashError) ?>" data-success="<?= htmlspecialchars((string)$flashSuccess) ?>" hidden></div>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/auth.js"></script>
</body>
</html>
