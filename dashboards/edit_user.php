<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$id = intval($_GET['id']);
$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->bind_param("i", $id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
if (!$user) {
    header("Location: manage_users.php");
    exit();
}

$msg = "";

if (isset($_POST['update_user'])) {
    if (!isValidCsrfToken('edit_user_form', $_POST['_csrf_token'] ?? '')) {
        $msg = "Your session expired. Please try updating the user again.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $location = trim($_POST['location']);
        $role = $_POST['role'];

        if ($username === '' || $email === '' || $location === '') {
            $msg = "Please fill in all required user fields.";
        } elseif (!empty($_POST['password']) && strlen($_POST['password']) < 8) {
            $msg = "New passwords must be at least 8 characters long.";
        } else {
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users SET username=?, email=?, location=?, role=?, password=? WHERE id=?
                ");
                $stmt->bind_param("sssssi", $username, $email, $location, $role, $password, $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE users SET username=?, email=?, location=?, role=? WHERE id=?
                ");
                $stmt->bind_param("ssssi", $username, $email, $location, $role, $id);
            }

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "User updated successfully.";
                header("Location: manage_users.php");
                exit();
            } else {
                $msg = "Update failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "adminmenu.php"; ?></div>
<div class="col-9">
<div class="form-card">

<h3>Edit User</h3>
<?php if ($msg) echo "<div class='msg'>" . htmlspecialchars($msg) . "</div>"; ?>

<form method="POST">
    <?= csrfInput('edit_user_form') ?>
    Username
    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

    Email
    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

    Location
    <input type="text" name="location" value="<?= htmlspecialchars($user['location']) ?>" required>

    Role
    <select name="role">
        <option value="public" <?= $user['role'] == 'public' ? 'selected' : '' ?>>Public</option>
        <option value="field_officer" <?= $user['role'] == 'field_officer' ? 'selected' : '' ?>>Field Officer</option>
        <option value="project_manager" <?= $user['role'] == 'project_manager' ? 'selected' : '' ?>>Project Manager</option>
        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
    </select>

    New Password (leave blank to keep current)
    <input type="password" name="password">

    <input type="submit" name="update_user" value="Update User">
</form>

</div>
</div>
</div>

<div class="dashboard-footer">(c) 2025 CDF Monitoring System</div>
</body>
</html>
