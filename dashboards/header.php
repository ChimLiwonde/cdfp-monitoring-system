<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();

$name = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
$roleLabel = formatRoleLabel($role);
?>

<header class="dashboard-header">
    <div class="dashboard-header__brand">
        <img src="../assets/img/logo.jpg" alt="CDF Monitoring System logo">
    </div>

    <div class="dashboard-header__copy">
        <span class="eyebrow"><?php echo htmlspecialchars($roleLabel); ?> Workspace</span>
        <h2><?php echo panelTitleForRole($role); ?></h2>
        <p>Welcome back, <strong><?php echo htmlspecialchars($name); ?></strong>. Track projects, budgets, requests, and collaboration from one connected system.</p>
    </div>

    <div class="dashboard-header__meta">
        <span class="dashboard-chip"><?php echo date('d M Y'); ?></span>
        <span class="dashboard-chip">Shared Civic Workflow</span>
    </div>
</header>
