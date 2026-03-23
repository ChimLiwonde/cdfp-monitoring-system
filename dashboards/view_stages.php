<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer'){
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$project_id = $_GET['project_id'] ?? 0;

// Fetch project info
$project_stmt = $conn->prepare("SELECT * FROM projects WHERE id=? AND created_by=?");
$project_stmt->bind_param("ii", $project_id, $user_id);
$project_stmt->execute();
$project = $project_stmt->get_result()->fetch_assoc();
if(!$project){ header("Location: field_officer.php"); exit(); }

// Fetch all stages for this project
$stages_stmt = $conn->prepare("SELECT * FROM project_stages WHERE project_id=? ORDER BY planned_start ASC");
$stages_stmt->bind_param("i", $project_id);
$stages_stmt->execute();
$stages = $stages_stmt->get_result();

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Status</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>
<?php include "header.php"; ?>
<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>
    <div class="col-9">
        <div class="form-card">
            <h3>Project Status for <?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?></h3>

            <?php if ($success_message): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <table class="dashboard-table">
                <tr>
                    <th>Status Item</th>
                    <th>Planned Start</th>
                    <th>Planned End</th>
                    <th>Actual Start</th>
                    <th>Actual End</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Overdue / Over Budget</th>
                    <th>Action</th>
                </tr>

                <?php while($row = $stages->fetch_assoc()): 
                    $overdue = ($row['actual_end'] && $row['actual_end'] > $row['planned_end']) ? 'Yes' : 'No';
                    $overbudget = ($row['spent_budget'] > $row['allocated_budget']) ? 'Yes' : 'No';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['stage_name']) ?></td>
                    <td><?= htmlspecialchars($row['planned_start']) ?></td>
                    <td><?= htmlspecialchars($row['planned_end']) ?></td>
                    <td><?= htmlspecialchars($row['actual_start'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['actual_end'] ?? '-') ?></td>
                    <td><?= "Allocated: ".$row['allocated_budget']." / Spent: ".$row['spent_budget'] ?></td>
                    <td><?= htmlspecialchars(formatStatusLabel($row['status'])) ?></td>
                    <td><?= $overdue=='Yes' || $overbudget=='Yes' ? "Overdue: $overdue, OverBudget: $overbudget" : "No" ?></td>
                    <td><a href="update_stage.php?id=<?php echo $row['id']; ?>">Update Status</a></td>
                </tr>
                <?php endwhile; ?>

            </table>

            <div style="text-align:center; margin-top:10px;">
                <a href="field_officer.php" class="back-btn">Back to Project Panel</a>
            </div>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>
</body>
</html>
