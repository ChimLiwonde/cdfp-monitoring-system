<?php
session_start();
require "../config/db.php";
require "../config/mail.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

if (!isset($_GET['id'], $_GET['status'])) {
    header("Location: adminprojects.php?status=all");
    exit();
}

$id = (int) $_GET['id'];
$status = $_GET['status'];
$return_to = $_GET['return_to'] ?? 'projects';
$redirect_url = ($return_to === 'detail')
    ? "admin_project_details.php?id={$id}"
    : "adminprojects.php?status=all";

/* ALLOW ONLY VALID STATUS */
$allowed = ['approved', 'denied'];
if (!in_array($status, $allowed)) {
    die("Invalid status.");
}

/* FETCH PROJECT + OFFICER */
$stmt = $conn->prepare("
    SELECT p.title, p.status AS current_status, u.email, u.username
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

if ($project['current_status'] !== 'pending') {
    $_SESSION['success_message'] = "Only pending projects can be approved or denied.";
    header("Location: " . $redirect_url);
    exit();
}

/* UPDATE STATUS */
$update = $conn->prepare("
    UPDATE projects 
    SET status = ? 
    WHERE id = ?
");
$update->bind_param("si", $status, $id);
$update->execute();

logProjectActivity(
    $conn,
    $id,
    'project_status_changed',
    $_SESSION['user_id'] ?? null,
    $_SESSION['role'] ?? 'admin',
    $project['current_status'],
    $status,
    'Project status updated to ' . formatStatusLabel($status) . ' by admin.'
);

/* EMAIL OFFICER */
$subject = "Project " . formatProjectCode($id) . " {$status}";
$body = "
    <h3>Hello {$project['username']},</h3>
    <p>Your project <b>" . formatProjectCode($id) . " - {$project['title']}</b> has been <b>" . formatStatusLabel($status) . "</b>.</p>
    <p>Regards,<br>CDF Admin</p>
";

sendEmail($project['email'], $subject, $body);

$_SESSION['success_message'] = "Project " . formatProjectCode($id) . " marked as " . formatStatusLabel($status) . ".";

header("Location: " . $redirect_url);
exit();
