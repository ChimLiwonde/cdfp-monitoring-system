<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";

if (isset($_POST['save_user'])) {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $location = trim($_POST['location']);
    $role     = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, location, role)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $username, $email, $password, $location, $role);

    if ($stmt->execute()) {
        header("Location: manage_users.php");
        exit();
    } else {
        $msg = "Error creating user.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add User</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "adminmenu.php"; ?></div>

<div class="col-9">
<div class="form-card">
<h3>Add New User</h3>

<?php if($msg) echo "<div class='msg'>$msg</div>"; ?>

<form method="POST">
    Username
    <input type="text" name="username" required>

    Email
    <input type="email" name="email" required>

    Location
    <input type="text" name="location" required>

    Role
    <select name="role" required>
        <option value="public">Public</option>
        <option value="field_officer">Field Officer</option>
        <option value="admin">Admin</option>
    </select>

    Password
    <input type="password" name="password" required>

    <input type="submit" name="save_user" value="Create User">
</form>
</div>
</div>
</div>

<div class="dashboard-footer">© 2025 CDF Monitoring System</div>
</body>
</html>
