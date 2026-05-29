<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user   = currentUser();
$userId = $user['id'];
$dbUser = $pdo->prepare('SELECT * FROM users WHERE id=?');
$dbUser->execute([$userId]);
$dbUser = $dbUser->fetch();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name     = trim($_POST['name'] ?? '');
        $currency = $_POST['currency'] ?? 'THB';
        if (!$name) { $errors[] = 'Name is required.'; }
        if (empty($errors)) {
            $pdo->prepare('UPDATE users SET name=?,currency=? WHERE id=?')->execute([$name,$currency,$userId]);
            // Update session
            $_SESSION['user']['name']     = $name;
            $_SESSION['user']['currency'] = $currency;
            $success = 'Profile updated successfully.';
            $dbUser['name']     = $name;
            $dbUser['currency'] = $currency;
        }
    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $dbUser['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash,$userId]);
            $success = 'Password changed successfully.';
        }
    }
}

$pageTitle = 'Profile';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">Profile Settings</h4>
            <p class="page-subtitle">Manage your account</p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check-circle me-2"></i><?= e($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle me-2"></i><?= e($e) ?></div>
    <?php endforeach; ?>

    <div class="row g-3">
        <!-- Profile Info -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0"><i class="fa fa-user me-2"></i>Personal Information</h6></div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-lg mx-auto mb-3"><?= strtoupper(substr($dbUser['name'],0,1)) ?></div>
                        <h5><?= e($dbUser['name']) ?></h5>
                        <p class="text-muted"><?= e($dbUser['email']) ?></p>
                        <p class="text-muted small">Member since <?= date('F Y', strtotime($dbUser['created_at'])) ?></p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= e($dbUser['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" value="<?= e($dbUser['email']) ?>" disabled>
                            <div class="form-text">Email cannot be changed.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="THB" <?= $dbUser['currency']==='THB'?'selected':'' ?>>฿ THB — Thai Baht</option>
                                <option value="USD" <?= $dbUser['currency']==='USD'?'selected':'' ?>>$ USD — US Dollar</option>
                                <option value="EUR" <?= $dbUser['currency']==='EUR'?'selected':'' ?>>€ EUR — Euro</option>
                                <option value="GBP" <?= $dbUser['currency']==='GBP'?'selected':'' ?>>£ GBP — British Pound</option>
                                <option value="JPY" <?= $dbUser['currency']==='JPY'?'selected':'' ?>>¥ JPY — Japanese Yen</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fa fa-save me-2"></i>Save Changes</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0"><i class="fa fa-lock me-2"></i>Change Password</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100"><i class="fa fa-key me-2"></i>Change Password</button>
                    </form>
                </div>
            </div>

            <!-- Stats -->
            <div class="card mt-3">
                <div class="card-header"><h6 class="card-title mb-0"><i class="fa fa-chart-pie me-2"></i>Account Statistics</h6></div>
                <div class="card-body">
                    <?php
                    $stats = [
                        'Income Records' => fetchSingleValue($pdo,'SELECT COUNT(*) FROM incomes WHERE user_id=?',[$userId]),
                        'Expense Records' => fetchSingleValue($pdo,'SELECT COUNT(*) FROM expenses WHERE user_id=?',[$userId]),
                        'Active Debts'   => fetchSingleValue($pdo,'SELECT COUNT(*) FROM debts WHERE user_id=? AND status="active"',[$userId]),
                        'Payments Made'  => fetchSingleValue($pdo,'SELECT COUNT(*) FROM debt_payments WHERE user_id=?',[$userId]),
                    ];
                    foreach ($stats as $label => $val):
                    ?>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted"><?= $label ?></span>
                        <strong><?= $val ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
