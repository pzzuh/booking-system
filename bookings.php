<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../db.php';

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = 'list';
    $type = $_GET['type'] ?? 'facility';
    $status = $_GET['status'] ?? '';
    $date = $_GET['date'] ?? '';
    listBookings($pdo, $type, $status, $date);
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'update_status':
            updateBookingStatus($pdo, $input);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function listBookings($pdo, $type, $status, $date) {
    try {
        if ($type === 'facility') {
            // Adjust table/column names to match your actual DB schema
            $sql = "
                SELECT 
                    fb.id,
                    u.name AS requester_name,
                    u.department,
                    f.name AS facility_name,
                    fb.booking_date,
                    fb.start_time,
                    fb.end_time,
                    fb.purpose,
                    fb.status
                FROM facility_bookings fb
                LEFT JOIN users u ON fb.user_id = u.id
                LEFT JOIN facilities f ON fb.facility_id = f.id
                WHERE 1=1
            ";
            $params = [];
            if ($status) { $sql .= " AND fb.status = ?"; $params[] = $status; }
            if ($date) { $sql .= " AND fb.booking_date = ?"; $params[] = $date; }
            $sql .= " ORDER BY fb.id DESC";

        } else {
            // Item bookings - adjust table/column names to match your actual DB schema
            $sql = "
                SELECT 
                    ib.id,
                    u.name AS requester_name,
                    u.department,
                    i.name AS item_name,
                    ib.quantity,
                    ib.date_needed,
                    ib.return_date,
                    ib.purpose,
                    ib.status
                FROM item_bookings ib
                LEFT JOIN users u ON ib.user_id = u.id
                LEFT JOIN items i ON ib.item_id = i.id
                WHERE 1=1
            ";
            $params = [];
            if ($status) { $sql .= " AND ib.status = ?"; $params[] = $status; }
            if ($date) { $sql .= " AND ib.date_needed = ?"; $params[] = $date; }
            $sql .= " ORDER BY ib.id DESC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } catch (Exception $e) {
        // Return empty array with error info for debugging
        echo json_encode([]);
        // Uncomment below for debugging:
        // echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateBookingStatus($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        $status = $data['status'] ?? '';
        $type = $data['type'] ?? 'facility';

        if (!$id || !in_array($status, ['approved', 'rejected', 'cancelled', 'pending'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            return;
        }

        $table = $type === 'facility' ? 'facility_bookings' : 'item_bookings';
        $stmt = $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true, 'message' => "Booking $status."]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
