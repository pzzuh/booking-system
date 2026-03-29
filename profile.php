<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$user = getCurrentUser();
$flash = getFlash();

$stmt = $pdo->prepare('SELECT id, full_name, email, phone, department, student_id, role, profile_photo, password_hash FROM users WHERE id = ?');
$stmt->execute([(int)$user['id']]);
$dbUser = $stmt->fetch();
if (!$dbUser) {
    session_unset();
    session_destroy();
    redirectWithMessage('login.php', 'danger', 'Account not found.');
}

// NDMU Colleges and Departments (same list as register.php)
$ndmuDepartments = [
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
    'Senior High School' => [
        'Academic Track - STEM',
        'Academic Track - ABM',
        'Academic Track - HUMSS',
        'Academic Track - GAS',
        'TVL Track',
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

$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    if (!empty($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name       = sanitizeInput($_POST['full_name'] ?? '');
        $phone      = sanitizeInput($_POST['phone'] ?? '');
        $department = sanitizeInput($_POST['department'] ?? '');
        $studentId  = sanitizeInput($_POST['student_id'] ?? '');

        // Validate department against allowed list
        $allDepts = [];
        foreach ($ndmuDepartments as $college => $depts) {
            foreach ($depts as $d) {
                $allDepts[] = $d;
            }
        }

        if ($name === '' || $phone === '' || $department === '' || $studentId === '') {
            $error = 'Please complete all profile fields.';
        } elseif (!in_array($department, $allDepts, true)) {
            $error = 'Please select a valid department.';
        } else {
            $photoPath = (string)($dbUser['profile_photo'] ?? '');
            if (!empty($_FILES['profile_photo']['name'])) {
                $file = $_FILES['profile_photo'];
                if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $error = 'Photo upload failed.';
                } else {
                    $size = (int)($file['size'] ?? 0);
                    if ($size > (2 * 1024 * 1024)) {
                        $error = 'Profile photo must be under 2MB.';
                    } else {
                        $tmp   = (string)($file['tmp_name'] ?? '');
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
                        if ($finfo) finfo_close($finfo);
                        $ext = match ($mime) {
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            default      => null
                        };
                        if (!$ext) {
                            $error = 'Only JPG/PNG images are allowed.';
                        } else {
                            $baseName = 'u' . (int)$dbUser['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                            $destRel  = 'uploads/profile_photos/' . $baseName;
                            $destAbs  = __DIR__ . '/' . $destRel;
                            if (!move_uploaded_file($tmp, $destAbs)) {
                                $error = 'Unable to save uploaded photo.';
                            } else {
                                $photoPath = $destRel;
                            }
                        }
                    }
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare('UPDATE users SET full_name=?, phone=?, department=?, student_id=?, profile_photo=? WHERE id=?');
                $stmt->execute([$name, $phone, $department, $studentId, $photoPath, (int)$dbUser['id']]);
                $_SESSION['user']['name'] = $name;
                $success = 'Profile updated successfully.';
            }
        }
    }

    if (!empty($_POST['action']) && $_POST['action'] === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_new_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'Please complete all password fields.';
        } elseif (!password_verify($current, (string)$dbUser['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $stmt->execute([$hash, (int)$dbUser['id']]);
            $success = 'Password updated successfully.';
        }
    }

    // Refresh from DB
    $stmt = $pdo->prepare('SELECT id, full_name, email, phone, department, student_id, role, profile_photo, password_hash FROM users WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
    $dbUser = $stmt->fetch();
}

$photo    = (string)($dbUser['profile_photo'] ?? '');
$photoSrc = $photo !== '' ? $photo : 'assets/images/ndmulogo.png';

// Role label helper
$roleLabels = [
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
$roleLabel = $roleLabels[(string)$dbUser['role']] ?? ucfirst((string)$dbUser['role']);
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <img src="<?= e($photoSrc) ?>" alt="Profile" width="140" height="140" class="rounded-circle border" style="object-fit:cover">
          <div class="mt-3 fw-bold fs-5"><?= e((string)$dbUser['full_name']) ?></div>
          <div class="text-muted"><?= e((string)$dbUser['email']) ?></div>
          <div class="mt-2">
            <span class="badge bg-primary"><?= e($roleLabel) ?></span>
          </div>
          <?php if ((string)$dbUser['role'] === 'student'): ?>
            <div class="text-muted small mt-2">Contact an administrator to update your role.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h2 class="h5 fw-semibold mb-3">Edit Profile</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="update_profile">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Name</label>
                <input class="form-control" name="full_name" required value="<?= e((string)$dbUser['full_name']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" required value="<?= e((string)($dbUser['phone'] ?? '')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Student / Employee ID</label>
                <input class="form-control" name="student_id" required value="<?= e((string)($dbUser['student_id'] ?? '')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">College / Department</label>
                <select class="form-select" name="department" required>
                  <option value="" disabled <?= empty($dbUser['department']) ? 'selected' : '' ?>>— Select —</option>
                  <?php foreach ($ndmuDepartments as $college => $depts): ?>
                    <optgroup label="<?= e($college) ?>">
                      <?php foreach ($depts as $dept): ?>
                        <option value="<?= e($dept) ?>" <?= ((string)($dbUser['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                          <?= e($dept) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Profile Photo <span class="text-muted">(JPG/PNG, &lt; 2MB)</span></label>
                <input class="form-control" type="file" name="profile_photo" accept="image/jpeg,image/png">
              </div>
            </div>
            <button class="btn btn-warning mt-3 fw-semibold">Save Changes</button>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h5 fw-semibold mb-3">Change Password</h2>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Current Password</label>
                <div class="input-group">
                  <input id="curPwd" class="form-control" type="password" name="current_password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password="#curPwd"><i class="fa-solid fa-eye"></i></button>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">New Password</label>
                <div class="input-group">
                  <input id="newPwd" class="form-control" type="password" name="new_password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password="#newPwd"><i class="fa-solid fa-eye"></i></button>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Confirm New Password</label>
                <div class="input-group">
                  <input id="newPwdC" class="form-control" type="password" name="confirm_new_password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password="#newPwdC"><i class="fa-solid fa-eye"></i></button>
                </div>
              </div>
            </div>
            <button class="btn btn-outline-primary mt-3 fw-semibold">Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
