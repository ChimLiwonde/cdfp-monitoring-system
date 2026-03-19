<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$name = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
?>

<header class="dashboard-header">
    <img src="../assets/img/logo.jpg" alt="Logo">

    <h2>
        <?php echo ucfirst(str_replace('_', ' ', $role)); ?> Panel
    </h2>

    <p>Welcome, <strong><?php echo htmlspecialchars($name); ?></strong></p>
</header>
