<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
requireValidCsrfOrDie();

$u = getCurrentUser();
try {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([(int)$u['id']]);
} catch (Throwable) {}

redirectWithMessage('notifications.php', 'success', 'All notifications marked as read.');

