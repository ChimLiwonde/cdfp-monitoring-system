<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$stage_id = intval($_GET['id'] ?? 0);

if ($stage_id <= 0) {
    header("Location: field_officer.php");
    exit();
}

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

if (isset($_POST['update_stage'])) {
    if (!isValidCsrfToken('update_stage_form', $_POST['_csrf_token'] ?? '')) {
        $msg = "Your session expired. Please submit the update again.";
    } else {
        $actual_start = !empty($_POST['actual_start']) ? $_POST['actual_start'] : null;
        $actual_end   = !empty($_POST['actual_end']) ? $_POST['actual_end'] : null;
        $status       = $_POST['status'];
        $notes        = trim($_POST['notes']);
        $previous_stage_status = $stage['status'];

        $update = $conn->prepare("
            UPDATE project_stages
            SET actual_start = ?,
                actual_end = ?,
                status = ?,
                notes = ?
            WHERE id = ?
        ");
        $update->bind_param(
            "ssssi",
            $actual_start,
            $actual_end,
            $status,
            $notes,
            $stage_id
        );

        if ($update->execute()) {
            $project_id = (int) $stage['project_id'];

            $currentProjectStmt = $conn->prepare("SELECT status FROM projects WHERE id = ?");
            $currentProjectStmt->bind_param("i", $project_id);
            $currentProjectStmt->execute();
            $current_project_status = $currentProjectStmt->get_result()->fetch_assoc()['status'] ?? 'approved';

            logProjectActivity(
                $conn,
                $project_id,
                'stage_status_changed',
                $user_id,
                $_SESSION['role'] ?? 'field_officer',
                $previous_stage_status,
                $status,
                "Status item '{$stage['stage_name']}' updated."
            );

            $new_project_status = determineProjectStatusFromStages($conn, $project_id, $current_project_status);
            if ($new_project_status !== $current_project_status) {
                $projectUpdate = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
                $projectUpdate->bind_param("si", $new_project_status, $project_id);
                $projectUpdate->execute();

                logProjectActivity(
                    $conn,
                    $project_id,
                    'project_status_changed',
                    $user_id,
                    $_SESSION['role'] ?? 'field_officer',
                    $current_project_status,
                    $new_project_status,
                    "Project lifecycle updated after status item review."
                );
            }

            $_SESSION['success_message'] = "Status item updated for " . formatProjectCode($project_id) . ".";
            header("Location: view_stages.php?project_id=$project_id");
            exit();
        }

        $msg = "Failed to update status item.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Project Status</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>

    <div class="col-9">
        <div class="form-card">
            <h3>Update Project Status</h3>

            <p><strong>Project:</strong> <?= formatProjectCode($stage['project_id']) ?> - <?= htmlspecialchars($stage['project_title']) ?></p>
            <p><strong>Status Item:</strong> <?= htmlspecialchars($stage['stage_name']) ?></p>
            <p><strong>Spent Budget:</strong> MWK <?= number_format((float) $stage['spent_budget'], 2) ?> <small>(managed from Project Expenses)</small></p>

            <?php if ($msg): ?>
                <div class="msg"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfInput('update_stage_form') ?>
                Actual Start Date
                <input type="date" name="actual_start" value="<?= htmlspecialchars($stage['actual_start'] ?? '') ?>">

                Actual End Date
                <input type="date" name="actual_end" value="<?= htmlspecialchars($stage['actual_end'] ?? '') ?>">

                Status
                <select name="status" required>
                    <option value="pending" <?= $stage['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= $stage['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $stage['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>

                Notes
                <textarea name="notes" style="width:100%;min-height:120px;padding:12px;border:1px solid #90caf9;border-radius:8px;"><?= htmlspecialchars($stage['notes'] ?? '') ?></textarea>

                <br>
                <input type="submit" name="update_stage" value="Update Status Item">
            </form>

            <br>
            <a href="view_stages.php?project_id=<?= $stage['project_id'] ?>">Back to Project Status</a>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
