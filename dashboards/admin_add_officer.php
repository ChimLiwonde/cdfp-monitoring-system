<?php
session_start();
require "../config/db.php";
require "../config/mail.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$message = "";

if (isset($_POST['create_officer'])) {

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $location = trim($_POST['district']);

    // Generate temporary password
    $temp_password = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 8);
    $hashed = password_hash($temp_password, PASSWORD_DEFAULT);

    // Insert officer
    $role = 'field_officer';

      $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, location, role)
        VALUES (?, ?, ?, ?, ?)
        ");

      if (!$stmt) {
      die("Prepare failed: " . $conn->error);
      }

      $stmt->bind_param(
      "sssss",
      $username,
      $email,
      $hashed,
      $location,
      $role
      );

      if ($stmt->execute()) {

    // Email content
    $subject = "CDF Monitoring System – Field Officer Account";
    $body = "
        <h3>Welcome to CDF Monitoring System</h3>
        <p>Your Field Officer account has been created successfully.</p>

        <p><b>Login Details</b></p>
        <p><b>Username:</b> {$username}</p>
        <p><b>Temporary Password:</b> {$temp_password}</p>

        <p>Please log in and change your password immediately for security reasons.</p>
        <br>
        <p>Regards,<br>CDF Monitoring System</p>
    ";

    // Send email and check result
    if (sendEmail($email, $subject, $body)) {
        $message = "Field Officer created successfully and login details sent by email.";
    } else {
        $message = "Field Officer created, but email could not be sent. Please notify the officer manually.";
    }

    } else {
    $message = "Error creating Field Officer. Please try again.";
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

    <!-- SIDEBAR -->
    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <!-- MAIN CONTENT -->
    <div class="col-9">
        <div class="form-card">
    <h3>Add Field Officer</h3>

    <?php if($message) echo "<div class='msg'>$message</div>"; ?>

    <form method="POST">
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

        <input type="submit" name="create_officer" value="Create Field Officer">
    </form>
    </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
