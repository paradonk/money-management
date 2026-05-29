<?php

function formatCurrency(float $amount, string $currency = 'THB'): string {
    $symbols = ['THB' => '฿', 'USD' => '$', 'EUR' => '€', 'JPY' => '¥', 'GBP' => '£'];
    $symbol  = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format($amount, 2);
}

function formatNumber(float $n): string {
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'K';
    return number_format($n, 0);
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function monthName(int $month): string {
    return date('F', mktime(0, 0, 0, $month, 1));
}

/* ── Interest calculations ─────────────────────────────────────────── */

function calculateMonthlyInterest(float $balance, float $annualRate, string $type): float {
    $r = $annualRate / 100;
    return match ($type) {
        'flat'        => 0,
        'credit_card' => $balance * ($r / 12),
        'compound'    => $balance * (pow(1 + $r / 12, 1) - 1),
        default       => $balance * ($r / 12),   // reducing / fixed
    };
}

function calculatePayoffMonths(float $balance, float $annualRate, float $monthly, string $type): int {
    if ($monthly <= 0 || $balance <= 0) return 0;
    $r      = $annualRate / 100 / 12;
    $months = 0;
    $max    = 600;
    while ($balance > 0.01 && $months < $max) {
        $interest = calculateMonthlyInterest($balance, $annualRate, $type);
        $principal = $monthly - $interest;
        if ($principal <= 0) return $max; // payment doesn't cover interest
        $balance -= $principal;
        $months++;
    }
    return $months;
}

function calculateTotalInterest(float $balance, float $annualRate, float $monthly, string $type): float {
    if ($monthly <= 0 || $balance <= 0) return 0;
    $total    = 0;
    $max      = 600;
    $payments = 0;
    while ($balance > 0.01 && $payments < $max) {
        $interest = calculateMonthlyInterest($balance, $annualRate, $type);
        $principal = $monthly - $interest;
        if ($principal <= 0) break;
        $total   += $interest;
        $balance -= $principal;
        $payments++;
    }
    return $total;
}

function payoffSchedule(float $balance, float $annualRate, float $monthly, string $type, int $maxMonths = 600): array {
    $schedule = [];
    $month    = 0;
    while ($balance > 0.01 && $month < $maxMonths) {
        $interest  = calculateMonthlyInterest($balance, $annualRate, $type);
        $principal = min($monthly - $interest, $balance);
        if ($principal <= 0) break;
        $balance -= $principal;
        $month++;
        $schedule[] = [
            'month'     => $month,
            'payment'   => $monthly,
            'principal' => round($principal, 2),
            'interest'  => round($interest, 2),
            'balance'   => round(max($balance, 0), 2),
        ];
    }
    return $schedule;
}

/* ── Dashboard summary queries ─────────────────────────────────────── */

function getDashboardSummary(PDO $pdo, int $userId, int $month, int $year): array {
    $totalIncome = (float)$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND month=? AND year=?')
        ->execute([$userId, $month, $year]) ? fetchSingleValue($pdo, 'SELECT COALESCE(SUM(amount),0) FROM incomes WHERE user_id=? AND month=? AND year=?', [$userId, $month, $year]) : 0;

    $totalExpense = fetchSingleValue($pdo, 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND month=? AND year=?', [$userId, $month, $year]);
    $totalDebt    = fetchSingleValue($pdo, 'SELECT COALESCE(SUM(remaining_balance),0) FROM debts WHERE user_id=? AND status!="paid"', [$userId]);
    $monthlyDebt  = fetchSingleValue($pdo, 'SELECT COALESCE(SUM(monthly_payment),0) FROM debts WHERE user_id=? AND status="active"', [$userId]);

    return [
        'total_income'   => (float)$totalIncome,
        'total_expense'  => (float)$totalExpense,
        'monthly_debt'   => (float)$monthlyDebt,
        'net_cash'       => (float)$totalIncome - (float)$totalExpense - (float)$monthlyDebt,
        'total_debt'     => (float)$totalDebt,
        'dti_ratio'      => $totalIncome > 0 ? round(((float)$monthlyDebt / (float)$totalIncome) * 100, 1) : 0,
    ];
}

function fetchSingleValue(PDO $pdo, string $sql, array $params = []): mixed {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function getUnreadNotificationCount(PDO $pdo, int $userId): int {
    return (int)fetchSingleValue($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$userId]);
}

function generateDebtNotifications(PDO $pdo, int $userId): void {
    $today  = (int)date('j');
    $debts  = $pdo->prepare('SELECT * FROM debts WHERE user_id=? AND status="active"');
    $debts->execute([$userId]);

    foreach ($debts->fetchAll() as $debt) {
        $dueDay = (int)$debt['due_day'];
        $diff   = $dueDay - $today;

        if ($diff >= 0 && $diff <= 5) {
            $exists = fetchSingleValue($pdo,
                'SELECT COUNT(*) FROM notifications WHERE user_id=? AND title LIKE ? AND DATE(created_at)=CURDATE()',
                [$userId, '%' . $debt['name'] . '%']
            );
            if (!$exists) {
                $pdo->prepare('INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)')
                    ->execute([
                        $userId,
                        'Payment Due: ' . $debt['name'],
                        'Your payment of ' . number_format($debt['monthly_payment'], 2) . ' is due on day ' . $dueDay . ' of this month.',
                        $diff <= 1 ? 'due_date' : 'due_date',
                    ]);
            }
        }
    }
}

function debtCategoryIcon(string $cat): string {
    return match ($cat) {
        'housing'     => 'fa-house',
        'car'         => 'fa-car',
        'credit_card' => 'fa-credit-card',
        'personal'    => 'fa-user',
        'student'     => 'fa-graduation-cap',
        'business'    => 'fa-briefcase',
        default       => 'fa-file-invoice-dollar',
    };
}

function debtStatusBadge(string $status): string {
    return match ($status) {
        'paid'    => '<span class="badge bg-success">Paid</span>',
        'overdue' => '<span class="badge bg-danger">Overdue</span>',
        default   => '<span class="badge bg-primary">Active</span>',
    };
}

function incomeTypeBadge(string $type): string {
    $colors = ['salary'=>'primary','bonus'=>'success','freelance'=>'info','passive'=>'warning','other'=>'secondary'];
    $c = $colors[$type] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst($type) . '</span>';
}

function expenseCategoryBadge(string $cat): string {
    $colors = ['food'=>'warning','transportation'=>'info','utilities'=>'primary','insurance'=>'success','internet'=>'secondary','family'=>'danger','other'=>'dark'];
    $c = $colors[$cat] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . ucfirst($cat) . '</span>';
}
