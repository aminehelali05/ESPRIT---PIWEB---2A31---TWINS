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
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in or create your account to start collaborating with professionals.">
  <title><?= $mode === 'register' ? 'Join the Community' : 'Welcome Back' ?> | Professional Networking</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
      tailwind.config = {
          darkMode: ['class', '[data-theme="dark"]']
      }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
  
  <!-- Required by Friend's UI Logic -->
  <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js"></script>

  <!-- Leaflet Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="min-h-screen overflow-x-hidden flex items-center justify-center relative py-10">
    <canvas id="particleCanvas"></canvas>

    <div style="position: fixed; top: 1rem; right: 1rem; z-index: 50;">
        <button type="button" class="theme-toggle btn-secondary p-2.5 rounded-full flex items-center justify-center shadow-lg bg-white/10 dark:bg-black/20 backdrop-blur-md border border-black/5 dark:border-white/10 hover:scale-105 transition-transform" aria-label="Toggle theme">
            <i data-lucide="moon" class="w-5 h-5 text-zinc-800 dark:text-zinc-200"></i>
        </button>
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
                    <input type="text" id="firstName" name="first_name" required class="auth-input !pl-4" placeholder="John">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Last Name</label>
                    <input type="text" id="lastName" name="last_name" required class="auth-input !pl-4" placeholder="Doe">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                    <input type="email" id="email" name="email" required class="auth-input" placeholder="john@example.com">
                </div>
            </div>

            <!-- Location Map Picker Integration -->
            <div class="space-y-2">
                <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Country / Location</label>
                <div class="relative flex gap-2">
                    <div class="relative flex-1">
                        <i data-lucide="map-pin" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="text" id="country" name="country" required readonly class="auth-input cursor-pointer" placeholder="Select location" onclick="openMapPicker()">
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
                    <span class="font-medium">Enroll Face ID (Optional)</span>
                </button>
                <div id="enrollmentStatus" class="text-sm text-center text-zinc-500 dark:text-gray-400 hidden"></div>
            </div>

            <div id="gamified-captcha-container" class="mb-4"></div>

            <button type="submit" id="registerBtn" class="btn-primary w-full py-3.5 shadow-lg shadow-blue-500/30" style="background: linear-gradient(135deg, var(--color-accent) 0%, #6366f1 100%);">
                Create Account
            </button>
        </form>

        <div class="mt-8 text-center text-sm text-zinc-500 dark:text-gray-400">
            Already have an account? <a href="auth.php?mode=login" class="text-blue-600 dark:text-cyan-400 font-semibold hover:underline">Sign In</a>
        </div>

        <?php endif; ?>

        <div class="mt-4 text-center border-t border-black/5 dark:border-white/10 pt-4">
            <a href="home.php" class="text-xs font-medium text-zinc-500 dark:text-gray-500 hover:text-zinc-800 dark:hover:text-gray-300 transition-colors flex items-center justify-center gap-1.5">
                <i data-lucide="arrow-left" class="w-3 h-3"></i> <span>Back to Hub</span>
            </a>
        </div>
    </div>

    <!-- Location Modal -->
    <div id="locationPickerModal" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden items-center justify-center z-50">
        <div class="glass-card w-full max-w-4xl p-0 m-4 relative overflow-hidden flex flex-col h-[80vh] shadow-2xl border border-white/20" style="background: var(--color-surface);">
            <div class="p-4 border-b border-black/5 dark:border-white/10 flex justify-between items-center" style="background: var(--color-bg);">
                <h3 class="text-xl font-bold text-zinc-800 dark:text-white" style="font-family: var(--font-primary);">Select Location</h3>
                <button type="button" class="text-zinc-400 hover:text-red-500 transition-colors" onclick="closeMapPicker()">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="relative flex-1 bg-zinc-100 dark:bg-zinc-900 border-x border-black/5 dark:border-white/5">
                <div id="map" class="absolute inset-0 w-full h-full"></div>
            </div>
            <div class="p-4 border-t border-black/5 dark:border-white/10" style="background: var(--color-surface);">
                <p class="mb-2 text-sm text-zinc-600 dark:text-gray-300">Selected: <span id="selectedLocationDisplay" class="font-semibold text-blue-600 dark:text-cyan-400">None</span></p>
                <button type="button" id="confirmLocationBtn" disabled onclick="confirmLocation()" class="w-full py-3 bg-blue-500 hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl text-white font-medium shadow-md transition-all">
                    Confirm Location
                </button>
            </div>
        </div>
    </div>

    <!-- Face Modal -->
    <div id="face-login-modal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden items-center justify-center z-50">
        <div class="glass-card max-w-xl w-full m-4 p-8 relative shadow-2xl border border-white/20" style="background: var(--color-surface);">
            <button id="cancel-face-login" class="absolute top-4 right-4 text-zinc-400 hover:text-red-500 transition-colors z-20">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <h2 class="text-2xl font-bold text-zinc-800 dark:text-white mb-4">Face Recognition</h2>
            <p class="text-zinc-500 dark:text-gray-400 mb-6">Position your face clearly in the camera for secure access.</p>

            <div class="relative bg-black rounded-2xl overflow-hidden mb-6 shadow-xl ring-1 ring-black/10 dark:ring-white/10 aspect-video flex-shrink-0">
                <video id="face-login-video" autoplay muted playsinline class="w-full h-full object-cover"></video>
                <canvas id="face-login-canvas" class="absolute top-0 left-0 w-full h-full object-cover"></canvas>

                <div class="absolute bottom-0 left-0 w-full bg-black/60 backdrop-blur-md p-4 border-t border-white/10">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-medium text-gray-300 tracking-wide uppercase">Scan Quality</span>
                        <span id="quality-percentage" class="text-xs font-bold text-white">0%</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-1.5 overflow-hidden">
                        <div id="quality-bar" class="bg-red-500 h-full rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between mt-3 text-[10px] uppercase font-bold tracking-wider">
                        <span id="login-quality-badge" class="px-2 py-0.5 rounded border border-white/10 bg-white/5 text-gray-400">Waiting...</span>
                        <span id="face-login-status" class="text-cyan-400">Initializing...</span>
                    </div>
                </div>
            </div>

            <button id="verify-face-btn" disabled class="btn-primary w-full py-3.5 rounded-xl disabled:opacity-50 disabled:grayscale transition-all duration-300 font-semibold shadow-lg">
                Verify Identity
            </button>
            <div id="login-troubleshooting" class="mt-6 hidden text-sm bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 p-3 rounded-lg border border-yellow-500/20"></div>
        </div>
    </div>

    <!-- Face ENROLLMENT Modal (Specifically for Register page) -->
    <div id="face-capture-modal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden items-center justify-center z-50">
        <div class="glass-card w-full max-w-2xl p-8 m-4 relative shadow-2xl border border-white/20" style="background: var(--color-surface);">
            <button id="cancel-face-capture" class="absolute top-4 right-4 text-zinc-400 hover:text-red-500 transition-colors z-20">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <h2 class="text-2xl font-bold text-zinc-800 dark:text-white mb-2">Enroll Face ID (Optional)</h2>
            <p class="text-zinc-500 dark:text-gray-400 mb-6 text-sm">Add biometric login for instant access to your account.</p>

            <div class="relative bg-black rounded-2xl overflow-hidden aspect-video shadow-xl ring-1 ring-white/10 flex-shrink-0">
                <video id="face-video" class="w-full h-full object-cover" autoplay muted playsinline></video>
                <canvas id="face-canvas" class="absolute top-0 left-0 w-full h-full object-cover"></canvas>
            </div>

            <div id="face-instructions" class="mt-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded-xl hidden">
                <p class="text-blue-600 dark:text-blue-300 text-sm font-medium flex items-center gap-2"></p>
            </div>

            <div id="capture-progress" class="mt-4 hidden">
                <div class="flex justify-between text-xs font-semibold text-zinc-500 dark:text-gray-400 mb-1 max-w-full">
                    <span class="uppercase tracking-wider">Acquisition Progress</span>
                    <span id="capture-count">0/5</span>
                </div>
                <div class="w-full bg-zinc-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div id="capture-progress-bar" class="bg-blue-500 h-full rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 mt-6">
                <button id="init-face-capture" class="flex-1 py-3 px-4 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium shadow-md transition-all">
                    Start Camera
                </button>
                <button id="capture-face-btn" class="flex-1 py-3 px-4 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl font-medium shadow-md transition-all hidden">
                    Capture Photo
                </button>
                <button id="skip-face-enrollment" class="py-3 px-6 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded-xl font-medium transition-all shadow-sm">
                    Skip Link
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts Injection -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../auth/js/face-config.js"></script>
    <script src="../../auth/js/auth-utils.js"></script>
    <script src="../../auth/js/face-utils.js"></script>
    <?php if ($mode === 'login'): ?>
        <script src="../../auth/js/face-login.js"></script>
    <?php else: ?>
        <script src="../../auth/js/password-strength.js"></script>
        <script src="../../auth/js/map-picker.js"></script>
        <script src="../../auth/js/face-capture.js"></script>
        <!-- Need a stub so friend's register.js logic isn't strictly requested. Instead, we are posting the form native via PHP inside Auth.php -->
    <?php endif; ?>
    <script src="../../auth/js/gamified-captcha.js"></script>

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
</body>
</html>
