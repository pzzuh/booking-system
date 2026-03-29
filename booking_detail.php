<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$me = getCurrentUser();
$flash = getFlash();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Invalid booking.');
}

$stmt = $pdo->prepare(
    "SELECT fb.*, f.name AS facility_name, f.location, u.full_name AS student_name, u.email AS student_email, u.department, u.student_id
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
$isAdmin = ((string)$me['role'] === 'admin');
$isApprover = in_array((string)$me['role'], approvalChain(), true);
if (!$isOwner && !$isAdmin && !$isApprover) {
    redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Access denied.');
}

$history = [];
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
          <h1 class="h4 fw-bold mb-1">Booking Detail</h1>
          <div class="text-muted">Facility: <?= e((string)$b['facility_name']) ?> (<?= e((string)$b['location']) ?>)</div>
        </div>
        <div class="text-end">
          <div><?= statusBadge((string)$b['status']) ?></div>
          <div class="small mt-1">Reviewing: <?= approvalRoleBadge((string)($b['current_approval_role'] ?? '')) ?></div>
        </div>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="fw-semibold mb-2">Student</div>
          <div><b>Name:</b> <?= e((string)$b['student_name']) ?></div>
          <div><b>Email:</b> <?= e((string)$b['student_email']) ?></div>
          <div><b>Department:</b> <?= e((string)($b['department'] ?? '')) ?></div>
          <div><b>Student ID:</b> <?= e((string)($b['student_id'] ?? '')) ?></div>
        </div>
        <div class="col-md-6">
          <div class="fw-semibold mb-2">Request</div>
          <div><b>Title:</b> <?= e((string)$b['title']) ?></div>
          <div><b>Purpose:</b> <?= e((string)$b['purpose']) ?></div>
          <div><b>Date:</b> <?= e((string)$b['date_start']) ?> to <?= e((string)$b['date_end']) ?></div>
          <div><b>Time:</b> <?= e((string)$b['time_start']) ?> to <?= e((string)$b['time_end']) ?></div>
          <div><b>Participants:</b> <?= (int)$b['participants'] ?></div>
        </div>
        <div class="col-12">
          <div><b>Notes:</b> <?= nl2br(e((string)($b['notes'] ?? ''))) ?></div>
          <?php if (!empty($b['rejection_reason'])): ?>
            <div class="alert alert-danger mt-3 mb-0"><b>Rejection reason:</b> <?= e((string)$b['rejection_reason']) ?></div>
          <?php endif; ?>
        </div>
      </div>
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
                <th>Reason/Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= e(ucwords(str_replace('_',' ', (string)$h['role']))) ?></td>
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

