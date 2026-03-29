<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');
$flash = getFlash();

$keys = ['school_name','address','email','phone','booking_rules'];
$values = array_fill_keys($keys, '');

try {
    $stmt = $pdo->query('SELECT `key`, `value` FROM system_settings');
    foreach ($stmt->fetchAll() as $r) {
        $k = (string)$r['key'];
        if (array_key_exists($k, $values)) $values[$k] = (string)$r['value'];
    }
} catch (Throwable) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    try {
        foreach ($keys as $k) {
            $v = sanitizeInput($_POST[$k] ?? '');
            $stmt = $pdo->prepare('INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
            $stmt->execute([$k, $v]);
        }
        redirectWithMessage('admin_settings.php', 'success', 'Settings saved.');
    } catch (Throwable) {
        redirectWithMessage('admin_settings.php', 'danger', 'Unable to save settings.');
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<h1 class="h4 fw-bold mb-3">Settings</h1>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body p-4">
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
      <div class="col-md-6">
        <label class="form-label">School Name</label>
        <input class="form-control" name="school_name" value="<?= e($values['school_name']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input class="form-control" name="phone" value="<?= e($values['phone']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" value="<?= e($values['email']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Address</label>
        <input class="form-control" name="address" value="<?= e($values['address']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Booking Rules</label>
        <textarea class="form-control" name="booking_rules" rows="5"><?= e($values['booking_rules']) ?></textarea>
      </div>
      <div class="col-12">
        <button class="btn btn-warning fw-semibold">Save Settings</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

