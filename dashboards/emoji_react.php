<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isValidCsrfToken('emoji_react_form', $_POST['_csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$comment_id = (int) ($_POST['comment_id'] ?? 0);
$emoji = (string) ($_POST['emoji'] ?? '');
$allowedEmojis = ['👍', '😂', '😮', '😢', '🔥'];

if ($comment_id <= 0 || !in_array($emoji, $allowedEmojis, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid reaction']);
    exit();
}

if (!canPublicReactToComment($conn, $user_id, $comment_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized reaction target']);
    exit();
}

$stmt = $conn->prepare("SELECT id FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND emoji = ?");
$stmt->bind_param("iis", $comment_id, $user_id, $emoji);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $del = $conn->prepare("DELETE FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND emoji = ?");
    $del->bind_param("iis", $comment_id, $user_id, $emoji);
    $del->execute();
    $status = 'removed';
} else {
    $ins = $conn->prepare("INSERT INTO comment_reactions (comment_id, user_id, emoji) VALUES (?, ?, ?)");
    $ins->bind_param("iis", $comment_id, $user_id, $emoji);
    $ins->execute();
    $status = 'added';
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM comment_reactions WHERE comment_id = ? AND emoji = ?");
$countStmt->bind_param("is", $comment_id, $emoji);
$countStmt->execute();
$count = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

echo json_encode(['status' => $status, 'count' => $count]);
exit();
