<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
requireValidCsrfOrDie();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage(roleRedirectTarget((string)($_SESSION['user']['role'] ?? '')), 'danger', 'Invalid request.');
}

$me = getCurrentUser();
$myRole = (string)$me['role'];
$action = sanitizeInput($_POST['action'] ?? '');
$bookingId = (int)($_POST['booking_id'] ?? 0);
$reason = sanitizeInput($_POST['reason'] ?? '');

if ($bookingId <= 0 || !in_array($action, ['approve','reject'], true)) {
    redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Invalid request.');
}

if (!in_array($myRole, approvalChain(), true)) {
    redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Access denied.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, user_id, status, current_approval_role, title FROM facility_bookings WHERE id = ? FOR UPDATE');
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) {
        $pdo->rollBack();
        redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Booking not found.');
    }
    if ((string)$b['status'] !== 'pending' || (string)$b['current_approval_role'] !== $myRole) {
        $pdo->rollBack();
        redirectWithMessage(roleRedirectTarget($myRole), 'warning', 'This booking is not awaiting your action.');
    }

    if ($action === 'approve') {
        $next = nextApprovalRole($myRole);
        if ($next) {
            $stmt = $pdo->prepare("UPDATE facility_bookings SET current_approval_role = ? WHERE id = ?");
            $stmt->execute([$next, $bookingId]);
        } else {
            $stmt = $pdo->prepare("UPDATE facility_bookings SET status='fully_approved', current_approval_role = NULL WHERE id = ?");
            $stmt->execute([$bookingId]);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO facility_booking_approvals (booking_id, role, approver_user_id, action, notes, rejection_reason, action_at)
             VALUES (?, ?, ?, 'approve', NULL, NULL, NOW())"
        );
        $stmt->execute([$bookingId, $myRole, (int)$me['id']]);

        $pdo->commit();

        // Notify student
        sendNotification(
            $pdo,
            (int)$b['user_id'],
            'Booking Approved',
            "Your booking \"{$b['title']}\" was approved by " . ucwords(str_replace('_',' ', $myRole)) . ".",
            'approval',
            $bookingId
        );

        // Notify next approvers
        if ($next) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1");
            $stmt->execute([$next]);
            foreach ($stmt->fetchAll() as $row) {
                sendNotification(
                    $pdo,
                    (int)$row['id'],
                    'Booking Needs Your Approval',
                    "A booking is awaiting your action (current: " . ucwords(str_replace('_',' ', $next)) . ").",
                    'approval',
                    $bookingId
                );
            }
        } else {
            // Fully approved: optional janitor/security heads-up (best effort)
            foreach (['janitor','security'] as $r) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1");
                    $stmt->execute([$r]);
                    foreach ($stmt->fetchAll() as $row) {
                        sendNotification(
                            $pdo,
                            (int)$row['id'],
                            'Upcoming Fully Approved Booking',
                            "A booking is now fully approved and may require preparation/security coverage.",
                            'booking',
                            $bookingId
                        );
                    }
                } catch (Throwable) {}
            }
        }

        redirectWithMessage(roleRedirectTarget($myRole), 'success', 'Booking approved.');
    }

    // Reject
    if ($reason === '') {
        $pdo->rollBack();
        redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Rejection reason is required.');
    }

    $stmt = $pdo->prepare("UPDATE facility_bookings SET status='rejected', current_approval_role=NULL, rejection_reason=? WHERE id=?");
    $stmt->execute([$reason, $bookingId]);
    $stmt = $pdo->prepare(
        "INSERT INTO facility_booking_approvals (booking_id, role, approver_user_id, action, notes, rejection_reason, action_at)
         VALUES (?, ?, ?, 'reject', NULL, ?, NOW())"
    );
    $stmt->execute([$bookingId, $myRole, (int)$me['id'], $reason]);

    $pdo->commit();

    sendNotification(
        $pdo,
        (int)$b['user_id'],
        'Booking Rejected',
        "Your booking \"{$b['title']}\" was rejected by " . ucwords(str_replace('_',' ', $myRole)) . ". Reason: {$reason}",
        'approval',
        $bookingId
    );

    redirectWithMessage(roleRedirectTarget($myRole), 'success', 'Booking rejected.');
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Unable to process action right now.');
}

