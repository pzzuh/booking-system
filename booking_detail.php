<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$me    = getCurrentUser();
$flash = getFlash();

$id   = (int)($_GET['id'] ?? 0);
$type = sanitizeInput($_GET['type'] ?? 'facility'); // 'facility' or 'item'
if (!in_array($type, ['facility', 'item'], true)) $type = 'facility';
if ($id <= 0) {
    redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Invalid booking.');
}

$isItem    = ($type === 'item');
$b         = null;
$history   = [];

$isOwner   = false;
$isAdmin   = ((string)$me['role'] === 'admin');
$isApprover = in_array((string)$me['role'], approvalChain(), true);

if ($isItem) {
    $stmt = $pdo->prepare(
        "SELECT ib.*, i.name AS item_name, i.category,
                u.full_name AS student_name, u.email AS student_email, u.department, u.student_id
         FROM item_bookings ib
         JOIN items i ON i.id = ib.item_id
         JOIN users u ON u.id = ib.user_id
         WHERE ib.id = ?"
    );
    $stmt->execute([$id]);
    $b = $stmt->fetch();

    if (!$b) {
        redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Item booking not found.');
    }

    $isOwner = ((int)$b['user_id'] === (int)$me['id']);
    if (!$isOwner && !$isAdmin && !$isApprover) {
        redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Access denied.');
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT a.role, a.action, a.action_at, a.rejection_reason, a.notes, u.full_name AS approver_name
             FROM item_booking_approvals a
             LEFT JOIN users u ON u.id = a.approver_user_id
             WHERE a.booking_id = ?
             ORDER BY a.action_at ASC, a.id ASC"
        );
        $stmt->execute([$id]);
        $history = $stmt->fetchAll();
    } catch (Throwable) {}

} else {
    $stmt = $pdo->prepare(
        "SELECT fb.*, f.name AS facility_name, f.location,
                u.full_name AS student_name, u.email AS student_email, u.department, u.student_id
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         JOIN users u ON u.id = fb.user_id
         WHERE fb.id = ?"
    );
    $stmt->execute([$id]);
    $b = $stmt->fetch();

    if (!$b) {
        redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Booking not found.');
    }

    $isOwner = ((int)$b['user_id'] === (int)$me['id']);
    if (!$isOwner && !$isAdmin && !$isApprover) {
        redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Access denied.');
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT a.role, a.action, a.action_at, a.rejection_reason, a.notes, u.full_name AS approver_name
             FROM facility_booking_approvals a
             LEFT JOIN users u ON u.id = a.approver_user_id
             WHERE a.booking_id = ?
             ORDER BY a.action_at ASC, a.id ASC"
        );
        $stmt->execute([$id]);
        $history = $stmt->fetchAll();
    } catch (Throwable) {}
}

$roleLabelMap = [
    'adviser'       => 'Adviser',
    'staff'         => 'Staff',
    'dean'          => 'Dean',
    'dsa_director'  => 'DSA Director',
    'ppss_director' => 'PPSS Director',
    'avp_admin'     => 'Administrative Vice President',
    'vp_admin'      => 'Vice President',
    'president'     => 'President',
    'admin'         => 'Admin',
    'janitor'       => 'Janitorial',
    'security'      => 'Security',
];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4" style="max-width:980px;">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h1 class="h4 fw-bold mb-1">
                        <?= $isItem ? 'Item Booking Detail' : 'Facility Booking Detail' ?>
                    </h1>
                    <?php if ($isItem): ?>
                        <div class="text-muted">
                            Item: <?= e((string)$b['item_name']) ?>
                            <span class="badge bg-light text-dark border ms-1"><?= e((string)$b['category']) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">Facility: <?= e((string)$b['facility_name']) ?> (<?= e((string)$b['location']) ?>)</div>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <div><?= statusBadge((string)$b['status']) ?></div>
                    <div class="small mt-1">Reviewing: <?= approvalRoleBadge((string)($b['current_approval_role'] ?? '')) ?></div>
                </div>
            </div>
            <hr>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="fw-semibold mb-2">Student / Borrower</div>
                    <div><b>Name:</b> <?= e((string)$b['student_name']) ?></div>
                    <div><b>Email:</b> <?= e((string)$b['student_email']) ?></div>
                    <div><b>Department:</b> <?= e((string)($b['department'] ?? '—')) ?></div>
                    <div><b>ID No.:</b> <?= e((string)($b['student_id'] ?? '—')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="fw-semibold mb-2">Request Details</div>
                    <?php if ($isItem): ?>
                        <div><b>Item:</b> <?= e((string)$b['item_name']) ?></div>
                        <div><b>Quantity:</b> <?= (int)$b['quantity_needed'] ?></div>
                        <div><b>Borrow Date:</b> <?= e((string)$b['borrow_date']) ?> at <?= e((string)$b['borrow_time']) ?></div>
                        <div><b>Return Date:</b> <?= e((string)$b['return_date']) ?> at <?= e((string)$b['return_time']) ?></div>
                    <?php else: ?>
                        <div><b>Title:</b> <?= e((string)$b['title']) ?></div>
                        <div><b>Purpose:</b> <?= e((string)$b['purpose']) ?></div>
                        <div><b>Date:</b> <?= e((string)$b['date_start']) ?> to <?= e((string)$b['date_end']) ?></div>
                        <div><b>Time:</b> <?= e((string)$b['time_start']) ?> to <?= e((string)$b['time_end']) ?></div>
                        <div><b>Participants:</b> <?= (int)$b['participants'] ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <div><b>Purpose / Notes:</b> <?= nl2br(e((string)($b['purpose'] ?? $b['notes'] ?? ''))) ?></div>
                    <?php if (!empty($b['rejection_reason'])): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <b>Rejection reason:</b> <?= e((string)$b['rejection_reason']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // Show Approve / Reject buttons if this approver's turn
            $canAct = $isApprover
                && (string)$b['status'] === 'pending'
                && (string)($b['current_approval_role'] ?? '') === (string)$me['role'];
            if ($canAct): ?>
            <hr>
            <div class="d-flex gap-2 mt-2">
                <form method="post" action="approval_action.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <input type="hidden" name="booking_type" value="<?= e($type) ?>">
                    <button class="btn btn-success">APPROVE</button>
                </form>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">REJECT</button>
            </div>

            <!-- Reject modal -->
            <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" action="approval_action.php">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="booking_type" value="<?= e($type) ?>">
                                <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="reason" rows="4" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-danger">Reject</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 fw-semibold mb-3">Approval History</h2>
            <?php if (!$history): ?>
                <div class="text-muted">No approval actions recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Approver</th>
                                <th>Action</th>
                                <th>Date</th>
                                <th>Reason / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $roleKey = (string)$h['role'];
                                        echo e($roleLabelMap[$roleKey] ?? ucwords(str_replace('_', ' ', $roleKey)));
                                        ?>
                                    </td>
                                    <td><?= e((string)($h['approver_name'] ?? '')) ?></td>
                                    <td><?= e(strtoupper((string)$h['action'])) ?></td>
                                    <td><?= e(!empty($h['action_at']) ? formatDateTime((string)$h['action_at']) : '') ?></td>
                                    <td><?= e((string)($h['rejection_reason'] ?? $h['notes'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
