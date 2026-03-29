<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$facilities = [];
try {
    $stmt = $pdo->query('SELECT id, name, location, capacity, photo_path, is_active FROM facilities WHERE is_active = 1 ORDER BY name ASC');
    $facilities = $stmt->fetchAll();
} catch (Throwable) {
    $facilities = [];
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubg.jpg')">
  <div class="banner-content container py-5">
    <h1 class="h2 fw-bold mb-1">Book a Facility</h1>
    <div class="text-white-50">Choose an available facility and submit your request.</div>
  </div>
</div>

<div class="container pb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted">Showing active facilities</div>
    <a class="btn btn-outline-secondary" href="facility_calendar.php"><i class="fa-regular fa-calendar me-1"></i>Calendar</a>
  </div>

  <?php if (!$facilities): ?>
    <div class="alert alert-info">No active facilities found. Please check back later.</div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($facilities as $f): ?>
      <?php
        $photo = (string)($f['photo_path'] ?? '');
        $img = $photo !== '' ? $photo : 'assets/images/ndmubg.jpg';
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <img src="<?= e($img) ?>" class="card-img-top" alt="<?= e((string)$f['name']) ?>" style="height:180px;object-fit:cover">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <h2 class="h5 mb-1"><?= e((string)$f['name']) ?></h2>
                <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= e((string)($f['location'] ?? '')) ?></div>
              </div>
              <span class="badge bg-primary">Capacity: <?= (int)($f['capacity'] ?? 0) ?></span>
            </div>
          </div>
          <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
            <a class="btn btn-warning w-100 fw-semibold" href="book_facility_form.php?facility_id=<?= (int)$f['id'] ?>">Book This</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
