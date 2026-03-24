<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!isset($_SESSION['role']) || $userId <= 0) {
    header("Location: ../Pages/login.php");
    exit();
}

$notificationId = (int) ($_POST['id'] ?? 0);
if ($notificationId <= 0) {
    header("Location: notifications.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrfToken('open_notification_form', $_POST['_csrf_token'] ?? '')) {
    header("Location: notifications.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT id, link
    FROM user_notifications
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $notificationId, $userId);
$stmt->execute();
$notification = $stmt->get_result()->fetch_assoc();

if (!$notification) {
    header("Location: notifications.php");
    exit();
}

$markRead = $conn->prepare("
    UPDATE user_notifications
    SET is_read = 1
    WHERE id = ? AND user_id = ?
");
$markRead->bind_param("ii", $notificationId, $userId);
$markRead->execute();

$link = trim((string) ($notification['link'] ?? ''));
if ($link === '' || preg_match('/^[a-z]+:\/\//i', $link)) {
    $link = 'notifications.php';
}

header("Location: " . $link);
exit();
