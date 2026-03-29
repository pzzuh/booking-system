<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('student');
$user = getCurrentUser();
$flash = getFlash();
$error = null;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Invalid booking.');
}

$stmt = $pdo->prepare(
    'SELECT fb.*, f.name AS facility_name
     FROM facility_bookings fb
     JOIN facilities f ON f.id = fb.facility_id
     WHERE fb.id = ? AND fb.user_id = ?'
);
$stmt->execute([$id, (int)$user['id']]);
$b = $stmt->fetch();
if (!$b) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Booking not found.');
}
if ((string)$b['status'] !== 'pending') {
    redirectWithMessage('student_dashboard.php', 'warning', 'Only pending bookings can be edited.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfOrDie();

    $title = sanitizeInput($_POST['title'] ?? '');
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $dateStart = sanitizeInput($_POST['date_start'] ?? '');
    $dateEnd = sanitizeInput($_POST['date_end'] ?? '');
    $timeStart = sanitizeInput($_POST['time_start'] ?? '');
    $timeEnd = sanitizeInput($_POST['time_end'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if ($title === '' || $purpose === '' || $dateStart === '' || $dateEnd === '' || $timeStart === '' || $timeEnd === '') {
        $error = 'Please complete all required fields.';
    } else {
        $newStart = "{$dateStart} {$timeStart}:00";
        $newEnd = "{$dateEnd} {$timeEnd}:00";

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM facility_bookings
             WHERE facility_id = ?
               AND id <> ?
               AND status NOT IN ('rejected','cancelled')
               AND (CONCAT(date_start,' ',time_start) < ?)
               AND (CONCAT(date_end,' ',time_end) > ?)"
        );
        $stmt->execute([(int)$b['facility_id'], $id, $newEnd, $newStart]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Conflict detected: The selected date/time overlaps an existing booking.';
        } else {
            $stmt = $pdo->prepare('UPDATE facility_bookings SET title=?, purpose=?, date_start=?, date_end=?, time_start=?, time_end=?, notes=? WHERE id=? AND user_id=?');
            $stmt->execute([$title, $purpose, $dateStart, $dateEnd, $timeStart, $timeEnd, $notes, $id, (int)$user['id']]);
            redirectWithMessage('student_dashboard.php', 'success', 'Booking updated successfully.');
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4" style="max-width:860px;">
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h1 class="h4 fw-bold mb-1">Edit Facility Booking</h1>
          <div class="text-muted"><?= e((string)$b['facility_name']) ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="student_dashboard.php">Back</a>
      </div>

      <form method="post" id="editForm">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" required value="<?= e($_POST['title'] ?? (string)$b['title']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Purpose</label>
            <input class="form-control" name="purpose" required value="<?= e($_POST['purpose'] ?? (string)$b['purpose']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date Start</label>
            <input id="dateStart" class="form-control" name="date_start" required value="<?= e($_POST['date_start'] ?? (string)$b['date_start']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date End</label>
            <input id="dateEnd" class="form-control" name="date_end" required value="<?= e($_POST['date_end'] ?? (string)$b['date_end']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Time Start</label>
            <input id="timeStart" class="form-control" name="time_start" required value="<?= e($_POST['time_start'] ?? (string)$b['time_start']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Time End</label>
            <input id="timeEnd" class="form-control" name="time_end" required value="<?= e($_POST['time_end'] ?? (string)$b['time_end']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"><?= e($_POST['notes'] ?? (string)$b['notes']) ?></textarea>
          </div>
        </div>

        <div id="conflictAlert" class="alert alert-danger mt-3 d-none"></div>

        <button id="saveBtn" class="btn btn-warning mt-3 fw-semibold">Save Changes</button>
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
    const btn = document.getElementById('saveBtn');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    async function check(){
      const payload = {
        facility_id: <?= (int)$b['facility_id'] ?>,
        date_start: fields[0].value,
        date_end: fields[1].value,
        time_start: fields[2].value,
        time_end: fields[3].value,
        csrf_token: csrfToken
      };
      if(!payload.date_start || !payload.date_end || !payload.time_start || !payload.time_end){
        alertBox.classList.add('d-none'); btn.disabled = false; return;
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
          btn.disabled = true;
        }else{
          alertBox.classList.add('d-none');
          btn.disabled = false;
        }
      }catch(e){ alertBox.classList.add('d-none'); btn.disabled = false; }
    }
    fields.forEach(f => f?.addEventListener('change', check));
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

