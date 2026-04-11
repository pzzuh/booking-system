<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($role, $role_label)) {
    http_response_code(500);
    echo 'Role dashboard misconfigured.';
    exit;
}

enforceCorrectDashboard((string)$role);

$user  = getCurrentUser();
$flash = getFlash();

// ── Summary counts ──────────────────────────────────────────────────────────
$summary = ['total' => 0, 'awaiting' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $summary['total'] = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings")->fetchColumn()
                      + (int)$pdo->query("SELECT COUNT(*) FROM item_bookings")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facility_bookings WHERE current_approval_role=? AND status='pending'");
    $stmt->execute([(string)$role]);
    $fAw = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_bookings WHERE current_approval_role=? AND status='pending'");
    $stmt->execute([(string)$role]);
    $summary['awaiting'] = $fAw + (int)$stmt->fetchColumn();

    $summary['approved'] = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE status='fully_approved'")->fetchColumn()
                         + (int)$pdo->query("SELECT COUNT(*) FROM item_bookings WHERE status='fully_approved'")->fetchColumn();
    $summary['rejected'] = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE status='rejected'")->fetchColumn()
                         + (int)$pdo->query("SELECT COUNT(*) FROM item_bookings WHERE status='rejected'")->fetchColumn();
} catch (Throwable) {}

// ── Action required ─────────────────────────────────────────────────────────
$actionFacility = [];
try {
    $stmt = $pdo->prepare(
        "SELECT fb.*, f.name AS facility_name, u.full_name AS student_name
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         JOIN users u ON u.id = fb.user_id
         WHERE fb.current_approval_role = ? AND fb.status = 'pending'
         ORDER BY fb.created_at ASC"
    );
    $stmt->execute([(string)$role]);
    $actionFacility = $stmt->fetchAll();
} catch (Throwable) {}

$actionItems = [];
try {
    $stmt = $pdo->prepare(
        "SELECT ib.*, i.name AS item_name, i.category, u.full_name AS student_name
         FROM item_bookings ib
         JOIN items i ON i.id = ib.item_id
         JOIN users u ON u.id = ib.user_id
         WHERE ib.current_approval_role = ? AND ib.status = 'pending'
         ORDER BY ib.created_at ASC"
    );
    $stmt->execute([(string)$role]);
    $actionItems = $stmt->fetchAll();
} catch (Throwable) {}

