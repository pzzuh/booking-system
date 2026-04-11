<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'list':
        listUsers($pdo);
        break;
    case 'add':
        addUser($pdo, $input);
        break;
    case 'update':
        updateUser($pdo, $input);
        break;
    case 'delete':
        deleteUser($pdo, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listUsers($pdo) {
    try {
        // Adjust column names to match YOUR actual table columns
        $stmt = $pdo->query("SELECT id, name, email, role, department, status FROM users ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function addUser($pdo, $data) {
    try {
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? '';
        $department = $data['department'] ?? '';
        $status = $data['status'] ?? 'active';

        if (!$name || !$email || !$password || !$role || !$department) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        // Check duplicate email
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists.']);
            return;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed, $role, $department, $status]);
        echo json_encode(['success' => true, 'message' => 'User added successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateUser($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? '';
        $department = $data['department'] ?? '';
        $status = $data['status'] ?? 'active';
        $password = $data['password'] ?? '';

        if (!$id || !$name || !$email || !$role || !$department) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=?, department=?, status=? WHERE id=?");
            $stmt->execute([$name, $email, $hashed, $role, $department, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, department=?, status=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $department, $status, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteUser($pdo, $data) {
    try {
        $id = intval($data['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); return; }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'User deleted.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
