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

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
$password_reset_info = pullSessionMessage('password_reset_info');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>

    <div class="col-9">
        <div class="form-card">
            <h3>User Management</h3>

            <?php if ($success_message !== ''): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($password_reset_info !== ''): ?>
                <div class="msg"><?= htmlspecialchars($password_reset_info) ?></div>
            <?php endif; ?>

            <a href="add_user.php" class="btn">+ Add New User</a>

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
                        <td><?= htmlspecialchars($u['location']) ?></td>
                        <td><strong><?= htmlspecialchars(formatRoleLabel($u['role'])) ?></strong></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <a href="edit_user.php?id=<?= (int) $u['id'] ?>">Edit</a> |
                            <form method="POST" action="reset_user_password.php" style="display:inline;">
                                <?= csrfInput('reset_user_password_form') ?>
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" style="background:none;border:none;color:#0b66c3;padding:0;cursor:pointer;">Reset Password</button>
                            </form>
                            |
                            <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                <?= csrfInput('delete_user_form') ?>
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" style="background:none;border:none;color:red;padding:0;cursor:pointer;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                </table>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-footer">(c) 2025 CDF Monitoring System</div>
</body>
</html>
