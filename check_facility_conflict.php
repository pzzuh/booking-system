<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['conflict' => false, 'message' => '']);
    exit;
}

$ok = validateCsrfToken($_POST['csrf_token'] ?? null);
if (!$ok) {
    http_response_code(400);
    echo json_encode(['conflict' => false, 'message' => 'Invalid request.']);
    exit;
}

$facilityId = (int)($_POST['facility_id'] ?? 0);
$dateStart = sanitizeInput($_POST['date_start'] ?? '');
$dateEnd = sanitizeInput($_POST['date_end'] ?? '');
$timeStart = sanitizeInput($_POST['time_start'] ?? '');
$timeEnd = sanitizeInput($_POST['time_end'] ?? '');

if ($facilityId <= 0 || $dateStart === '' || $dateEnd === '' || $timeStart === '' || $timeEnd === '') {
    echo json_encode(['conflict' => false, 'message' => '']);
    exit;
}

$newStart = "{$dateStart} {$timeStart}:00";
$newEnd = "{$dateEnd} {$timeEnd}:00";

try {
    $stmt = $pdo->prepare(
        "SELECT id, title, date_start, date_end, time_start, time_end
         FROM facility_bookings
         WHERE facility_id = ?
           AND status NOT IN ('rejected','cancelled')
           AND (CONCAT(date_start,' ',time_start) < ?)
           AND (CONCAT(date_end,' ',time_end) > ?)
         ORDER BY date_start ASC, time_start ASC
         LIMIT 1"
    );
    $stmt->execute([$facilityId, $newEnd, $newStart]);
    $row = $stmt->fetch();
    if ($row) {
        $msg = "Conflict with booking: " . (string)$row['title'] . " (" . (string)$row['date_start'] . " " . (string)$row['time_start'] . " - " . (string)$row['date_end'] . " " . (string)$row['time_end'] . ")";
        echo json_encode(['conflict' => true, 'message' => $msg]);
        exit;
    }
} catch (Throwable) {}

echo json_encode(['conflict' => false, 'message' => 'No conflict detected.']);
