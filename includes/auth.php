<?php
declare(strict_types=1);

// Secure session settings (Prompt 15)
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

// Session timeout (Prompt 15): 30 minutes
if (!empty($_SESSION['user'])) {
    $last = (int)($_SESSION['last_activity'] ?? 0);
    if ($last && (time() - $last) > 1800) {
        session_unset();
        session_destroy();
        session_start();
        redirectWithMessage('login.php', 'warning', 'Your session has expired. Please log in again.');
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

function getCurrentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirectWithMessage('login.php', 'warning', 'Please log in to continue.');
    }
}

function requireRole(array|string $roles): void
{
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    $userRole = (string)($_SESSION['user']['role'] ?? '');
    if (!in_array($userRole, $roles, true)) {
        header('Location: ' . roleRedirectTarget($userRole));
        exit;
    }
}

function enforceCorrectDashboard(string $expectedRole): void
{
    requireLogin();
    $userRole = (string)($_SESSION['user']['role'] ?? '');
    if ($userRole !== $expectedRole) {
        header('Location: ' . roleRedirectTarget($userRole));
        exit;
    }
}
