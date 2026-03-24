<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";
require "../config/mail.php";

if (($_SESSION['role'] ?? null) !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$message = "";

if (isset($_POST['create_officer'])) {
    if (!isValidCsrfToken('create_officer_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try creating the account again.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $location = trim($_POST['district']);
        $selected_role = $_POST['role'] ?? 'field_officer';
        $allowed_roles = ['field_officer', 'project_manager'];
        $role = in_array($selected_role, $allowed_roles, true) ? $selected_role : 'field_officer';
        $role_label = formatRoleLabel($role);

        $temp_password = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 8);
        $hashed = password_hash($temp_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, location, role)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            $message = "The account could not be prepared right now.";
        } else {
            $stmt->bind_param("sssss", $username, $email, $hashed, $location, $role);

            if ($stmt->execute()) {
                $subject = "CDF Monitoring System - {$role_label} Account";
                $body = "
                    <h3>Welcome to CDF Monitoring System</h3>
                    <p>Your {$role_label} account has been created successfully.</p>
                    <p><b>Assigned Role:</b> {$role_label}</p>

                    <p><b>Login Details</b></p>
                    <p><b>Username:</b> {$username}</p>
                    <p><b>Temporary Password:</b> {$temp_password}</p>

                    <p>Please log in and change your password immediately for security reasons.</p>
                    <br>
                    <p>Regards,<br>CDF Monitoring System</p>
                ";

                if (sendEmail($email, $subject, $body)) {
                    $message = "{$role_label} created successfully and login details sent by email.";
                } else {
                    $message = "{$role_label} created, but email could not be sent. Please notify the user manually.";
                }
            } else {
                $message = "Error creating {$role_label}. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9">
        <div class="form-card">
            <h3>Create Project Lead Account</h3>
            <p>Choose whether the new project lead should work as a field officer or project manager.</p>

            <?php if ($message) echo "<div class='msg'>" . htmlspecialchars($message) . "</div>"; ?>

            <form method="POST">
                <?= csrfInput('create_officer_form') ?>
                Username
                <input type="text" name="username" required>

                Email
                <input type="email" name="email" required>

                District
                <select name="district" required>
                    <option value="">Select District</option>
                    <option>Lilongwe</option>
                    <option>Blantyre</option>
                    <option>Mzuzu</option>
                    <option>Zomba</option>
                </select>

                Role
                <select name="role" required>
                    <option value="field_officer">Field Officer</option>
                    <option value="project_manager">Project Manager</option>
                </select>

                <input type="submit" name="create_officer" value="Create Project Lead Account">
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
