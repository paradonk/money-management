<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                loginUser($user);
                flashSet('success', 'Welcome back, ' . $user['name'] . '!');
                header('Location: ' . APP_URL . '/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
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
            <div class="auth-brand-icon"><i class="fa fa-chart-pie"></i></div>
            <h1 class="auth-brand-name"><?= APP_NAME ?></h1>
            <p class="text-muted">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="fa fa-exclamation-circle"></i> <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label fw-500">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com"
                           value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-500 d-flex justify-content-between">
                    Password
                    <a href="<?= APP_URL ?>/forgot-password.php" class="text-primary small">Forgot password?</a>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-lock text-muted"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                        <i class="fa fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg fw-600">
                <i class="fa fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

        <div class="auth-divider"><span>or</span></div>
        <p class="text-center text-muted">Don't have an account?
            <a href="<?= APP_URL ?>/register.php" class="text-primary fw-500">Create one free</a>
        </p>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword() {
    const inp = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
</script>
</body>
</html>
