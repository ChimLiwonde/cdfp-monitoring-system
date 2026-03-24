<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";
require "../config/mail.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrfToken('reset_user_password_form', $_POST['_csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Your session expired. Please try resetting the password again.';
    header("Location: manage_users.php");
    exit();
}

$user_id = (int) ($_POST['id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['error_message'] = 'Invalid user selected for password reset.';
    header("Location: manage_users.php");
    exit();
}

if ($user_id === (int) ($_SESSION['user_id'] ?? 0)) {
    $_SESSION['error_message'] = 'Use the account settings page to change your own password.';
    header("Location: manage_users.php");
    exit();
}

$userStmt = $conn->prepare("SELECT username, email FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$targetUser = $userStmt->get_result()->fetch_assoc();

if (!$targetUser) {
    $_SESSION['error_message'] = 'The selected user account could not be found.';
    header("Location: manage_users.php");
    exit();
}

$temp_password = substr(bin2hex(random_bytes(6)), 0, 10);
$hashed = password_hash($temp_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed, $user_id);
$stmt->execute();

$subject = "Temporary Password Reset";
$body = "
    <h3>Hello {$targetUser['username']},</h3>
    <p>Your account password was reset by an administrator.</p>
    <p><strong>Temporary Password:</strong> {$temp_password}</p>
    <p>Please log in and change your password as soon as possible.</p>
";

if (!empty($targetUser['email']) && sendEmail($targetUser['email'], $subject, $body)) {
    $_SESSION['success_message'] = 'Password reset successfully. A temporary password was emailed to the user.';
} else {
    $_SESSION['password_reset_info'] = 'Password reset successfully. Temporary password: ' . $temp_password;
}

header("Location: manage_users.php");
exit();
