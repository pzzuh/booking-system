<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$me = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Invalid booking.');
}

$stmt = $pdo->prepare(
    "SELECT fb.*, f.name AS facility_name, f.location,
            u.full_name AS student_name, u.student_id, u.department, u.email AS student_email
     FROM facility_bookings fb
     JOIN facilities f ON f.id = fb.facility_id
     JOIN users u ON u.id = fb.user_id
     WHERE fb.id = ?"
);
$stmt->execute([$id]);
$b = $stmt->fetch();
if (!$b) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Booking not found.');
}

$isOwner = ((int)$b['user_id'] === (int)$me['id']);
$isAdmin = ((string)$me['role'] === 'admin');
if (!$isOwner && !$isAdmin) {
    redirectWithMessage(roleRedirectTarget((string)$me['role']), 'danger', 'Access denied.');
}
if ((string)$b['status'] !== 'fully_approved') {
    redirectWithMessage($isAdmin ? 'admin_bookings.php' : 'student_dashboard.php', 'warning', 'Receipt is only available for fully approved bookings.');
}

$year = (new DateTime((string)($b['created_at'] ?? 'now')))->format('Y');
$ref = 'FB-' . $year . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

$approvals = [];
try {
    $stmt = $pdo->prepare(
        "SELECT a.role, a.action, a.action_at, a.notes, u.full_name AS approver_name
         FROM facility_booking_approvals a
         LEFT JOIN users u ON u.id = a.approver_user_id
         WHERE a.booking_id = ? AND a.action IN ('approve','reject')
         ORDER BY a.action_at ASC, a.id ASC"
    );
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $r) {
        $approvals[(string)$r['role']] = $r;
    }
} catch (Throwable) {}

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container py-4 position-relative">
  <div class="watermark-approved">APPROVED</div>

  <div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button class="btn btn-success" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Print</button>
    <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
  </div>

  <div class="bg-white border rounded-3 p-4 position-relative" style="z-index:1;">
    <div class="text-center mb-3">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="80" height="80" style="object-fit:contain" class="mb-2">
      <div class="fw-bold fs-4">NOTRE DAME OF MARBEL UNIVERSITY</div>
      <div class="text-muted small">Marbel, Koronadal City, South Cotabato, Philippines</div>
      <div class="fw-bold fs-5 mt-2">OFFICIAL BOOKING PERMIT</div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="small text-muted">Booking Reference</div>
        <div class="fw-semibold"><?= e($ref) ?></div>
      </div>
      <div class="col-md-6 text-md-end">
        <div class="small text-muted">Date Issued</div>
        <div class="fw-semibold"><?= e((new DateTime())->format('M d, Y')) ?></div>
      </div>
    </div>

    <hr>

    <div class="row g-3">
      <div class="col-md-6">
        <div class="fw-semibold mb-2">Student Information</div>
        <div><b>Name:</b> <?= e((string)$b['student_name']) ?></div>
        <div><b>Student ID:</b> <?= e((string)($b['student_id'] ?? '')) ?></div>
        <div><b>Department:</b> <?= e((string)($b['department'] ?? '')) ?></div>
        <div><b>Email:</b> <?= e((string)$b['student_email']) ?></div>
      </div>
      <div class="col-md-6">
        <div class="fw-semibold mb-2">Booking Details</div>
        <div><b>Facility:</b> <?= e((string)$b['facility_name']) ?></div>
        <div><b>Location:</b> <?= e((string)$b['location']) ?></div>
        <div><b>Date:</b> <?= e((string)$b['date_start']) ?> to <?= e((string)$b['date_end']) ?></div>
        <div><b>Time:</b> <?= e((string)$b['time_start']) ?> to <?= e((string)$b['time_end']) ?></div>
        <div><b>Participants:</b> <?= (int)$b['participants'] ?></div>
      </div>
      <div class="col-12">
        <div><b>Purpose:</b> <?= e((string)$b['purpose']) ?></div>
        <div><b>Notes:</b> <?= nl2br(e((string)($b['notes'] ?? ''))) ?></div>
      </div>
    </div>

    <hr>

    <div class="fw-semibold mb-2">Approval Signatures</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Role</th>
            <th>Approver</th>
            <th>Date Approved</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (approvalChain() as $role): ?>
            <?php $a = $approvals[$role] ?? null; ?>
            <tr>
              <td><?= e(ucwords(str_replace('_',' ', $role))) ?></td>
              <td><?= e($a['approver_name'] ?? 'Pending') ?></td>
              <td><?= e(!empty($a['action_at']) ? (new DateTime((string)$a['action_at']))->format('M d, Y') : 'Pending') ?></td>
              <td><?= e((string)($a['notes'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

