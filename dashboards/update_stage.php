<?php
session_start();
require "../config/db.php";

/* SHOW ERRORS (DEV MODE) */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* AUTH */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$stage_id = intval($_GET['id'] ?? 0);

if (!$stage_id) {
    header("Location: field_officer.php");
    exit();
}

/* FETCH STAGE + PROJECT OWNERSHIP */
$stmt = $conn->prepare("
    SELECT ps.*, p.title AS project_title, p.id AS project_id
    FROM project_stages ps
    JOIN projects p ON ps.project_id = p.id
    WHERE ps.id = ? AND p.created_by = ?
");
$stmt->bind_param("ii", $stage_id, $user_id);
$stmt->execute();
$stage = $stmt->get_result()->fetch_assoc();

if (!$stage) {
    header("Location: field_officer.php");
    exit();
}

$msg = "";

/* UPDATE STAGE */
if (isset($_POST['update_stage'])) {

    $actual_start = !empty($_POST['actual_start']) ? $_POST['actual_start'] : NULL;
    $actual_end   = !empty($_POST['actual_end'])   ? $_POST['actual_end']   : NULL;
    $spent_budget = floatval($_POST['spent_budget']);
    $status       = $_POST['status'];
    $notes        = $_POST['notes'];

    $update = $conn->prepare("
        UPDATE project_stages
        SET actual_start = ?,
            actual_end   = ?,
            spent_budget = ?,
            status       = ?,
            notes        = ?
        WHERE id = ?
    ");

    /* CORRECT TYPES */
    $update->bind_param(
        "ssdssi",
        $actual_start,
        $actual_end,
        $spent_budget,
        $status,
        $notes,
        $stage_id
    );

    if ($update->execute()) {

        /* UPDATE PROJECT STATUS BASED ON STAGES */
        $project_id = $stage['project_id'];

        $total = $conn->query("
            SELECT COUNT(*) FROM project_stages
            WHERE project_id = $project_id
        ")->fetch_row()[0];

        $completed = $conn->query("
            SELECT COUNT(*) FROM project_stages
            WHERE project_id = $project_id AND status = 'completed'
        ")->fetch_row()[0];

        if ($completed == $total && $total > 0) {
            $conn->query("UPDATE projects SET status='completed' WHERE id=$project_id");
        } elseif ($completed > 0) {
            $conn->query("UPDATE projects SET status='in_progress' WHERE id=$project_id");
        }

        header("Location: view_stages.php?project_id=$project_id");
        exit();
    } else {
        $msg = "Failed to update stage.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Stage</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>

    <div class="col-9">
        <div class="form-card">
            <h3>Update Stage</h3>

            <p><strong>Project:</strong> <?= htmlspecialchars($stage['project_title']) ?></p>
            <p><strong>Stage:</strong> <?= htmlspecialchars($stage['stage_name']) ?></p>

            <?php if ($msg): ?>
                <div class="msg"><?= $msg ?></div>
            <?php endif; ?>

            <form method="POST">

                Actual Start Date
                <input type="date" name="actual_start" value="<?= $stage['actual_start'] ?>">

                Actual End Date
                <input type="date" name="actual_end" value="<?= $stage['actual_end'] ?>">

                Spent Budget
                <input type="number" step="0.01" name="spent_budget"
                       value="<?= $stage['spent_budget'] ?>" required>

                Status
                <select name="status" required>
                    <option value="pending" <?= $stage['status']=='pending'?'selected':'' ?>>Pending</option>
                    <option value="in_progress" <?= $stage['status']=='in_progress'?'selected':'' ?>>In Progress</option>
                    <option value="completed" <?= $stage['status']=='completed'?'selected':'' ?>>Completed</option>
                </select>

                Notes
                <textarea name="notes"><?= $stage['notes'] ?></textarea>

                <br>
                <input type="submit" name="update_stage" value="Update Stage">
            </form>

            <br>
            <a href="view_stages.php?project_id=<?= $stage['project_id'] ?>">
                ← Back to Stages
            </a>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
