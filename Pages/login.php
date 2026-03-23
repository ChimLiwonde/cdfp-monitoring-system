<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

$message = "";

if (isset($_SESSION['role'])) {
    header("Location: ../dashboards/home.php");
    exit();
}

if (isset($_POST['login'])) {

    $username = trim($_POST['user_name']);
    $password = trim($_POST['user_password']);

    /* ============================
       DATABASE USERS LOGIN
    ============================ */
    $stmt = $conn->prepare("
        SELECT id, username, password, role 
        FROM users 
        WHERE username = ? OR email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            header("Location: ../dashboards/home.php");
            exit();

        } else {
            $message = "Invalid username/email or password";
        }

    } else {
        $message = "Invalid username/email or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CDF Monitoring System | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<div class="row">
    <header class="col-12">
        <img src="../assets/img/logo.jpg" alt="CDF Logo">
        <h1>CDF Monitoring System</h1>
    </header>
</div>

<div class="row">
    <div class="col-4"></div>

    <div class="col-4">
        <div class="form-card">

            <h3>Login</h3>
            <p>Login once and the system will open the correct workspace for your role automatically.</p>

            <?php if ($message): ?>
                <div class="msg"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Username or Email</label>
                <input type="text" name="user_name" required>

                <label>Password</label>
                <input type="password" name="user_password" required>

                <input type="submit" name="login" value="Login">
            </form>
              <br>
            <a href="create_account.php">Create Account</a>
        </div>
    </div>

    <div class="col-4"></div>
</div>

</body>
</html>
