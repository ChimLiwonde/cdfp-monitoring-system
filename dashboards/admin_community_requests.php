<?php
session_start();
require "../config/db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ================= FETCH COMMUNITY REQUESTS ================= */
$sql = "
SELECT 
    cr.id,
    cr.title,
    cr.description,
    cr.area,
    cr.district,
    cr.status,
    cr.created_at,
    u.username
FROM community_requests cr
JOIN users u ON u.id = cr.user_id
ORDER BY cr.created_at DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("SQL ERROR: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Community Requests</title>
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
            <h3>Community Requests</h3>

            <div class="table-wrap">
                <table class="dashboard-table">
                    <tr>
                        <th>ID</th>
                        <th>Citizen</th>
                        <th>Title</th>
                        <th>Area</th>
                        <th>District</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;color:gray;">
                                No community requests found
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['area']) ?></td>
                            <td><?= htmlspecialchars($row['district']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['description'])) ?></td>

                            <td>
                                <?php if ($row['status'] === 'reviewed'): ?>
                                    <span style="color:green;font-weight:bold;">Reviewed</span>
                                <?php else: ?>
                                    <span style="color:orange;">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>

                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <a href="review_community_request.php?id=<?= $row['id'] ?>"
                                       onclick="return confirm('Mark this request as reviewed?');">
                                        Mark Reviewed
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
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
