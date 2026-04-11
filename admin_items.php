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
            $name     = sanitizeInput($_POST['name'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $qty      = (int)($_POST['quantity_available'] ?? 0);
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            if ($name === '' || $category === '' || $qty < 0) {
                redirectWithMessage('admin_items.php', 'danger', 'Invalid item details.');
            }

            $photoPath = null;
            if (!empty($_FILES['photo']['name'])) {
                $file = $_FILES['photo'];
                if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                    $tmp   = (string)$file['tmp_name'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
                    if ($finfo) finfo_close($finfo);
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        default      => null
                    };
                    if ($ext) {
                        $base = 'item_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $rel  = 'uploads/item_photos/' . $base;
                        $abs  = __DIR__ . '/' . $rel;
                        if (move_uploaded_file($tmp, $abs)) $photoPath = $rel;
                    }
                }
            }

            $stmt = $pdo->prepare('INSERT INTO items (name, category, quantity_available, photo_path, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$name, $category, $qty, $photoPath, $isActive]);
            redirectWithMessage('admin_items.php', 'success', 'Item created.');
        }

        if ($action === 'adjust_qty') {
            $id  = (int)($_POST['id'] ?? 0);
            $qty = (int)($_POST['quantity_available'] ?? 0);
            if ($qty < 0) redirectWithMessage('admin_items.php', 'danger', 'Invalid quantity.');
            $stmt = $pdo->prepare('UPDATE items SET quantity_available = ? WHERE id = ?');
            $stmt->execute([$qty, $id]);
            redirectWithMessage('admin_items.php', 'success', 'Quantity updated.');
        }

        if ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE items SET is_active = IF(is_active=1,0,1) WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_items.php', 'success', 'Item status updated.');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_items.php', 'success', 'Item deleted.');
        }
    } catch (Throwable) {
        redirectWithMessage('admin_items.php', 'danger', 'Action failed.');
    }
}

$rows = [];
try {
    $rows = $pdo->query('SELECT * FROM items ORDER BY created_at DESC, id DESC')->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<h1 class="h4 fw-bold mb-3">Items</h1>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<!-- Add Item — clear layout with each field labelled -->
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Add Item</div>
    <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="name" placeholder="e.g. LCD Projector" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                    <input class="form-control" name="category" placeholder="e.g. Equipment" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" min="0" name="quantity_available"
                           placeholder="e.g. 5" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Photo <span class="text-muted fw-normal">(JPG/PNG, optional)</span></label>
                    <input class="form-control" type="file" name="photo" accept="image/jpeg,image/png">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="itemActive" checked>
                        <label class="form-check-label fw-semibold" for="itemActive">Active</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-warning fw-semibold w-100">Add Item</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Items Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Active</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No items yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$r['id'] ?></td>
                            <td>
                                <?php $img = $r['photo_path'] ? (string)$r['photo_path'] : 'assets/images/ndmubg.jpg'; ?>
                                <img src="<?= e($img) ?>" width="72" height="50"
                                     style="object-fit:cover;border-radius:6px;" class="border" alt="photo">
                            </td>
                            <td class="fw-semibold"><?= e((string)$r['name']) ?></td>
                            <td><?= e((string)$r['category']) ?></td>
                            <td>
                                <form method="post" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="adjust_qty">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input class="form-control form-control-sm" type="number" min="0"
                                           name="quantity_available" value="<?= (int)$r['quantity_available'] ?>"
                                           style="max-width:100px;">
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                            <td>
                                <?= (int)$r['is_active'] === 1
                                    ? '<span class="badge bg-success">Yes</span>'
                                    : '<span class="badge bg-secondary">No</span>' ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <?= (int)$r['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('Delete this item?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
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
