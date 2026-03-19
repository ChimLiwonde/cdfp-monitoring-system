<?php
session_start();
require "../config/db.php";

/* ================= SECURITY CHECK ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ================= VALIDATE REQUEST ID ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request ID");
}

$request_id = (int) $_GET['id'];

/* ================= UPDATE STATUS TO REVIEWED ================= */
$stmt = $conn->prepare("
    UPDATE community_requests 
    SET status = 'reviewed' 
    WHERE id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $request_id);

if (!$stmt->execute()) {
    die("Execution failed: " . $stmt->error);
}

$stmt->close();

/* ================= REDIRECT BACK ================= */
header("Location: admin_community_requests.php");
exit();
