<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

/* ===========================
   SECURITY CHECK
=========================== */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$msg = "";
$user_id = $_SESSION['user_id'];

/* ===========================
   CHANGE PASSWORD LOGIC
=========================== */
if (isset($_POST['change_password'])) {
    if (!isValidCsrfToken('officer_settings_form', $_POST['_csrf_token'] ?? '')) {
        $msg = "Your session expired. Please try updating your password again.";
    } else {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $msg = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $msg = "Use a password with at least 8 characters.";
        } else {
            /* FETCH CURRENT PASSWORD */
            $stmt = $conn->prepare("
                SELECT password FROM users WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            $stmt->fetch();
            $stmt->close();

            /* VERIFY OLD PASSWORD */
            if (!password_verify($old_password, $hashed_password)) {
                $msg = "Old password is incorrect.";
            } else {

                /* UPDATE PASSWORD */
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare("
                    UPDATE users SET password = ? WHERE id = ?
                ");
                $update->bind_param("si", $new_hashed, $user_id);
                $update->execute();

                $msg = "Password changed successfully.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <div class="col-3">
        <?php include "menu.php"; ?>
    </div>

    <div class="col-9">
        <div class="form-card">
            <h3>Change Password</h3>

            <?php if ($msg != "") echo "<div class='msg'>" . htmlspecialchars($msg) . "</div>"; ?>

            <form method="POST">
                <?= csrfInput('officer_settings_form') ?>

                Current Password
                <input type="password" name="old_password" required>

                New Password
                <input type="password" name="new_password" required>

                Confirm New Password
                <input type="password" name="confirm_password" required>

                <input type="submit" name="change_password" value="Update Password">
            </form>
        </div>
    </div>

</div>

<?php include "footer.php"; ?>

</body>
</html>
