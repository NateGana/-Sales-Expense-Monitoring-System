<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    log_activity($conn, (int) $_SESSION['user_id'], 'LOGOUT', $_SESSION['full_name'] . ' logged out');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ' . base_url('login.php'));
exit;
