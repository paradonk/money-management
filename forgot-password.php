<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$message = '';
$type    = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request.';
        $type    = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        $user  = $pdo->prepare('SELECT * FROM users WHERE email=?');
        $user->execute([$email]);
        $user = $user->fetch();

        // Always show success to prevent email enumeration
        $message = 'If that email is registered, a reset link has been sent.';
        $type    = 'success';

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?')
                ->execute([$token, $expires, $user['id']]);
            // In production: send email with reset link
            // mail($email, 'Password Reset', APP_URL . '/reset-password.php?token=' . $token);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="auth-brand-icon"><i class="fa fa-key"></i></div>
            <h1 class="auth-brand-name">Reset Password</h1>
            <p class="text-muted">Enter your email to receive a reset link</p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $type ?>"><i class="fa fa-info-circle me-2"></i><?= e($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="mb-4">
                <label class="form-label fw-500">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="fa fa-paper-plane me-2"></i>Send Reset Link
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= APP_URL ?>/login.php" class="text-muted"><i class="fa fa-arrow-left me-1"></i>Back to login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
