<?php
session_start();
require "../config/db.php";

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer'){
    header("Location: ../login.php");
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Stages</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>
<?php include "header.php"; ?>
<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>
    <div class="col-9">
        <div class="form-card">
            <h3>Stages for Project: <?php echo $project['title']; ?></h3>

            <table class="dashboard-table">
                <tr>
                    <th>Stage Name</th>
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
                    <td><?php echo $row['stage_name']; ?></td>
                    <td><?php echo $row['planned_start']; ?></td>
                    <td><?php echo $row['planned_end']; ?></td>
                    <td><?php echo $row['actual_start'] ?? '-'; ?></td>
                    <td><?php echo $row['actual_end'] ?? '-'; ?></td>
                    <td><?php echo "Allocated: ".$row['allocated_budget']." / Spent: ".$row['spent_budget']; ?></td>
                    <td><?php echo ucfirst(str_replace('_',' ',$row['status'])); ?></td>
                    <td><?php echo $overdue=='Yes' || $overbudget=='Yes' ? "Overdue: $overdue, OverBudget: $overbudget" : "No"; ?></td>
                    <td><a href="update_stage.php?id=<?php echo $row['id']; ?>">Update</a></td>
                </tr>
                <?php endwhile; ?>

            </table>

            <div style="text-align:center; margin-top:10px;">
                <a href="field_officer.php" class="back-btn">← Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>
</body>
</html>
