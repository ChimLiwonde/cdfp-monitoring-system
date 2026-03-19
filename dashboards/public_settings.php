<?php
session_start();
require "../config/db.php";

/* =====================
   SECURITY
===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

/* =====================
   UPDATE USERNAME
===================== */
if (isset($_POST['update_username'])) {
    $username = trim($_POST['username']);

    $stmt = $conn->prepare("UPDATE users SET username=? WHERE id=?");
    $stmt->bind_param("si", $username, $user_id);
    $stmt->execute();

    $_SESSION['username'] = $username;
    $message = "✅ Username updated successfully";
}

/* =====================
   UPDATE PASSWORD
===================== */
if (isset($_POST['update_password'])) {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "❌ Passwords do not match";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $user_id);
        $stmt->execute();

        $message = "✅ Password updated successfully";
    }
}

/* =====================
   FETCH USER
===================== */
$userStmt = $conn->prepare("SELECT username FROM users WHERE id=?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
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
<h3>⚙️ Account Settings</h3>

<?php if ($message): ?>
    <div class="msg"><?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="msg error"><?= $error ?></div>
<?php endif; ?>

<!-- UPDATE USERNAME -->
<form method="POST">
    <label>Username</label>
    <input type="text" name="username"
           value="<?= htmlspecialchars($user['username']) ?>" required>

    <br><br>
    <input type="submit" name="update_username" value="Update Username">
</form>

<hr>

<!-- UPDATE PASSWORD -->
<form method="POST">
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
