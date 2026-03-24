<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrfToken('delete_user_form', $_POST['_csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Your session expired. Please try deleting the user again.';
    header("Location: manage_users.php");
    exit();
}

$user_id = (int) ($_POST['id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['error_message'] = 'Invalid user selected for deletion.';
    header("Location: manage_users.php");
    exit();
}

if ($user_id === (int) ($_SESSION['user_id'] ?? 0)) {
    $_SESSION['error_message'] = 'You cannot delete your own account from this page.';
    header("Location: manage_users.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$_SESSION['success_message'] = 'User deleted successfully.';
header("Location: manage_users.php");
exit();
