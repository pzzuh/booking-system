<?php
session_start();

// Clear remember_token from DB before destroying session
if (!empty($_SESSION['user']['id'])) {
    try {
        require_once __DIR__ . '/includes/db.php';
        $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user']['id']]);
    } catch (Throwable) {}
}

session_unset();
session_destroy();

// Clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear the remember_token cookie
setcookie('remember_token', '', time() - 42000, '/', '', false, true);

header("Location: login.php");
exit();
?>
