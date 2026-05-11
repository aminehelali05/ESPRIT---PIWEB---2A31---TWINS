<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');

$controller = new UserController();
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenIsValid = $token !== '' && $controller->isPasswordResetTokenValid($token);
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your password and recover access to your account.">
    <title>Reset Password | Professional Networking</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: ['class', '[data-theme="dark"]']
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>

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

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-gradient-to-tr from-cyan-400 to-blue-600 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-lg shadow-cyan-500/30">
                <i data-lucide="key-round" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-purple-400" style="font-family: var(--font-primary);">Reset Password</h1>
            <p class="text-zinc-500 dark:text-gray-400 mt-2">Choose a new password for your account</p>
        </div>

        <?php if (!$tokenIsValid): ?>
            <div class="bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 rounded-xl p-4 text-sm flex items-start gap-2">
                <i data-lucide="triangle-alert" class="w-4 h-4 mt-0.5"></i>
                <span>This reset link is invalid or has expired.</span>
            </div>

            <div class="mt-6 text-center">
                <a href="forgot-password.php" class="text-indigo-600 dark:text-cyan-400 font-semibold hover:underline">Request a new reset link</a>
            </div>
        <?php else: ?>
            <form method="post" action="../../index.php?action=reset_password" class="space-y-6">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="space-y-2">
                    <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">New password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="password" name="password" required minlength="6" class="auth-input" placeholder="Create a strong password">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-zinc-600 dark:text-zinc-300 ml-1">Confirm password</label>
                    <div class="relative">
                        <i data-lucide="check-circle-2" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>
                        <input type="password" name="confirm_password" required minlength="6" class="auth-input" placeholder="Repeat your new password">
                    </div>
                </div>

                <button type="submit" class="btn-primary flex items-center justify-center gap-2 shadow-[0_8px_20px_-4px_rgba(79,82,217,0.3)]">
                    <i data-lucide="save" class="w-5 h-5"></i> Update Password
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center text-sm text-zinc-500 dark:text-gray-400">
            Remembered your password? <a href="auth.php" class="text-indigo-600 dark:text-cyan-400 font-semibold hover:underline">Back to Sign In</a>
        </div>

        <div class="mt-4 text-center border-t border-black/5 dark:border-white/10 pt-4">
            <a href="home.php" class="text-xs font-medium text-zinc-500 dark:text-gray-500 hover:text-zinc-800 dark:hover:text-gray-300 transition-colors flex items-center justify-center gap-1.5">
                <i data-lucide="arrow-left" class="w-3 h-3"></i> <span>Back to Hub</span>
            </a>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

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
                for (let i = 0; i < particleCount; i++) {
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
                    if (projected.x < 0 || projected.x > canvas.width || projected.y < 0 || projected.y > canvas.height) {
                        return;
                    }

                    ctx.beginPath();
                    ctx.arc(projected.x, projected.y, projected.size, 0, Math.PI * 2);
                    ctx.fillStyle = particleColor;
                    ctx.fill();

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
