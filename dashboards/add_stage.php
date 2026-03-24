<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$selected_project_id = (int) ($_POST['project_id'] ?? 0);

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

if (isset($_POST['add_stage'])) {
    if (!isValidCsrfToken('add_stage_form', $_POST['_csrf_token'] ?? '')) {
        $msg = "Your session expired. Please submit the status item again.";
    } else {
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

                    if ($stmt->execute()) {
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

                        $msg = "Status item added for " . formatProjectCode($project_id) . ". Allocated Budget: MWK " . number_format($allocated_budget, 2) . ". Remaining to allocate: MWK " . number_format(max($remaining_after, 0), 2) . ".";
                    } else {
                        $msg = "Error adding status item. Please try again.";
                    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>
<?php include "header.php"; ?>
<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>
    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Status Planning</span>
                    <h3>Break a project into status items with planned dates and budget allocation.</h3>
                    <p>Select one of your projects to review its current budget picture, then add a status item only when the project is approved or already in progress.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong>Status</strong>&nbsp; Timeline</div>
                    <div class="hero-pill"><strong>Budget</strong>&nbsp; Allocation</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($msg != ""): ?>
                <div class="msg"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <span class="section-kicker">Project Status / Progress Tracking</span>
                    <h3>Add Project Status Item</h3>
                </div>
                <p>Select any project you created to view its budget picture. Only approved or active projects can receive new status entries.</p>
            </div>

            <form method="POST">
                <?= csrfInput('add_stage_form') ?>

                <div class="form-grid">
                    <div class="full-span">
                        <label for="project_id">Select Project</label>
                        <select id="project_id" name="project_id" required onchange="calculateBudget(this.value)">
                            <option value="">-- Select Project --</option>
                            <?php while ($row = $projects->fetch_assoc()): ?>
                                <?php
                                $project_total_budget = (float) $row['estimated_budget'] + (float) $row['contractor_fee'];
                                $allocated_total = (float) $row['allocated_total'];
                                $remaining_allocatable = $project_total_budget - $allocated_total;
                                ?>
                                <option
                                    value="<?= $row['id']; ?>"
                                    <?= $selected_project_id === (int) $row['id'] ? 'selected' : ''; ?>
                                    data-code="<?= htmlspecialchars(formatProjectCode($row['id'])); ?>"
                                    data-title="<?= htmlspecialchars($row['title']); ?>"
                                    data-district="<?= htmlspecialchars($row['district']); ?>"
                                    data-status="<?= htmlspecialchars($row['status']); ?>"
                                    data-total="<?= $project_total_budget; ?>"
                                    data-allocated="<?= $allocated_total; ?>"
                                    data-remaining="<?= $remaining_allocatable; ?>">
                                    <?= htmlspecialchars(formatProjectCode($row['id']) . ' - ' . $row['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="full-span" id="projectSummary" style="display:none;">
                        <div class="detail-grid">
                            <div class="detail-card">
                                <strong>Project</strong>
                                <span id="projectSummaryTitle"></span>
                            </div>
                            <div class="detail-card">
                                <strong>District</strong>
                                <span id="projectSummaryDistrict"></span>
                            </div>
                            <div class="detail-card">
                                <strong>Current Status</strong>
                                <span id="projectSummaryStatus"></span>
                            </div>
                            <div class="detail-card">
                                <strong>Total Budget</strong>
                                <span>MWK <span id="projectSummaryBudget"></span></span>
                            </div>
                            <div class="detail-card">
                                <strong>Allocated to Status Items</strong>
                                <span>MWK <span id="projectSummaryAllocated"></span></span>
                            </div>
                            <div class="detail-card">
                                <strong>Remaining to Allocate</strong>
                                <span>MWK <span id="projectSummaryRemaining"></span></span>
                            </div>
                        </div>
                    </div>

                    <div class="full-span">
                        <label for="stage_name">Status Item Title</label>
                        <input id="stage_name" type="text" name="stage_name" value="<?= htmlspecialchars($_POST['stage_name'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label for="planned_start">Planned Start Date</label>
                        <input id="planned_start" type="date" name="planned_start" value="<?= htmlspecialchars($_POST['planned_start'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label for="planned_end">Planned End Date</label>
                        <input id="planned_end" type="date" name="planned_end" value="<?= htmlspecialchars($_POST['planned_end'] ?? '') ?>" required>
                    </div>

                    <div class="full-span">
                        <label for="allocated_budget">Allocated Budget for This Status Item</label>
                        <input id="allocated_budget" type="number" name="allocated_budget" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['allocated_budget'] ?? '') ?>" required>
                        <small>This allocation must fit within the remaining unallocated project budget.</small>
                    </div>
                </div>

                <input type="submit" name="add_stage" id="addStageButton" value="Add Status Item" style="margin-top:18px;">
            </form>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>

<script>
function calculateBudget(projectId) {
    const select = document.getElementById('project_id');
    const option = select.options[select.selectedIndex];
    const total = parseFloat(option.getAttribute('data-total') || 0);
    const allocated = parseFloat(option.getAttribute('data-allocated') || 0);
    const remaining = parseFloat(option.getAttribute('data-remaining') || 0);
    const summary = document.getElementById('projectSummary');
    const button = document.getElementById('addStageButton');
    const budgetInput = document.getElementById('allocated_budget');
    const status = option.getAttribute('data-status') || '';
    const readableStatus = status.replace(/_/g, ' ');

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

calculateBudget(document.getElementById('project_id').value);
</script>
</body>
</html>
