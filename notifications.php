<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user   = currentUser();
$userId = $user['id'];
$currency = $user['currency'];

generateDebtNotifications($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === 0) {
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$userId]);
        } else {
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id,$userId]);
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([$id,$userId]);
        echo json_encode(['success' => true]);
    }
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$sql = 'SELECT * FROM notifications WHERE user_id=?';
$params = [$userId];
if ($filter === 'unread') { $sql .= ' AND is_read=0'; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4 class="page-title">Notifications</h4>
            <p class="page-subtitle">Alerts and reminders</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <a href="?filter=all" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline-primary' ?>">All</a>
                <a href="?filter=unread" class="btn btn-sm <?= $filter==='unread'?'btn-primary':'btn-outline-primary' ?>">Unread</a>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="markAllRead()">
                <i class="fa fa-check-double me-1"></i>Mark All Read
            </button>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="card"><div class="card-body text-center py-5 text-muted">
        <i class="fa fa-bell-slash fa-3x mb-3 d-block"></i>
        <h5>No notifications</h5>
        <p>You're all caught up!</p>
    </div></div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <?php
            $typeIcon = ['due_date'=>['fa-calendar-exclamation','text-warning'],'overdue'=>['fa-exclamation-triangle','text-danger'],'budget_warning'=>['fa-wallet','text-info'],'goal'=>['fa-flag','text-success'],'info'=>['fa-info-circle','text-primary']];
            foreach ($notifications as $n):
                $icon = $typeIcon[$n['type']] ?? ['fa-bell','text-muted'];
            ?>
            <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>" id="notif-<?= $n['id'] ?>">
                <div class="notif-icon <?= $icon[1] ?>">
                    <i class="fa <?= $icon[0] ?>"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-title"><?= e($n['title']) ?></div>
                    <div class="notif-message"><?= e($n['message']) ?></div>
                    <div class="notif-time"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
                </div>
                <div class="notif-actions">
                    <?php if (!$n['is_read']): ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="markRead(<?= $n['id'] ?>)" title="Mark read">
                        <i class="fa fa-check"></i>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteNotif(<?= $n['id'] ?>)" title="Delete">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function markRead(id) {
    const d = new FormData(); d.append('action','mark_read'); d.append('id',id);
    fetch(window.location.href,{method:'POST',body:d}).then(()=>document.getElementById('notif-'+id).classList.remove('unread'));
}
function markAllRead() {
    const d = new FormData(); d.append('action','mark_read'); d.append('id',0);
    fetch(window.location.href,{method:'POST',body:d}).then(()=>location.reload());
}
function deleteNotif(id) {
    const d = new FormData(); d.append('action','delete'); d.append('id',id);
    fetch(window.location.href,{method:'POST',body:d}).then(()=>{
        const el = document.getElementById('notif-'+id);
        el.style.animation = 'fadeOut 0.3s'; setTimeout(()=>el.remove(),300);
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
