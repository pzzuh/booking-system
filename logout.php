<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_unset();
session_destroy();
session_start();
redirectWithMessage('login.php', 'success', 'You have been logged out.');

