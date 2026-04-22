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
    <title>Reset Password | LARPLARP</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="glass-card w-full max-w-md p-8">
        <h1 class="text-3xl font-bold mb-3">Reset password</h1>
        <p class="text-zinc-500 mb-6">Choose a new password for your account.</p>

        <?php if ($flashError): ?>
            <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl p-3 text-sm"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
            <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 rounded-xl p-3 text-sm"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if (!$tokenIsValid): ?>
            <div class="bg-amber-500/10 border border-amber-500/20 text-amber-700 rounded-xl p-4 text-sm">
                This reset link is invalid or has expired.
            </div>
            <div class="mt-6 text-sm text-zinc-500">
                <a href="forgot-password.php" class="text-indigo-600 hover:underline">Request a new reset link</a>
            </div>
        <?php else: ?>
            <form method="post" action="../../index.php?action=reset_password" class="space-y-5">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="space-y-2">
                    <label class="text-sm font-medium">New password</label>
                    <input type="password" name="password" class="auth-input !pl-4" required minlength="6">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Confirm password</label>
                    <input type="password" name="confirm_password" class="auth-input !pl-4" required minlength="6">
                </div>
                <button type="submit" class="btn-primary">Update password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
