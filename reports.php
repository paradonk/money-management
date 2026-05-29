<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user     = currentUser();
$userId   = $user['id'];
$currency = $user['currency'];
$year     = (int)($_GET['year'] ?? date('Y'));

// Monthly summary for the year
$monthlySummary = [];
for ($m = 1; $m <= 12; $m++) {
    $inc = (float)fetchSingleValue($pdo, 'SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND month=? AND year=?', [$userId,$m,$year]);
    $exp = (float)fetchSingleValue($pdo, 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND month=? AND year=?', [$userId,$m,$year]);
    $monthlySummary[] = ['month' => $m, 'label' => monthName($m), 'income' => $inc, 'expense' => $exp, 'net' => $inc - $exp];
}

// Debt summary
$debtSummary = $pdo->prepare('SELECT d.*, (SELECT COALESCE(SUM(amount),0) FROM debt_payments p WHERE p.debt_id=d.id) as total_paid FROM debts WHERE d.user_id=? ORDER BY d.status,d.name');
$debtSummary->execute([$userId]);
$debtSummary = $debtSummary->fetchAll();

// Payment history
$payments = $pdo->prepare('SELECT dp.*, d.name as debt_name FROM debt_payments dp JOIN debts d ON d.id=dp.debt_id WHERE dp.user_id=? ORDER BY dp.payment_date DESC LIMIT 50');
$payments->execute([$userId]);
$payments = $payments->fetchAll();

// Expense by category for year
$expByYear = $pdo->prepare('SELECT category, SUM(amount) as total FROM expenses WHERE user_id=? AND year=? GROUP BY category ORDER BY total DESC');
$expByYear->execute([$userId,$year]);
$expByYear = $expByYear->fetchAll();

$totalYearIncome  = array_sum(array_column($monthlySummary,'income'));
$totalYearExpense = array_sum(array_column($monthlySummary,'expense'));

// Export handling
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="money_report_' . $year . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Month','Income','Expense','Net']);
        foreach ($monthlySummary as $row) {
            fputcsv($out, [$row['label'], $row['income'], $row['expense'], $row['net']]);
        }
        fputcsv($out, ['TOTAL', $totalYearIncome, $totalYearExpense, $totalYearIncome - $totalYearExpense]);
        fclose($out);
        exit;
    }
}

