<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$msg = "";

if (isset($_POST['save_user'])) {
    if (!isValidCsrfToken('add_user_form', $_POST['_csrf_token'] ?? '')) {
        $msg = "Your session expired. Please try creating the user again.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $location = trim($_POST['location']);
        $role = $_POST['role'];
        $rawPassword = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $location === '' || strlen($rawPassword) < 8) {
            $msg = "Provide all required fields and use a password with at least 8 characters.";
        } else {
            $password = password_hash($rawPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, location, role)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $username, $email, $password, $location, $role);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "User created successfully.";
                header("Location: manage_users.php");
                exit();
            } else {
                $msg = "Error creating user.";
            }
        }
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

<?php if ($msg) echo "<div class='msg'>" . htmlspecialchars($msg) . "</div>"; ?>

<form method="POST">
    <?= csrfInput('add_user_form') ?>
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
        <option value="project_manager">Project Manager</option>
        <option value="admin">Admin</option>
    </select>

    Password
    <input type="password" name="password" required>

    <input type="submit" name="save_user" value="Create User">
</form>
</div>
</div>
</div>

<div class="dashboard-footer">(c) 2025 CDF Monitoring System</div>
</body>
</html>
