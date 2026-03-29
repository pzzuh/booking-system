<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$facilities = [];
try {
    $stmt = $pdo->query('SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC');
    $facilities = $stmt->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
      <h1 class="h4 fw-bold mb-1">Facility Calendar</h1>
      <div class="text-muted">Monthly view of facility bookings.</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <label class="text-muted small">Filter facility</label>
      <select id="facilityFilter" class="form-select" style="min-width:280px;">
        <option value="">All facilities</option>
        <?php foreach ($facilities as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= e((string)$f['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="btn btn-outline-secondary" href="book_facility.php">Back</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div id="calendar"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventTitle">Booking Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="eventBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
  (function(){
    const modalEl = document.getElementById('eventModal');
    const modal = new bootstrap.Modal(modalEl);
    const titleEl = document.getElementById('eventTitle');
    const bodyEl = document.getElementById('eventBody');
    const filter = document.getElementById('facilityFilter');

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
      initialView: 'dayGridMonth',
      height: 'auto',
      events: (info, success, failure) => {
        const url = new URL('get_facility_events.php', window.location.href);
        if (filter.value) url.searchParams.set('facility_id', filter.value);
        fetch(url, {headers:{'Accept':'application/json'}})
          .then(r => r.json())
          .then(success)
          .catch(failure);
      },
      eventClick: (info) => {
        const ext = info.event.extendedProps || {};
        titleEl.textContent = info.event.title || 'Booking Details';
        bodyEl.innerHTML = `
          <div class="row g-2">
            <div class="col-md-6"><b>Facility:</b> ${ext.facility_name || ''}</div>
            <div class="col-md-6"><b>Student:</b> ${ext.student_name || ''}</div>
            <div class="col-md-6"><b>Date:</b> ${ext.date_start || ''} to ${ext.date_end || ''}</div>
            <div class="col-md-6"><b>Time:</b> ${ext.time_start || ''} to ${ext.time_end || ''}</div>
            <div class="col-12"><b>Purpose:</b> ${ext.purpose || ''}</div>
            <div class="col-12"><b>Notes:</b> ${ext.notes || ''}</div>
            <div class="col-12"><b>Status:</b> ${ext.status_badge || ''} <span class="ms-2"><b>Reviewing:</b> ${ext.current_role_badge || ''}</span></div>
          </div>
        `;
        modal.show();
      }
    });
    calendar.render();

    filter.addEventListener('change', () => calendar.refetchEvents());
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
