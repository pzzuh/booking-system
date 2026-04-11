<?php
// =============================================================
// includes/db.php  — Database connection
// Place this file at:  booking-system/includes/db.php
//
// Adjust DB_HOST / DB_USER / DB_PASS below to match your
// local environment (XAMPP default is root with empty password).
// =============================================================

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'univ_book');
define('DB_USER', 'root');       // Change if your MySQL user is different
define('DB_PASS', '');           // Change if you have a MySQL password
define('DB_CHARSET', 'utf8mb4');

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_NAME,
    DB_CHARSET
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Show a clean error instead of a PHP stack trace
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Database Error</title>
    <style>body{font-family:sans-serif;padding:2rem;background:#f8d7da;color:#842029;}
    .box{max-width:600px;margin:auto;background:#fff;border:1px solid #f5c2c7;
    border-radius:8px;padding:2rem;}</style></head><body>
    <div class="box">
    <h2>⚠ Database Connection Failed</h2>
    <p>The application could not connect to the database. Please check:</p>
    <ul>
      <li>MySQL / MariaDB is running (start XAMPP/Laragon)</li>
      <li><code>DB_USER</code> and <code>DB_PASS</code> in <code>includes/db.php</code></li>
      <li>Database <code>univ_book</code> has been created and imported</li>
    </ul>
    <details><summary>Technical detail</summary><pre>' .
    htmlspecialchars($e->getMessage(), ENT_QUOTES) .
    '</pre></details></div></body></html>';
    exit;
}
