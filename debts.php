<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user     = currentUser();
$userId   = $user['id'];
$currency = $user['currency'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fields = ['name','category','original_amount','remaining_balance','interest_rate','interest_type','monthly_payment','due_day','loan_term','start_date','penalty_fee','status','notes'];
        $data = [];
        foreach ($fields as $f) $data[$f] = $_POST[$f] ?? '';
        if (!$data['name'] || $data['original_amount'] <= 0) { echo json_encode(['success'=>false,'message'=>'Name and amount required']); exit; }
        $pdo->prepare('INSERT INTO debts (user_id,name,category,original_amount,remaining_balance,interest_rate,interest_type,monthly_payment,due_day,loan_term,start_date,penalty_fee,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$userId,$data['name'],$data['category'],(float)$data['original_amount'],(float)$data['remaining_balance'],(float)$data['interest_rate'],$data['interest_type'],(float)$data['monthly_payment'],(int)$data['due_day'],($data['loan_term']?:(null)),$data['start_date']?:null,(float)$data['penalty_fee'],$data['status'],$data['notes']]);
        echo json_encode(['success'=>true,'message'=>'Debt added.']);
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE debts SET name=?,category=?,original_amount=?,remaining_balance=?,interest_rate=?,interest_type=?,monthly_payment=?,due_day=?,loan_term=?,start_date=?,penalty_fee=?,status=?,notes=? WHERE id=? AND user_id=?')
            ->execute([$_POST['name'],$_POST['category'],(float)$_POST['original_amount'],(float)$_POST['remaining_balance'],(float)$_POST['interest_rate'],$_POST['interest_type'],(float)$_POST['monthly_payment'],(int)$_POST['due_day'],($_POST['loan_term']?:null),$_POST['start_date']?:null,(float)$_POST['penalty_fee'],$_POST['status'],$_POST['notes'],$id,$userId]);
        echo json_encode(['success'=>true,'message'=>'Debt updated.']);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM debts WHERE id=? AND user_id=?')->execute([$id,$userId]);
        echo json_encode(['success'=>true,'message'=>'Debt deleted.']);
    } elseif ($action === 'get') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM debts WHERE id=? AND user_id=?');
        $stmt->execute([$id,$userId]);
        echo json_encode($stmt->fetch()?:['success'=>false]);
    } elseif ($action === 'add_payment') {
        $debtId   = (int)($_POST['debt_id'] ?? 0);
        $amount   = (float)($_POST['amount'] ?? 0);
        $date     = $_POST['payment_date'] ?? date('Y-m-d');
        $notes    = $_POST['notes'] ?? '';
        // Fetch debt to calculate principal/interest split
        $debt = $pdo->prepare('SELECT * FROM debts WHERE id=? AND user_id=?');
        $debt->execute([$debtId,$userId]);
        $debt = $debt->fetch();
        if (!$debt) { echo json_encode(['success'=>false,'message'=>'Debt not found']); exit; }
        $interest  = calculateMonthlyInterest($debt['remaining_balance'], $debt['interest_rate'], $debt['interest_type']);
        $principal = max(0, $amount - $interest);
        $newBalance = max(0, $debt['remaining_balance'] - $principal);
        $pdo->prepare('INSERT INTO debt_payments (debt_id,user_id,amount,principal,interest,payment_date,notes) VALUES (?,?,?,?,?,?,?)')
            ->execute([$debtId,$userId,$amount,round($principal,2),round($interest,2),$date,$notes]);
        $status = $newBalance <= 0.01 ? 'paid' : 'active';
        $pdo->prepare('UPDATE debts SET remaining_balance=?,status=? WHERE id=?')->execute([round($newBalance,2),$status,$debtId]);
        echo json_encode(['success'=>true,'message'=>'Payment recorded. New balance: '.number_format($newBalance,2)]);
    }
    exit;
}

$filterStatus = $_GET['status'] ?? 'all';
$sql = 'SELECT * FROM debts WHERE user_id=?';
$params = [$userId];
if ($filterStatus !== 'all') { $sql .= ' AND status=?'; $params[] = $filterStatus; }
$sql .= ' ORDER BY status,due_day';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$debts = $stmt->fetchAll();

$totalDebt    = (float)fetchSingleValue($pdo,'SELECT COALESCE(SUM(remaining_balance),0) FROM debts WHERE user_id=? AND status!="paid"',[$userId]);
$monthlyTotal = (float)fetchSingleValue($pdo,'SELECT COALESCE(SUM(monthly_payment),0) FROM debts WHERE user_id=? AND status="active"',[$userId]);

