<<<<<<< Updated upstream
﻿<!DOCTYPE html>
<html lang="en" data-theme="dark">
=======
<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');

if (UserController::isAuthenticated()) {
    if (UserController::isAdmin()) {
        header('Location: ../BackOffice/dashboard.php');
    } else {
        header('Location: home.php');
    }
  exit;
}

$mode = isset($_GET['mode']) && strtolower((string)$_GET['mode']) === 'register' ? 'register' : 'login';
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
>>>>>>> Stashed changes
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
      <a href="TemplateHome.php" class="navbar-brand">
        <div class="brand-icon">â¬¡</div>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="TemplateHome.php">Home</a>
        <a href="auth.html" class="active">Sign In</a>
        <a href="social.html">Feed</a>
        <a href="skills.html">Skills</a>
        <a href="projects.html">Projects</a>
        <a href="reviews.html">Reviews</a>
        <a href="challenges.html">Challenges</a>
      </div>
      <div class="navbar-actions">
        <!-- Theme Toggle -->
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
<<<<<<< Updated upstream
=======
    </div>

    <!-- MAIN CARD -->
    <div class="glass-card w-full max-w-lg p-8 m-4 relative z-10 animate-fade-in" style="backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);">
        
        <?php if ($flashError): ?>
            <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl p-3 text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($flashError) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 rounded-xl p-3 text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i> <?= htmlspecialchars($flashSuccess) ?>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
        
        <!-- LOGIN VIEW -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-gradient-to-tr from-cyan-400 to-blue-600 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-lg shadow-cyan-500/30">
                <i data-lucide="zap" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-purple-400" style="font-family: var(--font-primary);">Welcome Back</h1>
            <p class="text-zinc-500 dark:text-gray-400 mt-2">Sign in to continue your journey</p>
        </div>

        <form id="loginForm" method="post" action="../../index.php?action=login" class="space-y-6">
            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="email" id="email" name="email" required class="auth-input" placeholder="you@example.com">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="password" id="password" name="password" required class="auth-input" placeholder="••••••••">
                </div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="remember_me" class="w-4 h-4 rounded text-cyan-600 focus:ring-cyan-500">
                    <span class="text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-800 dark:group-hover:text-white transition-colors">Remember me</span>
                </label>
                <a href="../../auth/forgot_password.html" class="text-indigo-600 dark:text-cyan-400 hover:underline transition-colors font-medium">Forgot password?</a>
            </div>

            <div id="gamified-captcha-container" class="mb-6"></div>

            <button type="submit" id="loginSubmitBtn" class="btn-primary flex items-center justify-center gap-2 shadow-[0_8px_20px_-4px_rgba(79,82,217,0.3)]">
                <i data-lucide="log-in" class="w-5 h-5"></i> Sign In
            </button>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-zinc-200 dark:border-zinc-800"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 text-zinc-400" style="background-color: var(--color-surface); border-radius: 999px;">Or continue with</span>
                </div>
            </div>

            <button type="button" id="face-login-btn" class="w-full py-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-all duration-300 flex items-center justify-center gap-3 group shadow-sm">
                <i data-lucide="scan-face" class="w-5 h-5 text-indigo-500 dark:text-cyan-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-zinc-700 dark:text-zinc-100 font-medium">Face Recognition</span>
            </button>
        </form>

        <div class="mt-8 text-center text-sm text-zinc-500 dark:text-gray-400">
            Don't have an account? <a href="auth.php?mode=register" class="text-indigo-600 dark:text-cyan-400 font-semibold hover:underline">Create Account</a>
        </div>

        <?php else: ?>

        <!-- REGISTER VIEW -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-purple-400">Join the Community</h1>
            <p class="text-zinc-500 dark:text-gray-400 mt-2">Start your journey of making an impact today</p>
        </div>

        <form id="registerForm" method="post" action="../../index.php?action=register" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">First Name</label>
                    <input type="text" id="firstName" name="first_name" required class="auth-input !pl-4" placeholder="Amine">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Last Name</label>
                    <input type="text" id="lastName" name="last_name" required class="auth-input !pl-4" placeholder="HELALI">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="email" id="email" name="email" required class="auth-input" placeholder="helali@example.com">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Account Type</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="auth-type-option">
                        <input type="radio" name="account_type" value="freelancer" checked>
                        <span>Freelancer</span>
                    </label>
                    <label class="auth-type-option">
                        <input type="radio" name="account_type" value="client">
                        <span>Client</span>
                    </label>
                </div>
            </div>

            <!-- Location Map Picker Integration -->
            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Country / Location</label>
                <div class="relative flex gap-2">
                    <div class="relative flex-1">
                        <i data-lucide="map-pin" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" id="country" name="country" readonly class="auth-input cursor-pointer" value="Unknown" placeholder="Unknown" onclick="openMapPicker()">
                    </div>
                    <button type="button" onclick="openMapPicker()" class="px-4 py-2 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-xl border border-zinc-200 dark:border-zinc-700 transition-colors shadow-sm">
                        <i data-lucide="map" class="w-5 h-5"></i>
                    </button>
                </div>
                <input type="hidden" id="latitude" name="latitude">
                <input type="hidden" id="longitude" name="longitude">
                <input type="hidden" id="city" name="city">
                <input type="hidden" id="fullAddress" name="fullAddress">
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <label for="password" class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-zinc-400"></i>
                    <input id="password" name="password" type="password" required class="auth-input" placeholder="Create a strong password">
                </div>
            </div>

            <!-- Password Strength Meter -->
            <div class="space-y-2" id="passwordStrengthContainer" style="display: none;">
                <div class="flex gap-1 h-1">
                    <div class="h-full w-1/4 rounded-full bg-zinc-200 dark:bg-zinc-700 transition-colors duration-300" id="strengthBar1"></div>
                    <div class="h-full w-1/4 rounded-full bg-zinc-200 dark:bg-zinc-700 transition-colors duration-300" id="strengthBar2"></div>
                    <div class="h-full w-1/4 rounded-full bg-zinc-200 dark:bg-zinc-700 transition-colors duration-300" id="strengthBar3"></div>
                    <div class="h-full w-1/4 rounded-full bg-zinc-200 dark:bg-zinc-700 transition-colors duration-300" id="strengthBar4"></div>
                </div>
                <p class="text-xs text-zinc-500 text-right" id="strengthText">Enter password</p>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Confirm Password</label>
                <div class="relative">
                    <i data-lucide="check-circle-2" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="password" id="confirmPassword" name="confirm_password" required minlength="6" class="auth-input" placeholder="••••••••">
                </div>
            </div>

            <div class="space-y-2">
                <button type="button" id="enrollFaceBtn" class="w-full px-4 py-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-gray-300 hover:text-blue-600 transition-all duration-300 flex items-center justify-center gap-3 group">
                    <i data-lucide="scan-face" class="w-5 h-5 text-blue-500 group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Enroll Face ID</span>
                </button>
                <div id="enrollmentStatus" class="text-sm text-center text-zinc-500 dark:text-gray-400 hidden"></div>
            </div>
