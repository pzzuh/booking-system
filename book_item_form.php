<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$user = getCurrentUser();

$itemId = (int)($_GET['item_id'] ?? 0);
if ($itemId <= 0) {
    redirectWithMessage('book_item.php', 'danger', 'Invalid item.');
}

$stmt = $pdo->prepare('SELECT id, name, category, quantity_available, photo_path FROM items WHERE id = ? AND is_active = 1');
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) {
    redirectWithMessage('book_item.php', 'danger', 'Item not found or inactive.');
}

$flash = getFlash();
$error = null;

function itemIsAvailable(PDO $pdo, int $itemId, int $qtyNeeded, string $borrowStart, string $returnEnd): array
{
    $stmt = $pdo->prepare('SELECT quantity_available FROM items WHERE id = ?');
    $stmt->execute([$itemId]);
    $availableNow = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity_needed), 0)
         FROM item_bookings
         WHERE item_id = ?
           AND status NOT IN ('rejected','cancelled')
           AND (CONCAT(borrow_date,' ',borrow_time) < ?)
           AND (CONCAT(return_date,' ',return_time) > ?)"
    );
    $stmt->execute([$itemId, $returnEnd, $borrowStart]);
    $reserved = (int)$stmt->fetchColumn();

    // quantity_available is treated as real-time stock on hand
    $effective = $availableNow - $reserved;
    if ($effective >= $qtyNeeded) {
        return [true, "Available. Remaining after reservation: " . max(0, $effective - $qtyNeeded)];
    }
    return [false, "Not enough quantity available for the selected dates. Available: " . max(0, $effective)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $qty = (int)($_POST['quantity_needed'] ?? 0);
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $borrowDate = sanitizeInput($_POST['borrow_date'] ?? '');
    $returnDate = sanitizeInput($_POST['return_date'] ?? '');
    $borrowTime = sanitizeInput($_POST['borrow_time'] ?? '');
    $returnTime = sanitizeInput($_POST['return_time'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if ($qty <= 0 || $purpose === '' || $borrowDate === '' || $returnDate === '' || $borrowTime === '' || $returnTime === '') {
        $error = 'Please complete all required fields.';
    } else {
        $start = "{$borrowDate} {$borrowTime}:00";
        $end = "{$returnDate} {$returnTime}:00";

        try {
            [$ok, $msg] = itemIsAvailable($pdo, $itemId, $qty, $start, $end);
            if (!$ok) {
                $error = $msg;
            } else {
                $pdo->beginTransaction();

                // Re-check and decrement atomically
                $stmt = $pdo->prepare('SELECT quantity_available FROM items WHERE id = ? FOR UPDATE');
                $stmt->execute([$itemId]);
                $avail = (int)$stmt->fetchColumn();
                if ($avail < $qty) {
                    $pdo->rollBack();
                    $error = 'Not enough quantity available right now.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO item_bookings
                         (user_id, item_id, quantity_needed, purpose, borrow_date, return_date, borrow_time, return_time, notes, status, current_approval_role, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", "adviser", NOW())'
                    );
                    $stmt->execute([(int)$user['id'], $itemId, $qty, $purpose, $borrowDate, $returnDate, $borrowTime, $returnTime, $notes]);
                    $bookingId = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare('UPDATE items SET quantity_available = quantity_available - ? WHERE id = ?');
                    $stmt->execute([$qty, $itemId]);

                    $pdo->commit();

                    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'adviser' AND is_active = 1");
                    foreach ($stmt->fetchAll() as $row) {
                        sendNotification(
                            $pdo,
                            (int)$row['id'],
                            'New Item Borrowing Request',
                            "{$user['name']} submitted an item borrowing request for {$item['name']}.",
                            'booking',
                            $bookingId
                        );
                    }

                    redirectWithMessage('student_dashboard.php', 'success', 'Item request submitted successfully.');
                }
            }
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Unable to submit request. Please try again.';
        }
    }
}

$photo = (string)($item['photo_path'] ?? '');
$img = $photo !== '' ? $photo : 'assets/images/ndmubg.jpg';
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

  <div class="card shadow-sm mb-4">
    <div class="row g-0">
      <div class="col-md-4">
        <img src="<?= e($img) ?>" class="w-100 h-100" alt="<?= e((string)$item['name']) ?>" style="object-fit:cover;min-height:220px">
      </div>
      <div class="col-md-8">
        <div class="card-body">
          <h1 class="h4 fw-bold mb-1"><?= e((string)$item['name']) ?></h1>
          <div class="text-muted mb-2"><?= e((string)($item['category'] ?? '')) ?></div>
          <span class="badge bg-success">Available: <?= (int)($item['quantity_available'] ?? 0) ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h2 class="h5 fw-semibold mb-3">Borrowing Details</h2>
      <form method="post" id="itemBorrowForm">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Quantity Needed</label>
            <input id="qtyNeeded" class="form-control" type="number" min="1" name="quantity_needed" required value="<?= e($_POST['quantity_needed'] ?? '') ?>">
          </div>
          <div class="col-md-8">
            <label class="form-label">Purpose</label>
            <input class="form-control" name="purpose" required value="<?= e($_POST['purpose'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Borrow Date</label>
            <input id="borrowDate" class="form-control" name="borrow_date" required value="<?= e($_POST['borrow_date'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Return Date</label>
            <input id="returnDate" class="form-control" name="return_date" required value="<?= e($_POST['return_date'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Borrow Time</label>
            <input id="borrowTime" class="form-control" name="borrow_time" required value="<?= e($_POST['borrow_time'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Return Time</label>
            <input id="returnTime" class="form-control" name="return_time" required value="<?= e($_POST['return_time'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>

        <div id="availAlert" class="alert alert-danger mt-3 d-none"></div>

        <div class="d-flex gap-2 mt-3">
          <button id="submitBtn" class="btn btn-warning fw-semibold">Submit Request</button>
          <a class="btn btn-outline-secondary" href="book_item.php">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  (function(){
    flatpickr('#borrowDate', {dateFormat:'Y-m-d'});
    flatpickr('#returnDate', {dateFormat:'Y-m-d'});
    flatpickr('#borrowTime', {enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:false});
    flatpickr('#returnTime', {enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:false});

    const ids = ['qtyNeeded','borrowDate','returnDate','borrowTime','returnTime'];
    const fields = ids.map(id => document.getElementById(id));
    const alertBox = document.getElementById('availAlert');
    const submitBtn = document.getElementById('submitBtn');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    async function check(){
      const payload = {
        item_id: <?= (int)$itemId ?>,
        quantity_needed: fields[0].value,
        borrow_date: fields[1].value,
        return_date: fields[2].value,
        borrow_time: fields[3].value,
        return_time: fields[4].value,
        csrf_token: csrfToken
      };
      if(!payload.quantity_needed || !payload.borrow_date || !payload.return_date || !payload.borrow_time || !payload.return_time){
        alertBox.classList.add('d-none');
        submitBtn.disabled = false;
        return;
      }
      try{
        const res = await fetch('check_item_conflict.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
          body: new URLSearchParams(payload).toString()
        });
        const data = await res.json();
        if(!data.available){
          alertBox.textContent = data.message || 'Not available.';
          alertBox.classList.remove('d-none');
          submitBtn.disabled = true;
        }else{
          alertBox.classList.add('d-none');
          submitBtn.disabled = false;
        }
      }catch(e){
        alertBox.classList.add('d-none');
        submitBtn.disabled = false;
      }
    }
    fields.forEach(f => f?.addEventListener('change', check));
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
