<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

enforceCorrectDashboard('admin');

$months = [];
$counts = [];
$topFacilities = [];
try {
    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
         FROM facility_bookings
         GROUP BY ym
         ORDER BY ym ASC
         LIMIT 24"
    );
    foreach ($stmt->fetchAll() as $r) {
        $months[] = (string)$r['ym'];
        $counts[] = (int)$r['c'];
    }
} catch (Throwable) {}

try {
    $stmt = $pdo->query(
        "SELECT f.name, COUNT(*) AS c
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         GROUP BY f.id
         ORDER BY c DESC
         LIMIT 8"
    );
    $topFacilities = $stmt->fetchAll();
} catch (Throwable) {}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

<h1 class="h4 fw-bold mb-3">Reports</h1>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">Bookings per Month (Facilities)</h2>
        <canvas id="bookingsPerMonth" height="140"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">Most-booked Facilities</h2>
        <canvas id="topFacilities" height="140"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function(){
    const months = <?= json_encode($months) ?>;
    const counts = <?= json_encode($counts) ?>;

    new Chart(document.getElementById('bookingsPerMonth'), {
      type: 'line',
      data: {
        labels: months,
        datasets: [{
          label: 'Facility bookings',
          data: counts,
          borderColor: '#198754',
          backgroundColor: 'rgba(25,135,84,.15)',
          tension: .3,
          fill: true
        }]
      },
      options: {responsive:true, plugins:{legend:{display:true}}}
    });

    const topLabels = <?= json_encode(array_map(fn($r) => (string)$r['name'], $topFacilities)) ?>;
    const topCounts = <?= json_encode(array_map(fn($r) => (int)$r['c'], $topFacilities)) ?>;
    new Chart(document.getElementById('topFacilities'), {
      type: 'bar',
      data: {
        labels: topLabels,
        datasets: [{label:'Bookings', data: topCounts, backgroundColor:'#0d6efd'}]
      },
      options: {responsive:true, plugins:{legend:{display:false}}}
    });
  })();
</script>

<?php require_once __DIR__ . '/includes/admin_sidebar_end.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