>>>>>>> Stashed changes

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
            <a href="profile.html" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="dashboard.html" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.html" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
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
              Don't have an account? <button class="auth-toggle-btn" id="show-register">Create one</button>
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

<<<<<<< Updated upstream
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/mouse-tracking.js"></script>
  <script src="../assets/js/auth.js"></script>
=======
    <!-- Scripts Injection -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/user.js"></script>

    <script>
        if(typeof lucide !== 'undefined') lucide.createIcons();

        // Advanced Premium 3D Canvas Background Animation
        const canvas = document.getElementById('particleCanvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            let particles = [];
            const particleCount = 100;
            const focalLength = 400;

            function initCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                particles = [];
                for(let i = 0; i < particleCount; i++) {
                    particles.push({
                        x: (Math.random() - 0.5) * 2000,
                        y: (Math.random() - 0.5) * 2000,
                        z: Math.random() * 2000,
                        vx: (Math.random() - 0.5) * 1,
                        vy: (Math.random() - 0.5) * 1,
                        vz: (Math.random() - 0.5) * 2
                    });
                }
            }
            initCanvas();
            
            function project(p) {
                const scale = focalLength / (focalLength + p.z);
                return {
                    x: p.x * scale + canvas.width / 2,
                    y: p.y * scale + canvas.height / 2,
                    size: 2 * scale
                };
            }

            function drawNet() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                const particleColor = isDark ? 'rgba(255, 255, 255, 0.4)' : 'rgba(79, 82, 217, 0.4)';
                const lineColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(79, 82, 217, 0.1)';

                particles.sort((a, b) => b.z - a.z);

                particles.forEach((p, i) => {
                    p.x += p.vx;
                    p.y += p.vy;
                    p.z += p.vz;

                    if (p.z < -focalLength) p.z = 2000;
                    if (p.z > 2000) p.z = -focalLength + 1;

                    const projected = project(p);
                    if (projected.x < 0 || projected.x > canvas.width || projected.y < 0 || projected.y > canvas.height) return;

                    ctx.beginPath();
                    ctx.arc(projected.x, projected.y, projected.size, 0, Math.PI * 2);
                    ctx.fillStyle = particleColor;
                    ctx.fill();

                    // Connection lines for nearby particles
                    for (let j = i + 1; j < particles.length; j++) {
                        const p2 = particles[j];
                        const dist = Math.sqrt(Math.pow(p.x - p2.x, 2) + Math.pow(p.y - p2.y, 2) + Math.pow(p.z - p2.z, 2));
                        if (dist < 200) {
                            const proj2 = project(p2);
                            ctx.beginPath();
                            ctx.moveTo(projected.x, projected.y);
                            ctx.lineTo(proj2.x, proj2.y);
                            ctx.strokeStyle = lineColor;
                            ctx.stroke();
                        }
                    }
                });
                requestAnimationFrame(drawNet);
            }
            drawNet();
            window.addEventListener('resize', initCanvas);
        }
    </script>
>>>>>>> Stashed changes
</body>
</html>

