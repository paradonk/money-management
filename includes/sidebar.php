<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function navItem(string $href, string $icon, string $label, string $page, string $current): string {
    $active = ($page === $current) ? 'active' : '';
    return '<li class="nav-item">
        <a class="nav-link ' . $active . '" href="' . $href . '">
            <i class="fa ' . $icon . ' nav-icon"></i>
            <span>' . $label . '</span>
        </a>
    </li>';
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fa fa-chart-pie"></i></div>
        <span class="brand-name"><?= APP_NAME ?></span>
    </div>

    <div class="sidebar-section-label">MAIN</div>
    <ul class="nav flex-column sidebar-nav">
        <?= navItem(APP_URL.'/dashboard.php', 'fa-tachometer-alt', 'Dashboard', 'dashboard', $currentPage) ?>
        <?= navItem(APP_URL.'/income.php', 'fa-wallet', 'Income', 'income', $currentPage) ?>
        <?= navItem(APP_URL.'/expenses.php', 'fa-receipt', 'Expenses', 'expenses', $currentPage) ?>
    </ul>

    <div class="sidebar-section-label">DEBT</div>
    <ul class="nav flex-column sidebar-nav">
        <?= navItem(APP_URL.'/debts.php', 'fa-file-invoice-dollar', 'My Debts', 'debts', $currentPage) ?>
        <?= navItem(APP_URL.'/forecast.php', 'fa-chart-line', 'Forecast', 'forecast', $currentPage) ?>
    </ul>

    <div class="sidebar-section-label">MORE</div>
    <ul class="nav flex-column sidebar-nav">
        <?= navItem(APP_URL.'/reports.php', 'fa-chart-bar', 'Reports', 'reports', $currentPage) ?>
        <?= navItem(APP_URL.'/notifications.php', 'fa-bell', 'Notifications', 'notifications', $currentPage) ?>
        <?= navItem(APP_URL.'/profile.php', 'fa-user-cog', 'Profile', 'profile', $currentPage) ?>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-danger btn-sm w-100">
            <i class="fa fa-sign-out-alt me-2"></i>Logout
        </a>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
