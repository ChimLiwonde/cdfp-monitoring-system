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
    <link rel="stylesheet" href="../assets/css/flexible.css"/>
</head>
<body>

<div class='row'>
    <header class='col-12'>
        <img src="../assets/img/logo.jpg" style="height:60px;"><br>
        <h1>CDF Monitoring System</h1>
    </header>
</div>

<div class='row'>
    <div class='col-4'></div>
    <div class='col-4'>
    <div class="form-card">

        <h3>Create Your Account</h3>

        <?php if($message!="") echo "<div class='msg'>" . htmlspecialchars($message) . "</div>"; ?>

        <form method="POST">
            <?= csrfInput('create_account_form') ?>

            Username
            <input type="text" name="user_name" required>

            Email
            <input type="email" name="user_email" required>

            Location / District
             <select name="location" required>
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

            Password
            <input type="password" name="user_password" required>

            <input type="submit" name="register" value="Create Account">
        </form>

        <br>
        <a href="login.php">Login</a>

    </div>
</div>
    <div class='col-4'></div>
</div>

</body>
</html>
