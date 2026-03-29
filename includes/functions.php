<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitizeInput(?string $value): string
{
    $value = trim((string)$value);
    $value = str_replace("\0", '', $value);
    return $value;
}

function formatDateTime(?string $dt): string
{
    if (!$dt) return '';
    try {
        return (new DateTime($dt))->format('M d, Y h:i A');
    } catch (Throwable) {
        return (string)$dt;
    }
}

function formatDate(?string $d): string
{
    if (!$d) return '';
    try {
        return (new DateTime($d))->format('M d, Y');
    } catch (Throwable) {
        return (string)$d;
    }
}

function timeAgo(?string $dt): string
{
    if (!$dt) return '';
    try {
        $then = new DateTime($dt);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $then->getTimestamp();
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return $then->format('M d, Y');
    } catch (Throwable) {
        return (string)$dt;
    }
}

function statusBadge(string $status): string
{
    $map = [
        'pending' => 'warning',
        'fully_approved' => 'success',
        'approved' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        'in_progress' => 'info',
        'open' => 'primary',
        'resolved' => 'success',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . e($color) . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function approvalRoleBadge(?string $role): string
{
    if (!$role) return '<span class="badge bg-secondary">—</span>';
    return '<span class="badge bg-info text-dark">' . e(ucwords(str_replace('_', ' ', $role))) . '</span>';
}

function sendNotification(PDO $pdo, int $user_id, string $title, string $message, string $type, ?int $booking_id = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, booking_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
    $stmt->execute([$user_id, $title, $message, $type, $booking_id]);
}

function generateCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$sessionToken) return false;
    return hash_equals((string)$sessionToken, (string)$token);
}

function requireValidCsrfOrDie(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            die('Invalid request');
        }
    }
}

function redirectWithMessage(string $to, string $type, string $message): never
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $to);
    exit;
}

function getFlash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

function roleRedirectTarget(string $role): string
{
    return match ($role) {
        'student' => 'student_dashboard.php',
        'adviser' => 'adviser.php',
        'staff' => 'staff.php',
        'dsa_director' => 'dsa_director.php',
        'ppss_director' => 'ppss_director.php',
        'dean' => 'dean.php',
        'avp_admin' => 'avp_admin.php',
        'vp_admin' => 'vp_admin.php',
        'president' => 'president.php',
        'admin' => 'admin_panel.php',
        'janitor' => 'janitor_dashboard.php',
        'security' => 'security_dashboard.php',
        default => 'login.php',
    };
}

function approvalChain(): array
{
    // Updated order per requirement:
    // Staff → Dean → PPSS → DSA (Adviser remains the initial step)
    return ['adviser', 'staff', 'dean', 'ppss_director', 'dsa_director', 'avp_admin', 'vp_admin', 'president'];
}

function nextApprovalRole(?string $current): ?string
{
    $chain = approvalChain();
    if (!$current) return null;
    $idx = array_search($current, $chain, true);
    if ($idx === false) return null;
    return $chain[$idx + 1] ?? null;
}