$pageTitle = 'Reports';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">Financial Reports</h4>
            <p class="page-subtitle">Annual overview and analysis</p>
        </div>
        <div class="d-flex gap-2">
            <form class="d-flex gap-2 me-2" method="GET">
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <a href="?year=<?= $year ?>&export=csv" class="btn btn-outline-success btn-sm">
                <i class="fa fa-file-csv me-2"></i>Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-print me-2"></i>Print
            </button>
        </div>
    </div>

    <!-- Year Summary KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-income">
                <div class="kpi-icon"><i class="fa fa-coins"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Income <?= $year ?></div>
                    <div class="kpi-value"><?= formatCurrency($totalYearIncome, $currency) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-expense">
                <div class="kpi-icon"><i class="fa fa-receipt"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Expenses <?= $year ?></div>
                    <div class="kpi-value"><?= formatCurrency($totalYearExpense, $currency) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card <?= ($totalYearIncome-$totalYearExpense)>=0?'kpi-positive':'kpi-negative' ?>">
                <div class="kpi-icon"><i class="fa fa-chart-line"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Net Savings</div>
                    <div class="kpi-value"><?= formatCurrency($totalYearIncome - $totalYearExpense, $currency) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-debt">
                <div class="kpi-icon"><i class="fa fa-percentage"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Savings Rate</div>
                    <div class="kpi-value"><?= $totalYearIncome > 0 ? round(($totalYearIncome-$totalYearExpense)/$totalYearIncome*100,1) : 0 ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0"><i class="fa fa-chart-bar me-2 text-primary"></i>Monthly Summary <?= $year ?></h6>
        </div>
        <div class="card-body"><canvas id="annualChart" height="80"></canvas></div>
    </div>

    <!-- Monthly Table -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="card-title mb-0">Month-by-Month Breakdown</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Month</th><th class="text-end">Income</th><th class="text-end">Expenses</th><th class="text-end">Net</th><th>Bar</th></tr></thead>
                    <tbody>
                    <?php foreach ($monthlySummary as $row): ?>
                    <?php $net = $row['net']; ?>
                    <tr>
                        <td class="fw-500"><?= $row['label'] ?></td>
                        <td class="text-end text-success"><?= formatCurrency($row['income'], $currency) ?></td>
                        <td class="text-end text-danger"><?= formatCurrency($row['expense'], $currency) ?></td>
                        <td class="text-end <?= $net >= 0 ? 'text-success' : 'text-danger' ?> fw-600"><?= formatCurrency($net, $currency) ?></td>
                        <td style="min-width:120px">
                            <?php if ($row['income'] > 0 || $row['expense'] > 0): ?>
                            <div class="progress" style="height:6px">
                                <?php $mx = max($totalYearIncome/12, 1); ?>
                                <div class="progress-bar bg-success" style="width:<?= min(100,round($row['income']/$mx*50)) ?>%"></div>
                                <div class="progress-bar bg-danger" style="width:<?= min(100,round($row['expense']/$mx*50)) ?>%"></div>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-700 table-light">
                            <td>Total</td>
                            <td class="text-end text-success"><?= formatCurrency($totalYearIncome, $currency) ?></td>
                            <td class="text-end text-danger"><?= formatCurrency($totalYearExpense, $currency) ?></td>
                            <td class="text-end <?= ($totalYearIncome-$totalYearExpense)>=0?'text-success':'text-danger' ?>"><?= formatCurrency($totalYearIncome-$totalYearExpense, $currency) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Debt Summary -->
    <div class="card mb-4">
        <div class="card-header"><h6 class="card-title mb-0"><i class="fa fa-file-invoice-dollar me-2 text-primary"></i>Debt Summary</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Debt Name</th><th>Category</th><th class="text-end">Original</th><th class="text-end">Remaining</th><th class="text-end">Total Paid</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($debtSummary as $d): ?>
                    <tr>
                        <td class="fw-500"><?= e($d['name']) ?></td>
                        <td><?= ucfirst(str_replace('_',' ',$d['category'])) ?></td>
                        <td class="text-end"><?= formatCurrency($d['original_amount'], $currency) ?></td>
                        <td class="text-end text-danger"><?= formatCurrency($d['remaining_balance'], $currency) ?></td>
                        <td class="text-end text-success"><?= formatCurrency($d['total_paid'], $currency) ?></td>
                        <td><?= debtStatusBadge($d['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($debtSummary)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No debts recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card">
        <div class="card-header"><h6 class="card-title mb-0"><i class="fa fa-history me-2 text-primary"></i>Payment History (Last 50)</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Debt</th><th class="text-end">Amount</th><th class="text-end">Principal</th><th class="text-end">Interest</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                        <td class="fw-500"><?= e($p['debt_name']) ?></td>
                        <td class="text-end"><?= formatCurrency($p['amount'], $currency) ?></td>
                        <td class="text-end text-primary"><?= formatCurrency($p['principal'], $currency) ?></td>
                        <td class="text-end text-danger"><?= formatCurrency($p['interest'], $currency) ?></td>
                        <td class="text-muted small"><?= e($p['notes'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No payments recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const annualData = <?= json_encode($monthlySummary) ?>;
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('annualChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: annualData.map(d => d.label),
            datasets: [
                { label: 'Income', data: annualData.map(d => d.income), backgroundColor: 'rgba(16,185,129,0.8)', borderRadius: 4 },
                { label: 'Expense', data: annualData.map(d => d.expense), backgroundColor: 'rgba(239,68,68,0.8)', borderRadius: 4 },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
