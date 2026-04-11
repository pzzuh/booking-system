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
        listItems($pdo);
        break;
    case 'add':
        addItem($pdo, $input);
        break;
    case 'update':
        updateItem($pdo, $input);
        break;
    case 'delete':
        deleteItem($pdo, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listItems($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, category, quantity, description, status FROM items ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function addItem($pdo, $data) {
    try {
        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? '');
        $quantity = intval($data['quantity'] ?? 0);
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'available';

        if (!$name || !$category) {
            echo json_encode(['success' => false, 'message' => 'Name and category are required.']);
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO items (name, category, quantity, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category, $quantity, $description, $status]);
        echo json_encode(['success' => true, 'message' => 'Item added!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateItem($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? '');
        $quantity = intval($data['quantity'] ?? 0);
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'available';

        if (!$id || !$name || !$category) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE items SET name=?, category=?, quantity=?, description=?, status=? WHERE id=?");
        $stmt->execute([$name, $category, $quantity, $description, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Item updated!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteItem($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); return; }
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Item deleted.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
