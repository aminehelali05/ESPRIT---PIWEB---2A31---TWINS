<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

if (isset($_GET['logout'])) {
  session_unset();
  session_destroy();
  header('Location: login.php');
  exit;
}

if (isset($_GET['register'])) {
  header('Location: register.php');
  exit;
}

header('Location: login.php');
exit;

if (isset($_GET['logout'])) {
  session_unset();
  session_destroy();
  header('Location: auth.php');
  exit;
}

$userController = new UserController();
$loginError = '';
$registerError = '';
$registerSuccess = '';

// --- Handle Login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $loginError = 'Please enter both email and password.';
    } else {
      $foundUser = $userController->getUserByEmail($email);

        if ($foundUser && password_verify($password, $foundUser->getPassword())) {
            $_SESSION['user_id'] = $foundUser->getId();
            $_SESSION['user_name'] = $foundUser->getFullName();
            $_SESSION['user_email'] = $foundUser->getEmail();
            $_SESSION['user_role'] = $foundUser->getRole();

            if (strtolower($foundUser->getRole()) === 'admin') {
          header('Location: ../BackOffice/user_list.php');
            } else {
                header('Location: profile.php');
            }
            exit;
        } else {
            $loginError = 'Invalid email or password.';
        }
    }
}

// --- Handle Register ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'register') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '') {
        $registerError = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $registerError = 'Password must be at least 6 characters.';
    } elseif ($userController->emailExists($email)) {
        $registerError = 'An account with this email already exists.';
    } else {
        $newUser = new User(
            $firstName, $lastName, $email,
            password_hash($password, PASSWORD_DEFAULT),
            '', 'user', 1, date('Y-m-d H:i:s')
        );
        $userController->addUser($newUser);
        $registerSuccess = 'Account created successfully! You can now sign in.';
    }
}

