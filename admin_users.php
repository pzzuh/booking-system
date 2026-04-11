<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');

$flash = getFlash();

// Corrected role labels
$roles = [
    'student'       => 'Student',
    'adviser'       => 'Adviser',
    'staff'         => 'Staff',
    'dsa_director'  => 'DSA Director',
    'ppss_director' => 'PPSS Director',
    'dean'          => 'Dean',
    'avp_admin'     => 'Administrative Vice President',
    'vp_admin'      => 'Vice President',
    'president'     => 'President',
    'admin'         => 'Admin',
    'janitor'       => 'Janitorial',
    'security'      => 'Security',
];

// Departments list for dropdown (no Senior High School)
$allDepartments = [
    'College of Arts and Sciences' => [
        'Bachelor of Arts in Communication',
        'Bachelor of Arts in English Language Studies',
        'Bachelor of Science in Biology',
        'Bachelor of Science in Mathematics',
        'Bachelor of Science in Psychology',
        'Bachelor of Science in Social Work',
    ],
    'College of Business and Accountancy' => [
        'Bachelor of Science in Accountancy',
        'Bachelor of Science in Business Administration - Financial Management',
        'Bachelor of Science in Business Administration - Human Resource Management',
        'Bachelor of Science in Business Administration - Marketing Management',
        'Bachelor of Science in Business Administration - Operations Management',
    ],
    'College of Computer Studies' => [
        'Bachelor of Science in Computer Science',
        'Bachelor of Science in Information Technology',
        'Bachelor of Science in Information Systems',
    ],
    'College of Criminal Justice Education' => [
        'Bachelor of Science in Criminology',
    ],
    'College of Education' => [
        'Bachelor of Elementary Education',
        'Bachelor of Secondary Education - English',
        'Bachelor of Secondary Education - Mathematics',
        'Bachelor of Secondary Education - Science',
        'Bachelor of Secondary Education - Social Studies',
        'Bachelor of Physical Education',
    ],
    'College of Engineering' => [
        'Bachelor of Science in Civil Engineering',
        'Bachelor of Science in Electrical Engineering',
        'Bachelor of Science in Electronics Engineering',
        'Bachelor of Science in Mechanical Engineering',
    ],
    'College of Health Sciences' => [
        'Bachelor of Science in Nursing',
        'Bachelor of Science in Pharmacy',
        'Bachelor of Science in Medical Technology',
        'Bachelor of Science in Physical Therapy',
        'Bachelor of Science in Radiologic Technology',
    ],
    'College of Law' => [
        'Juris Doctor',
    ],
    'Graduate School' => [
        'Master of Arts in Education',
        'Master of Business Administration',
        'Master of Science in Information Technology',
        'Doctor of Philosophy in Educational Management',
    ],
    'Administration / Non-Academic' => [
        'Office of the President',
        'Office of the VP for Administration',
        'Office of the VP for Academics',
        'Office of the AVP for Admin',
        'Dean\'s Office',
        'Director of Student Affairs (DSA)',
        'Physical Plant and Security Services (PPSS)',
        'Registrar\'s Office',
        'Finance Office',
        'Human Resources Office',
        'Information Technology Office',
        'Library',
        'Guidance and Counseling Center',
        'Campus Ministry',
        'Athletics',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();
    $action = sanitizeInput($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name       = sanitizeInput($_POST['full_name'] ?? '');
            $email      = sanitizeInput($_POST['email'] ?? '');
            $role       = sanitizeInput($_POST['role'] ?? 'student');
            $pwd        = (string)($_POST['password'] ?? '');
            $department = sanitizeInput($_POST['department'] ?? '');

            if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || $pwd === '' || strlen($pwd) < 8 || !array_key_exists($role, $roles)) {
                redirectWithMessage('admin_users.php', 'danger', 'Invalid user details. Password must be at least 8 characters.');
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) {
                redirectWithMessage('admin_users.php', 'danger', 'Email already exists.');
            }
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, department, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
            $stmt->execute([$name, $email, $hash, $role, $department]);
            redirectWithMessage('admin_users.php', 'success', 'User created successfully.');
        }

        if ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id = ?');
            $stmt->execute([$id]);
            redirectWithMessage('admin_users.php', 'success', 'User status updated.');
        }

        if ($action === 'update_user') {
            $id         = (int)($_POST['id'] ?? 0);
            $name       = sanitizeInput($_POST['full_name'] ?? '');
            $studentId  = sanitizeInput($_POST['student_id'] ?? '');
            $role       = sanitizeInput($_POST['role'] ?? '');
            $department = sanitizeInput($_POST['department'] ?? '');

            if (!array_key_exists($role, $roles)) {
                redirectWithMessage('admin_users.php', 'danger', 'Invalid role selected.');
            }
            $stmt = $pdo->prepare('UPDATE users SET full_name=?, student_id=?, role=?, department=? WHERE id=?');
            $stmt->execute([$name, $studentId, $role, $department, $id]);
            redirectWithMessage('admin_users.php', 'success', 'User info updated successfully.');
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
            <div class="col-md-3">
                <select class="form-select" name="department">
                    <option value="">— Department (optional) —</option>
                    <?php foreach ($allDepartments as $college => $depts): ?>
                        <optgroup label="<?= e($college) ?>">
                            <?php foreach ($depts as $dept): ?>
                                <option value="<?= e($dept) ?>"><?= e($dept) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input class="form-control" type="password" name="password" placeholder="Temp password (8+ chars)" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-warning w-100 fw-semibold">
                    <i class="fa-solid fa-plus me-1"></i>+ Create
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
                        <th>Name / ID No.</th>
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
                                <!-- Inline editable name & student_id -->
                                <form method="post" class="d-flex flex-column gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="role" value="<?= e((string)$u['role']) ?>">
                                    <input class="form-control form-control-sm" name="full_name"
                                           value="<?= e((string)$u['full_name']) ?>" required>
                                    <input class="form-control form-control-sm text-muted" name="student_id"
                                           placeholder="ID number"
                                           value="<?= e((string)($u['student_id'] ?? '')) ?>">
                                    <select class="form-select form-select-sm" name="department">
                                        <option value="">— Department —</option>
                                        <?php foreach ($allDepartments as $college => $depts): ?>
                                            <optgroup label="<?= e($college) ?>">
                                                <?php foreach ($depts as $dept): ?>
                                                    <option value="<?= e($dept) ?>"
                                                        <?= ((string)($u['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                                                        <?= e($dept) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary mt-1">Update</button>
                                </form>
                            </td>
                            <td class="small"><?= e((string)$u['email']) ?></td>
                            <td class="small text-muted"><?= e((string)($u['department'] ?? '—')) ?></td>

                            <!-- Role Assignment (separate form for clarity) -->
                            <td>
                                <form method="post" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="full_name" value="<?= e((string)$u['full_name']) ?>">
                                    <input type="hidden" name="student_id" value="<?= e((string)($u['student_id'] ?? '')) ?>">
                                    <input type="hidden" name="department" value="<?= e((string)($u['department'] ?? '')) ?>">
                                    <select class="form-select form-select-sm" name="role" style="min-width:180px;"
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
