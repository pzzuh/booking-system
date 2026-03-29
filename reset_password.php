<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . roleRedirectTarget((string)($_SESSION['user']['role'] ?? '')));
    exit;
}

// Pull and validate the token from the URL
$token = sanitizeInput($_GET['token'] ?? '');
if ($token === '') {
    redirectWithMessage('login.php', 'danger', 'Invalid password reset link.');
}

$stmt = $pdo->prepare(
    'SELECT id, reset_token_expires FROM users WHERE reset_token = ? LIMIT 1'
);
$stmt->execute([$token]);
$u = $stmt->fetch();

if (!$u) {
    redirectWithMessage('login.php', 'danger', 'Invalid or expired reset link.');
}

$expires = (string)($u['reset_token_expires'] ?? '');
if ($expires === '' || strtotime($expires) < time()) {
    redirectWithMessage('login.php', 'danger', 'This reset link has expired. Please request a new one.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    // Re-validate the token from the hidden field on POST so the link stays intact
    $postToken = sanitizeInput($_POST['reset_token'] ?? '');
    if ($postToken === '' || !hash_equals($token, $postToken)) {
        redirectWithMessage('login.php', 'danger', 'Invalid or tampered reset link.');
    }

    $new     = (string)($_POST['new_password']     ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);

        // Clear the token after successful reset so the link can't be reused
        $stmt = $pdo->prepare(
            'UPDATE users
                SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL
              WHERE id = ?'
        );
        $stmt->execute([$hash, (int)$u['id']]);

        redirectWithMessage('login.php', 'success', 'Password updated successfully. Please sign in.');
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container py-5" style="max-width:560px;">

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="text-center mb-4">
    <img src="assets/images/ndmulogo.png" alt="NDMU" width="70" height="70"
         style="object-fit:contain" class="mb-2">
    <div class="fw-bold fs-4">Reset Password</div>
    <div class="text-muted">Create a new password for your account.</div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token"   value="<?= e(generateCsrfToken()) ?>">
        <!-- Carry the reset token through the POST so the URL never needs to change -->
        <input type="hidden" name="reset_token"  value="<?= e($token) ?>">

        <div class="mb-3">
          <label for="newPwd" class="form-label">New Password</label>
          <div class="input-group">
            <input
              id="newPwd"
              class="form-control"
              type="password"
              name="new_password"
              required
              minlength="8"
              autocomplete="new-password"
            >
            <button
              class="btn btn-outline-secondary"
              type="button"
              data-toggle-password="#newPwd"
              aria-label="Show/Hide password"
            ><i class="fa-solid fa-eye"></i></button>
          </div>
          <div class="form-text">Minimum 8 characters.</div>
        </div>

        <div class="mb-3">
          <label for="newPwd2" class="form-label">Confirm New Password</label>
          <div class="input-group">
            <input
              id="newPwd2"
              class="form-control"
              type="password"
              name="confirm_password"
              required
              minlength="8"
              autocomplete="new-password"
            >
            <button
              class="btn btn-outline-secondary"
              type="button"
              data-toggle-password="#newPwd2"
              aria-label="Show/Hide password"
            ><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>

        <button class="btn btn-warning w-100 fw-semibold">Update Password</button>

        <div class="text-center mt-3">
          <a href="login.php">&larr; Back to Sign In</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
