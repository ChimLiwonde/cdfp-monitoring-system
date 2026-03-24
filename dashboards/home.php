<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();

if (!isset($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$dashboard = dashboardFileForRole($_SESSION['role']);

if ($dashboard === null) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header("Location: ../Pages/login.php");
    exit();
}

header("Location: {$dashboard}");
exit();
