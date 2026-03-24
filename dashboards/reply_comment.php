<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (($_SESSION['role'] ?? null) !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrfToken('admin_comments_reply_form', $_POST['_csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Your session expired. Please try saving the reply again.';
    header("Location: admin_comments.php");
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$reply = trim($_POST['reply'] ?? '');

if ($id <= 0 || $reply === '') {
    $_SESSION['error_message'] = 'Provide a valid reply before saving.';
    header("Location: admin_comments.php");
    exit();
}

$stmt = $conn->prepare("
    UPDATE project_comments
    SET admin_reply = ?, replied_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("si", $reply, $id);
$stmt->execute();

$_SESSION['success_message'] = 'Reply saved successfully.';
header("Location: admin_comments.php");
exit();
