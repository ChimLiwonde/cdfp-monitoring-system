<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* FETCH PROJECTS */
$stmt = $conn->prepare("
    SELECT id, title, district,
           IFNULL(NULLIF(status,''),'pending') AS project_status,
           estimated_budget, contractor_fee, location
    FROM projects
    WHERE created_by = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
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

<table class="dashboard-table">
<tr>
    <th>Project</th>
    <th>District</th>
    <th>Status</th>
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
    <td><?= htmlspecialchars($row['title']) ?></td>
    <td><?= htmlspecialchars($row['district']) ?></td>
    <td class="<?= $statusClass ?>"><?= ucfirst(str_replace('_',' ',$final_status)) ?></td>
    <td><?= number_format($display_cost,2) ?></td>
    <td>
        <?php
        if ($final_status === 'pending') {
            echo "<a href='edit_project.php?id=$project_id'>Edit</a>";
        }
        elseif (in_array($final_status,['approved','in_progress'])) {
            echo "<a href='view_stages.php?project_id=$project_id'>Manage Stages</a>";
        }
        elseif ($final_status === 'completed') {
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
