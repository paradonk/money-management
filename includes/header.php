<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <?php if (!empty($extraCss)) echo $extraCss; ?>
</head>
<body>
<?php
$flash = flashGet();
$user  = currentUser();
$currency = $user['currency'] ?? 'THB';
$unreadCount = isLoggedIn() ? getUnreadNotificationCount($pdo, $user['id']) : 0;
?>
<div class="app-wrapper">
<!-- Top Navbar -->
<nav class="navbar navbar-expand navbar-dark bg-sidebar fixed-top px-3" style="height:60px;left:var(--sidebar-width)">
    <button class="btn btn-icon me-2 d-lg-none" id="sidebarToggle">
        <i class="fa fa-bars text-white"></i>
    </button>
    <span class="navbar-brand fw-semibold d-lg-none"><?= APP_NAME ?></span>
    <div class="ms-auto d-flex align-items-center gap-2">
        <a href="<?= APP_URL ?>/notifications.php" class="btn btn-icon position-relative" title="Notifications">
            <i class="fa fa-bell text-white"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="badge-dot"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <div class="dropdown">
            <button class="btn btn-icon d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <div class="avatar-sm"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></div>
                <span class="text-white d-none d-md-inline fw-500"><?= e($user['name'] ?? '') ?></span>
                <i class="fa fa-chevron-down text-white-50 small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                <li><h6 class="dropdown-header"><?= e($user['email'] ?? '') ?></h6></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/profile.php"><i class="fa fa-user-cog me-2 text-muted"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
