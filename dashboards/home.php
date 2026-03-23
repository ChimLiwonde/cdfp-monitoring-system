<?php
session_start();
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$dashboard = dashboardFileForRole($_SESSION['role']);

if ($dashboard === null) {
    session_destroy();
    header("Location: ../Pages/login.php");
    exit();
}

header("Location: {$dashboard}");
exit();
