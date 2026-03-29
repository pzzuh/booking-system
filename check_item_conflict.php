<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['available' => true, 'message' => '']);
    exit;
}

$ok = validateCsrfToken($_POST['csrf_token'] ?? null);
if (!$ok) {
    http_response_code(400);
    echo json_encode(['available' => false, 'message' => 'Invalid request.']);
    exit;
}

$itemId = (int)($_POST['item_id'] ?? 0);
$qty = (int)($_POST['quantity_needed'] ?? 0);
$borrowDate = sanitizeInput($_POST['borrow_date'] ?? '');
$returnDate = sanitizeInput($_POST['return_date'] ?? '');
$borrowTime = sanitizeInput($_POST['borrow_time'] ?? '');
$returnTime = sanitizeInput($_POST['return_time'] ?? '');

if ($itemId <= 0 || $qty <= 0 || $borrowDate === '' || $returnDate === '' || $borrowTime === '' || $returnTime === '') {
    echo json_encode(['available' => true, 'message' => '']);
    exit;
}

$start = "{$borrowDate} {$borrowTime}:00";
$end = "{$returnDate} {$returnTime}:00";

try {
    $stmt = $pdo->prepare('SELECT quantity_available, name FROM items WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        echo json_encode(['available' => false, 'message' => 'Item not found.']);
        exit;
    }
    $availableNow = (int)$item['quantity_available'];

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity_needed), 0)
         FROM item_bookings
         WHERE item_id = ?
           AND status NOT IN ('rejected','cancelled')
           AND (CONCAT(borrow_date,' ',borrow_time) < ?)
           AND (CONCAT(return_date,' ',return_time) > ?)"
    );
    $stmt->execute([$itemId, $end, $start]);
    $reserved = (int)$stmt->fetchColumn();
    $effective = $availableNow - $reserved;

    if ($effective >= $qty) {
        echo json_encode(['available' => true, 'message' => 'Available.']);
        exit;
    }
    echo json_encode(['available' => false, 'message' => 'Not enough quantity available for the selected dates. Available: ' . max(0, $effective)]);
    exit;
} catch (Throwable) {
    echo json_encode(['available' => true, 'message' => 'Available.']);
    exit;
}

