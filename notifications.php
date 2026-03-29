<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$u = getCurrentUser();
$flash = getFlash();

$rows = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([(int)$u['id']]);
    $rows = $stmt->fetchAll();
} catch (Throwable) {}

function notifIcon(string $type): string {
    return match ($type) {
        'approval' => 'fa-solid fa-stamp text-success',
        'booking' => 'fa-regular fa-calendar-days text-warning',
        'alert' => 'fa-solid fa-triangle-exclamation text-danger',
        default => 'fa-regular fa-bell text-primary',
    };
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4" style="max-width:980px;">
  <input type="hidden" id="csrfToken" value="<?= e(generateCsrfToken()) ?>">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-3">
    <div>
      <h1 class="h4 fw-bold mb-1">Notifications</h1>
      <div class="text-muted">Newest first</div>
    </div>
    <form method="post" action="notifications_mark_all.php" class="m-0">
      <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
      <button class="btn btn-outline-primary"><i class="fa-regular fa-circle-check me-1"></i>Mark All as Read</button>
    </form>
  </div>

  <div class="list-group shadow-sm">
    <?php if (!$rows): ?>
      <div class="list-group-item">No notifications yet.</div>
    <?php endif; ?>

    <?php foreach ($rows as $n): ?>
      <a href="#" class="list-group-item list-group-item-action d-flex gap-3 notif-item <?= (int)$n['is_read'] === 0 ? 'unread-highlight' : '' ?>"
         data-id="<?= (int)$n['id'] ?>"
         data-booking-id="<?= e((string)($n['booking_id'] ?? '')) ?>"
         data-type="<?= e((string)($n['type'] ?? '')) ?>">
        <div class="pt-1">
          <i class="<?= e(notifIcon((string)($n['type'] ?? ''))) ?>"></i>
        </div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="<?= (int)$n['is_read'] === 0 ? 'fw-bold' : 'fw-semibold' ?>"><?= e((string)$n['title']) ?></div>
              <div class="text-muted"><?= e((string)$n['message']) ?></div>
            </div>
            <div class="text-muted small"><?= e(timeAgo((string)$n['created_at'])) ?></div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<script>
  (function(){
    const items = Array.from(document.querySelectorAll('.notif-item'));
    const csrfToken = document.getElementById('csrfToken')?.value || '';
    async function markRead(id){
      try{
        const res = await fetch('notifications_mark_read.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
          body: new URLSearchParams({id, csrf_token: csrfToken}).toString()
        });
        if(!res.ok) return false;
        const data = await res.json();
        return !!data.ok;
      }catch(e){ return false; }
    }

    items.forEach(a => {
      a.addEventListener('click', async (ev) => {
        ev.preventDefault();
        const id = a.getAttribute('data-id');
        const bookingId = a.getAttribute('data-booking-id');
        await markRead(id);
        if(bookingId){
          window.location.href = 'booking_detail.php?id=' + encodeURIComponent(bookingId);
        }else{
          window.location.reload();
        }
      });
    });
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

