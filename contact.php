<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user = getCurrentUser();
$flash = getFlash();

$settings = [
    'school_name' => 'Notre Dame of Marbel University',
    'address' => 'Marbel, Koronadal City, South Cotabato, Philippines',
    'email' => 'admin@ndmu.edu.ph',
    'phone' => '(000) 000-0000',
];
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('school_name','address','email','phone')");
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string)$row['key']] = (string)$row['value'];
    }
} catch (Throwable) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $action = sanitizeInput($_POST['action'] ?? 'send_message');

    // Admin updating contact settings
    if ($action === 'update_settings' && isset($user['role']) && (string)$user['role'] === 'admin') {
        $newAddress = sanitizeInput($_POST['address'] ?? '');
        $newEmail   = sanitizeInput($_POST['email_setting'] ?? '');
        $newPhone   = sanitizeInput($_POST['phone_setting'] ?? '');
        $newSchool  = sanitizeInput($_POST['school_name'] ?? '');
        try {
            foreach ([
                'address'     => $newAddress,
                'email'       => $newEmail,
                'phone'       => $newPhone,
                'school_name' => $newSchool,
            ] as $key => $val) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute([$key, $val, $val]);
            }
            redirectWithMessage('contact.php', 'success', 'Contact information updated.');
        } catch (Throwable) {
            redirectWithMessage('contact.php', 'danger', 'Failed to update settings.');
        }
    }

    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $subject === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithMessage('contact.php', 'danger', 'Please complete all fields with a valid email.');
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
        $stmt->execute([$name, $email, $subject, $message]);
    } catch (Throwable) {
        redirectWithMessage('contact.php', 'danger', 'Unable to send message right now. Please try again later.');
    }

    $adminEmail = $settings['email'] ?: 'admin@ndmu.edu.ph';
    @mail($adminEmail, "[NDMU Booking] {$subject}", "From: {$name} <{$email}>\n\n{$message}");

    redirectWithMessage('contact.php', 'success', 'Your message has been received. We will get back to you within 24 hours.');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="banner text-white mb-4" style="background-image:url('assets/images/ndmubgp.jpg')">
  <div class="banner-content container py-5">
    <div class="d-flex align-items-center gap-3">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="48" height="48" style="object-fit:contain">
      <div>
        <h1 class="h2 fw-bold mb-1">Contact Us</h1>
        <div class="text-white-50">We’d love to hear from you.</div>
      </div>
    </div>
  </div>
</div>

<div class="container pb-5">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h2 class="h5 fw-semibold mb-3">Contact Information</h2>
          <div class="mb-2"><i class="fa-solid fa-building me-2 text-muted"></i><strong><?= e($settings['school_name']) ?></strong></div>
          <div class="mb-2"><i class="fa-solid fa-location-dot me-2 text-muted"></i><?= e($settings['address']) ?></div>
          <div class="mb-2"><i class="fa-solid fa-phone me-2 text-muted"></i><?= e($settings['phone']) ?></div>
          <div class="mb-2"><i class="fa-solid fa-envelope me-2 text-muted"></i><?= e($settings['email']) ?></div>
        </div>
      </div>
      <?php if (isset($user['role']) && (string)$user['role'] === 'admin'): ?>
      <div class="card shadow-sm border-warning">
        <div class="card-header fw-semibold text-warning-emphasis bg-warning-subtle">
          <i class="fa-solid fa-pen me-1"></i> Update Contact Info
        </div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="update_settings">
            <div class="mb-2">
              <label class="form-label small">School Name</label>
              <input class="form-control form-control-sm" name="school_name" value="<?= e($settings['school_name']) ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label small">Address</label>
              <input class="form-control form-control-sm" name="address" value="<?= e($settings['address']) ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label small">Phone</label>
              <input class="form-control form-control-sm" name="phone_setting" value="<?= e($settings['phone']) ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label small">Email</label>
              <input class="form-control form-control-sm" type="email" name="email_setting" value="<?= e($settings['email']) ?>" required>
            </div>
            <button class="btn btn-warning btn-sm fw-semibold mt-1 w-100">Update Contact Info</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h5 fw-semibold mb-3">Send a message</h2>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" value="<?= e($user['name'] ?? '') ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Subject</label>
                <input class="form-control" name="subject" required>
              </div>
              <div class="col-12">
                <label class="form-label">Message</label>
                <textarea class="form-control" name="message" rows="6" required></textarea>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-warning">Submit</button>
                <a class="btn btn-outline-secondary" href="faq.php">View FAQ</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