$pageTitle = 'My Debts';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">My Debts</h4>
            <p class="page-subtitle">Track and manage all your loans</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <?php foreach (['all'=>'All','active'=>'Active','overdue'=>'Overdue','paid'=>'Paid'] as $k=>$v): ?>
                <a href="?status=<?= $k ?>" class="btn btn-sm <?= $filterStatus===$k ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $v ?></a>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#debtModal">
                <i class="fa fa-plus me-2"></i>Add Debt
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-debt">
                <div class="kpi-icon"><i class="fa fa-file-invoice-dollar"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Total Outstanding</div>
                    <div class="kpi-value"><?= formatCurrency($totalDebt, $currency) ?></div>
                    <div class="kpi-sub"><?= count(array_filter($debts, fn($d)=>$d['status']==='active')) ?> active debts</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-expense">
                <div class="kpi-icon"><i class="fa fa-calendar-check"></i></div>
                <div class="kpi-body">
                    <div class="kpi-label">Monthly Payments</div>
                    <div class="kpi-value"><?= formatCurrency($monthlyTotal, $currency) ?></div>
                    <div class="kpi-sub">Per month total</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php if (empty($debts)): ?>
        <div class="col-12"><div class="card"><div class="card-body text-center py-5 text-muted">
            <i class="fa fa-check-circle fa-3x mb-3 text-success d-block"></i>
            No debts found. <a href="#" data-bs-toggle="modal" data-bs-target="#debtModal">Add your first debt</a>
        </div></div></div>
        <?php endif; ?>
        <?php foreach ($debts as $d): ?>
        <?php
            $pct = $d['original_amount'] > 0
                ? min(100, round((1 - $d['remaining_balance'] / $d['original_amount']) * 100, 1))
                : 0;
            $months = calculatePayoffMonths($d['remaining_balance'], $d['interest_rate'], $d['monthly_payment'], $d['interest_type']);
            $freeDate = $months > 0 ? date('M Y', strtotime('+' . $months . ' months')) : 'N/A';
            $totalInt = round(calculateTotalInterest($d['remaining_balance'], $d['interest_rate'], $d['monthly_payment'], $d['interest_type']), 2);
        ?>
        <div class="col-lg-6">
            <div class="card debt-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="debt-icon"><i class="fa <?= debtCategoryIcon($d['category']) ?>"></i></div>
                            <div>
                                <h6 class="mb-0 fw-700"><?= e($d['name']) ?></h6>
                                <span class="text-muted small"><?= ucfirst(str_replace('_',' ',$d['category'])) ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <?= debtStatusBadge($d['status']) ?>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="editDebt(<?= $d['id'] ?>)"><i class="fa fa-edit me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="recordPayment(<?= $d['id'] ?>, '<?= e($d['name']) ?>', <?= $d['monthly_payment'] ?>)"><i class="fa fa-money-bill me-2"></i>Record Payment</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteDebt(<?= $d['id'] ?>, '<?= e($d['name']) ?>')"><i class="fa fa-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="debt-stat">
                                <div class="debt-stat-label">Remaining Balance</div>
                                <div class="debt-stat-value text-danger"><?= formatCurrency($d['remaining_balance'], $currency) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="debt-stat">
                                <div class="debt-stat-label">Monthly Payment</div>
                                <div class="debt-stat-value"><?= formatCurrency($d['monthly_payment'], $currency) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="debt-stat">
                                <div class="debt-stat-label">Interest Rate</div>
                                <div class="debt-stat-value"><?= $d['interest_rate'] ?>% <small class="text-muted">(<?= $d['interest_type'] ?>)</small></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="debt-stat">
                                <div class="debt-stat-label">Est. Interest Left</div>
                                <div class="debt-stat-value text-warning"><?= formatCurrency($totalInt, $currency) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="debt-stat">
                                <div class="debt-stat-label">Due Day</div>
                                <div class="debt-stat-value">Every day <?= $d['due_day'] ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="debt-stat">
                                <div class="debt-stat-label">Debt-Free Date</div>
                                <div class="debt-stat-value text-primary"><?= $freeDate ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted">Payoff Progress</span>
                        <span class="small fw-600"><?= $pct ?>%</span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted"><?= formatCurrency($d['original_amount'] - $d['remaining_balance'], $currency) ?> paid</span>
                        <span class="small text-muted"><?= formatCurrency($d['remaining_balance'], $currency) ?> left</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Debt Modal -->
