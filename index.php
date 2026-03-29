<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) {
    $role = (string)($_SESSION['user']['role'] ?? '');
    header('Location: ' . roleRedirectTarget($role));
    exit;
}

$settings = [
    'school_name' => 'Notre Dame of Marbel University',
    'address' => 'Marbel, Koronadal City, South Cotabato, Philippines',
];
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('school_name','address')");
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string)$row['key']] = (string)$row['value'];
    }
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="ndmu-hero d-flex align-items-center" style="background-image:url('assets/images/ndmubgp.jpg')">
  <div class="container text-center py-5">
    <img src="assets/images/ndmulogo.png" alt="NDMU" width="100" height="100" style="object-fit:contain" class="mb-3">
    <h1 class="display-5 fw-bold mb-1"><?= e($settings['school_name']) ?></h1>
    <div class="h4 fw-semibold brand-gold mb-4">Facility Booking System</div>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
      <a class="btn btn-warning btn-lg" href="login.php">Sign In</a>
      <a class="btn btn-outline-light btn-lg" href="register.php">Register</a>
    </div>
  </div>
</section>

<section class="py-5 bg-white">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="fa-regular fa-calendar-days fa-lg text-success"></i>
              <h5 class="mb-0">Book a Facility</h5>
            </div>
            <p class="text-muted mb-0">Reserve classrooms, halls, and campus venues with conflict checks and transparent approvals.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="fa-solid fa-box-open fa-lg text-primary"></i>
              <h5 class="mb-0">Borrow Equipment</h5>
            </div>
            <p class="text-muted mb-0">Request campus equipment and supplies with availability checks and pickup/return schedules.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="fa-regular fa-circle-check fa-lg text-warning"></i>
              <h5 class="mb-0">Track Approvals</h5>
            </div>
            <p class="text-muted mb-0">Follow each approval step from Adviser through President with real-time notifications.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="banner p-4 p-md-5 text-white" style="background-image:url('assets/images/ndmubg.jpg')">
      <div class="banner-content">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
          <div>
            <h2 class="fw-bold mb-1">Efficient. Transparent. Streamlined.</h2>
            <div class="text-white-50">Learn how booking and approvals work.</div>
          </div>
          <a class="btn btn-warning btn-lg" href="faq.php">Learn More</a>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="py-4 bg-ndmu-dark text-white">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
    <div>
      <div class="fw-semibold"><?= e($settings['school_name']) ?></div>
      <div class="text-white-50 small"><?= e($settings['address']) ?></div>
    </div>
    <div class="d-flex gap-3">
      <a class="text-white text-decoration-none" href="faq.php">FAQ</a>
      <a class="text-white text-decoration-none" href="contact.php">Contact Us</a>
    </div>
  </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
