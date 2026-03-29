<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('janitor');
$me = getCurrentUser();
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    $id = (int)($_POST['alert_id'] ?? 0);
    $action = sanitizeInput($_POST['action'] ?? '');
    if ($id > 0 && in_array($action, ['in_progress','resolved'], true)) {
        try {
            $stmt = $pdo->prepare('UPDATE maintenance_alerts SET status = ? WHERE id = ? AND assigned_to_role = ?');
            $stmt->execute([$action, $id, 'janitor']);
            redirectWithMessage('janitor_dashboard.php', 'success', 'Alert updated.');
        } catch (Throwable) {
            redirectWithMessage('janitor_dashboard.php', 'danger', 'Unable to update alert.');
        }
    }
}

$alerts = [];
try {
    $stmt = $pdo->prepare(
        "SELECT ma.*, f.name AS facility_name
         FROM maintenance_alerts ma
         JOIN facilities f ON f.id = ma.facility_id
         WHERE ma.assigned_to_role = 'janitor' AND ma.status IN ('open','in_progress')
         ORDER BY ma.severity DESC, ma.date_start ASC"
    );
    $stmt->execute();
    $alerts = $stmt->fetchAll();
} catch (Throwable) {}

$upcoming = [];
try {
    $stmt = $pdo->query(
        "SELECT fb.id, fb.date_start, fb.date_end, fb.time_start, fb.time_end, f.name AS facility_name
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         WHERE fb.status = 'fully_approved'
           AND fb.date_start <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
           AND fb.date_end >= CURDATE()
         ORDER BY fb.date_start ASC, fb.time_start ASC"
    );
    $upcoming = $stmt->fetchAll();
} catch (Throwable) {}

function sevBadge(string $sev): string {
    $c = match ($sev) { 'high' => 'danger', 'medium' => 'warning', 'low' => 'success', default => 'secondary' };
    return '<span class="badge bg-' . e($c) . '">' . e(ucfirst($sev)) . '</span>';
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubg.jpg')">
  <div class="banner-content container py-4">
    <div class="d-flex align-items-center gap-3">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="54" height="54" style="object-fit:contain">
      <div>
        <div class="h4 fw-bold mb-1">Janitor Dashboard</div>
        <div class="text-white-50">Logged in as <?= e((string)$me['name']) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="container pb-5">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body p-4">
      <h2 class="h5 fw-semibold mb-3">Maintenance Alerts</h2>
      <?php if (!$alerts): ?>
        <div class="alert alert-success mb-0">No open alerts assigned to Janitor.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Facility</th>
                <th>Title</th>
                <th>Severity</th>
                <th>Date Range</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($alerts as $a): ?>
                <tr>
                  <td><?= e((string)$a['facility_name']) ?></td>
                  <td><?= e((string)$a['title']) ?></td>
                  <td><?= sevBadge((string)$a['severity']) ?></td>
                  <td class="small"><?= e((string)$a['date_start']) ?> → <?= e((string)$a['date_end']) ?></td>
                  <td><?= statusBadge((string)$a['status']) ?></td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                        <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="action" value="in_progress">
                        <button class="btn btn-sm btn-outline-primary">Mark In Progress</button>
                      </form>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                        <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                        <input type="hidden" name="action" value="resolved">
                        <button class="btn btn-sm btn-success">Mark Resolved</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h2 class="h5 fw-semibold mb-3">Today + Next 7 Days (Fully Approved)</h2>
      <?php if (!$upcoming): ?>
        <div class="text-muted">No fully approved bookings found for the next 7 days.</div>
      <?php else: ?>
        <?php
          $byDate = [];
          foreach ($upcoming as $u) {
              $byDate[(string)$u['date_start']][] = $u;
          }
        ?>
        <div class="row g-3">
          <?php foreach ($byDate as $d => $list): ?>
            <div class="col-md-6 col-lg-4">
              <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2"><?= e($d) ?></div>
                <?php foreach ($list as $u): ?>
                  <div class="small mb-2">
                    <div class="fw-semibold"><?= e((string)$u['facility_name']) ?></div>
                    <div class="text-muted"><?= e((string)$u['time_start']) ?> → <?= e((string)$u['time_end']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