<div class="modal fade" id="debtModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="debtModalTitle"><i class="fa fa-plus me-2"></i>Add Debt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="debtForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add" id="debtAction">
                    <input type="hidden" name="id" id="debtId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Debt Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="debtName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" id="debtCategory" class="form-select">
                                <option value="housing">Housing Loan</option>
                                <option value="car">Car Loan</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="personal">Personal Loan</option>
                                <option value="student">Student Loan</option>
                                <option value="business">Business Loan</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Original Amount <span class="text-danger">*</span></label>
                            <input type="number" name="original_amount" id="debtOriginal" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remaining Balance <span class="text-danger">*</span></label>
                            <input type="number" name="remaining_balance" id="debtBalance" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interest Rate (% per year)</label>
                            <input type="number" name="interest_rate" id="debtRate" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interest Type</label>
                            <select name="interest_type" id="debtInterestType" class="form-select">
                                <option value="reducing">Reducing Balance</option>
                                <option value="flat">Flat Rate</option>
                                <option value="fixed">Fixed</option>
                                <option value="compound">Compound</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monthly Payment <span class="text-danger">*</span></label>
                            <input type="number" name="monthly_payment" id="debtMonthly" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Due Day</label>
                            <input type="number" name="due_day" id="debtDueDay" class="form-control" min="1" max="31" value="1">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Loan Term (months)</label>
                            <input type="number" name="loan_term" id="debtTerm" class="form-control" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="debtStart" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Penalty Fee</label>
                            <input type="number" name="penalty_fee" id="debtPenalty" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="debtStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="overdue">Overdue</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="debtNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitDebt()"><i class="fa fa-save me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-money-bill me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="payForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add_payment">
                    <input type="hidden" name="debt_id" id="payDebtId">
                    <div class="mb-3">
                        <label class="form-label">Payment Amount</label>
                        <input type="number" name="amount" id="payAmount" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitPayment()"><i class="fa fa-check me-1"></i>Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrfToken() ?>';
function submitDebt() {
    fetch(window.location.href,{method:'POST',body:new FormData(document.getElementById('debtForm'))})
        .then(r=>r.json()).then(res=>{
            if(res.success) Swal.fire({icon:'success',title:res.message,timer:1500,showConfirmButton:false}).then(()=>location.reload());
            else Swal.fire({icon:'error',title:res.message});
        });
}
function editDebt(id) {
    const d=new FormData(); d.append('csrf_token',CSRF); d.append('action','get'); d.append('id',id);
    fetch(window.location.href,{method:'POST',body:d}).then(r=>r.json()).then(row=>{
        document.getElementById('debtModalTitle').innerHTML='<i class="fa fa-edit me-2"></i>Edit Debt';
        document.getElementById('debtAction').value='edit';
        document.getElementById('debtId').value=row.id;
        ['name','category','interest_type','status','notes'].forEach(f=>{document.getElementById('debt'+f.charAt(0).toUpperCase()+f.slice(1).replace('_','').replace('type','Type').replace('status','Status').replace('notes','Notes')).value=row[f]||'';});
        document.getElementById('debtName').value=row.name;
        document.getElementById('debtCategory').value=row.category;
        document.getElementById('debtOriginal').value=row.original_amount;
        document.getElementById('debtBalance').value=row.remaining_balance;
        document.getElementById('debtRate').value=row.interest_rate;
        document.getElementById('debtInterestType').value=row.interest_type;
        document.getElementById('debtMonthly').value=row.monthly_payment;
        document.getElementById('debtDueDay').value=row.due_day;
        document.getElementById('debtTerm').value=row.loan_term||'';
        document.getElementById('debtStart').value=row.start_date||'';
        document.getElementById('debtPenalty').value=row.penalty_fee||0;
        document.getElementById('debtStatus').value=row.status;
        document.getElementById('debtNotes').value=row.notes||'';
        new bootstrap.Modal(document.getElementById('debtModal')).show();
    });
}
function deleteDebt(id,name) {
    Swal.fire({title:'Delete "'+name+'"?',text:'All payment history will be deleted.',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'Delete'})
        .then(r=>{
            if(!r.isConfirmed) return;
            const d=new FormData(); d.append('csrf_token',CSRF); d.append('action','delete'); d.append('id',id);
            fetch(window.location.href,{method:'POST',body:d}).then(r=>r.json()).then(res=>{if(res.success)location.reload();});
        });
}
function recordPayment(id,name,amount) {
    document.getElementById('payDebtId').value=id;
    document.getElementById('payAmount').value=amount;
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}
function submitPayment() {
    fetch(window.location.href,{method:'POST',body:new FormData(document.getElementById('payForm'))})
        .then(r=>r.json()).then(res=>{
            if(res.success) Swal.fire({icon:'success',title:'Payment Recorded',text:res.message,timer:2000,showConfirmButton:false}).then(()=>location.reload());
            else Swal.fire({icon:'error',title:res.message});
        });
}
document.getElementById('debtModal').addEventListener('hidden.bs.modal',()=>{
    document.getElementById('debtModalTitle').innerHTML='<i class="fa fa-plus me-2"></i>Add Debt';
    document.getElementById('debtAction').value='add'; document.getElementById('debtId').value='';
    document.getElementById('debtForm').reset();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
