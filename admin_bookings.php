<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');

$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $action = sanitizeInput($_POST['action'] ?? '');
    $type   = sanitizeInput($_POST['type']   ?? '');
    $id     = (int)($_POST['id']             ?? 0);
    $reason = sanitizeInput($_POST['reason'] ?? '');

    try {
        if ($type === 'facility' && $id > 0) {
            if ($action === 'force_approve') {
                $stmt = $pdo->prepare("UPDATE facility_bookings SET status='fully_approved', current_approval_role=NULL WHERE id=?");
                $stmt->execute([$id]);
                redirectWithMessage('admin_bookings.php', 'success', 'Facility booking force-approved.');
            }
            if ($action === 'force_reject') {
                if ($reason === '') $reason = 'Rejected by admin.';
                $stmt = $pdo->prepare("UPDATE facility_bookings SET status='rejected', current_approval_role=NULL, rejection_reason=? WHERE id=?");
                $stmt->execute([$reason, $id]);
                redirectWithMessage('admin_bookings.php', 'success', 'Facility booking rejected.');
            }
        }

        if ($type === 'item' && $id > 0) {
            if ($action === 'force_approve') {
                $stmt = $pdo->prepare("UPDATE item_bookings SET status='fully_approved', current_approval_role=NULL WHERE id=?");
                $stmt->execute([$id]);
                redirectWithMessage('admin_bookings.php', 'success', 'Item booking force-approved.');
            }
            if ($action === 'force_reject') {
                if ($reason === '') $reason = 'Rejected by admin.';
                $stmt = $pdo->prepare("UPDATE item_bookings SET status='rejected', current_approval_role=NULL, rejection_reason=? WHERE id=?");
                $stmt->execute([$reason, $id]);
                redirectWithMessage('admin_bookings.php', 'success', 'Item booking rejected.');
            }
        }
    } catch (Throwable) {
        redirectWithMessage('admin_bookings.php', 'danger', 'Action failed.');
    }
}

$status  = sanitizeInput($_GET['status'] ?? '');
$typeTab = sanitizeInput($_GET['tab']    ?? 'facility');

$statusOk = ($status === '' || in_array($status, ['pending','fully_approved','rejected','cancelled'], true));
if (!$statusOk) $status = '';

$facilityRows = [];
$itemRows     = [];

try {
    $w    = $status ? 'WHERE fb.status = ?' : '';
    $stmt = $pdo->prepare(
        "SELECT fb.id, fb.title, fb.date_start, fb.date_end, fb.time_start, fb.time_end,
                fb.status, fb.current_approval_role,
                f.name AS facility_name, u.full_name AS student_name
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         JOIN users      u ON u.id = fb.user_id
         {$w}
         ORDER BY fb.created_at DESC, fb.id DESC
         LIMIT 200"
    );
    $stmt->execute($status ? [$status] : []);
    $facilityRows = $stmt->fetchAll();
} catch (Throwable) {}

try {
    $w    = $status ? 'WHERE ib.status = ?' : '';
    $stmt = $pdo->prepare(
        "SELECT ib.id, ib.quantity_needed, ib.borrow_date, ib.return_date,
                ib.borrow_time, ib.return_time, ib.status, ib.current_approval_role,
                i.name AS item_name, i.category, u.full_name AS student_name
         FROM item_bookings ib
         JOIN items i ON i.id = ib.item_id
         JOIN users u ON u.id = ib.user_id
         {$w}
         ORDER BY ib.created_at DESC, ib.id DESC
         LIMIT 200"
    );
    $stmt->execute($status ? [$status] : []);
    $itemRows = $stmt->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Bookings</h1>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<form class="row g-2 align-items-end mb-3" id="bookingFilterForm">
    <div class="col-md-3">
        <label class="form-label">Status filter</label>
        <select class="form-select" name="status" onchange="document.getElementById('bookingFilterForm').submit()">
            <option value="">All</option>
            <?php foreach (['pending','fully_approved','rejected','cancelled'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="hidden" name="tab" value="<?= e($typeTab) ?>">
</form>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $typeTab === 'facility' ? 'active' : '' ?>" href="?<?= e(http_build_query(['tab'=>'facility','status'=>$status])) ?>">Facility</a></li>
    <li class="nav-item"><a class="nav-link <?= $typeTab === 'item'     ? 'active' : '' ?>" href="?<?= e(http_build_query(['tab'=>'item',    'status'=>$status])) ?>">Items</a></li>
</ul>

<?php if ($typeTab !== 'item'): ?>

    <!-- ── FACILITY BOOKINGS ──────────────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Facility Bookings (latest 200)</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th><th>Title</th><th>Student</th><th>Facility</th>
                            <th>Dates</th><th>Status</th><th>Reviewing</th>
                            <th class="text-end">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facilityRows as $r): ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><a href="booking_detail.php?type=facility&id=<?= (int)$r['id'] ?>"><?= e((string)$r['title']) ?></a></td>
                                <td><?= e((string)$r['student_name']) ?></td>
                                <td><?= e((string)$r['facility_name']) ?></td>
                                <td class="small"><?= e((string)$r['date_start']) ?> → <?= e((string)$r['date_end']) ?></td>
                                <td><?= statusBadge((string)$r['status']) ?></td>
                                <td><?= approvalRoleBadge((string)($r['current_approval_role'] ?? '')) ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                            <input type="hidden" name="type"   value="facility">
                                            <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="action" value="force_approve">
                                            <button class="btn btn-sm btn-success">Force-Approve</button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejF<?= (int)$r['id'] ?>">Reject</button>
                                    </div>
                                    <!-- Reject modal -->
                                    <div class="modal fade" id="rejF<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Facility Booking</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                                        <input type="hidden" name="type"   value="facility">
                                                        <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                                        <input type="hidden" name="action" value="force_reject">
                                                        <label class="form-label">Reason</label>
                                                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button class="btn btn-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ── ITEM BOOKINGS ─────────────────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Item Bookings (latest 200)</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th><th>Student</th><th>Item</th><th>Qty</th>
                            <th>Dates</th><th>Status</th><th>Reviewing</th>
                            <th class="text-end">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itemRows as $r): ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= e((string)$r['student_name']) ?></td>
                                <td><?= e((string)$r['item_name']) ?> <span class="text-muted small">(<?= e((string)$r['category']) ?>)</span></td>
                                <td><?= (int)$r['quantity_needed'] ?></td>
                                <td class="small"><?= e((string)$r['borrow_date']) ?> → <?= e((string)$r['return_date']) ?></td>
                                <td><?= statusBadge((string)$r['status']) ?></td>
                                <td><?= approvalRoleBadge((string)($r['current_approval_role'] ?? '')) ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                            <input type="hidden" name="type"   value="item">
                                            <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="action" value="force_approve">
                                            <button class="btn btn-sm btn-success">Force-Approve</button>
                                        </form>
                                        <button class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejI<?= (int)$r['id'] ?>">Reject</button>
                                    </div>
                                    <!-- Reject modal -->
                                    <div class="modal fade" id="rejI<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Item Booking</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                                        <input type="hidden" name="type"   value="item">
                                                        <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                                        <input type="hidden" name="action" value="force_reject">
                                                        <label class="form-label">Reason</label>
                                                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button class="btn btn-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
