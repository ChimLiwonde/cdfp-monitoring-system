<?php
session_start();
require "../config/db.php";
require "../config/mail.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id'], $_GET['status'])) {
    header("Location: adminprojects.php");
    exit();
}

$id = (int) $_GET['id'];
$status = $_GET['status'];

/* ALLOW ONLY VALID STATUS */
$allowed = ['approved', 'denied'];
if (!in_array($status, $allowed)) {
    die("Invalid status.");
}

/* FETCH PROJECT + OFFICER */
$stmt = $conn->prepare("
    SELECT p.title, u.email, u.username
    FROM projects p
    JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Project not found.");
}

$project = $result->fetch_assoc();

/* UPDATE STATUS */
$update = $conn->prepare("
    UPDATE projects 
    SET status = ? 
    WHERE id = ?
");
$update->bind_param("si", $status, $id);
$update->execute();

/* EMAIL OFFICER */
$subject = "Project {$status}";
$body = "
    <h3>Hello {$project['username']},</h3>
    <p>Your project <b>{$project['title']}</b> has been <b>{$status}</b>.</p>
    <p>Regards,<br>CDF Admin</p>
";

sendEmail($project['email'], $subject, $body);

header("Location: adminprojects.php");
exit();
