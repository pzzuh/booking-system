<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$items = [];
try {
    $stmt = $pdo->query('SELECT id, name, category, quantity_available, photo_path, is_active FROM items WHERE is_active = 1 ORDER BY category ASC, name ASC');
    $items = $stmt->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubg.jpg')">
  <div class="banner-content container py-5">
    <h1 class="h2 fw-bold mb-1">Borrow Equipment</h1>
    <div class="text-white-50">Request items and track approvals.</div>
  </div>
</div>

<div class="container pb-5">
  <?php if (!$items): ?>
    <div class="alert alert-info">No active items found. Please check back later.</div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($items as $it): ?>
      <?php
        $photo = (string)($it['photo_path'] ?? '');
        $img = $photo !== '' ? $photo : 'assets/images/ndmubg.jpg';
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm">
          <img src="<?= e($img) ?>" class="card-img-top" alt="<?= e((string)$it['name']) ?>" style="height:180px;object-fit:cover">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <h2 class="h5 mb-1"><?= e((string)$it['name']) ?></h2>
                <div class="text-muted small"><?= e((string)($it['category'] ?? '')) ?></div>
              </div>
              <span class="badge bg-success">Available: <?= (int)($it['quantity_available'] ?? 0) ?></span>
            </div>
          </div>
          <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
            <a class="btn btn-warning w-100 fw-semibold" href="book_item_form.php?item_id=<?= (int)$it['id'] ?>">Borrow</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
