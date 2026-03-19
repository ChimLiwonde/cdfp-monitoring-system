<?php
require "../config/db.php";

$message = "";

if (isset($_POST['register'])) {

    $username  = $_POST['user_name'];
    $email     = $_POST['user_email'];
    $location  = $_POST['location'];
    $password  = password_hash($_POST['user_password'], PASSWORD_DEFAULT);

    // Check email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "Email already exists!";
    } else {

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, location) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $password, $location);

        if ($stmt->execute()) {
            header("Location: login.php");
            exit();
        } else {
            $message = "Registration failed!";
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

        <?php if($message!="") echo "<div class='msg'>$message</div>"; ?>

        <form method="POST">

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
