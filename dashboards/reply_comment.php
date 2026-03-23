<?php
session_start();
require "../config/db.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$id    = $_POST['id'];
$reply = trim($_POST['reply']);

$stmt = $conn->prepare("
    UPDATE project_comments
    SET admin_reply = ?, replied_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("si", $reply, $id);
$stmt->execute();

header("Location: admin_comments.php");
exit();
