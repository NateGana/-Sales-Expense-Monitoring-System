<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . base_url(isLoggedIn() ? 'dashboard.php' : 'login.php'));
exit;
