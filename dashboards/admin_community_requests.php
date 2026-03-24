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

$counts = [
    'all' => 0,
    'pending' => 0,
    'reviewed' => 0
];

$countsResult = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM community_requests
    GROUP BY status
");

while ($countsResult && ($countRow = $countsResult->fetch_assoc())) {
    $counts[$countRow['status']] = (int) $countRow['total'];
}

$counts['all'] = $counts['pending'] + $counts['reviewed'];

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $heading_map[$status] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Community Review Queue</span>
                    <h3><?= $heading_map[$status] ?></h3>
                    <p>Review citizen submissions from one central queue, leave clear notes, and keep a clean record of what has already been handled.</p>
                    <div class="hero-actions">
                        <a href="admin.php" class="back-btn">Back to Dashboard</a>
                    </div>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $counts['pending'] ?></strong>&nbsp; Pending</div>
                    <div class="hero-pill"><strong><?= $counts['reviewed'] ?></strong>&nbsp; Reviewed</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Queue Summary</span>
                    <h3>Request Overview</h3>
                </div>
                <p>Open a filtered view to focus on new requests or audit the notes already sent back to citizens.</p>
            </div>

            <?php if ($success_message): ?>
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($query_error): ?>
                <div class="msg error"><?= htmlspecialchars($query_error) ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="detail-card">
                    <strong>Total Requests</strong>
                    <span class="metric-value"><?= $counts['all'] ?></span>
                </div>
                <div class="detail-card">
                    <strong>Pending Review</strong>
                    <span class="metric-value"><?= $counts['pending'] ?></span>
                </div>
                <div class="detail-card">
                    <strong>Reviewed</strong>
                    <span class="metric-value"><?= $counts['reviewed'] ?></span>
                </div>
            </div>

            <div class="toolbar space-top-md">
                <a href="admin_community_requests.php?status=all" class="toolbar-link <?= $status === 'all' ? 'active' : '' ?>">All Requests</a>
                <a href="admin_community_requests.php?status=pending" class="toolbar-link <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
                <a href="admin_community_requests.php?status=reviewed" class="toolbar-link <?= $status === 'reviewed' ? 'active' : '' ?>">Reviewed</a>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Review Table</span>
                    <h3><?= $heading_map[$status] ?></h3>
                </div>
                <p>Each request keeps its citizen, location, status, review note, and action history together.</p>
            </div>

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
                            <td>#<?= (int) $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['area']) ?></td>
                            <td><?= htmlspecialchars($row['district']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['description'])) ?></td>

                            <td>
                                <?php if ($row['status'] === 'reviewed'): ?>
                                    <span class="status-badge approved">Reviewed</span>
                                    <?php if (!empty($row['reviewed_at'])): ?>
                                        <br><small><?= date("d M Y, H:i", strtotime($row['reviewed_at'])) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge pending">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row['review_notes'])): ?>
                                    <?= nl2br(htmlspecialchars($row['review_notes'])) ?>
                                    <?php if (!empty($row['reviewed_by_name'])): ?>
                                        <br><small>By <?= htmlspecialchars($row['reviewed_by_name']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="muted">No note</span>
                                <?php endif; ?>
                            </td>

                            <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>

                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <a href="review_community_request.php?id=<?= (int) $row['id'] ?>" class="action-chip action-chip--primary">Review Request</a>
                                <?php else: ?>
                                    <span class="action-chip action-chip--soft">Completed</span>
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
