<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

$message = "";

if (isset($_SESSION['role'])) {
    header("Location: ../dashboards/home.php");
    exit();
}

if (isset($_POST['login'])) {
    if (!isValidCsrfToken('login_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try logging in again.";
    } else {
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
                session_regenerate_id(true);
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
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CDF Monitoring System | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body class="auth-page">

<main class="auth-shell">
    <section class="auth-panel auth-panel--hero">
        <div class="auth-brand">
            <img src="../assets/img/logo.jpg" alt="CDF Monitoring System logo">
            <div>
                <span class="eyebrow">CDF Monitoring System</span>
            </div>
        </div>

        <h1>Monitor public projects with one connected civic workspace.</h1>
        <p>Track approvals, budgets, community requests, progress updates, and stakeholder collaboration in a cleaner centralized flow.</p>

        <div class="auth-feature-list">
            <div class="auth-feature">
                <strong>Role-Aware Access</strong>
                <span>One login routes each user to the right workspace automatically.</span>
            </div>
            <div class="auth-feature">
                <strong>Budget Visibility</strong>
                <span>Keep project spending, status, and alerts visible in one system.</span>
            </div>
            <div class="auth-feature">
                <strong>Community Accountability</strong>
                <span>Projects, requests, replies, and reports stay linked across roles.</span>
            </div>
        </div>
    </section>

    <section class="auth-panel auth-panel--form">
        <h2>Welcome Back</h2>
        <p>Sign in once and continue from the panel that matches your role.</p>

        <?php if ($message): ?>
            <div class="msg error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfInput('login_form') ?>

            <label for="user_name">Username or Email</label>
            <input id="user_name" type="text" name="user_name" required>

            <label for="user_password">Password</label>
            <input id="user_password" type="password" name="user_password" required>

            <input type="submit" name="login" value="Login">
        </form>

        <div class="auth-links">
            <a href="create_account.php">Create an account</a>
        </div>

        <p class="auth-note">Use your existing account details. The system will open the correct dashboard for your role after login.</p>
    </section>
</main>

</body>
</html>
