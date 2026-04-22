<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | LARPLARP</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="glass-card w-full max-w-md p-8">
        <h1 class="text-3xl font-bold mb-3">Forgot password</h1>
        <p class="text-zinc-500 mb-6">Enter your email address and we’ll send you a reset link.</p>

        <?php if ($flashError): ?>
            <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl p-3 text-sm"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 rounded-xl p-3 text-sm"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <form method="post" action="../../index.php?action=forgot_password_request" class="space-y-5">
            <div class="space-y-2">
                <label class="text-sm font-medium">Email</label>
                <input type="email" name="email" class="auth-input !pl-4" placeholder="you@example.com" required>
            </div>
            <button type="submit" class="btn-primary">Send reset link</button>
        </form>

        <div class="mt-6 text-sm text-zinc-500">
            <a href="auth.php" class="text-indigo-600 hover:underline">Back to sign in</a>
        </div>
    </div>
</body>
</html>
