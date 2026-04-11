<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');

$user = getCurrentUser();

// Fetch dashboard stats
$totalUsers = 0;
$totalFacilities = 0;
$totalItems = 0;
$facilityBookings = 0;
$itemBookings = 0;

try {
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (Throwable) {}

try {
    $totalFacilities = (int)$pdo->query('SELECT COUNT(*) FROM facilities')->fetchColumn();
} catch (Throwable) {}

try {
    $totalItems = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
} catch (Throwable) {}

try {
    $facilityBookings = (int)$pdo->query('SELECT COUNT(*) FROM facility_bookings')->fetchColumn();
} catch (Throwable) {}

try {
    $itemBookings = (int)$pdo->query('SELECT COUNT(*) FROM item_bookings')->fetchColumn();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<div class="d-flex align-items-center gap-3 mb-4">
  <img src="assets/images/ndmulogo.png" alt="NDMU" width="40" height="40" style="object-fit:contain">
  <div>
    <h1 class="h4 fw-bold mb-0">NDMU Facility Booking System — Admin Panel</h1>
    <div class="text-muted">Welcome, <?= e($user['name'] ?? 'Admin') ?></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Users</div>
        <div class="h3 fw-bold mb-1"><?= $totalUsers ?></div>
        <a href="admin_users.php" class="small">Manage users</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Facilities</div>
        <div class="h3 fw-bold mb-1"><?= $totalFacilities ?></div>
        <a href="admin_facilities.php" class="small">Manage facilities</a>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Items</div>
        <div class="h3 fw-bold mb-1"><?= $totalItems ?></div>
        <a href="admin_items.php" class="small">Manage items</a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Facility Bookings</div>
        <div class="h3 fw-bold mb-1"><?= $facilityBookings ?></div>
        <a href="admin_bookings.php" class="small">View bookings</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Item Bookings</div>
        <div class="h3 fw-bold mb-1"><?= $itemBookings ?></div>
        <a href="admin_bookings.php" class="small">View bookings</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
