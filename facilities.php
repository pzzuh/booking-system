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
    $action = $_GET['action'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'list':
        listFacilities($pdo);
        break;
    case 'add':
        addFacility($pdo, $input);
        break;
    case 'update':
        updateFacility($pdo, $input);
        break;
    case 'delete':
        deleteFacility($pdo, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listFacilities($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, location, capacity, description, status FROM facilities ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function addFacility($pdo, $data) {
    try {
        $name = trim($data['name'] ?? '');
        $location = trim($data['location'] ?? '');
        $capacity = intval($data['capacity'] ?? 0);
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'available';

        if (!$name || !$location || !$capacity) {
            echo json_encode(['success' => false, 'message' => 'Name, location and capacity are required.']);
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO facilities (name, location, capacity, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $location, $capacity, $description, $status]);
        echo json_encode(['success' => true, 'message' => 'Facility added!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateFacility($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $location = trim($data['location'] ?? '');
        $capacity = intval($data['capacity'] ?? 0);
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'available';

        if (!$id || !$name || !$location || !$capacity) {
            echo json_encode(['success' => false, 'message' => 'All fields required.']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE facilities SET name=?, location=?, capacity=?, description=?, status=? WHERE id=?");
        $stmt->execute([$name, $location, $capacity, $description, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Facility updated!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteFacility($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); return; }
        $stmt = $pdo->prepare("DELETE FROM facilities WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Facility deleted.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
