<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin'); // prevents non-admin access
$me = getCurrentUser();
$flash = getFlash();

$facilities = [];
try {
    $facilities = $pdo->query('SELECT id, name FROM facilities ORDER BY name ASC')->fetchAll();
} catch (Throwable) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    $action = sanitizeInput($_POST['action'] ?? 'create');

    if ($action === 'create') {
        $facilityId = (int)($_POST['facility_id'] ?? 0);
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $severity = sanitizeInput($_POST['severity'] ?? 'low');
        $dateStart = sanitizeInput($_POST['date_start'] ?? '');
        $dateEnd = sanitizeInput($_POST['date_end'] ?? '');
        $assign = sanitizeInput($_POST['assigned_to_role'] ?? '');

        if ($facilityId <= 0 || $title === '' || $description === '' || $dateStart === '' || $dateEnd === '' || !in_array($severity, ['high','medium','low'], true) || !in_array($assign, ['janitor','security'], true)) {
            redirectWithMessage('admin_maintenance.php', 'danger', 'Please complete all fields.');
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO maintenance_alerts (facility_id, title, description, severity, date_start, date_end, assigned_to_role, status, created_by_user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW())"
            );
            $stmt->execute([$facilityId, $title, $description, $severity, $dateStart, $dateEnd, $assign, (int)$me['id']]);
            $alertId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE role = ? AND is_active = 1');
            $stmt->execute([$assign]);
            foreach ($stmt->fetchAll() as $row) {
                sendNotification(
                    $pdo,
                    (int)$row['id'],
                    'Maintenance Alert Assigned',
                    "New {$severity} maintenance alert: {$title}",
                    'alert',
                    $alertId
                );
            }
            redirectWithMessage('admin_maintenance.php', 'success', 'Maintenance alert created and notifications sent.');
        } catch (Throwable) {
            redirectWithMessage('admin_maintenance.php', 'danger', 'Unable to create alert.');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM maintenance_alerts WHERE id = ?');
                $stmt->execute([$id]);
                redirectWithMessage('admin_maintenance.php', 'success', 'Alert deleted.');
            } catch (Throwable) {
                redirectWithMessage('admin_maintenance.php', 'danger', 'Unable to delete alert.');
            }
        }
    }
}

$alerts = [];
try {
    $alerts = $pdo->query(
        "SELECT ma.*, f.name AS facility_name
         FROM maintenance_alerts ma
         JOIN facilities f ON f.id = ma.facility_id
         ORDER BY ma.created_at DESC, ma.id DESC"
    )->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4" style="max-width:1100px;">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 fw-bold mb-0">Admin Maintenance</h1>
    <a class="btn btn-outline-secondary" href="admin_panel.php">Back to Admin Panel</a>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body p-4">
      <h2 class="h5 fw-semibold mb-3">Create Maintenance Alert</h2>
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="col-md-6">
          <label class="form-label">Facility</label>
          <select class="form-select" name="facility_id" required>
            <option value="">Select facility...</option>
            <?php foreach ($facilities as $f): ?>
              <option value="<?= (int)$f['id'] ?>"><?= e((string)$f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Assign to</label>
          <select class="form-select" name="assigned_to_role" required>
            <option value="janitor">Janitor</option>
            <option value="security">Security</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Title</label>
          <input class="form-control" name="title" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Severity</label>
          <select class="form-select" name="severity" required>
            <option value="high">High</option>
            <option value="medium" selected>Medium</option>
            <option value="low">Low</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3" required></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Date Start</label>
          <input class="form-control" type="date" name="date_start" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Date End</label>
          <input class="form-control" type="date" name="date_end" required>
        </div>
        <div class="col-12">
          <button class="btn btn-warning fw-semibold">Create Alert</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h2 class="h5 fw-semibold mb-3">All Alerts</h2>
      <?php if (!$alerts): ?>
        <div class="text-muted">No alerts yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Facility</th>
                <th>Title</th>
                <th>Severity</th>
                <th>Date Range</th>
                <th>Assigned</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($alerts as $a): ?>
                <tr>
                  <td><?= (int)$a['id'] ?></td>
                  <td><?= e((string)$a['facility_name']) ?></td>
                  <td><?= e((string)$a['title']) ?></td>
                  <td><?= e(ucfirst((string)$a['severity'])) ?></td>
                  <td class="small"><?= e((string)$a['date_start']) ?> → <?= e((string)$a['date_end']) ?></td>
                  <td><?= e(ucfirst((string)$a['assigned_to_role'])) ?></td>
                  <td><?= statusBadge((string)$a['status']) ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this alert?');">
                      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
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

