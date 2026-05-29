<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $currency = $_POST['currency'] ?? 'THB';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $exists = fetchSingleValue($pdo, 'SELECT COUNT(*) FROM users WHERE email=?', [$email]);
            if ($exists) {
                $error = 'This email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('INSERT INTO users (name, email, password, currency) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $email, $hash, $currency]);
                flashSet('success', 'Account created! Please sign in.');
                header('Location: ' . APP_URL . '/login.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= APP_NAME ?></title>
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
            <p class="text-muted">Create your free account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label fw-500">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-user text-muted"></i></span>
                    <input type="text" name="name" class="form-control" placeholder="Your name"
                           value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-500">Email Address <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com"
                           value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-500">Currency</label>
                <select name="currency" class="form-select">
                    <option value="THB" <?= (($_POST['currency'] ?? 'THB') === 'THB') ? 'selected' : '' ?>>฿ THB — Thai Baht</option>
                    <option value="USD" <?= (($_POST['currency'] ?? '') === 'USD') ? 'selected' : '' ?>>$ USD — US Dollar</option>
                    <option value="EUR" <?= (($_POST['currency'] ?? '') === 'EUR') ? 'selected' : '' ?>>€ EUR — Euro</option>
                    <option value="GBP" <?= (($_POST['currency'] ?? '') === 'GBP') ? 'selected' : '' ?>>£ GBP — British Pound</option>
                    <option value="JPY" <?= (($_POST['currency'] ?? '') === 'JPY') ? 'selected' : '' ?>>¥ JPY — Japanese Yen</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-500">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-500">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-lock text-muted"></i></span>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg fw-600">
                <i class="fa fa-user-plus me-2"></i>Create Account
            </button>
        </form>

        <div class="auth-divider"><span>or</span></div>
        <p class="text-center text-muted">Already have an account?
            <a href="<?= APP_URL ?>/login.php" class="text-primary fw-500">Sign in</a>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
