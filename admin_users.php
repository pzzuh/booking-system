<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');
$flash = getFlash();

$roles = [
    'student'       => 'Student',
    'adviser'       => 'Adviser',
    'staff'         => 'Staff',
    'dsa_director'  => 'DSA Director',
    'ppss_director' => 'PPSS Director',
    'dean'          => 'Dean',
    'avp_admin'     => 'AVP Admin',
    'vp_admin'      => 'VP Admin',
    'president'     => 'President',
    'admin'         => 'Admin',
    'janitor'       => 'Janitor',
    'security'      => 'Security',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    $action = sanitizeInput($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name  = sanitizeInput($_POST['full_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $role  = sanitizeInput($_POST['role'] ?? 'student');
            $pwd   = (string)($_POST['password'] ?? '');
            if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $pwd === '' || strlen($pwd) < 8 || !array_key_exists($role, $roles)) {
                redirectWithMessage('admin_users.php', 'danger', 'Invalid user details. Password must be at least 8 characters.');
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) {
                redirectWithMessage('admin_users.php', 'danger', 'Email already exists.');
            }
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
            $stmt->execute([$name, $email, $hash, $role]);
            redirectWithMessage('admin_users.php', 'success', 'User created successfully.');
        }

        if ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_users.php', 'success', 'User status updated.');
        }

        if ($action === 'update_role') {
            $id   = (int)($_POST['id'] ?? 0);
            $role = sanitizeInput($_POST['role'] ?? '');
            if (!array_key_exists($role, $roles)) {
                redirectWithMessage('admin_users.php', 'danger', 'Invalid role selected.');
            }
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $id]);
            redirectWithMessage('admin_users.php', 'success', 'Role updated successfully.');
        }

        if ($action === 'reset_password') {
            $id  = (int)($_POST['id'] ?? 0);
            $pwd = (string)($_POST['new_password'] ?? '');
            if (strlen($pwd) < 8) {
                redirectWithMessage('admin_users.php', 'danger', 'Password must be at least 8 characters.');
            }
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $id]);
            redirectWithMessage('admin_users.php', 'success', 'Password reset successfully.');
        }

    } catch (Throwable) {
        redirectWithMessage('admin_users.php', 'danger', 'Action failed. Please try again.');
    }
}

// Search / filter
$search     = sanitizeInput($_GET['search'] ?? '');
$filterRole = sanitizeInput($_GET['role'] ?? '');

$where  = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filterRole !== '' && array_key_exists($filterRole, $roles)) {
    $where   .= ' AND u.role = ?';
    $params[] = $filterRole;
}

$users = [];
try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, department, student_id, is_active, created_at FROM users u {$where} ORDER BY created_at DESC, id DESC");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 fw-bold mb-0">User Management</h1>
  <span class="badge bg-secondary"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></span>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<!-- Create User -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold">
    <i class="fa-solid fa-user-plus me-2"></i>Add New User
  </div>
  <div class="card-body p-4">
    <p class="text-muted small mb-3">Create a user with a specific role. For self-registered accounts, role defaults to <strong>Student</strong> and can be changed below.</p>
    <form method="post" class="row g-2">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="col-md-3">
        <input class="form-control" name="full_name" placeholder="Full name" required>
      </div>
      <div class="col-md-3">
        <input class="form-control" type="email" name="email" placeholder="Email" required>
      </div>
      <div class="col-md-2">
        <select class="form-select" name="role">
          <?php foreach ($roles as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $val === 'student' ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input class="form-control" type="password" name="password" placeholder="Temp password (8+ chars)" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-warning w-100 fw-semibold">
          <i class="fa-solid fa-plus me-1"></i>Create
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Filter / Search -->
<div class="card shadow-sm mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <input class="form-control" name="search" placeholder="Search name, email or ID…" value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <select class="form-select" name="role">
          <option value="">All Roles</option>
          <?php foreach ($roles as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $filterRole === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-primary w-100">Filter</button>
      </div>
      <div class="col-sm-2">
        <a class="btn btn-outline-secondary w-100" href="admin_users.php">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Users Table -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Department</th>
            <th>Role</th>
            <th>Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td class="ps-3 text-muted small"><?= (int)$u['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= e((string)$u['full_name']) ?></div>
                <?php if ($u['student_id']): ?>
                  <div class="text-muted small"><?= e((string)$u['student_id']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small"><?= e((string)$u['email']) ?></td>
              <td class="small text-muted"><?= e((string)($u['department'] ?? '—')) ?></td>

              <!-- Role Assignment -->
              <td>
                <form method="post" class="d-flex gap-2 align-items-center">
                  <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                  <input type="hidden" name="action" value="update_role">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <select class="form-select form-select-sm" name="role" style="min-width:150px;"
                    title="Change role for <?= e((string)$u['full_name']) ?>">
                    <?php foreach ($roles as $val => $label): ?>
                      <option value="<?= e($val) ?>" <?= (string)$u['role'] === $val ? 'selected' : '' ?>>
                        <?= e($label) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-primary" title="Save role">
                    <i class="fa-solid fa-check"></i>
                  </button>
                </form>
              </td>

              <td>
                <?= (int)$u['is_active'] === 1
                  ? '<span class="badge bg-success">Active</span>'
                  : '<span class="badge bg-secondary">Inactive</span>' ?>
              </td>

              <td class="text-end pe-3">
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                  <!-- Toggle Active -->
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary">
                      <?= (int)$u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>

                  <!-- Reset Password -->
                  <button class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#reset<?= (int)$u['id'] ?>">
                    Reset Password
                  </button>
                </div>

                <!-- Reset Password Modal -->
                <div class="modal fade" id="reset<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Reset Password — <?= e((string)$u['full_name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="post">
                        <div class="modal-body">
                          <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                          <input type="hidden" name="action" value="reset_password">
                          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                          <label class="form-label">New password <span class="text-muted">(min. 8 characters)</span></label>
                          <input class="form-control" type="password" name="new_password" required minlength="8">
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button class="btn btn-danger">Reset Password</button>
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
