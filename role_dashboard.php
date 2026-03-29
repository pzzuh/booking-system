<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($role, $role_label)) {
    http_response_code(500);
    echo 'Role dashboard misconfigured.';
    exit;
}

enforceCorrectDashboard((string)$role);
$user = getCurrentUser();
$flash = getFlash();

$summary = ['total'=>0,'awaiting'=>0,'approved'=>0,'rejected'=>0];
try {
    $summary['total'] = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings")->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facility_bookings WHERE current_approval_role = ? AND status = 'pending'");
    $stmt->execute([(string)$role]);
    $summary['awaiting'] = (int)$stmt->fetchColumn();
    $summary['approved'] = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE status = 'fully_approved'")->fetchColumn();
    $summary['rejected'] = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE status = 'rejected'")->fetchColumn();
} catch (Throwable) {}

$actionRequired = [];
try {
    $stmt = $pdo->prepare(
        "SELECT fb.*, f.name AS facility_name, u.full_name AS student_name
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         JOIN users u ON u.id = fb.user_id
         WHERE fb.current_approval_role = ?
           AND fb.status = 'pending'
         ORDER BY fb.created_at ASC, fb.id ASC"
    );
    $stmt->execute([(string)$role]);
    $actionRequired = $stmt->fetchAll();
} catch (Throwable) {}

// All bookings (pagination + status filter)
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending','fully_approved','rejected','cancelled'], true)) {
    $where = 'WHERE fb.status = ?';
    $params[] = $statusFilter;
}

$allRows = [];
$totalRows = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facility_bookings fb {$where}");
    $stmt->execute($params);
    $totalRows = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT fb.id, fb.title, fb.date_start, fb.date_end, fb.time_start, fb.time_end, fb.status, fb.current_approval_role,
                f.name AS facility_name,
                u.full_name AS student_name
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         JOIN users u ON u.id = fb.user_id
         {$where}
         ORDER BY fb.created_at DESC, fb.id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $allRows = $stmt->fetchAll();
} catch (Throwable) {}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubg.jpg')">
  <div class="banner-content container py-4">
    <div class="d-flex align-items-center gap-3">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="54" height="54" style="object-fit:contain">
      <div>
        <div class="h4 fw-bold mb-1"><?= e((string)$role_label) ?> Dashboard</div>
        <div class="text-white-50">Logged in as <?= e((string)$user['name']) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="container pb-5">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Total Bookings</div>
        <div class="fs-4 fw-bold"><?= (int)$summary['total'] ?></div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Awaiting My Action</div>
        <div class="fs-4 fw-bold"><?= (int)$summary['awaiting'] ?></div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Approved</div>
        <div class="fs-4 fw-bold"><?= (int)$summary['approved'] ?></div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Rejected</div>
        <div class="fs-4 fw-bold"><?= (int)$summary['rejected'] ?></div>
      </div></div>
    </div>
  </div>

  <ul class="nav nav-tabs" id="roleTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#actionRequired" type="button" role="tab">ACTION REQUIRED</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#allBookings" type="button" role="tab">ALL BOOKINGS</button>
    </li>
  </ul>

  <div class="tab-content border border-top-0 bg-white p-3 p-md-4 shadow-sm">
    <div class="tab-pane fade show active" id="actionRequired" role="tabpanel">
      <?php if (!$actionRequired): ?>
        <div class="alert alert-success">No bookings awaiting your action.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Student</th>
                <th>Facility</th>
                <th>Dates</th>
                <th>Participants</th>
                <th>Purpose</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($actionRequired as $b): ?>
                <tr>
                  <td><?= e((string)$b['student_name']) ?></td>
                  <td><?= e((string)$b['facility_name']) ?></td>
                  <td class="small">
                    <?= e((string)$b['date_start']) ?> → <?= e((string)$b['date_end']) ?><br>
                    <span class="text-muted"><?= e((string)$b['time_start']) ?> → <?= e((string)$b['time_end']) ?></span>
                  </td>
                  <td><?= (int)$b['participants'] ?></td>
                  <td><?= e((string)$b['purpose']) ?></td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                      <form method="post" action="approval_action.php" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                        <button class="btn btn-sm btn-success">APPROVE</button>
                      </form>
                      <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= (int)$b['id'] ?>">REJECT</button>
                      <a class="btn btn-sm btn-outline-secondary" href="booking_detail.php?id=<?= (int)$b['id'] ?>">Details</a>
                    </div>

                    <div class="modal fade" id="rejectModal<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Reject Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <form method="post" action="approval_action.php">
                            <div class="modal-body">
                              <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                              <input type="hidden" name="action" value="reject">
                              <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                              <label class="form-label">Rejection reason</label>
                              <textarea class="form-control" name="reason" rows="4" required></textarea>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button class="btn btn-danger">Reject</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="allBookings" role="tabpanel">
      <form class="row g-2 align-items-end mb-3">
        <div class="col-sm-4 col-md-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="">All</option>
            <?php foreach (['pending','fully_approved','rejected','cancelled'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4 col-md-3">
          <button class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Student</th>
              <th>Facility</th>
              <th>Dates</th>
              <th>Status</th>
              <th>Reviewing</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allRows as $r): ?>
              <tr class="pointer" onclick="window.location.href='booking_detail.php?id=<?= (int)$r['id'] ?>'">
                <td><?= (int)$r['id'] ?></td>
                <td><?= e((string)$r['title']) ?></td>
                <td><?= e((string)$r['student_name']) ?></td>
                <td><?= e((string)$r['facility_name']) ?></td>
                <td class="small"><?= e((string)$r['date_start']) ?> → <?= e((string)$r['date_end']) ?></td>
                <td><?= statusBadge((string)$r['status']) ?></td>
                <td><?= approvalRoleBadge((string)($r['current_approval_role'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <nav>
        <ul class="pagination">
          <?php for ($p=1; $p <= $totalPages; $p++): ?>
            <?php
              $qs = $_GET;
              $qs['page'] = $p;
              $url = '?' . http_build_query($qs);
            ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($url) ?>"><?= (int)$p ?></a></li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

