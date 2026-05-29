<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user     = currentUser();
$userId   = $user['id'];
$currency = $user['currency'];

$stmt = $pdo->prepare('SELECT * FROM debts WHERE user_id=? AND status="active" ORDER BY interest_rate DESC');
$stmt->execute([$userId]);
$activeDebts = $stmt->fetchAll();

// Build forecast data for each strategy
function buildStrategySchedule(array $debts, string $strategy, float $extraPayment = 0): array {
    if (empty($debts)) return [];
    // Sort debts by strategy
    if ($strategy === 'avalanche') {
        usort($debts, fn($a,$b) => $b['interest_rate'] <=> $a['interest_rate']);
    } elseif ($strategy === 'snowball') {
        usort($debts, fn($a,$b) => $a['remaining_balance'] <=> $b['remaining_balance']);
    }

    $balances  = array_column($debts, 'remaining_balance', 'id');
    $rates     = array_column($debts, 'interest_rate', 'id');
    $payments  = array_column($debts, 'monthly_payment', 'id');
    $types     = array_column($debts, 'interest_type', 'id');
    $names     = array_column($debts, 'name', 'id');
    $ids       = array_keys($balances);
    $order     = array_column($debts, 'id');

    $month     = 0;
    $totalPaid = 0;
    $totalInt  = 0;
    $months    = [];
    $paidOff   = [];
    $extra     = $extraPayment;

    while (count($paidOff) < count($ids) && $month < 600) {
        $month++;
        $monthData = [];
        // Find focus debt (first unpaid in order)
        $focusId = null;
        foreach ($order as $id) {
            if (!isset($paidOff[$id])) { $focusId = $id; break; }
        }

        foreach ($ids as $id) {
            if (isset($paidOff[$id])) continue;
            $balance  = $balances[$id];
            $interest = calculateMonthlyInterest($balance, $rates[$id], $types[$id]);
            $pmt      = $payments[$id];
            if ($id === $focusId) $pmt += $extra;
            $principal = min($pmt - $interest, $balance);
            if ($principal <= 0) { $principal = 0; }
            $newBal   = max(0, $balance - $principal);
            $balances[$id] = $newBal;
            $totalPaid   += $principal + $interest;
            $totalInt    += $interest;
            $monthData[$id] = ['balance' => round($newBal, 2), 'payment' => round($pmt, 2), 'principal' => round($principal,2), 'interest' => round($interest,2)];
            if ($newBal <= 0.01) {
                $paidOff[$id] = $month;
                $extra += $payments[$id]; // snowball freed payment
            }
        }
        $months[] = ['month' => $month, 'data' => $monthData, 'total_balance' => round(array_sum($balances), 2)];
    }

    return [
        'months'      => $month,
        'total_int'   => round($totalInt, 2),
        'payoff_date' => date('M Y', strtotime('+' . $month . ' months')),
        'schedule'    => array_slice($months, 0, 120),
        'paid_off'    => $paidOff,
        'names'       => $names,
    ];
}

$extra = (float)($_GET['extra'] ?? 0);

$avalanche = buildStrategySchedule($activeDebts, 'avalanche', $extra);
$snowball   = buildStrategySchedule($activeDebts, 'snowball',  $extra);
$noExtra   = buildStrategySchedule($activeDebts, 'avalanche',  0);

