<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

// Ensure the shared project workflow is only used by project lead roles.
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Fetch all created projects so the page stays connected to the officer's work
$projects_stmt = $conn->prepare("
    SELECT id, title, district, status, estimated_budget, contractor_fee
    FROM projects
    WHERE created_by=?
    ORDER BY created_at DESC
");
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

// Handle form submission
if(isset($_POST['add_stage'])){
    $project_id = intval($_POST['project_id']);
    $stage_name = trim($_POST['stage_name']);
    $planned_start = $_POST['planned_start'];
    $planned_end = $_POST['planned_end'];

    if(empty($project_id) || empty($stage_name) || empty($planned_start) || empty($planned_end)){
        $msg = "Please fill in all fields correctly.";
    } else {
        $project_meta_stmt = $conn->prepare("
            SELECT title, status, estimated_budget, contractor_fee
            FROM projects
            WHERE id=? AND created_by=?
        ");
        $project_meta_stmt->bind_param("ii", $project_id, $user_id);
        $project_meta_stmt->execute();
        $project_meta = $project_meta_stmt->get_result()->fetch_assoc();

        if (!$project_meta) {
            $msg = "The selected project was not found.";
        } elseif (!in_array($project_meta['status'], ['approved', 'in_progress'])) {
            $msg = "Only approved or active projects can receive status updates.";
        } else {
            $project_total = $project_meta['estimated_budget'] + $project_meta['contractor_fee'];

            $existing_stage_stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM project_stages
                WHERE project_id=?
            ");
            $existing_stage_stmt->bind_param("i", $project_id);
            $existing_stage_stmt->execute();
            $existing_stages = $existing_stage_stmt->get_result()->fetch_assoc()['cnt'];

            $allocated_budget = $existing_stages > 0 ? round($project_total / ($existing_stages + 1), 2) : $project_total;

            $stmt = $conn->prepare("
                INSERT INTO project_stages (project_id, stage_name, planned_start, planned_end, allocated_budget, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("isssd", $project_id, $stage_name, $planned_start, $planned_end, $allocated_budget);

            if($stmt->execute()){
                logProjectActivity(
                    $conn,
                    $project_id,
                    'status_item_added',
                    $user_id,
                    $_SESSION['role'] ?? 'field_officer',
                    null,
                    'pending',
                    "Status item '{$stage_name}' added with planned dates {$planned_start} to {$planned_end}."
                );

                $msg = "Status item added for " . formatProjectCode($project_id) . ". Allocated Budget: MWK " . number_format($allocated_budget,2);
            } else {
                $msg = "Error adding status item. Please try again.";
            }
        }
    }
}
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
            <h3>Project Status / Progress Tracking</h3>
            <?php if($msg!="") echo "<div class='msg'>$msg</div>"; ?>
            <p>Select any project you created to view its details. Only approved or active projects can receive new status entries.</p>

            <form method="POST">
                <label>Select Project</label>
                <select name="project_id" required onchange="calculateBudget(this.value)">
                    <option value="">-- Select Project --</option>
                    <?php while($row = $projects->fetch_assoc()): ?>
                        <option
                            value="<?php echo $row['id']; ?>"
                            data-code="<?php echo htmlspecialchars(formatProjectCode($row['id'])); ?>"
                            data-title="<?php echo htmlspecialchars($row['title']); ?>"
                            data-district="<?php echo htmlspecialchars($row['district']); ?>"
                            data-status="<?php echo htmlspecialchars($row['status']); ?>"
                            data-total="<?php echo $row['estimated_budget'] + $row['contractor_fee']; ?>">
                            <?php echo htmlspecialchars(formatProjectCode($row['id']) . ' - ' . $row['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <div id="projectSummary" class="form-card" style="margin:15px 0; display:none;">
                    <h4 id="projectSummaryTitle"></h4>
                    <p><strong>District:</strong> <span id="projectSummaryDistrict"></span></p>
                    <p><strong>Current Status:</strong> <span id="projectSummaryStatus"></span></p>
                    <p><strong>Total Budget:</strong> MWK <span id="projectSummaryBudget"></span></p>
                </div>

                <label>Status Item Title</label>
                <input type="text" name="stage_name" required>

                <label>Planned Start Date</label>
                <input type="date" name="planned_start" required>

                <label>Planned End Date</label>
                <input type="date" name="planned_end" required>

                <label>Allocated Budget (Auto-calculated)</label>
                <input type="number" name="allocated_budget" id="allocated_budget" step="0.01" readonly>

                <input type="submit" name="add_stage" id="addStageButton" value="Add Status Item">
            </form>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>

<script>
function calculateBudget(projectId){
    var select = document.querySelector('select[name="project_id"]');
    var option = select.options[select.selectedIndex];
    var total = parseFloat(option.getAttribute('data-total') || 0);
    var summary = document.getElementById('projectSummary');
    var button = document.getElementById('addStageButton');
    var status = option.getAttribute('data-status') || '';
    var readableStatus = status.replace(/_/g, ' ');

    if (!projectId) {
        summary.style.display = 'none';
        document.getElementById('allocated_budget').value = '';
        button.disabled = false;
        return;
    }

    document.getElementById('projectSummaryTitle').textContent =
        option.getAttribute('data-code') + ' - ' + option.getAttribute('data-title');
    document.getElementById('projectSummaryDistrict').textContent =
        option.getAttribute('data-district') || 'N/A';
    document.getElementById('projectSummaryStatus').textContent = readableStatus;
    document.getElementById('projectSummaryBudget').textContent = total.toFixed(2);
    summary.style.display = 'block';

    document.getElementById('allocated_budget').value = total.toFixed(2);

    button.disabled = !(status === 'approved' || status === 'in_progress');
}

calculateBudget(document.querySelector('select[name="project_id"]').value);
</script>
</body>
</html>
