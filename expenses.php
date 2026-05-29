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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF']); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $cat    = $_POST['category'] ?? 'other';
        $name   = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $type   = $_POST['expense_type'] ?? 'recurring';
        $m      = (int)($_POST['month'] ?? $month);
        $y      = (int)($_POST['year']  ?? $year);
        $dueDay = (int)($_POST['due_day'] ?? 1);
        $notes  = trim($_POST['notes'] ?? '');
        if (!$name || $amount <= 0) { echo json_encode(['success'=>false,'message'=>'Name and amount required']); exit; }
        $pdo->prepare('INSERT INTO expenses (user_id,category,name,amount,expense_type,month,year,due_day,notes) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$userId,$cat,$name,$amount,$type,$m,$y,$dueDay,$notes]);
        echo json_encode(['success' => true, 'message' => 'Expense added.']);
    } elseif ($action === 'edit') {
        $id     = (int)($_POST['id'] ?? 0);
        $cat    = $_POST['category'] ?? 'other';
        $name   = trim($_POST['name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $type   = $_POST['expense_type'] ?? 'recurring';
        $m      = (int)($_POST['month'] ?? $month);
        $y      = (int)($_POST['year']  ?? $year);
        $dueDay = (int)($_POST['due_day'] ?? 1);
        $notes  = trim($_POST['notes'] ?? '');
        $pdo->prepare('UPDATE expenses SET category=?,name=?,amount=?,expense_type=?,month=?,year=?,due_day=?,notes=? WHERE id=? AND user_id=?')
            ->execute([$cat,$name,$amount,$type,$m,$y,$dueDay,$notes,$id,$userId]);
        echo json_encode(['success' => true, 'message' => 'Expense updated.']);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM expenses WHERE id=? AND user_id=?')->execute([$id,$userId]);
        echo json_encode(['success' => true, 'message' => 'Expense deleted.']);
    } elseif ($action === 'get') {
        $id   = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM expenses WHERE id=? AND user_id=?');
        $stmt->execute([$id,$userId]);
        echo json_encode($stmt->fetch() ?: ['success'=>false]);
    }
    exit;
}

$expenses     = $pdo->prepare('SELECT * FROM expenses WHERE user_id=? AND month=? AND year=? ORDER BY category,name');
$expenses->execute([$userId, $month, $year]);
$expenses     = $expenses->fetchAll();
$totalExpense = array_sum(array_column($expenses, 'amount'));

$pageTitle = 'Expenses';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">Expenses</h4>
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
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expModal">
                <i class="fa fa-plus me-2"></i>Add Expense
            </button>
        </div>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-expense">
                <div class="kpi-icon"><i class="fa fa-receipt"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Expenses</div>
                    <div class="kpi-value"><?= formatCurrency($totalExpense, $currency) ?></div>
                    <div class="kpi-sub"><?= count($expenses) ?> entries</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="card-title mb-0">Expense Records</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>Name</th><th>Category</th><th>Type</th>
                            <th>Due Day</th><th class="text-end">Amount</th><th>Notes</th><th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa fa-inbox fa-3x mb-3 d-block"></i>No expenses recorded.
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($expenses as $i => $exp): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-500"><?= e($exp['name']) ?></td>
                            <td><?= expenseCategoryBadge($exp['category']) ?></td>
                            <td><span class="badge bg-<?= $exp['expense_type'] === 'recurring' ? 'primary' : 'secondary' ?>">
                                <?= ucfirst(str_replace('_',' ',$exp['expense_type'])) ?></span></td>
                            <td class="text-muted">Day <?= $exp['due_day'] ?></td>
                            <td class="text-end text-danger fw-600"><?= formatCurrency($exp['amount'], $currency) ?></td>
                            <td class="text-muted small"><?= e($exp['notes'] ?? '—') ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editExp(<?= $exp['id'] ?>)"><i class="fa fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteExp(<?= $exp['id'] ?>, '<?= e($exp['name']) ?>')"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($expenses)): ?>
                    <tfoot>
                        <tr class="table-danger fw-700">
                            <td colspan="5" class="text-end">Total</td>
                            <td class="text-end"><?= formatCurrency($totalExpense, $currency) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="expModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expModalTitle"><i class="fa fa-plus me-2"></i>Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="expForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add" id="expAction">
                    <input type="hidden" name="id" id="expId">
                    <div class="mb-3">
                        <label class="form-label">Expense Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="expName" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" id="expCategory" class="form-select">
                                <option value="food">Food</option>
                                <option value="transportation">Transportation</option>
                                <option value="utilities">Utilities</option>
                                <option value="insurance">Insurance</option>
                                <option value="internet">Internet</option>
                                <option value="family">Family</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select name="expense_type" id="expType" class="form-select">
                                <option value="recurring">Recurring</option>
                                <option value="one_time">One-time</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="expAmount" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Day</label>
                            <input type="number" name="due_day" id="expDueDay" class="form-control" min="1" max="31" value="1">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Month</label>
                            <select name="month" id="expMonth" class="form-select">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $month ? 'selected' : '' ?>><?= monthName($i) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Year</label>
                            <select name="year" id="expYear" class="form-select">
                                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="expNotes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitExp()"><i class="fa fa-save me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrfToken() ?>';
function submitExp() {
    fetch(window.location.href, { method: 'POST', body: new FormData(document.getElementById('expForm')) })
        .then(r => r.json()).then(res => {
            if (res.success) Swal.fire({ icon:'success', title:res.message, timer:1500, showConfirmButton:false }).then(()=>location.reload());
            else Swal.fire({ icon:'error', title:res.message });
        });
}
function editExp(id) {
    const d = new FormData(); d.append('csrf_token',CSRF); d.append('action','get'); d.append('id',id);
    fetch(window.location.href,{method:'POST',body:d}).then(r=>r.json()).then(row=>{
        document.getElementById('expModalTitle').innerHTML='<i class="fa fa-edit me-2"></i>Edit Expense';
        document.getElementById('expAction').value='edit';
        document.getElementById('expId').value=row.id;
        document.getElementById('expName').value=row.name;
        document.getElementById('expCategory').value=row.category;
        document.getElementById('expType').value=row.expense_type;
        document.getElementById('expAmount').value=row.amount;
        document.getElementById('expDueDay').value=row.due_day;
        document.getElementById('expMonth').value=row.month;
        document.getElementById('expYear').value=row.year;
        document.getElementById('expNotes').value=row.notes||'';
        new bootstrap.Modal(document.getElementById('expModal')).show();
    });
}
function deleteExp(id, name) {
    Swal.fire({title:'Delete "'+name+'"?',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'Delete'})
        .then(r=>{
            if(!r.isConfirmed) return;
            const d=new FormData(); d.append('csrf_token',CSRF); d.append('action','delete'); d.append('id',id);
            fetch(window.location.href,{method:'POST',body:d}).then(r=>r.json()).then(res=>{if(res.success)location.reload();});
        });
}
document.getElementById('expModal').addEventListener('hidden.bs.modal',()=>{
    document.getElementById('expModalTitle').innerHTML='<i class="fa fa-plus me-2"></i>Add Expense';
    document.getElementById('expAction').value='add'; document.getElementById('expId').value='';
    document.getElementById('expForm').reset();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
