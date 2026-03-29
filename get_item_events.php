<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query(
        "SELECT ib.id, ib.purpose, ib.borrow_date, ib.return_date, ib.borrow_time, ib.return_time, ib.status, ib.current_approval_role,
                i.name AS item_name,
                u.full_name AS student_name
         FROM item_bookings ib
         JOIN items i ON i.id = ib.item_id
         JOIN users u ON u.id = ib.user_id
         ORDER BY ib.borrow_date ASC, ib.borrow_time ASC"
    );
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
        default => '#fd7e14',
    };
    $start = (string)$r['borrow_date'] . 'T' . (string)$r['borrow_time'];
    $end = (string)$r['return_date'] . 'T' . (string)$r['return_time'];
    $events[] = [
        'id' => (int)$r['id'],
        'title' => (string)$r['item_name'],
        'start' => $start,
        'end' => $end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'item_name' => (string)$r['item_name'],
            'student_name' => (string)$r['student_name'],
            'purpose' => (string)$r['purpose'],
            'borrow_date' => (string)$r['borrow_date'],
            'return_date' => (string)$r['return_date'],
            'borrow_time' => (string)$r['borrow_time'],
            'return_time' => (string)$r['return_time'],
            'status_badge' => statusBadge($status),
            'current_role_badge' => approvalRoleBadge((string)$r['current_approval_role']),
        ],
    ];
}

echo json_encode($events);

