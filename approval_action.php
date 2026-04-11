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

$me          = getCurrentUser();
$myRole      = (string)$me['role'];
$action      = sanitizeInput($_POST['action'] ?? '');
$bookingId   = (int)($_POST['booking_id'] ?? 0);
$bookingType = sanitizeInput($_POST['booking_type'] ?? 'facility');
$reason      = sanitizeInput($_POST['reason'] ?? '');

if ($bookingId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Invalid request.');
}
if (!in_array($myRole, approvalChain(), true)) {
    redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Access denied.');
}
if (!in_array($bookingType, ['facility', 'item'], true)) {
    $bookingType = 'facility';
}
$isItem = ($bookingType === 'item');

try {
    $pdo->beginTransaction();

    if ($isItem) {
        $stmt = $pdo->prepare(
            'SELECT ib.id, ib.user_id, ib.status, ib.current_approval_role, i.name AS item_name
             FROM item_bookings ib
             JOIN items i ON i.id = ib.item_id
             WHERE ib.id = ? FOR UPDATE'
        );
        $stmt->execute([$bookingId]);
        $b = $stmt->fetch();

        if (!$b) { $pdo->rollBack(); redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Item booking not found.'); }
        if ((string)$b['status'] !== 'pending' || (string)$b['current_approval_role'] !== $myRole) {
            $pdo->rollBack(); redirectWithMessage(roleRedirectTarget($myRole), 'warning', 'This booking is not awaiting your action.');
        }

        if ($action === 'approve') {
            $next = nextApprovalRole($myRole);
            if ($next) {
                $pdo->prepare("UPDATE item_bookings SET current_approval_role=? WHERE id=?")->execute([$next, $bookingId]);
            } else {
                $pdo->prepare("UPDATE item_bookings SET status='fully_approved', current_approval_role=NULL WHERE id=?")->execute([$bookingId]);
            }
            try {
                $pdo->prepare("INSERT INTO item_booking_approvals (booking_id,role,approver_user_id,action,notes,rejection_reason,action_at) VALUES (?,?,?,'approve',NULL,NULL,NOW())")
                    ->execute([$bookingId, $myRole, (int)$me['id']]);
            } catch (Throwable) {}
            $pdo->commit();
            sendNotification($pdo, (int)$b['user_id'], 'Item Booking Approved',
                "Your item booking \"{$b['item_name']}\" was approved by ".ucwords(str_replace('_',' ',$myRole)).".", 'approval', $bookingId);
            if ($next) {
                $rows = $pdo->prepare("SELECT id FROM users WHERE role=? AND is_active=1");
                $rows->execute([$next]);
                foreach ($rows->fetchAll() as $row) {
                    sendNotification($pdo,(int)$row['id'],'Item Booking Needs Your Approval','An item booking is awaiting your action.','approval',$bookingId);
                }
            }
            redirectWithMessage(roleRedirectTarget($myRole), 'success', 'Item booking approved.');
        }

        if ($reason === '') { $pdo->rollBack(); redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Rejection reason is required.'); }
        $pdo->prepare("UPDATE item_bookings SET status='rejected', current_approval_role=NULL, rejection_reason=? WHERE id=?")->execute([$reason, $bookingId]);
        try {
            $pdo->prepare("INSERT INTO item_booking_approvals (booking_id,role,approver_user_id,action,notes,rejection_reason,action_at) VALUES (?,?,?,'reject',NULL,?,NOW())")
                ->execute([$bookingId, $myRole, (int)$me['id'], $reason]);
        } catch (Throwable) {}
        $pdo->commit();
        sendNotification($pdo, (int)$b['user_id'], 'Item Booking Rejected',
            "Your item booking \"{$b['item_name']}\" was rejected by ".ucwords(str_replace('_',' ',$myRole)).". Reason: {$reason}", 'approval', $bookingId);
        redirectWithMessage(roleRedirectTarget($myRole), 'success', 'Item booking rejected.');

    } else {
        $stmt = $pdo->prepare('SELECT id, user_id, status, current_approval_role, title FROM facility_bookings WHERE id=? FOR UPDATE');
        $stmt->execute([$bookingId]);
        $b = $stmt->fetch();

        if (!$b) { $pdo->rollBack(); redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Booking not found.'); }
        if ((string)$b['status'] !== 'pending' || (string)$b['current_approval_role'] !== $myRole) {
            $pdo->rollBack(); redirectWithMessage(roleRedirectTarget($myRole), 'warning', 'This booking is not awaiting your action.');
        }

        if ($action === 'approve') {
            $next = nextApprovalRole($myRole);
            if ($next) {
                $pdo->prepare("UPDATE facility_bookings SET current_approval_role=? WHERE id=?")->execute([$next, $bookingId]);
            } else {
                $pdo->prepare("UPDATE facility_bookings SET status='fully_approved', current_approval_role=NULL WHERE id=?")->execute([$bookingId]);
            }
            $pdo->prepare("INSERT INTO facility_booking_approvals (booking_id,role,approver_user_id,action,notes,rejection_reason,action_at) VALUES (?,?,?,'approve',NULL,NULL,NOW())")
                ->execute([$bookingId, $myRole, (int)$me['id']]);
            $pdo->commit();
            sendNotification($pdo,(int)$b['user_id'],'Booking Approved',
                "Your booking \"{$b['title']}\" was approved by ".ucwords(str_replace('_',' ',$myRole)).".",'approval',$bookingId);
            if ($next) {
                $rows = $pdo->prepare("SELECT id FROM users WHERE role=? AND is_active=1");
                $rows->execute([$next]);
                foreach ($rows->fetchAll() as $row) {
                    sendNotification($pdo,(int)$row['id'],'Booking Needs Your Approval',
                        "A booking is awaiting your action (current: ".ucwords(str_replace('_',' ',$next)).").",'approval',$bookingId);
                }
            } else {
                foreach (['janitor','security'] as $r) {
                    try {
                        $rows = $pdo->prepare("SELECT id FROM users WHERE role=? AND is_active=1");
                        $rows->execute([$r]);
                        foreach ($rows->fetchAll() as $row) {
                            sendNotification($pdo,(int)$row['id'],'Upcoming Fully Approved Booking',
                                "A booking is now fully approved and may require preparation/security coverage.",'booking',$bookingId);
                        }
                    } catch (Throwable) {}
                }
            }
            redirectWithMessage(roleRedirectTarget($myRole), 'success', 'Booking approved.');
        }

        if ($reason === '') { $pdo->rollBack(); redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Rejection reason is required.'); }
        $pdo->prepare("UPDATE facility_bookings SET status='rejected', current_approval_role=NULL, rejection_reason=? WHERE id=?")->execute([$reason, $bookingId]);
        $pdo->prepare("INSERT INTO facility_booking_approvals (booking_id,role,approver_user_id,action,notes,rejection_reason,action_at) VALUES (?,?,?,'reject',NULL,?,NOW())")
            ->execute([$bookingId, $myRole, (int)$me['id'], $reason]);
        $pdo->commit();
        sendNotification($pdo,(int)$b['user_id'],'Booking Rejected',
            "Your booking \"{$b['title']}\" was rejected by ".ucwords(str_replace('_',' ',$myRole)).". Reason: {$reason}",'approval',$bookingId);
        redirectWithMessage(roleRedirectTarget($myRole), 'success', 'Booking rejected.');
    }
} catch (Throwable) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirectWithMessage(roleRedirectTarget($myRole), 'danger', 'Unable to process action right now.');
}