// ── All Bookings ─────────────────────────────────────────────────────────────
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$bookingType  = sanitizeInput($_GET['btype'] ?? 'facility');
$activeTab    = sanitizeInput($_GET['tab'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

if (!in_array($bookingType, ['facility','item'], true)) $bookingType = 'facility';
if (!in_array($statusFilter, ['','pending','fully_approved','rejected','cancelled'], true)) $statusFilter = '';

$showAllTab = ($statusFilter !== '' || $activeTab === 'all');
$allRows    = [];
$totalRows  = 0;

if ($bookingType === 'facility') {
    $where  = $statusFilter ? 'WHERE fb.status = ?' : '';
    $params = $statusFilter ? [$statusFilter] : [];
    try {
        $c = $pdo->prepare("SELECT COUNT(*) FROM facility_bookings fb {$where}");
        $c->execute($params);
        $totalRows = (int)$c->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT fb.id, fb.title AS label, fb.date_start, fb.date_end, fb.status,
                    fb.current_approval_role, f.name AS resource_name, u.full_name AS student_name
             FROM facility_bookings fb
             JOIN facilities f ON f.id = fb.facility_id
             JOIN users u ON u.id = fb.user_id
             {$where} ORDER BY fb.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $allRows = $stmt->fetchAll();
    } catch (Throwable) {}
} else {
    $where  = $statusFilter ? 'WHERE ib.status = ?' : '';
    $params = $statusFilter ? [$statusFilter] : [];
    try {
        $c = $pdo->prepare("SELECT COUNT(*) FROM item_bookings ib {$where}");
        $c->execute($params);
        $totalRows = (int)$c->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT ib.id, i.name AS label, ib.borrow_date AS date_start, ib.return_date AS date_end,
                    ib.status, ib.current_approval_role, i.name AS resource_name, u.full_name AS student_name
             FROM item_bookings ib
             JOIN items i ON i.id = ib.item_id
             JOIN users u ON u.id = ib.user_id
             {$where} ORDER BY ib.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $allRows = $stmt->fetchAll();
    } catch (Throwable) {}
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubg.jpg')">
    <div class="banner-content container py-4">
        <div class="d-flex align-items-center gap-3">
            <img src="assets/images/ndmulogo.png" alt="NDMU" width="54" height="54" style="object-fit:contain">
            <div>
                <div class="h4 fw-bold mb-1"><?= e((string)$role_label) ?> Dashboard</div>
                <div class="text-white-50">Logged in as <?= e((string)$user['name']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <?php foreach (['Total Bookings'=>'total','Awaiting My Action'=>'awaiting','Approved'=>'approved','Rejected'=>'rejected'] as $lbl=>$key): ?>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small"><?= e($lbl) ?></div>
            <div class="fs-4 fw-bold"><?= (int)$summary[$key] ?></div>
        </div></div></div>
        <?php endforeach; ?>
    </div>

    <ul class="nav nav-tabs" id="roleTabs">
        <li class="nav-item">
            <button class="nav-link <?= !$showAllTab ? 'active' : '' ?>"
                    data-bs-toggle="tab" data-bs-target="#actionRequired" type="button">ACTION REQUIRED</button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $showAllTab ? 'active' : '' ?>"
                    data-bs-toggle="tab" data-bs-target="#allBookings" type="button">ALL BOOKINGS</button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 bg-white p-3 p-md-4 shadow-sm">

        <!-- ACTION REQUIRED -->
        <div class="tab-pane fade <?= !$showAllTab ? 'show active' : '' ?>" id="actionRequired">
            <?php if (!$actionFacility && !$actionItems): ?>
                <div class="alert alert-success mb-0">No bookings awaiting your action.</div>
            <?php else: ?>

                <?php if ($actionFacility): ?>
                <h6 class="fw-bold text-uppercase text-muted mb-2">Facility Bookings</h6>
                <div class="table-responsive mb-4">
                    <table class="table align-middle">
                        <thead><tr><th>Student</th><th>Facility</th><th>Dates</th><th>Participants</th><th>Purpose</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($actionFacility as $b): ?>
                            <tr>
                                <td><?= e((string)$b['student_name']) ?></td>
                                <td><?= e((string)$b['facility_name']) ?></td>
                                <td class="small"><?= e((string)$b['date_start']) ?> → <?= e((string)$b['date_end']) ?><br><span class="text-muted"><?= e((string)$b['time_start']) ?> → <?= e((string)$b['time_end']) ?></span></td>
                                <td><?= (int)$b['participants'] ?></td>
                                <td><?= e((string)$b['purpose']) ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <form method="post" action="approval_action.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                            <input type="hidden" name="booking_type" value="facility">
                                            <button class="btn btn-sm btn-success">APPROVE</button>
                                        </form>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rjF<?= (int)$b['id'] ?>">REJECT</button>
                                        <a class="btn btn-sm btn-outline-secondary" href="booking_detail.php?id=<?= (int)$b['id'] ?>">Details</a>
                                    </div>
                                    <div class="modal fade" id="rjF<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">Reject Facility Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <form method="post" action="approval_action.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="booking_type" value="facility">
                                                    <label class="form-label">Rejection reason</label>
                                                    <textarea class="form-control" name="reason" rows="4" required></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn btn-danger">Reject</button>
                                                </div>
                                            </form>
                                        </div></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($actionItems): ?>
                <h6 class="fw-bold text-uppercase text-muted mb-2">Item Bookings</h6>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Student</th><th>Item</th><th>Qty</th><th>Borrow</th><th>Return</th><th>Purpose</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($actionItems as $b): ?>
                            <tr>
                                <td><?= e((string)$b['student_name']) ?></td>
                                <td><?= e((string)$b['item_name']) ?> <span class="badge bg-light text-dark border"><?= e((string)$b['category']) ?></span></td>
                                <td><?= (int)$b['quantity_needed'] ?></td>
                                <td class="small"><?= e((string)$b['borrow_date']) ?><br><span class="text-muted"><?= e((string)$b['borrow_time']) ?></span></td>
                                <td class="small"><?= e((string)$b['return_date']) ?><br><span class="text-muted"><?= e((string)$b['return_time']) ?></span></td>
                                <td><?= e((string)($b['purpose'] ?? '')) ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <form method="post" action="approval_action.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                            <input type="hidden" name="booking_type" value="item">
                                            <button class="btn btn-sm btn-success">APPROVE</button>
                                        </form>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rjI<?= (int)$b['id'] ?>">REJECT</button>
                                    </div>
                                    <div class="modal fade" id="rjI<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">Reject Item Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <form method="post" action="approval_action.php">
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="booking_type" value="item">
                                                    <label class="form-label">Rejection reason</label>
                                                    <textarea class="form-control" name="reason" rows="4" required></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn btn-danger">Reject</button>
                                                </div>
                                            </form>
                                        </div></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ALL BOOKINGS -->
        <div class="tab-pane fade <?= $showAllTab ? 'show active' : '' ?>" id="allBookings">
            <form class="row g-2 align-items-end mb-3" method="get" id="filterForm">
                <input type="hidden" name="tab" value="all">
                <div class="col-sm-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="btype" id="btypeSelect">
                        <option value="facility" <?= $bookingType === 'facility' ? 'selected' : '' ?>>Facility</option>
                        <option value="item"     <?= $bookingType === 'item'     ? 'selected' : '' ?>>Item</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" id="statusSelect">
                        <option value="">All</option>
                        <?php foreach (['pending','fully_approved','rejected','cancelled'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th><th>Title / Item</th><th>Student</th>
                            <th>Resource</th><th>Dates</th><th>Status</th><th>Reviewing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allRows as $r): ?>
                            <tr class="pointer" onclick="window.location.href='booking_detail.php?type=<?= e($bookingType) ?>&id=<?= (int)$r['id'] ?>'">
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= e((string)$r['label']) ?></td>
                                <td><?= e((string)$r['student_name']) ?></td>
                                <td><?= e((string)$r['resource_name']) ?></td>
                                <td class="small"><?= e((string)$r['date_start']) ?> → <?= e((string)$r['date_end']) ?></td>
                                <td><?= statusBadge((string)$r['status']) ?></td>
                                <td><?= approvalRoleBadge((string)($r['current_approval_role'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$allRows): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No bookings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav><ul class="pagination">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $qs = $_GET; $qs['page'] = $p; $qs['tab'] = 'all'; ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= e(http_build_query($qs)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        </div>

    </div>
</div>

<script>
['statusSelect','btypeSelect'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', function(){ document.getElementById('filterForm').submit(); });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
