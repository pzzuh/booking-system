<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$user = getCurrentUser();
$unreadCount = 0;
if ($user) {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([(int)$user['id']]);
        $unreadCount = (int)$stmt->fetchColumn();
    } catch (Throwable) {
        $unreadCount = 0;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-ndmu-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $user ? 'student_dashboard.php' : 'index.php' ?>">
      <img src="assets/images/ndmulogo.png" alt="NDMU" width="28" height="28" style="object-fit:contain">
      <span>NDMU Booking</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ndmuNav" aria-controls="ndmuNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="ndmuNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="faq.php">FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>

        <?php if ($user): ?>
          <?php if ((string)$user['role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin_panel.php"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Admin Panel</a></li>
          <?php endif; ?>

          <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
          <li class="nav-item"><a class="nav-link" href="notifications.php">Notifications</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <?php if ($user): ?>
          <li class="nav-item">
            <a class="nav-link position-relative" href="notifications.php" aria-label="Notifications">
              <i class="fa-solid fa-bell"></i>
              <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="<?= $unreadCount ? '' : 'display:none;' ?>">
                <?= (int)$unreadCount ?>
              </span>
            </a>
          </li>
          <li class="nav-item">
            <span class="navbar-text small text-white-50"><?= e((string)$user['name']) ?></span>
          </li>
          <li class="nav-item">
            <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-sm btn-outline-light" href="login.php">Sign In</a></li>
          <li class="nav-item"><a class="btn btn-sm btn-warning" href="register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php if ($user): ?>
<script>
  (function(){
    const badge = document.getElementById('notifBadge');
    async function refreshCount(){
      try{
        const res = await fetch('get_notification_count.php', {headers: {'Accept':'application/json'}});
        if(!res.ok) return;
        const data = await res.json();
        const c = Number(data.count || 0);
        if(c > 0){
          badge.style.display = '';
          badge.textContent = String(c);
        }else{
          badge.style.display = 'none';
        }
      }catch(e){}
    }
    setInterval(refreshCount, 30000);
  })();
</script>
<?php endif; ?>
