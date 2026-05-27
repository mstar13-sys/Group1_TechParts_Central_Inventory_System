<?php
// logout.php
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}
session_destroy();

session_start();
setFlash('warning', 'You have logged out. Reminder: admin must create a database backup for safety purposes.', 'Backup Reminder');

header('Location: /pages/login.php');
exit;
