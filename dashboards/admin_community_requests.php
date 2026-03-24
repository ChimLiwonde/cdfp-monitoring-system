<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

/* ================= FETCH COMMUNITY REQUESTS ================= */
$status = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'reviewed'];

if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}

$baseSql = "
SELECT 
    cr.id,
    cr.title,
    cr.description,
    cr.area,
    cr.district,
    cr.status,
    cr.review_notes,
    cr.reviewed_at,
    cr.created_at,
    u.username,
    reviewer.username AS reviewed_by_name
FROM community_requests cr
JOIN users u ON u.id = cr.user_id
LEFT JOIN users reviewer ON reviewer.id = cr.reviewed_by
";

$result = false;
$query_error = '';

if ($status === 'all') {
    $result = $conn->query($baseSql . " ORDER BY cr.created_at DESC");
} else {
    $stmt = $conn->prepare($baseSql . " WHERE cr.status = ? ORDER BY cr.created_at DESC");
    if ($stmt) {
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

if (!$result) {
    $query_error = 'Community requests could not be loaded right now.';
}

$heading_map = [
    'all' => 'Community Requests',
    'pending' => 'Pending Community Requests',
    'reviewed' => 'Reviewed Community Requests'
];

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $heading_map[$status] ?></title>
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
            <h3><?= $heading_map[$status] ?></h3>

            <?php if ($success_message): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($query_error): ?>
                <div class="msg error"><?= htmlspecialchars($query_error) ?></div>
            <?php endif; ?>

            <p>
                <a href="admin_community_requests.php?status=all">All</a> |
                <a href="admin_community_requests.php?status=pending">Pending</a> |
                <a href="admin_community_requests.php?status=reviewed">Reviewed</a>
            </p>

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
                        <th>Review Note</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>

                    <?php if (!$result || $result->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" style="text-align:center;color:gray;">
                                No community requests found
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($result && ($row = $result->fetch_assoc())): ?>
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
                                    <?php if (!empty($row['reviewed_at'])): ?>
                                        <br><small><?= date("d M Y, H:i", strtotime($row['reviewed_at'])) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:orange;">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row['review_notes'])): ?>
                                    <?= nl2br(htmlspecialchars($row['review_notes'])) ?>
                                    <?php if (!empty($row['reviewed_by_name'])): ?>
                                        <br><small>By <?= htmlspecialchars($row['reviewed_by_name']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:gray;">No note</span>
                                <?php endif; ?>
                            </td>

                            <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>

                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <a href="review_community_request.php?id=<?= $row['id'] ?>">Review Request</a>
                                <?php else: ?>
                                    <span style="color:gray;">Completed</span>
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
