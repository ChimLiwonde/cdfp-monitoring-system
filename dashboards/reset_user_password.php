<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$user_id = (int) $_GET['id'];

/* Generate secure temporary password */
$temp_password = substr(bin2hex(random_bytes(6)), 0, 10);
$hashed = password_hash($temp_password, PASSWORD_DEFAULT);

/* Update password */
$stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
$stmt->bind_param("si", $hashed, $user_id);
$stmt->execute();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Password Reset</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>

    <div class="col-9">
        <div class="form-card">
            <h3>Password Reset Successful</h3>

            <p><strong>Temporary Password:</strong></p>
            <div style="font-size:20px;background:#f3f3f3;padding:15px;border-radius:6px;">
                <?= htmlspecialchars($temp_password) ?>
            </div>

            <p style="color:red;margin-top:10px;">
                ⚠ Copy this password now. It cannot be recovered again.
            </p>

            <a href="manage_users.php" class="btn">← Back to Users</a>
        </div>
    </div>
</div>

</body>
</html>
