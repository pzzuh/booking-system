<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
requireValidCsrfOrDie();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('student_dashboard.php', 'danger', 'Invalid request.');
}

$user = getCurrentUser();
$type = sanitizeInput($_POST['type'] ?? '');
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0 || !in_array($type, ['facility','item'], true)) {
    redirectWithMessage('student_dashboard.php', 'danger', 'Invalid request.');
}

try {
    if ($type === 'facility') {
        $stmt = $pdo->prepare('SELECT status FROM facility_bookings WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, (int)$user['id']]);
        $status = (string)$stmt->fetchColumn();
        if (!$status) redirectWithMessage('student_dashboard.php', 'danger', 'Booking not found.');
        if (in_array($status, ['fully_approved','rejected'], true)) {
            redirectWithMessage('student_dashboard.php', 'warning', 'This booking can no longer be cancelled.');
        }
        $stmt = $pdo->prepare("UPDATE facility_bookings SET status='cancelled', current_approval_role=NULL WHERE id=? AND user_id=?");
        $stmt->execute([$id, (int)$user['id']]);
        sendNotification($pdo, (int)$user['id'], 'Booking Cancelled', 'Your facility booking has been cancelled.', 'system', $id);
        redirectWithMessage('student_dashboard.php', 'success', 'Facility booking cancelled.');
    }

    if ($type === 'item') {
        $stmt = $pdo->prepare('SELECT item_id, quantity_needed, status FROM item_bookings WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, (int)$user['id']]);
        $b = $stmt->fetch();
        if (!$b) redirectWithMessage('student_dashboard.php', 'danger', 'Request not found.');
        $status = (string)$b['status'];
        if (in_array($status, ['fully_approved','rejected'], true)) {
            redirectWithMessage('student_dashboard.php', 'warning', 'This request can no longer be cancelled.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE item_bookings SET status='cancelled', current_approval_role=NULL WHERE id=? AND user_id=?");
        $stmt->execute([$id, (int)$user['id']]);
        $stmt = $pdo->prepare('UPDATE items SET quantity_available = quantity_available + ? WHERE id = ?');
        $stmt->execute([(int)$b['quantity_needed'], (int)$b['item_id']]);
        $pdo->commit();

        sendNotification($pdo, (int)$user['id'], 'Request Cancelled', 'Your item borrowing request has been cancelled.', 'system', $id);
        redirectWithMessage('student_dashboard.php', 'success', 'Item request cancelled and quantity restored.');
    }
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirectWithMessage('student_dashboard.php', 'danger', 'Unable to cancel right now.');
}

