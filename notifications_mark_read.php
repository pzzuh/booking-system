<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false]);
    exit;
}

$ok = validateCsrfToken($_POST['csrf_token'] ?? null);
if (!$ok) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$u = getCurrentUser();
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, (int)$u['id']]);
    echo json_encode(['ok' => true]);
    exit;
} catch (Throwable) {
    echo json_encode(['ok' => false]);
    exit;
}

