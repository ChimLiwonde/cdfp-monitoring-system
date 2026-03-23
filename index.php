<?php
session_start();

if (isset($_SESSION['role'])) {
    header("Location: dashboards/home.php");
} else {
    header("Location: Pages/login.php");
}
exit();
