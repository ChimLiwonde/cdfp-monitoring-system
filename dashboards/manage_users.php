<?php
session_start();
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

                    <?php while($u = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['location']) ?></td>
                        <td><strong><?= ucfirst(str_replace('_',' ', $u['role'])) ?></strong></td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <a href="edit_user.php?id=<?= $u['id'] ?>">Edit</a> |
                            <a href="reset_user_password.php?id=<?= $u['id'] ?>">Reset Password</a> |
                            <a href="delete_user.php?id=<?= $u['id'] ?>"
                               onclick="return confirm('Delete this user?');"
                               style="color:red;">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                </table>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-footer">© 2025 CDF Monitoring System</div>
</body>
</html>
