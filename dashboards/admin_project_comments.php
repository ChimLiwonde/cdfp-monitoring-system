<?php
session_start();
require "../config/db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ================= FETCH PROJECT COMMENTS ================= */
$sql = "
SELECT 
    pc.id,
    pc.comment,
    pc.admin_reply,
    pc.created_at,
    p.title AS project_title,
    u.username
FROM project_comments pc
JOIN users u ON u.id = pc.user_id
JOIN projects p ON p.id = pc.project_id
ORDER BY pc.created_at DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("SQL ERROR: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Comments</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9">
        <div class="form-card">
            <h3>Project Comments & Admin Replies</h3>

            <div class="table-wrap">
                <table class="dashboard-table">
                    <tr>
                        <th>ID</th>
                        <th>Project</th>
                        <th>User</th>
                        <th>Comment</th>
                        <th>Admin Reply</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:gray;">
                                No comments found
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['project_title']) ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['comment'])) ?></td>
                            <td>
                                <?= $row['admin_reply']
                                    ? nl2br(htmlspecialchars($row['admin_reply']))
                                    : '<span style="color:gray;">No reply yet</span>' ?>
                            </td>
                            <td>
                                <a href="admin_reply_comment.php?id=<?= $row['id'] ?>">
                                    Reply
                                </a>
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
