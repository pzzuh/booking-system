<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$u = getCurrentUser();
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int)$u['id']]);
    $count = (int)$stmt->fetchColumn();
} catch (Throwable) {
    $count = 0;
}

echo json_encode(['count' => $count]);

