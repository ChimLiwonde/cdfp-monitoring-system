<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

$userStmt = $conn->prepare("SELECT username, password FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: ../Pages/login.php");
    exit();
}

if (isset($_POST['update_username'])) {
    if (!isValidCsrfToken('public_settings_username', $_POST['_csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');

        if ($username === '') {
            $error = 'Username cannot be empty.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();

            $_SESSION['username'] = $username;
            $user['username'] = $username;
            $message = 'Username updated successfully.';
        }
    }
}

if (isset($_POST['update_password'])) {
    if (!isValidCsrfToken('public_settings_password', $_POST['_csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Use a password with at least 8 characters.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            $stmt->execute();

            $user['password'] = $hashed;
            $message = 'Password updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Account Settings</title>
<link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "publicmenu.php"; ?></div>

<div class="col-9 public-dashboard">
<div class="form-card">
<h3>Account Settings</h3>

<?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="msg error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfInput('public_settings_username') ?>
    <label>Username</label>
    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

    <br><br>
    <input type="submit" name="update_username" value="Update Username">
</form>

<hr>

<form method="POST">
    <?= csrfInput('public_settings_password') ?>
    <label>Current Password</label>
    <input type="password" name="current_password" required>

    <label>New Password</label>
    <input type="password" name="password" required>

    <label>Confirm Password</label>
    <input type="password" name="confirm_password" required>

    <br><br>
    <input type="submit" name="update_password" value="Update Password">
</form>

</div>
</div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
