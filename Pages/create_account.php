<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

$message = "";

if (isset($_POST['register'])) {
    if (!isValidCsrfToken('create_account_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try registering again.";
    } else {

        $username = trim($_POST['user_name'] ?? '');
        $email = trim($_POST['user_email'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $rawPassword = $_POST['user_password'] ?? '';

        if ($username === '' || $email === '' || $location === '') {
            $message = "Please fill in all required fields.";
        } elseif (strlen($rawPassword) < 8) {
            $message = "Password must be at least 8 characters long.";
        } else {
            $password = password_hash($rawPassword, PASSWORD_DEFAULT);

            // Check email exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $message = "Email already exists!";
            } else {

                $role = 'public';
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, location, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $password, $location, $role);

                if ($stmt->execute()) {
                    header("Location: login.php");
                    exit();
                } else {
                    $message = "Registration failed!";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Account</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css"/>
</head>
<body class="auth-page">

<main class="auth-shell">
    <section class="auth-panel auth-panel--hero">
        <div class="auth-brand">
            <img src="../assets/img/logo.jpg" alt="CDF Monitoring System logo">
            <div>
                <span class="eyebrow">Citizen Registration</span>
            </div>
        </div>

        <h1>Create your account and start following projects in your district.</h1>
        <p>Public users can view local projects, submit community requests, read admin replies, and stay informed through notifications.</p>

        <div class="auth-feature-list">
            <div class="auth-feature">
                <strong>District-Based Visibility</strong>
                <span>See project activity and requests connected to your location.</span>
            </div>
            <div class="auth-feature">
                <strong>Community Participation</strong>
                <span>Share requests, feedback, and reactions without leaving the platform.</span>
            </div>
            <div class="auth-feature">
                <strong>Secure Access</strong>
                <span>Accounts are created with protected login and role-based access.</span>
            </div>
        </div>
    </section>

    <section class="auth-panel auth-panel--form">
        <h2>Create Your Account</h2>
        <p>Register as a citizen user to access district updates, requests, and replies.</p>

        <?php if ($message !== ""): ?>
            <div class="msg error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfInput('create_account_form') ?>

            <label for="user_name">Username</label>
            <input id="user_name" type="text" name="user_name" required>

            <label for="user_email">Email</label>
            <input id="user_email" type="email" name="user_email" required>

            <label for="location">Location / District</label>
            <select id="location" name="location" required>
                <option value="">-- Select District --</option>
                <option value="Lilongwe">Lilongwe</option>
                <option value="Blantyre">Blantyre</option>
                <option value="Mzuzu">Mzuzu</option>
                <option value="Zomba">Zomba</option>
                <option value="Kasungu">Kasungu</option>
                <option value="Mangochi">Mangochi</option>
                <option value="Salima">Salima</option>
                <option value="Mchinji">Mchinji</option>
                <option value="Dedza">Dedza</option>
                <option value="Ntcheu">Ntcheu</option>
                <option value="Karonga">Karonga</option>
                <option value="Mzimba">Mzimba</option>
                <option value="Balaka">Balaka</option>
            </select>

            <label for="user_password">Password</label>
            <input id="user_password" type="password" name="user_password" required>

            <input type="submit" name="register" value="Create Account">
        </form>

        <div class="auth-links">
            <a href="login.php">Back to login</a>
        </div>

        <p class="auth-note">New accounts are created as public users. Admin, field officer, and project manager roles are managed inside the system.</p>
    </section>
</main>

</body>
</html>