$showRegister = isset($_GET['register']) || $registerError !== '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in or create your Diversity.is account.">
  <title>Sign In — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    /* Enhanced auth form matching reference screenshot */
    .auth-input-group {
      position: relative;
      margin-bottom: 1.25rem;
    }
    .auth-input-group label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--color-text-secondary);
      margin-bottom: 0.5rem;
    }
    .auth-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .auth-input-wrap .input-icon {
      position: absolute;
      left: 14px;
      color: var(--color-text-secondary);
      pointer-events: none;
      z-index: 2;
    }
    .auth-input-wrap input,
    .auth-input-wrap select {
      width: 100%;
      padding: 0.85rem 1rem 0.85rem 2.75rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 0.625rem;
      color: var(--color-text-primary);
      font-size: 0.95rem;
      font-family: inherit;
      transition: all 0.2s;
    }
    [data-theme="light"] .auth-input-wrap input,
    [data-theme="light"] .auth-input-wrap select {
      background: rgba(0,0,0,0.03);
      border-color: rgba(0,0,0,0.12);
    }
    .auth-input-wrap input:focus {
      outline: none;
      border-color: var(--color-accent);
      box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
    }
    .auth-input-wrap input::placeholder {
      color: rgba(156,163,175,0.6);
    }
    .auth-captcha-bar {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.85rem 1rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 0.625rem;
      margin-bottom: 1.25rem;
      cursor: pointer;
      transition: border-color 0.2s;
    }
    [data-theme="light"] .auth-captcha-bar {
      background: rgba(0,0,0,0.03);
      border-color: rgba(0,0,0,0.1);
    }
    .auth-captcha-bar:hover {
      border-color: var(--color-accent);
    }
    .captcha-check {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      border: 2px solid rgba(255,255,255,0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s;
      flex-shrink: 0;
    }
    .captcha-check.checked {
      background: var(--color-accent);
      border-color: var(--color-accent);
    }
    .captcha-text {
      flex: 1;
      font-size: 0.9rem;
      color: var(--color-text-secondary);
    }
    .auth-face-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      width: 100%;
      padding: 0.85rem;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 0.625rem;
      color: var(--color-text-primary);
      font-size: 0.95rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      margin-top: 0.75rem;
    }
    .auth-face-btn:hover {
      border-color: var(--color-accent-secondary);
      background: rgba(34,211,238,0.05);
    }
    .auth-face-btn i { color: var(--color-accent-secondary); }
    .auth-error-msg {
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.3);
      color: #FCA5A5;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      font-size: 0.85rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    [data-theme="light"] .auth-error-msg { color: #DC2626; }
    .auth-success-msg {
      background: rgba(34,197,94,0.1);
      border: 1px solid rgba(34,197,94,0.3);
      color: #86EFAC;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      font-size: 0.85rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    [data-theme="light"] .auth-success-msg { color: #16A34A; }
    .auth-card-inner {
      padding: 2.5rem;
      max-width: 480px;
      margin: 0 auto;
    }
    .auth-remember-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    .auth-remember-row label {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: var(--color-text-secondary);
      cursor: pointer;
    }
    .auth-remember-row a {
      font-size: 0.85rem;
      color: var(--color-accent-secondary);
      text-decoration: none;
    }
    .auth-remember-row a:hover { text-decoration: underline; }
    .auth-submit-btn {
      width: 100%;
      padding: 0.95rem;
      background: linear-gradient(135deg, var(--color-accent-secondary), #67e8f9);
      border: none;
      border-radius: 0.625rem;
      color: #0B0F1A;
      font-size: 1.05rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 1.25rem;
    }
    .auth-submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(34,211,238,0.25);
    }
    .auth-divider-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.25rem;
    }
    .auth-divider-row hr {
      flex: 1;
      border: none;
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    [data-theme="light"] .auth-divider-row hr { border-top-color: rgba(0,0,0,0.08); }
    .auth-divider-row span {
      font-size: 0.8rem;
      color: var(--color-text-secondary);
    }
    .auth-bottom-text {
      text-align: center;
      font-size: 0.85rem;
      color: var(--color-text-secondary);
      margin-top: 1rem;
    }
    .auth-bottom-text a {
      color: var(--color-accent-secondary);
      text-decoration: none;
      font-weight: 600;
    }
    .auth-bottom-text a:hover { text-decoration: underline; }
    .form-row-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
  </style>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <!-- Navbar -->
  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand">
        <div class="brand-icon">⬡</div>
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
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
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

          <!-- ===== LOGIN FACE ===== -->
          <div class="auth-face auth-front glass-card" style="<?= $showRegister ? 'display:none;' : '' ?>">
            <div class="auth-card-inner">
              <div class="auth-header" style="text-align:center; margin-bottom:1.5rem;">
                <h1 style="font-size:1.8rem; font-weight:700; font-style:italic; margin-bottom:0.25rem;">Welcome Back</h1>
                <p style="color:var(--color-text-secondary); font-size:0.95rem;">Sign in to continue your journey</p>
              </div>

              <?php if ($loginError): ?>
                <div class="auth-error-msg">
                  <i data-lucide="alert-circle" class="w-4 h-4"></i>
                  <?= htmlspecialchars($loginError) ?>
                </div>
              <?php endif; ?>
              <?php if ($registerSuccess): ?>
                <div class="auth-success-msg">
                  <i data-lucide="check-circle" class="w-4 h-4"></i>
                  <?= htmlspecialchars($registerSuccess) ?>
                </div>
              <?php endif; ?>

              <form method="POST" action="auth.php">
                <input type="hidden" name="form_type" value="login">

                <div class="auth-input-group">
                  <label>Email</label>
                  <div class="auth-input-wrap">
                    <i data-lucide="mail" class="w-4 h-4 input-icon"></i>
                    <input type="email" name="email" placeholder="you@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                  </div>
                </div>

                <div class="auth-input-group">
                  <label>Password</label>
                  <div class="auth-input-wrap">
                    <i data-lucide="lock" class="w-4 h-4 input-icon"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                  </div>
                </div>

                <div class="auth-remember-row">
                  <label><input type="checkbox" name="remember"> Remember me</label>
                  <a href="#">Forgot password?</a>
                </div>

                <div class="auth-captcha-bar" id="captchaBar">
                  <div class="captcha-check" id="captchaCheck"></div>
                  <span class="captcha-text">Verify you are human</span>
                  <i data-lucide="shield-check" class="w-4 h-4" style="color:var(--color-text-secondary);"></i>
                </div>

                <button type="submit" class="auth-submit-btn">Sign In</button>
              </form>

              <div class="auth-divider-row">
                <hr><span>Or continue with</span><hr>
              </div>

              <button class="auth-face-btn" id="faceLoginBtn">
                <i data-lucide="scan-face" class="w-5 h-5"></i>
                Login with Face Recognition
              </button>

              <p class="auth-bottom-text">
                Don't have an account? <a href="auth.php?register=1">Create one</a>
              </p>
            </div>
          </div>

          <!-- ===== REGISTER FACE ===== -->
          <div class="auth-face auth-back glass-card" style="<?= $showRegister ? '' : 'display:none;' ?>">
            <div class="auth-card-inner">
              <div class="auth-header" style="text-align:center; margin-bottom:1.5rem;">
                <h1 style="font-size:1.8rem; font-weight:700; font-style:italic; margin-bottom:0.25rem;">Create Account</h1>
                <p style="color:var(--color-text-secondary); font-size:0.95rem;">Join the Diversity.is community</p>
              </div>

              <?php if ($registerError): ?>
                <div class="auth-error-msg">
                  <i data-lucide="alert-circle" class="w-4 h-4"></i>
                  <?= htmlspecialchars($registerError) ?>
                </div>
              <?php endif; ?>

              <form method="POST" action="auth.php">
                <input type="hidden" name="form_type" value="register">

                <div class="form-row-2">
                  <div class="auth-input-group">
                    <label>First name</label>
                    <div class="auth-input-wrap">
                      <i data-lucide="user" class="w-4 h-4 input-icon"></i>
                      <input type="text" name="first_name" placeholder="John" required
                             value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                  </div>
                  <div class="auth-input-group">
                    <label>Last name</label>
                    <div class="auth-input-wrap">
                      <i data-lucide="user" class="w-4 h-4 input-icon"></i>
                      <input type="text" name="last_name" placeholder="Doe" required
                             value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                  </div>
                </div>

                <div class="auth-input-group">
                  <label>Email</label>
                  <div class="auth-input-wrap">
                    <i data-lucide="mail" class="w-4 h-4 input-icon"></i>
                    <input type="email" name="email" placeholder="john@example.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                  </div>
                </div>

                <div class="auth-input-group">
                  <label>Password</label>
                  <div class="auth-input-wrap">
                    <i data-lucide="lock" class="w-4 h-4 input-icon"></i>
                    <input type="password" name="password" placeholder="Min 6 characters" required minlength="6">
                  </div>
                </div>

                <button type="submit" class="auth-submit-btn">Create Account</button>
              </form>

              <p class="auth-bottom-text">
                Already have an account? <a href="auth.php">Sign in</a>
              </p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
  <script>
    // Init Lucide
    if (window.lucide) lucide.createIcons();

    // Captcha mock interaction
    const captchaBar = document.getElementById('captchaBar');
    const captchaCheck = document.getElementById('captchaCheck');
    if (captchaBar) {
      captchaBar.addEventListener('click', () => {
        captchaCheck.classList.toggle('checked');
        if (captchaCheck.classList.contains('checked')) {
          captchaCheck.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        } else {
          captchaCheck.innerHTML = '';
        }
      });
    }
  </script>
</body>
</html>
