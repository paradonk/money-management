<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user     = currentUser();
$userId   = $user['id'];
$currency = $user['currency'];
$month    = (int)($_GET['month'] ?? date('n'));
$year     = (int)($_GET['year']  ?? date('Y'));

generateDebtNotifications($pdo, $userId);

$summary = getDashboardSummary($pdo, $userId, $month, $year);

// Monthly trend (last 6 months)
$trend = [];
for ($i = 5; $i >= 0; $i--) {
    $m = $month - $i;
    $y = $year;
    while ($m <= 0) { $m += 12; $y--; }
    $inc = (float)fetchSingleValue($pdo, 'SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND month=? AND year=?', [$userId,$m,$y]);
    $exp = (float)fetchSingleValue($pdo, 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND month=? AND year=?', [$userId,$m,$y]);
    $trend[] = ['label' => monthName($m) . ' ' . $y, 'income' => $inc, 'expense' => $exp];
}

// Debt distribution
$debts = $pdo->prepare('SELECT name, remaining_balance, category FROM debts WHERE user_id=? AND status!="paid" ORDER BY remaining_balance DESC');
$debts->execute([$userId]);
$debts = $debts->fetchAll();

// Recent income
$recentIncome = $pdo->prepare('SELECT * FROM incomes WHERE user_id=? AND month=? AND year=? ORDER BY created_at DESC LIMIT 5');
$recentIncome->execute([$userId, $month, $year]);
$recentIncome = $recentIncome->fetchAll();

// Recent expenses
$recentExpenses = $pdo->prepare('SELECT * FROM expenses WHERE user_id=? AND month=? AND year=? ORDER BY created_at DESC LIMIT 5');
$recentExpenses->execute([$userId, $month, $year]);
$recentExpenses = $recentExpenses->fetchAll();

// Upcoming debt payments
$upcomingDebts = $pdo->prepare('SELECT * FROM debts WHERE user_id=? AND status="active" ORDER BY due_day ASC LIMIT 5');
$upcomingDebts->execute([$userId]);
$upcomingDebts = $upcomingDebts->fetchAll();

// Expense by category
$expByCategory = $pdo->prepare('SELECT category, SUM(amount) as total FROM expenses WHERE user_id=? AND month=? AND year=? GROUP BY category ORDER BY total DESC');
$expByCategory->execute([$userId, $month, $year]);
$expByCategory = $expByCategory->fetchAll();

$flash    = flashGet();
$pageTitle = 'Dashboard';
?>
<?php ob_start(); ?>
<script>
const trendData = <?= json_encode($trend) ?>;
const debtData  = <?= json_encode($debts) ?>;
const expCatData = <?= json_encode($expByCategory) ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/charts.js"></script>
<?php $extraJs = ob_get_clean(); ?>

<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mx-3 mt-3" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4 class="page-title">Dashboard</h4>
            <p class="page-subtitle">Financial overview for <?= monthName($month) . ' ' . $year ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form class="d-flex gap-2" method="GET">
                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $i == $month ? 'selected' : '' ?>><?= monthName($i) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card kpi-income">
                <div class="kpi-icon"><i class="fa fa-arrow-down"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Income</div>
                    <div class="kpi-value"><?= formatCurrency($summary['total_income'], $currency) ?></div>
                    <div class="kpi-sub">This month</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card kpi-expense">
                <div class="kpi-icon"><i class="fa fa-arrow-up"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Expenses</div>
                    <div class="kpi-value"><?= formatCurrency($summary['total_expense'], $currency) ?></div>
                    <div class="kpi-sub"><?= formatCurrency($summary['monthly_debt'], $currency) ?> debt payments</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card <?= $summary['net_cash'] >= 0 ? 'kpi-positive' : 'kpi-negative' ?>">
                <div class="kpi-icon"><i class="fa fa-wallet"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Net Cash Flow</div>
                    <div class="kpi-value"><?= formatCurrency($summary['net_cash'], $currency) ?></div>
                    <div class="kpi-sub">After all payments</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card kpi-debt">
                <div class="kpi-icon"><i class="fa fa-file-invoice-dollar"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Debt</div>
                    <div class="kpi-value"><?= formatCurrency($summary['total_debt'], $currency) ?></div>
                    <div class="kpi-sub">DTI: <?= $summary['dti_ratio'] ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="fa fa-chart-bar me-2 text-primary"></i>Income vs Expenses (6 months)</h6>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="110"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="fa fa-chart-pie me-2 text-primary"></i>Debt Distribution</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (empty($debts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa fa-check-circle fa-3x mb-3 text-success"></i>
                        <p>No active debts!</p>
                    </div>
                    <?php else: ?>
                    <canvas id="debtPieChart" height="220"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense by Category + Upcoming Payments -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="fa fa-receipt me-2 text-primary"></i>Expenses by Category</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($expByCategory)): ?>
                    <div class="text-center text-muted py-4">No expenses recorded this month</div>
                    <?php else: ?>
                    <canvas id="expCatChart" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="card-title mb-0"><i class="fa fa-calendar me-2 text-primary"></i>Upcoming Payments</h6>
                    <a href="<?= APP_URL ?>/debts.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Debt</th><th>Due Day</th><th class="text-end">Amount</th><th>Progress</th></tr></thead>
                            <tbody>
                            <?php foreach ($upcomingDebts as $d): ?>
                            <?php
                                $pct = $d['original_amount'] > 0
                                    ? round((1 - $d['remaining_balance'] / $d['original_amount']) * 100, 0)
                                    : 0;
                                $today = (int)date('j');
                                $isUrgent = (int)$d['due_day'] - $today >= 0 && (int)$d['due_day'] - $today <= 5;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-500"><?= e($d['name']) ?></div>
                                    <div class="text-muted small"><?= ucfirst(str_replace('_',' ',$d['category'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $isUrgent ? 'bg-warning text-dark' : 'bg-light text-dark' ?>">
                                        Day <?= $d['due_day'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-600"><?= formatCurrency($d['monthly_payment'], $currency) ?></td>
                                <td style="min-width:100px">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px">
                                            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <span class="small text-muted"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upcomingDebts)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No active debts</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="card-title mb-0"><i class="fa fa-wallet me-2 text-success"></i>Recent Income</h6>
                    <a href="<?= APP_URL ?>/income.php" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <tbody>
                            <?php foreach ($recentIncome as $inc): ?>
                            <tr>
                                <td><div class="fw-500"><?= e($inc['name']) ?></div><?= incomeTypeBadge($inc['type']) ?></td>
                                <td class="text-end text-success fw-600"><?= formatCurrency($inc['amount'], $currency) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentIncome)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No income recorded</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="card-title mb-0"><i class="fa fa-receipt me-2 text-danger"></i>Recent Expenses</h6>
                    <a href="<?= APP_URL ?>/expenses.php" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <tbody>
                            <?php foreach ($recentExpenses as $exp): ?>
                            <tr>
                                <td><div class="fw-500"><?= e($exp['name']) ?></div><?= expenseCategoryBadge($exp['category']) ?></td>
                                <td class="text-end text-danger fw-600"><?= formatCurrency($exp['amount'], $currency) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentExpenses)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">No expenses recorded</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
