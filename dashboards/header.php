<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();

$name = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
?>

<header class="dashboard-header">
    <img src="../assets/img/logo.jpg" alt="Logo">

    <h2>
        <?php echo panelTitleForRole($role); ?>
    </h2>

    <p>Welcome, <strong><?php echo htmlspecialchars($name); ?></strong></p>
</header>
