<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user_city_project.php");
    exit();
}

if (!isValidCsrfToken('public_comment_form', $_POST['_csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Your session expired. Please submit the comment again.';
    header("Location: user_city_project.php");
    exit();
}

$project_id = (int) ($_POST['project_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($comment === '') {
    $_SESSION['error_message'] = 'Write a comment before submitting.';
    header("Location: user_city_project.php");
    exit();
}

if (!canPublicCommentOnProject($conn, $user_id, $project_id)) {
    $_SESSION['error_message'] = 'Comments are only available for visible pending or approved projects.';
    header("Location: user_city_project.php");
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO project_comments (project_id, user_id, comment)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iis", $project_id, $user_id, $comment);
$stmt->execute();

$_SESSION['success_message'] = 'Comment submitted successfully.';
header("Location: user_city_project.php");
exit();
