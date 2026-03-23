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

// Fetch all stages for this project, including assignment summary
$stages_stmt = $conn->prepare("
    SELECT
        ps.*,
        COALESCE(
            GROUP_CONCAT(CONCAT(ptm.full_name, ' (', ptm.role_title, ')') ORDER BY ptm.full_name SEPARATOR ', '),
            ''
        ) AS assigned_members
    FROM project_stages ps
    LEFT JOIN project_stage_assignments psa ON psa.stage_id = ps.id
    LEFT JOIN project_team_members ptm ON ptm.id = psa.team_member_id
    WHERE ps.project_id=?
    GROUP BY ps.id
    ORDER BY ps.planned_start ASC, ps.id ASC
");
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
                    <th>Assigned To</th>
                    <th>Planned Start</th>
                    <th>Planned End</th>
                    <th>Actual Start</th>
                    <th>Actual End</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Overdue / Over Budget</th>
                    <th>Action</th>
                </tr>

                <?php if ($stages->num_rows === 0): ?>
                <tr>
                    <td colspan="10" style="text-align:center;color:gray;">No status items added yet.</td>
                </tr>
                <?php endif; ?>

                <?php while($row = $stages->fetch_assoc()): 
                    $overdue = ($row['actual_end'] && $row['actual_end'] > $row['planned_end']) ? 'Yes' : 'No';
                    $overbudget = ($row['spent_budget'] > $row['allocated_budget']) ? 'Yes' : 'No';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['stage_name']) ?></td>
                    <td><?= htmlspecialchars($row['assigned_members'] ?: 'Not assigned') ?></td>
                    <td><?= htmlspecialchars($row['planned_start']) ?></td>
                    <td><?= htmlspecialchars($row['planned_end']) ?></td>
                    <td><?= htmlspecialchars($row['actual_start'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['actual_end'] ?? '-') ?></td>
                    <td>
                        Allocated: <?= number_format((float) $row['allocated_budget'], 2) ?><br>
                        Spent: <?= number_format((float) $row['spent_budget'], 2) ?>
                    </td>
                    <td><?= htmlspecialchars(formatStatusLabel($row['status'])) ?></td>
                    <td><?= $overdue=='Yes' || $overbudget=='Yes' ? "Overdue: $overdue, OverBudget: $overbudget" : "No" ?></td>
                    <td>
                        <a href="update_stage.php?id=<?= $row['id'] ?>">Update Status</a><br>
                        <a href="task_assignments.php?project_id=<?= $project['id'] ?>&stage_id=<?= $row['id'] ?>">Assign Task</a><br>
                        <a href="project_expenses.php?project_id=<?= $project['id'] ?>&stage_id=<?= $row['id'] ?>">Log Expense</a>
                    </td>
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
