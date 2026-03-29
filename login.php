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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, email, role, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || (isset($u['is_active']) && (int)$u['is_active'] === 0) || !password_verify($password, (string)$u['password_hash'])) {
            // Generic message — does not reveal whether email or password was wrong (anti-enumeration)
            $error = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => (int)$u['id'],
                'name'  => (string)$u['full_name'],
                'email' => (string)$u['email'],
                'role'  => (string)$u['role'],
            ];
            $_SESSION['last_activity'] = time();

            if ($remember) {
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + 60 * 60 * 24 * 7,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            }

            // Redirect to the dashboard that matches the user's assigned role
            header('Location: ' . roleRedirectTarget((string)$u['role']));
            exit;
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid auth-page" style="background-image:url('assets/images/ndmubg.jpg')">
  <div class="row min-vh-100">
    <div class="col-lg-6 d-none d-lg-flex ndmu-split-left align-items-end" style="background-image:url('assets/images/ndmubgp.jpg')">
      <div class="p-5">
        <div class="h2 fw-bold mb-2">Notre Dame of Marbel University</div>
        <div class="h5 text-white-50 mb-0">Facility Booking System</div>
      </div>
    </div>

    <div class="col-lg-6 d-flex align-items-center bg-white">
      <div class="container py-5" style="max-width:520px;">
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

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
            </div>

            <div class="mb-2">
              <label class="form-label">Password</label>
              <div class="input-group">
                <input id="loginPassword" class="form-control" type="password" name="password" required autocomplete="current-password">
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#loginPassword" aria-label="Show/Hide password">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" <?= !empty($_POST['remember']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="rememberMe">Remember Me</label>
              </div>
              <a class="text-decoration-none" href="forgot_password.php">Forgot Password?</a>
            </div>

            <button class="btn btn-warning w-100 fw-semibold">Sign In</button>
            <div class="text-center mt-3">
              <span class="text-muted">No account?</span> <a href="register.php">Register</a>
            </div>
            <div class="text-muted small text-center mt-2">
              You will be redirected to your assigned role's dashboard automatically.
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
