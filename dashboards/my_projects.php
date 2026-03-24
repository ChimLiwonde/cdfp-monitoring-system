<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* FETCH PROJECTS */
$stmt = $conn->prepare("
    SELECT p.id, p.title, p.district,
           IFNULL(NULLIF(status,''),'pending') AS project_status,
           p.estimated_budget, p.contractor_fee, p.location,
           p.review_notes, p.reviewed_at,
           reviewer.username AS reviewed_by_name
    FROM projects p
    LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by
    WHERE p.created_by = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Projects</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <style>
        .status-pending{color:#f57c00;font-weight:bold;}
        .status-approved{color:#2e7d32;font-weight:bold;}
        .status-in_progress{color:#0288d1;font-weight:bold;}
        .status-completed{color:#1565c0;font-weight:bold;}
        .status-denied{color:#d32f2f;font-weight:bold;}
    </style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "menu.php"; ?></div>
<div class="col-9">

<div class="form-card">
<h3>Your Projects</h3>

<?php if ($success_message !== ''): ?>
    <div class="msg"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<?php if ($error_message !== ''): ?>
    <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<table class="dashboard-table">
<tr>
    <th>Project ID</th>
    <th>Project</th>
    <th>District</th>
    <th>Status</th>
    <th>Review</th>
    <th>Total Cost (MWK)</th>
    <th>Action</th>
</tr>

<?php
while ($row = $result->fetch_assoc()) {

    $project_id = $row['id'];
    $final_status = $row['project_status'];

    /* CHECK STAGES */
    if ($final_status !== 'denied') {
        $st = $conn->query("
            SELECT COUNT(*) total,
                   SUM(status='completed') completed,
                   SUM(status='in_progress') in_progress
            FROM project_stages WHERE project_id=$project_id
        ")->fetch_assoc();

        if ($st['total'] > 0) {
            if ($st['completed'] == $st['total']) {
                $final_status = 'completed';
            } elseif ($st['in_progress'] > 0 || $st['completed'] > 0) {
                $final_status = 'in_progress';
            }
        }
    }

    /* STATUS CLASS */
    $statusClass = "status-$final_status";

    /* TOTAL COST LOGIC */
    if ($final_status === 'completed') {
        $display_cost = 0;
    } else {
        $display_cost = $row['estimated_budget'] + $row['contractor_fee'];
    }
?>
<tr>
    <td><?= formatProjectCode($project_id) ?></td>
    <td><?= htmlspecialchars($row['title']) ?></td>
    <td><?= htmlspecialchars($row['district']) ?></td>
    <td class="<?= $statusClass ?>"><?= ucfirst(str_replace('_',' ',$final_status)) ?></td>
    <td>
        <?php if (!empty($row['review_notes']) || !empty($row['reviewed_at'])): ?>
            <?php if (!empty($row['reviewed_at'])): ?>
                <small><?= date('d M Y', strtotime($row['reviewed_at'])) ?></small><br>
            <?php endif; ?>
            <?php if (!empty($row['reviewed_by_name'])): ?>
                <small>By <?= htmlspecialchars($row['reviewed_by_name']) ?></small><br>
            <?php endif; ?>
            <?= htmlspecialchars($row['review_notes'] ?: 'Reviewed without note') ?>
        <?php else: ?>
            <span style="color:gray;">Awaiting review</span>
        <?php endif; ?>
    </td>
    <td><?= number_format($display_cost,2) ?></td>
    <td>
        <?php
        if ($final_status === 'pending') {
            echo "<a href='edit_project.php?id=$project_id'>Edit</a> | <a href='view_project_details.php?id=$project_id'>View Details</a>";
        }
        elseif (in_array($final_status,['approved','in_progress'])) {
            echo "<a href='view_stages.php?project_id=$project_id'>Manage Status</a> | <a href='view_project_details.php?id=$project_id'>View Details</a>";
        }
        elseif (in_array($final_status, ['completed', 'denied'], true)) {
            echo "<a href='view_project_details.php?id=$project_id'>View Details</a>";
        }
        else {
            echo "<span style='color:gray;'>Locked</span>";
        }
        ?>
    </td>
</tr>
<?php } ?>

</table>
</div>
</div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
