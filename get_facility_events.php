<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$facilityId = (int)($_GET['facility_id'] ?? 0);

$where = '';
$params = [];
if ($facilityId > 0) {
    $where = 'WHERE fb.facility_id = ?';
    $params[] = $facilityId;
}

try {
    $stmt = $pdo->prepare(
        "SELECT fb.id, fb.title, fb.purpose, fb.notes, fb.date_start, fb.date_end, fb.time_start, fb.time_end,
                fb.status, fb.current_approval_role,
                f.name AS facility_name,
                u.full_name AS student_name
         FROM facility_bookings fb
         JOIN facilities f ON f.id = fb.facility_id
         JOIN users u ON u.id = fb.user_id
         {$where}
         ORDER BY fb.date_start ASC, fb.time_start ASC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable) {
    $rows = [];
}

$events = [];
foreach ($rows as $r) {
    $status = (string)$r['status'];
    $color = match ($status) {
        'fully_approved' => '#198754',
        'rejected' => '#dc3545',
        default => '#fd7e14', // pending/orange
    };
    $start = (string)$r['date_start'] . 'T' . (string)$r['time_start'];
    $end = (string)$r['date_end'] . 'T' . (string)$r['time_end'];
    $events[] = [
        'id' => (int)$r['id'],
        'title' => (string)$r['title'],
        'start' => $start,
        'end' => $end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'facility_name' => (string)$r['facility_name'],
            'student_name' => (string)$r['student_name'],
            'purpose' => (string)$r['purpose'],
            'notes' => (string)$r['notes'],
            'date_start' => (string)$r['date_start'],
            'date_end' => (string)$r['date_end'],
            'time_start' => (string)$r['time_start'],
            'time_end' => (string)$r['time_end'],
            'status_badge' => statusBadge($status),
            'current_role_badge' => approvalRoleBadge((string)$r['current_approval_role']),
        ],
    ];
}

echo json_encode($events);