$pageTitle = 'Debt Forecast';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">Debt Forecast & Simulator</h4>
            <p class="page-subtitle">See when you'll be debt-free and compare payoff strategies</p>
        </div>
    </div>

    <?php if (empty($activeDebts)): ?>
    <div class="card"><div class="card-body text-center py-5">
        <i class="fa fa-check-circle fa-4x text-success mb-3 d-block"></i>
        <h4>Congratulations! You have no active debts.</h4>
        <a href="<?= APP_URL ?>/debts.php" class="btn btn-primary mt-2">View Debt History</a>
    </div></div>
    <?php else: ?>

    <!-- What-if Simulator -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fa fa-sliders-h me-2"></i>What-If Simulator</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="form-label">Extra Monthly Payment</label>
                    <div class="input-group">
                        <span class="input-group-text"><?= $currency ?></span>
                        <input type="number" name="extra" class="form-control" value="<?= $extra ?>" min="0" step="100" placeholder="0">
                    </div>
                    <div class="form-text">How much extra can you pay each month?</div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-calculator me-2"></i>Calculate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Strategy Comparison -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-secondary h-100">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fa fa-minus-circle me-2"></i>No Extra Payment</h6>
                </div>
                <div class="card-body">
                    <div class="stat-row"><span>Payoff Date</span><strong><?= $noExtra['payoff_date'] ?? 'N/A' ?></strong></div>
                    <div class="stat-row"><span>Months</span><strong><?= $noExtra['months'] ?? 0 ?></strong></div>
                    <div class="stat-row"><span>Total Interest</span><strong class="text-danger"><?= formatCurrency($noExtra['total_int'] ?? 0, $currency) ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fa fa-fire me-2"></i>Avalanche Strategy</h6>
                    <small class="opacity-75">Pay highest interest first</small>
                </div>
                <div class="card-body">
                    <div class="stat-row"><span>Payoff Date</span><strong class="text-primary"><?= $avalanche['payoff_date'] ?? 'N/A' ?></strong></div>
                    <div class="stat-row"><span>Months</span><strong><?= $avalanche['months'] ?? 0 ?></strong></div>
                    <div class="stat-row"><span>Total Interest</span><strong class="text-success"><?= formatCurrency($avalanche['total_int'] ?? 0, $currency) ?></strong></div>
                    <?php if (!empty($noExtra) && $noExtra['months'] > ($avalanche['months']??0)): ?>
                    <div class="alert alert-success py-2 px-3 mt-2 small">
                        <i class="fa fa-check me-1"></i>Save <?= $noExtra['months'] - ($avalanche['months']??0) ?> months & <?= formatCurrency(($noExtra['total_int']??0) - ($avalanche['total_int']??0), $currency) ?> interest!
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fa fa-snowflake me-2"></i>Snowball Strategy</h6>
                    <small class="opacity-75">Pay smallest balance first</small>
                </div>
                <div class="card-body">
                    <div class="stat-row"><span>Payoff Date</span><strong class="text-success"><?= $snowball['payoff_date'] ?? 'N/A' ?></strong></div>
                    <div class="stat-row"><span>Months</span><strong><?= $snowball['months'] ?? 0 ?></strong></div>
                    <div class="stat-row"><span>Total Interest</span><strong class="text-warning"><?= formatCurrency($snowball['total_int'] ?? 0, $currency) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payoff Timeline Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0"><i class="fa fa-chart-line me-2 text-primary"></i>Balance Over Time — Avalanche Strategy</h6>
        </div>
        <div class="card-body">
            <canvas id="forecastChart" height="80"></canvas>
        </div>
    </div>

    <!-- Debt Payoff Order -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fa fa-fire me-2 text-primary"></i>Avalanche — Payoff Order</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Debt</th><th class="text-center">Paid Off By</th></tr></thead>
                            <tbody>
                            <?php if (!empty($avalanche['paid_off'])): ?>
                            <?php
                                $order = $avalanche['paid_off'];
                                arsort($order);
                                $order = array_reverse($order, true);
                            ?>
                            <?php foreach ($order as $id => $mo): ?>
                            <tr>
                                <td><?= e($avalanche['names'][$id] ?? 'Debt') ?></td>
                                <td class="text-center"><?= date('M Y', strtotime('+' . $mo . ' months')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0"><i class="fa fa-snowflake me-2 text-success"></i>Snowball — Payoff Order</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Debt</th><th class="text-center">Paid Off By</th></tr></thead>
                            <tbody>
                            <?php if (!empty($snowball['paid_off'])): ?>
                            <?php
                                $sOrder = $snowball['paid_off'];
                                asort($sOrder);
                            ?>
                            <?php foreach ($sOrder as $id => $mo): ?>
                            <tr>
                                <td><?= e($snowball['names'][$id] ?? 'Debt') ?></td>
                                <td class="text-center"><?= date('M Y', strtotime('+' . $mo . ' months')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
<?php if (!empty($avalanche['schedule'])): ?>
const scheduleData = <?= json_encode(array_map(fn($m) => $m['total_balance'], $avalanche['schedule'])) ?>;
const scheduleLabels = <?= json_encode(array_map(fn($m) => 'M' . $m['month'], $avalanche['schedule'])) ?>;
const snowballData = <?= json_encode(array_map(fn($m) => $m['total_balance'], $snowball['schedule'] ?? [])) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('forecastChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: scheduleLabels,
            datasets: [
                {
                    label: 'Avalanche Balance',
                    data: scheduleData,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                },
                {
                    label: 'Snowball Balance',
                    data: snowballData.slice(0, scheduleLabels.length),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.05)',
                    fill: false,
                    tension: 0.4,
                    borderDash: [5,5],
                    pointRadius: 0,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '฿' + (v/1000).toFixed(0) + 'K' } }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
