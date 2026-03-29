<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$user = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Invalid booking.');
}

$stmt = $pdo->prepare(
    'SELECT fb.*, f.name AS facility_name, f.location, f.photo_path
     FROM facility_bookings fb
     JOIN facilities f ON f.id = fb.facility_id
     WHERE fb.id = ? AND fb.user_id = ?'
);
$stmt->execute([$id, (int)$user['id']]);
$b = $stmt->fetch();
if (!$b) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Booking not found.');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-5" style="max-width:780px;">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fa-regular fa-circle-check text-success fa-lg"></i>
        <h1 class="h4 fw-bold mb-0">Booking Submitted</h1>
      </div>
      <div class="text-muted mb-4">Your request has been submitted for review.</div>

      <div class="row g-3">
        <div class="col-md-5">
          <?php $img = $b['photo_path'] ? (string)$b['photo_path'] : 'assets/images/ndmubg.jpg'; ?>
          <img src="<?= e($img) ?>" class="w-100 rounded" style="height:220px;object-fit:cover" alt="Facility">
        </div>
        <div class="col-md-7">
          <div class="fw-semibold"><?= e((string)$b['facility_name']) ?></div>
          <div class="text-muted small mb-2"><i class="fa-solid fa-location-dot me-1"></i><?= e((string)$b['location']) ?></div>
          <div class="mb-2"><?= statusBadge((string)$b['status']) ?> <span class="ms-2">Reviewing: <?= approvalRoleBadge((string)$b['current_approval_role']) ?></span></div>
          <div><b>Title:</b> <?= e((string)$b['title']) ?></div>
          <div><b>Date:</b> <?= e((string)$b['date_start']) ?> to <?= e((string)$b['date_end']) ?></div>
          <div><b>Time:</b> <?= e((string)$b['time_start']) ?> to <?= e((string)$b['time_end']) ?></div>
          <div><b>Participants:</b> <?= (int)$b['participants'] ?></div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <a class="btn btn-warning fw-semibold" href="student_dashboard.php">Go to Dashboard</a>
        <a class="btn btn-outline-secondary" href="facility_calendar.php">View Calendar</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
