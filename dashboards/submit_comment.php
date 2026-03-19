<?php
session_start();
require "../config/db.php";

if ($_SESSION['role'] !== 'public') {
    header("Location: ../login.php");
    exit();
}

$project_id = $_POST['project_id'];
$comment    = trim($_POST['comment']);
$user_id   = $_SESSION['user_id'];

if ($comment !== "") {
    $stmt = $conn->prepare("
        INSERT INTO project_comments (project_id, user_id, comment)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $project_id, $user_id, $comment);
    $stmt->execute();
}

header("Location: public.php");
exit();
