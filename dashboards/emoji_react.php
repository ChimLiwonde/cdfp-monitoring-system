<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    http_response_code(403);
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];
$comment_id = $_POST['comment_id'];
$emoji = $_POST['emoji'];

// Toggle reaction
$stmt = $conn->prepare("SELECT id FROM comment_reactions WHERE comment_id=? AND user_id=? AND emoji=?");
$stmt->bind_param("iis", $comment_id, $user_id, $emoji);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Remove reaction
    $del = $conn->prepare("DELETE FROM comment_reactions WHERE comment_id=? AND user_id=? AND emoji=?");
    $del->bind_param("iis", $comment_id, $user_id, $emoji);
    $del->execute();
    $status = 'removed';
} else {
    // Add reaction
    $ins = $conn->prepare("INSERT INTO comment_reactions (comment_id,user_id,emoji) VALUES (?,?,?)");
    $ins->bind_param("iis", $comment_id, $user_id, $emoji);
    $ins->execute();
    $status = 'added';
}

// Return updated count for this emoji
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM comment_reactions WHERE comment_id=? AND emoji=?");
$countStmt->bind_param("is", $comment_id, $emoji);
$countStmt->execute();
$count = $countStmt->get_result()->fetch_assoc()['total'];

echo json_encode(['status'=>$status, 'count'=>$count]);
?>
