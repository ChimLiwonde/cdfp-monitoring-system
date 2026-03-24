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
$selected_project_id = (int) ($_POST['project_id'] ?? 0);

// Fetch all created projects so the page stays connected to the officer's work
$projects_stmt = $conn->prepare("
    SELECT
        p.id,
        p.title,
        p.district,
        p.status,
        p.estimated_budget,
        p.contractor_fee,
        COALESCE(
            (SELECT SUM(ps.allocated_budget) FROM project_stages ps WHERE ps.project_id = p.id),
            0
        ) AS allocated_total
    FROM projects p
    WHERE created_by=?
    ORDER BY created_at DESC
");
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

// Handle form submission
if(isset($_POST['add_stage'])){
    $project_id = intval($_POST['project_id']);
    $selected_project_id = $project_id;
    $stage_name = trim($_POST['stage_name']);
    $planned_start = $_POST['planned_start'];
    $planned_end = $_POST['planned_end'];
    $allocated_budget = (float) ($_POST['allocated_budget'] ?? 0);

    if (empty($project_id) || empty($stage_name) || empty($planned_start) || empty($planned_end) || $allocated_budget <= 0) {
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
            $budget_summary = getProjectBudgetSummary($conn, $project_id);
            $remaining_allocatable_budget = (float) $budget_summary['remaining_allocatable_budget'];

            if ($remaining_allocatable_budget <= 0) {
                $msg = "This project has no remaining budget available for new status-item allocation.";
            } elseif ($allocated_budget - $remaining_allocatable_budget > 0.01) {
                $msg = "Allocated budget exceeds the remaining unallocated project budget of MWK " . number_format($remaining_allocatable_budget, 2) . ".";
            } else {
                $remaining_after = $remaining_allocatable_budget - $allocated_budget;

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
                        "Status item '{$stage_name}' added with planned dates {$planned_start} to {$planned_end} and allocation of MWK " . number_format($allocated_budget, 2) . "."
                    );

                    $msg = "Status item added for " . formatProjectCode($project_id) . ". Allocated Budget: MWK " . number_format($allocated_budget,2) . ". Remaining to allocate: MWK " . number_format(max($remaining_after, 0), 2) . ".";
                } else {
                    $msg = "Error adding status item. Please try again.";
                }
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
                        <?php
                        $project_total_budget = (float) $row['estimated_budget'] + (float) $row['contractor_fee'];
                        $allocated_total = (float) $row['allocated_total'];
                        $remaining_allocatable = $project_total_budget - $allocated_total;
                        ?>
                        <option
                            value="<?php echo $row['id']; ?>"
                            <?php echo $selected_project_id === (int) $row['id'] ? 'selected' : ''; ?>
                            data-code="<?php echo htmlspecialchars(formatProjectCode($row['id'])); ?>"
                            data-title="<?php echo htmlspecialchars($row['title']); ?>"
                            data-district="<?php echo htmlspecialchars($row['district']); ?>"
                            data-status="<?php echo htmlspecialchars($row['status']); ?>"
                            data-total="<?php echo $project_total_budget; ?>"
                            data-allocated="<?php echo $allocated_total; ?>"
                            data-remaining="<?php echo $remaining_allocatable; ?>">
                            <?php echo htmlspecialchars(formatProjectCode($row['id']) . ' - ' . $row['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <div id="projectSummary" class="form-card" style="margin:15px 0; display:none;">
                    <h4 id="projectSummaryTitle"></h4>
                    <p><strong>District:</strong> <span id="projectSummaryDistrict"></span></p>
                    <p><strong>Current Status:</strong> <span id="projectSummaryStatus"></span></p>
                    <p><strong>Total Budget:</strong> MWK <span id="projectSummaryBudget"></span></p>
                    <p><strong>Allocated to Status Items:</strong> MWK <span id="projectSummaryAllocated"></span></p>
                    <p><strong>Remaining to Allocate:</strong> MWK <span id="projectSummaryRemaining"></span></p>
                </div>

                <label>Status Item Title</label>
                <input type="text" name="stage_name" value="<?= htmlspecialchars($_POST['stage_name'] ?? '') ?>" required>

                <label>Planned Start Date</label>
                <input type="date" name="planned_start" value="<?= htmlspecialchars($_POST['planned_start'] ?? '') ?>" required>

                <label>Planned End Date</label>
                <input type="date" name="planned_end" value="<?= htmlspecialchars($_POST['planned_end'] ?? '') ?>" required>

                <label>Allocated Budget for This Status Item</label>
                <input type="number" name="allocated_budget" id="allocated_budget" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['allocated_budget'] ?? '') ?>" required>
                <small style="display:block;margin:6px 0 14px;color:#666;">This allocation must fit within the remaining unallocated project budget.</small>

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
    var allocated = parseFloat(option.getAttribute('data-allocated') || 0);
    var remaining = parseFloat(option.getAttribute('data-remaining') || 0);
    var summary = document.getElementById('projectSummary');
    var button = document.getElementById('addStageButton');
    var budgetInput = document.getElementById('allocated_budget');
    var status = option.getAttribute('data-status') || '';
    var readableStatus = status.replace(/_/g, ' ');

    if (!projectId) {
        summary.style.display = 'none';
        budgetInput.value = '';
        budgetInput.disabled = false;
        budgetInput.removeAttribute('max');
        button.disabled = false;
        return;
    }

    document.getElementById('projectSummaryTitle').textContent =
        option.getAttribute('data-code') + ' - ' + option.getAttribute('data-title');
    document.getElementById('projectSummaryDistrict').textContent =
        option.getAttribute('data-district') || 'N/A';
    document.getElementById('projectSummaryStatus').textContent = readableStatus;
    document.getElementById('projectSummaryBudget').textContent = total.toFixed(2);
    document.getElementById('projectSummaryAllocated').textContent = allocated.toFixed(2);
    document.getElementById('projectSummaryRemaining').textContent = remaining.toFixed(2);
    summary.style.display = 'block';

    budgetInput.max = Math.max(remaining, 0).toFixed(2);
    if (!budgetInput.value || parseFloat(budgetInput.value) > remaining) {
        budgetInput.value = remaining > 0 ? remaining.toFixed(2) : '';
    }

    button.disabled = !(status === 'approved' || status === 'in_progress') || remaining <= 0;
    budgetInput.disabled = button.disabled;
}

calculateBudget(document.querySelector('select[name="project_id"]').value);
</script>
</body>
</html>
