<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . roleRedirectTarget((string)($_SESSION['user']['role'] ?? '')));
    exit;
}

$flash = getFlash();
$error = null;

// NDMU Colleges and Departments
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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $fullName   = sanitizeInput($_POST['full_name'] ?? '');
    $email      = sanitizeInput($_POST['email'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $confirm    = (string)($_POST['confirm_password'] ?? '');
    $studentId  = sanitizeInput($_POST['student_id'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $phone      = sanitizeInput($_POST['phone'] ?? '');

    // Flatten all valid department options for validation
    $allDepts = [];
    foreach ($ndmuDepartments as $college => $depts) {
        $allDepts[] = $college; // allow college-level selection too
        foreach ($depts as $d) {
            $allDepts[] = $d;
        }
    }

    if ($fullName === '' || $email === '' || $password === '' || $confirm === '' || $studentId === '' || $department === '' || $phone === '') {
        $error = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (!in_array($department, $allDepts, true)) {
        $error = 'Please select a valid department.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and include an uppercase letter, lowercase letter, number, and symbol.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // Role is always 'student' by default — admin assigns specific roles later
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, student_id, department, phone, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())');
            $stmt->execute([$fullName, $email, $hash, $studentId, $department, $phone, 'student']);
            redirectWithMessage('login.php', 'success', 'Registration successful. Please sign in.');
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid auth-page" style="background-image:url('assets/images/ndmubgp.jpg')">
  <div class="row min-vh-100">
    <div class="col-lg-6 d-none d-lg-flex ndmu-split-left align-items-end" style="background-image:url('assets/images/ndmubg.jpg')">
      <div class="p-5">
        <div class="h2 fw-bold mb-2">Create your account</div>
        <div class="text-white-50">Use your official NDMU email for faster verification.</div>
      </div>
    </div>

    <div class="col-lg-6 d-flex align-items-center bg-white">
      <div class="container py-5" style="max-width:620px;">
        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-3 mb-4">
          <img src="assets/images/ndmulogo.png" alt="NDMU" width="60" height="60" style="object-fit:contain">
          <div>
            <div class="fw-bold fs-5">Notre Dame of Marbel University</div>
            <div class="text-muted">Facility Booking System</div>
          </div>
        </div>

        <form method="post" class="glass-card">
          <div class="p-4">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Full Name</label>
                <input class="form-control" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
              </div>

              <div class="col-md-6">
                <label class="form-label">Password</label>
                <div class="input-group">
                  <input id="regPwd" class="form-control" type="password" name="password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password="#regPwd" aria-label="Show/Hide password">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                  <input id="regPwd2" class="form-control" type="password" name="confirm_password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password="#regPwd2" aria-label="Show/Hide password">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Student / Employee ID</label>
                <input class="form-control" name="student_id" required value="<?= e($_POST['student_id'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" required value="<?= e($_POST['phone'] ?? '') ?>">
              </div>

              <div class="col-12">
                <label class="form-label">College / Department</label>
                <select class="form-select" name="department" required>
                  <option value="" disabled <?= empty($_POST['department']) ? 'selected' : '' ?>>— Select your college/department —</option>
                  <?php foreach ($ndmuDepartments as $college => $depts): ?>
                    <optgroup label="<?= e($college) ?>">
                      <?php foreach ($depts as $dept): ?>
                        <option value="<?= e($dept) ?>" <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                          <?= e($dept) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="alert alert-info small mt-3 mb-0 py-2">
              <i class="fa-solid fa-circle-info me-1"></i>
              All new accounts are registered as <strong>Student</strong> by default. An administrator can assign your specific role after registration.
            </div>

            <div class="d-flex gap-2 mt-4">
              <button class="btn btn-warning fw-semibold">Create Account</button>
              <a class="btn btn-outline-secondary" href="login.php">Back to Sign In</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
