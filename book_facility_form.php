<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$user = getCurrentUser();

$facilityId = (int)($_GET['facility_id'] ?? 0);
if ($facilityId <= 0) {
    redirectWithMessage('book_facility.php', 'danger', 'Invalid facility.');
}

$stmt = $pdo->prepare('SELECT id, name, location, capacity, photo_path FROM facilities WHERE id = ? AND is_active = 1');
$stmt->execute([$facilityId]);
$facility = $stmt->fetch();
if (!$facility) {
    redirectWithMessage('book_facility.php', 'danger', 'Facility not found or inactive.');
}

$flash = getFlash();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $title = sanitizeInput($_POST['title'] ?? '');
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $dateStart = sanitizeInput($_POST['date_start'] ?? '');
    $dateEnd = sanitizeInput($_POST['date_end'] ?? '');
    $timeStart = sanitizeInput($_POST['time_start'] ?? '');
    $timeEnd = sanitizeInput($_POST['time_end'] ?? '');
    $participants = (int)($_POST['participants'] ?? 0);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if ($title === '' || $purpose === '' || $dateStart === '' || $dateEnd === '' || $timeStart === '' || $timeEnd === '' || $participants <= 0) {
        $error = 'Please complete all required fields.';
    } else {
        // Server-side conflict check
        $newStart = "{$dateStart} {$timeStart}:00";
        $newEnd = "{$dateEnd} {$timeEnd}:00";
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM facility_bookings
             WHERE facility_id = ?
               AND status NOT IN ('rejected','cancelled')
               AND (CONCAT(date_start,' ',time_start) < ?)
               AND (CONCAT(date_end,' ',time_end) > ?)"
        );
        $stmt->execute([$facilityId, $newEnd, $newStart]);
        $conflicts = (int)$stmt->fetchColumn();

        if ($conflicts > 0) {
            $error = 'Conflict detected: The selected date/time overlaps an existing booking.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO facility_bookings
                 (user_id, facility_id, title, purpose, date_start, date_end, time_start, time_end, participants, notes, status, current_approval_role, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", "adviser", NOW())'
            );
            $stmt->execute([
                (int)$user['id'],
                $facilityId,
                $title,
                $purpose,
                $dateStart,
                $dateEnd,
                $timeStart,
                $timeEnd,
                $participants,
                $notes
            ]);
            $bookingId = (int)$pdo->lastInsertId();

            // Notify all advisers
            $stmt = $pdo->query("SELECT id FROM users WHERE role = 'adviser' AND is_active = 1");
            foreach ($stmt->fetchAll() as $row) {
                sendNotification(
                    $pdo,
                    (int)$row['id'],
                    'New Facility Booking Request',
                    "{$user['name']} submitted a facility booking request for {$facility['name']}.",
                    'booking',
                    $bookingId
                );
            }

            header('Location: facility_booking_confirm.php?id=' . $bookingId);
            exit;
        }
    }
}

$photo = (string)($facility['photo_path'] ?? '');
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
        <img src="<?= e($img) ?>" class="w-100 h-100" alt="<?= e((string)$facility['name']) ?>" style="object-fit:cover;min-height:220px">
      </div>
      <div class="col-md-8">
        <div class="card-body">
          <h1 class="h4 fw-bold mb-1"><?= e((string)$facility['name']) ?></h1>
          <div class="text-muted mb-2"><i class="fa-solid fa-location-dot me-1"></i><?= e((string)($facility['location'] ?? '')) ?></div>
          <span class="badge bg-primary">Capacity: <?= (int)($facility['capacity'] ?? 0) ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h2 class="h5 fw-semibold mb-3">Booking Details</h2>
      <form method="post" id="facilityBookingForm">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" required value="<?= e($_POST['title'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Purpose</label>
            <input class="form-control" name="purpose" required value="<?= e($_POST['purpose'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date Start</label>
            <input id="dateStart" class="form-control" name="date_start" required value="<?= e($_POST['date_start'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date End</label>
            <input id="dateEnd" class="form-control" name="date_end" required value="<?= e($_POST['date_end'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Time Start</label>
            <input id="timeStart" class="form-control" name="time_start" required value="<?= e($_POST['time_start'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Time End</label>
            <input id="timeEnd" class="form-control" name="time_end" required value="<?= e($_POST['time_end'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Number of Participants</label>
            <input class="form-control" type="number" min="1" name="participants" required value="<?= e($_POST['participants'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>

        <div id="conflictAlert" class="alert alert-danger mt-3 d-none"></div>

        <div class="d-flex gap-2 mt-3">
          <button id="submitBtn" class="btn btn-warning fw-semibold">Submit Booking</button>
          <a class="btn btn-outline-secondary" href="book_facility.php">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  (function(){
    flatpickr('#dateStart', {dateFormat:'Y-m-d'});
    flatpickr('#dateEnd', {dateFormat:'Y-m-d'});
    flatpickr('#timeStart', {enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:false});
    flatpickr('#timeEnd', {enableTime:true, noCalendar:true, dateFormat:'H:i', time_24hr:false});

    const fields = ['dateStart','dateEnd','timeStart','timeEnd'].map(id => document.getElementById(id));
    const alertBox = document.getElementById('conflictAlert');
    const submitBtn = document.getElementById('submitBtn');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    async function check(){
      const payload = {
        facility_id: <?= (int)$facilityId ?>,
        date_start: fields[0].value,
        date_end: fields[1].value,
        time_start: fields[2].value,
        time_end: fields[3].value,
        csrf_token: csrfToken
      };
      if(!payload.date_start || !payload.date_end || !payload.time_start || !payload.time_end){
        alertBox.classList.add('d-none');
        submitBtn.disabled = false;
        return;
      }
      try{
        const res = await fetch('check_facility_conflict.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
          body: new URLSearchParams(payload).toString()
        });
        const data = await res.json();
        if(data.conflict){
          alertBox.textContent = data.message || 'Conflict detected.';
          alertBox.classList.remove('d-none');
          submitBtn.disabled = true;
        }else{
          alertBox.classList.add('d-none');
          submitBtn.disabled = false;
        }
      }catch(e){
        // Fail open: allow submit (server will re-check)
        alertBox.classList.add('d-none');
        submitBtn.disabled = false;
      }
    }

    fields.forEach(f => f?.addEventListener('change', check));
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
