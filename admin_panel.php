<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');
$me = getCurrentUser();
$flash = getFlash();

$stats = ['users'=>0,'facilities'=>0,'items'=>0,'facility_bookings'=>0,'item_bookings'=>0];
try {
    $stats['users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['facilities'] = (int)$pdo->query('SELECT COUNT(*) FROM facilities')->fetchColumn();
    $stats['items'] = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    $stats['facility_bookings'] = (int)$pdo->query('SELECT COUNT(*) FROM facility_bookings')->fetchColumn();
    $stats['item_bookings'] = (int)$pdo->query('SELECT COUNT(*) FROM item_bookings')->fetchColumn();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="d-flex align-items-center gap-2">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="36" height="36" style="object-fit:contain">
      <div class="fw-bold">NDMU Facility Booking System — Admin Panel</div>
    </div>
    <div class="text-muted small">Welcome, <?= e((string)$me['name']) ?></div>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Users</div>
      <div class="fs-4 fw-bold"><?= (int)$stats['users'] ?></div>
      <a href="admin_users.php" class="small">Manage users</a>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Facilities</div>
      <div class="fs-4 fw-bold"><?= (int)$stats['facilities'] ?></div>
      <a href="admin_facilities.php" class="small">Manage facilities</a>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Items</div>
      <div class="fs-4 fw-bold"><?= (int)$stats['items'] ?></div>
      <a href="admin_items.php" class="small">Manage items</a>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Facility Bookings</div>
      <div class="fs-4 fw-bold"><?= (int)$stats['facility_bookings'] ?></div>
      <a href="admin_bookings.php" class="small">View bookings</a>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm"><div class="card-body">
      <div class="text-muted small">Item Bookings</div>
      <div class="fs-4 fw-bold"><?= (int)$stats['item_bookings'] ?></div>
      <a href="admin_bookings.php" class="small">View bookings</a>
    </div></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

