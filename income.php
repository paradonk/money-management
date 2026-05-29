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

// Handle POST actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type   = $_POST['type'] ?? 'other';
        $name   = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $m      = (int)($_POST['month'] ?? $month);
        $y      = (int)($_POST['year']  ?? $year);
        $notes  = trim($_POST['notes'] ?? '');
        if (!$name || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Name and amount are required.']); exit;
        }
        $pdo->prepare('INSERT INTO incomes (user_id,type,name,amount,month,year,notes) VALUES (?,?,?,?,?,?,?)')
            ->execute([$userId,$type,$name,$amount,$m,$y,$notes]);
        echo json_encode(['success' => true, 'message' => 'Income added successfully.']);
    } elseif ($action === 'edit') {
        $id     = (int)($_POST['id'] ?? 0);
        $type   = $_POST['type'] ?? 'other';
        $name   = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $m      = (int)($_POST['month'] ?? $month);
        $y      = (int)($_POST['year']  ?? $year);
        $notes  = trim($_POST['notes'] ?? '');
        if (!$name || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Name and amount are required.']); exit;
        }
        $pdo->prepare('UPDATE incomes SET type=?,name=?,amount=?,month=?,year=?,notes=? WHERE id=? AND user_id=?')
            ->execute([$type,$name,$amount,$m,$y,$notes,$id,$userId]);
        echo json_encode(['success' => true, 'message' => 'Income updated.']);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM incomes WHERE id=? AND user_id=?')->execute([$id,$userId]);
        echo json_encode(['success' => true, 'message' => 'Income deleted.']);
    } elseif ($action === 'get') {
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM incomes WHERE id=? AND user_id=?');
        $stmt->execute([$id,$userId]);
        $row = $stmt->fetch();
        echo json_encode($row ?: ['success' => false]);
    }
    exit;
}

$incomes = $pdo->prepare('SELECT * FROM incomes WHERE user_id=? AND month=? AND year=? ORDER BY created_at DESC');
$incomes->execute([$userId, $month, $year]);
$incomes = $incomes->fetchAll();

$totalIncome = array_sum(array_column($incomes, 'amount'));

$byType = [];
foreach ($incomes as $inc) {
    $byType[$inc['type']] = ($byType[$inc['type']] ?? 0) + $inc['amount'];
}

$pageTitle = 'Income';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">Income</h4>
            <p class="page-subtitle"><?= monthName($month) . ' ' . $year ?></p>
        </div>
        <div class="d-flex gap-2">
            <form class="d-flex gap-2 me-2" method="GET">
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
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#incomeModal">
                <i class="fa fa-plus me-2"></i>Add Income
            </button>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-income">
                <div class="kpi-icon"><i class="fa fa-coins"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Income</div>
                    <div class="kpi-value"><?= formatCurrency($totalIncome, $currency) ?></div>
                    <div class="kpi-sub"><?= count($incomes) ?> entries</div>
                </div>
            </div>
        </div>
        <?php
        $typeColors = ['salary'=>'primary','bonus'=>'success','freelance'=>'info','passive'=>'warning','other'=>'secondary'];
        foreach ($byType as $type => $amt):
        ?>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small"><?= ucfirst($type) ?></div>
                            <div class="fw-700 fs-5"><?= formatCurrency($amt, $currency) ?></div>
                        </div>
                        <span class="badge bg-<?= $typeColors[$type] ?? 'secondary' ?>"><?= round($totalIncome > 0 ? ($amt/$totalIncome*100) : 0) ?>%</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Income Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="card-title mb-0">Income Records</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th>Notes</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incomes)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa fa-inbox fa-3x mb-3 d-block"></i>
                            No income recorded for this month.
                            <a href="#" data-bs-toggle="modal" data-bs-target="#incomeModal">Add now</a>
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($incomes as $i => $inc): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><div class="fw-500"><?= e($inc['name']) ?></div></td>
                            <td><?= incomeTypeBadge($inc['type']) ?></td>
                            <td class="text-end text-success fw-600"><?= formatCurrency($inc['amount'], $currency) ?></td>
                            <td class="text-muted small"><?= e($inc['notes'] ?? '—') ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editIncome(<?= $inc['id'] ?>)">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteIncome(<?= $inc['id'] ?>, '<?= e($inc['name']) ?>')">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($incomes)): ?>
                    <tfoot>
                        <tr class="table-primary fw-700">
                            <td colspan="3" class="text-end">Total</td>
                            <td class="text-end"><?= formatCurrency($totalIncome, $currency) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Income Modal -->
<div class="modal fade" id="incomeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incomeModalTitle"><i class="fa fa-plus me-2"></i>Add Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="incomeForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add" id="incomeAction">
                    <input type="hidden" name="id" id="incomeId">
                    <div class="mb-3">
                        <label class="form-label">Income Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="incomeName" class="form-control" placeholder="e.g. Monthly Salary" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" id="incomeType" class="form-select">
                                <option value="salary">Salary</option>
                                <option value="bonus">Bonus</option>
                                <option value="freelance">Freelance</option>
                                <option value="passive">Passive Income</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="incomeAmount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Month</label>
                            <select name="month" id="incomeMonth" class="form-select">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $month ? 'selected' : '' ?>><?= monthName($i) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year</label>
                            <select name="year" id="incomeYear" class="form-select">
                                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="incomeNotes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitIncome()">
                    <i class="fa fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrfToken() ?>';

function submitIncome() {
    const form = document.getElementById('incomeForm');
    const data = new FormData(form);
    fetch(window.location.href, { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: res.message });
            }
        });
}

function editIncome(id) {
    const data = new FormData();
    data.append('csrf_token', CSRF);
    data.append('action', 'get');
    data.append('id', id);
    fetch(window.location.href, { method: 'POST', body: data })
        .then(r => r.json())
        .then(row => {
            document.getElementById('incomeModalTitle').innerHTML = '<i class="fa fa-edit me-2"></i>Edit Income';
            document.getElementById('incomeAction').value = 'edit';
            document.getElementById('incomeId').value = row.id;
            document.getElementById('incomeName').value = row.name;
            document.getElementById('incomeType').value = row.type;
            document.getElementById('incomeAmount').value = row.amount;
            document.getElementById('incomeMonth').value = row.month;
            document.getElementById('incomeYear').value = row.year;
            document.getElementById('incomeNotes').value = row.notes || '';
            new bootstrap.Modal(document.getElementById('incomeModal')).show();
        });
}

function deleteIncome(id, name) {
    Swal.fire({ title: 'Delete "' + name + '"?', icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#ef4444', confirmButtonText: 'Delete' })
        .then(result => {
            if (!result.isConfirmed) return;
            const data = new FormData();
            data.append('csrf_token', CSRF);
            data.append('action', 'delete');
            data.append('id', id);
            fetch(window.location.href, { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) location.reload();
                });
        });
}

document.getElementById('incomeModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('incomeModalTitle').innerHTML = '<i class="fa fa-plus me-2"></i>Add Income';
    document.getElementById('incomeAction').value = 'add';
    document.getElementById('incomeId').value = '';
    document.getElementById('incomeForm').reset();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
