<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    $action = sanitizeInput($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $location = sanitizeInput($_POST['location'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 0);
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            if ($name === '' || $location === '' || $capacity <= 0) {
                redirectWithMessage('admin_facilities.php', 'danger', 'Invalid facility details.');
            }

            $photoPath = null;
            if (!empty($_FILES['photo']['name'])) {
                $file = $_FILES['photo'];
                if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                    $tmp = (string)$file['tmp_name'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $tmp) : '';
                    if ($finfo) finfo_close($finfo);
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        default => null
                    };
                    if ($ext) {
                        $base = 'fac_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $rel = 'uploads/facility_photos/' . $base;
                        $abs = __DIR__ . '/' . $rel;
                        if (move_uploaded_file($tmp, $abs)) $photoPath = $rel;
                    }
                }
            }

            $stmt = $pdo->prepare('INSERT INTO facilities (name, location, capacity, photo_path, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$name, $location, $capacity, $photoPath, $isActive]);
            redirectWithMessage('admin_facilities.php', 'success', 'Facility created.');
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $location = sanitizeInput($_POST['location'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 0);
            if ($name === '' || $location === '' || $capacity <= 0) {
                redirectWithMessage('admin_facilities.php', 'danger', 'Invalid facility details.');
            }

            $photoPath = null;
            if (!empty($_FILES['photo']['name'])) {
                $file = $_FILES['photo'];
                if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                    $tmp = (string)$file['tmp_name'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $tmp) : '';
                    if ($finfo) finfo_close($finfo);
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        default => null
                    };
                    if ($ext) {
                        $base = 'fac_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $rel = 'uploads/facility_photos/' . $base;
                        $abs = __DIR__ . '/' . $rel;
                        if (move_uploaded_file($tmp, $abs)) $photoPath = $rel;
                    }
                }
            }

            if ($photoPath) {
                $stmt = $pdo->prepare('UPDATE facilities SET name=?, location=?, capacity=?, photo_path=? WHERE id=?');
                $stmt->execute([$name, $location, $capacity, $photoPath, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE facilities SET name=?, location=?, capacity=? WHERE id=?');
                $stmt->execute([$name, $location, $capacity, $id]);
            }
            redirectWithMessage('admin_facilities.php', 'success', 'Facility updated.');
        }

        if ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE facilities SET is_active = IF(is_active=1,0,1) WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_facilities.php', 'success', 'Facility status updated.');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM facility_bookings WHERE facility_id = ?');
            $stmt->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM facilities WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_facilities.php', 'success', 'Facility deleted.');
        }
    } catch (Throwable) {
        redirectWithMessage('admin_facilities.php', 'danger', 'Action failed.');
    }
}

$rows = [];
try {
    $rows = $pdo->query('SELECT * FROM facilities ORDER BY created_at DESC, id DESC')->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<h1 class="h4 fw-bold mb-3">Facilities</h1>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
  <div class="card-body p-4">
    <h2 class="h6 fw-semibold mb-3">Add Facility</h2>
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="col-md-3"><input class="form-control" name="name" placeholder="Name" required></div>
      <div class="col-md-3"><input class="form-control" name="location" placeholder="Location" required></div>
      <div class="col-md-2"><input class="form-control" type="number" min="1" name="capacity" placeholder="Capacity" required></div>
      <div class="col-md-2">
        <label class="form-label small mb-1 fw-semibold">Photo (JPG/PNG)</label>
        <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png">
      </div>
      <div class="col-md-1 d-flex align-items-center">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="facActive" checked>
          <label class="form-check-label" for="facActive">Active</label>
        </div>
      </div>
      <div class="col-md-1"><button class="btn btn-warning w-100 fw-semibold">Add</button></div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-4">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Photo</th>
            <th>Name</th>
            <th>Location</th>
            <th>Capacity</th>
            <th>Active</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td>
                <?php $img = $r['photo_path'] ? (string)$r['photo_path'] : 'assets/images/ndmubg.jpg'; ?>
                <img src="<?= e($img) ?>" width="58" height="40" style="object-fit:cover" class="rounded border" alt="photo">
              </td>
              <td><?= e((string)$r['name']) ?></td>
              <td><?= e((string)$r['location']) ?></td>
              <td><?= (int)$r['capacity'] ?></td>
              <td><?= (int)$r['is_active'] === 1 ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updFac<?= (int)$r['id'] ?>">Update</button>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary"><?= (int)$r['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this facility?');">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </div>
                <!-- Update Facility Modal -->
                <div class="modal fade" id="updFac<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Update Facility</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="post" enctype="multipart/form-data">
                        <div class="modal-body">
                          <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input class="form-control" name="name" value="<?= e((string)$r['name']) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input class="form-control" name="location" value="<?= e((string)$r['location']) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input class="form-control" type="number" min="1" name="capacity" value="<?= (int)$r['capacity'] ?>" required>
                          </div>
                          <div class="mb-3">
                            <label class="form-label">Photo (JPG/PNG, optional)</label>
                            <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png">
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button class="btn btn-primary">Save Changes</button>
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
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

