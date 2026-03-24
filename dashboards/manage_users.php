<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$result = $conn->query("
    SELECT id, username, email, location, role, created_at
    FROM users
    ORDER BY created_at DESC
");

$counts = [
    'total' => 0,
    'admin' => 0,
    'project_leads' => 0,
    'public' => 0
];

$countsResult = $conn->query("
    SELECT role, COUNT(*) AS total
    FROM users
    GROUP BY role
");

while ($countsResult && ($countRow = $countsResult->fetch_assoc())) {
    $role = $countRow['role'];
    $total = (int) $countRow['total'];
    $counts['total'] += $total;

    if ($role === 'admin') {
        $counts['admin'] += $total;
    } elseif ($role === 'public') {
        $counts['public'] += $total;
    } elseif (in_array($role, ['field_officer', 'project_manager'], true)) {
        $counts['project_leads'] += $total;
    }
}

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
$password_reset_info = pullSessionMessage('password_reset_info');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>

    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">User Management</span>
                    <h3>Manage the people behind the workflow</h3>
                    <p>Create accounts, adjust role access, and safely reset credentials without leaving the central admin workspace.</p>
                    <div class="hero-actions">
                        <a href="admin.php" class="back-btn">Back to Dashboard</a>
                        <a href="add_user.php" class="button-link btn-secondary">Add New User</a>
                    </div>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $counts['total'] ?></strong>&nbsp; Total Users</div>
                    <div class="hero-pill"><strong><?= $counts['project_leads'] ?></strong>&nbsp; Project Leads</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Access Summary</span>
                    <h3>Role Distribution</h3>
                </div>
                <p>Use this view to confirm that the right mix of admins, project leads, and citizen accounts is available in the system.</p>
            </div>

            <div class="stats-grid">
                <div class="detail-card">
                    <strong>Total Accounts</strong>
                    <span class="metric-value"><?= $counts['total'] ?></span>
                </div>
                <div class="detail-card">
                    <strong>Admins</strong>
                    <span class="metric-value"><?= $counts['admin'] ?></span>
                </div>
                <div class="detail-card">
                    <strong>Project Leads</strong>
                    <span class="metric-value"><?= $counts['project_leads'] ?></span>
                </div>
                <div class="detail-card">
                    <strong>Public Users</strong>
                    <span class="metric-value"><?= $counts['public'] ?></span>
                </div>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Account List</span>
                    <h3>All Registered Users</h3>
                </div>
                <p>Review each account, update role assignments, or trigger secure reset and delete actions when cleanup is needed.</p>
            </div>

            <?php if ($success_message !== ''): ?>
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($password_reset_info !== ''): ?>
                <div class="msg"><?= htmlspecialchars($password_reset_info) ?></div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="dashboard-table">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>

                    <?php while ($u = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int) $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['location'] ?: 'Not set') ?></td>
                        <td><span class="status-badge <?= htmlspecialchars($u['role'] === 'public' ? 'approved' : ($u['role'] === 'admin' ? 'in_progress' : 'pending')) ?>"><?= htmlspecialchars(formatRoleLabel($u['role'])) ?></span></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?><br><small><?= date('H:i', strtotime($u['created_at'])) ?></small></td>
                        <td>
                            <div class="mini-actions">
                                <a href="edit_user.php?id=<?= (int) $u['id'] ?>" class="action-chip action-chip--soft">Edit</a>
                                <form method="POST" action="reset_user_password.php" class="inline-form">
                                    <?= csrfInput('reset_user_password_form') ?>
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="action-chip">Reset Password</button>
                                </form>
                                <form method="POST" action="delete_user.php" class="inline-form" onsubmit="return confirm('Delete this user?');">
                                    <?= csrfInput('delete_user_form') ?>
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="action-chip action-chip--danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                </table>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
