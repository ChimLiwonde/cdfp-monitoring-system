<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int)($_GET['id'] ?? 0);

/* Prevent deleting yourself */
if ($user_id === (int)$_SESSION['user_id']) {
    header("Location: manage_users.php?error=self_delete");
    exit();
}

/* Delete user — related records auto-delete */
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

header("Location: manage_users.php?deleted=1");
exit();
