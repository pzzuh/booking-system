<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/smtp_mailer.php';

if (isLoggedIn()) {
    header('Location: ' . roleRedirectTarget((string)($_SESSION['user']['role'] ?? '')));
    exit;
}

$flash   = getFlash();
$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $email = sanitizeInput($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up user by the email they typed
        $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // Always show the same success message to prevent email enumeration
        $success = 'If that email is registered, a password reset link has been sent to it.';

        if ($u) {
            // Generate a secure random token and a 1-hour expiry
            $token   = bin2hex(random_bytes(32));
            $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare(
                'UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?'
            );
            $stmt->execute([$token, $expires, (int)$u['id']]);

            // Build the reset URL dynamically so it works on any host / sub-path
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base   = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\');
            $link   = "{$scheme}://{$host}{$base}/reset_password.php?token=" . urlencode($token);

            // Send the reset link to the email address the user typed
            $sent = sendPasswordResetEmail((string)$u['email'], (string)$u['full_name'], $link);

            if (!$sent) {
                // Log for admin but do NOT leak this to the user
                error_log('Forgot-password: failed to send reset email to ' . $u['email']);
            }
        }

        // Clear the submitted email so the field does not re-populate after success
        $_POST['email'] = '';
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container py-5" style="max-width:520px;">

  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <div class="text-center mb-4">
    <img src="assets/images/ndmulogo.png" alt="NDMU" width="70" height="70"
         style="object-fit:contain" class="mb-2">
    <div class="fw-bold fs-4">Forgot Password</div>
    <div class="text-muted">Enter your registered email to receive a reset link.</div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

        <div class="mb-3">
          <label for="email" class="form-label">Email address</label>
          <input
            id="email"
            class="form-control"
            type="email"
            name="email"
            required
            autocomplete="email"
            value="<?= e($_POST['email'] ?? '') ?>"
            placeholder="you@example.com"
          >
        </div>

        <button class="btn btn-warning w-100 fw-semibold">Send Reset Link</button>

        <div class="text-center mt-3">
          <a href="login.php">&larr; Back to Sign In</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
