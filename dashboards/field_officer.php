<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* ======================= SECURITY ======================= */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ======================= PROJECT COUNTS ======================= */
$counts = [
    'pending'   => 0,
    'approved'  => 0,
    'in_progress' => 0,
    'completed' => 0,
    'denied'    => 0
];

$projects = $conn->query("
    SELECT id, status 
    FROM projects 
    WHERE created_by = $user_id
");

while ($p = $projects->fetch_assoc()) {

    if ($p['status'] === 'denied') {
        $counts['denied']++;
        continue;
    }

    // Count completed stages
    $stages = $conn->query("
        SELECT COUNT(*) total,
               SUM(status='completed') completed
        FROM project_stages
        WHERE project_id = {$p['id']}
    ")->fetch_assoc();

    if ($stages['total'] > 0 && $stages['total'] == $stages['completed']) {
        $counts['completed']++;
        $conn->query("UPDATE projects SET status='completed' WHERE id={$p['id']}");
    } else {
        $counts[$p['status']]++;
    }
}

$totalProjects = array_sum($counts);

/* ======================= LINE GRAPH DATA ======================= */
$budget_labels = [];
$allocated_data = [];
$spent_data = [];

$budget_q = $conn->query("
    SELECT p.id, p.title,
           SUM(ps.allocated_budget) allocated,
           SUM(ps.spent_budget) spent
    FROM projects p
    JOIN project_stages ps ON ps.project_id = p.id
    WHERE p.created_by = $user_id
      AND p.status IN ('approved', 'in_progress', 'completed')
    GROUP BY p.id
");

while ($b = $budget_q->fetch_assoc()) {
    $budget_labels[]   = $b['title'];
    $allocated_data[] = $b['allocated'];
    $spent_data[]     = $b['spent'];
}

/* ======================= FETCH PROJECTS FOR PROGRESS ======================= */
$projects_stmt = $conn->prepare("
    SELECT id, title FROM projects WHERE created_by=? AND status IN ('approved','in_progress','completed')
");
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

$progress_data = [];
while ($proj = $projects->fetch_assoc()) {
    $pid = $proj['id'];
    $total_stages = $conn->query("SELECT COUNT(*) FROM project_stages WHERE project_id=$pid")->fetch_row()[0];
    $completed = $conn->query("SELECT COUNT(*) FROM project_stages WHERE project_id=$pid AND status='completed'")->fetch_row()[0];
    $in_progress = $conn->query("SELECT COUNT(*) FROM project_stages WHERE project_id=$pid AND status='in_progress'")->fetch_row()[0];

    // Weighted progress: completed=1, in_progress=0.5
    $progress_units = ($completed * 1) + ($in_progress * 0.5);
    $percent = $total_stages > 0 ? round(($progress_units / $total_stages) * 100) : 0;

    $progress_data[] = [
        'id' => $pid,
        'title' => $proj['title'],
        'percent' => $percent
    ];
}

/* ======================= CONTRACTOR STATUS ======================= */
$contractor_status = $conn->query("
    SELECT c.name contractor,
           p.title project,
           p.id project_id
    FROM contractor_projects cp
    JOIN contractors c ON c.id = cp.contractor_id
    JOIN projects p ON p.id = cp.project_id
    WHERE cp.assigned_by = $user_id
");

$budget_watchlist = $conn->query("
    SELECT
        p.id,
        p.title,
        (p.estimated_budget + p.contractor_fee) AS total_budget,
        COALESCE(stage_totals.allocated_total, 0) AS allocated_total,
        COALESCE(SUM(pe.amount), 0) AS total_spent
    FROM projects p
    LEFT JOIN (
        SELECT project_id, SUM(allocated_budget) AS allocated_total
        FROM project_stages
        GROUP BY project_id
    ) stage_totals ON stage_totals.project_id = p.id
    LEFT JOIN project_expenses pe ON pe.project_id = p.id
    WHERE p.created_by = $user_id
      AND p.status IN ('approved', 'in_progress', 'completed')
    GROUP BY p.id, p.title, p.estimated_budget, p.contractor_fee, stage_totals.allocated_total
    ORDER BY p.created_at DESC
");

$resource_allocation = $conn->query("
    SELECT
        p.id,
        p.title,
        COUNT(DISTINCT ptm.id) AS team_members,
        COUNT(DISTINCT psa.id) AS assigned_tasks,
        COUNT(DISTINCT c.id) AS contractors
    FROM projects p
    LEFT JOIN project_team_members ptm ON ptm.project_id = p.id
    LEFT JOIN project_stages ps ON ps.project_id = p.id
    LEFT JOIN project_stage_assignments psa ON psa.stage_id = ps.id
    LEFT JOIN contractor_projects cp ON cp.project_id = p.id
    LEFT JOIN contractors c ON c.id = cp.contractor_id
    WHERE p.created_by = $user_id
      AND p.status IN ('approved', 'in_progress', 'completed')
    GROUP BY p.id, p.title
    ORDER BY p.created_at DESC
");

$over_budget_projects_count = $conn->query("
    SELECT COUNT(*) AS total
    FROM (
        SELECT p.id
        FROM projects p
        LEFT JOIN project_expenses pe ON pe.project_id = p.id
        WHERE p.created_by = $user_id
        GROUP BY p.id, p.estimated_budget, p.contractor_fee
        HAVING COALESCE(SUM(pe.amount), 0) > (p.estimated_budget + p.contractor_fee)
    ) alert_projects
")->fetch_assoc()['total'];

$over_budget_status_items_count = $conn->query("
    SELECT COUNT(*) AS total
    FROM project_stages ps
    JOIN projects p ON p.id = ps.project_id
    WHERE p.created_by = $user_id
      AND ps.spent_budget > ps.allocated_budget
")->fetch_assoc()['total'];

$collaboration_message_count = $conn->query("
    SELECT COUNT(*) AS total
    FROM project_collaboration_messages pcm
    JOIN projects p ON p.id = pcm.project_id
    WHERE p.created_by = $user_id
")->fetch_assoc()['total'];

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html>
<head>
<title>Project Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/flexible.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "menu.php"; ?></div>

<div class="col-9">

<?php if ($success_message): ?>
<div class="form-card" style="margin-bottom:15px;">
    <div class="msg"><?= htmlspecialchars($success_message) ?></div>
</div>
<?php endif; ?>

<!-- ======================= SUMMARY CARDS ======================= -->
<div class="row">
<?php
$cards = [
    'Total Projects' => $totalProjects,
    'Pending'        => $counts['pending'],
    'Approved'       => $counts['approved'],
    'In Progress'    => $counts['in_progress'],
    'Completed'      => $counts['completed'],
    'Denied'         => $counts['denied']
];
foreach ($cards as $t => $v):
?>
<div class="col-3">
    <div class="form-card" style="text-align:center;">
        <h3><?= $t ?></h3>
        <h2><?= $v ?></h2>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ======================= PIE + LINE ======================= -->
<div class="row">
<div class="col-6">
<div class="form-card">
<h3>Project Status</h3>
<canvas id="statusPie"></canvas>
</div>
</div>

<div class="col-6">
<div class="form-card">
<h3>Budget vs Spending (Active Projects)</h3>
<canvas id="budgetLine"></canvas>
</div>
</div>
</div>

<!-- ===================== PROJECT PROGRESS ====================== -->
        <div class="row">
            <div class="col-12">
                <div class="form-card">
                    <h3>Project Progress</h3>
                    <?php if (empty($progress_data)): ?>
                        <p>No approved or in-progress projects yet.</p>
                    <?php else: ?>
                        <?php foreach ($progress_data as $p): ?>
                            <strong><?= formatProjectCode($p['id']) ?> - <?= htmlspecialchars($p['title']) ?></strong>
                            <div style="background:#ddd;width:100%;height:20px;border-radius:5px;margin-bottom:5px;">
                                <div style="
                                    width:<?= $p['percent'] ?>%;
                                    background:<?= $p['percent']==100?'#1565c0':'#2e7d32' ?>;
                                    height:100%;
                                    border-radius:5px;
                                "></div>
                            </div>
                            <span><?= $p['percent'] ?>% Completed</span> &nbsp;|&nbsp;
                            <a href="view_stages.php?project_id=<?= $p['id'] ?>">View Status</a>
                            <br><br>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
</div>
</div>

<div class="row">
<div class="col-4">
<div class="form-card" style="text-align:center;">
<h3>Over Budget Projects</h3>
<h2 style="color:#d32f2f;"><?= $over_budget_projects_count ?></h2>
<a href="project_expenses.php">Review Expenses</a>
</div>
</div>
<div class="col-4">
<div class="form-card" style="text-align:center;">
<h3>Over Budget Status Items</h3>
<h2 style="color:#d32f2f;"><?= $over_budget_status_items_count ?></h2>
<a href="add_stage.php">Manage Project Status</a>
</div>
</div>
<div class="col-4">
<div class="form-card" style="text-align:center;">
<h3>Internal Messages</h3>
<h2 style="color:#1565c0;"><?= $collaboration_message_count ?></h2>
<a href="project_collaboration.php">Open Collaboration</a>
</div>
</div>
</div>

<div class="row">
<div class="col-6">
<div class="form-card">
<h3>Budget Watchlist</h3>
<table class="dashboard-table">
<tr>
<th>Project ID</th>
<th>Project</th>
<th>Planning vs Spending</th>
<th>Status</th>
</tr>
<?php if ($budget_watchlist->num_rows === 0): ?>
<tr>
<td colspan="4" style="text-align:center;color:gray;">No active project budgets to monitor yet.</td>
</tr>
<?php endif; ?>
<?php while ($budget_row = $budget_watchlist->fetch_assoc()): ?>
<?php
$remaining_budget = (float) $budget_row['total_budget'] - (float) $budget_row['total_spent'];
$remaining_to_allocate = (float) $budget_row['total_budget'] - (float) $budget_row['allocated_total'];
$budget_state = $remaining_budget < 0 ? 'Over budget' : 'Within budget';
?>
<tr>
<td><?= formatProjectCode($budget_row['id']) ?></td>
<td><?= htmlspecialchars($budget_row['title']) ?></td>
<td>
Allocated: MWK <?= number_format((float) $budget_row['allocated_total'], 2) ?><br>
Unallocated: MWK <?= number_format($remaining_to_allocate, 2) ?><br>
Spent: MWK <?= number_format((float) $budget_row['total_spent'], 2) ?><br>
Budget: MWK <?= number_format((float) $budget_row['total_budget'], 2) ?>
</td>
<td style="color:<?= $remaining_budget < 0 ? '#d32f2f' : '#2e7d32' ?>;">
<?= $budget_state ?>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>

<div class="col-6">
<div class="form-card">
<h3>Resource Allocation</h3>
<table class="dashboard-table">
<tr>
<th>Project ID</th>
<th>Project</th>
<th>Team Members</th>
<th>Assigned Tasks</th>
<th>Contractors</th>
</tr>
<?php if ($resource_allocation->num_rows === 0): ?>
<tr>
<td colspan="5" style="text-align:center;color:gray;">No project resources allocated yet.</td>
</tr>
<?php endif; ?>
<?php while ($resource_row = $resource_allocation->fetch_assoc()): ?>
<tr>
<td><?= formatProjectCode($resource_row['id']) ?></td>
<td><?= htmlspecialchars($resource_row['title']) ?></td>
<td><?= (int) $resource_row['team_members'] ?></td>
<td><?= (int) $resource_row['assigned_tasks'] ?></td>
<td><?= (int) $resource_row['contractors'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>
</div>

<!-- ======================= CONTRACTOR STATUS ======================= -->
<div class="row">
<div class="col-12">
<div class="form-card">
<h3>Contractor Assignments</h3>

<table class="dashboard-table">
<tr>
<th>Project ID</th>
<th>Contractor</th>
<th>Project</th>
<th>Status</th>
</tr>

<?php if ($contractor_status->num_rows === 0): ?>
<tr>
<td colspan="4" style="text-align:center;color:gray;">No contractor assignments yet.</td>
</tr>
<?php endif; ?>

<?php while($c = $contractor_status->fetch_assoc()): 
    // Get project stage info
    $stage_info = $conn->query("
        SELECT COUNT(*) total,
               SUM(status='completed') completed
        FROM project_stages
        WHERE project_id = {$c['project_id']}
    ")->fetch_assoc();

    if ($stage_info['total'] > 0 && $stage_info['total'] == $stage_info['completed']) {
        $display_status = "Completed";
    } else {
        $project_status = $conn->query("
            SELECT status FROM projects WHERE id={$c['project_id']}
        ")->fetch_assoc();
        if ($project_status['status'] === 'pending') $display_status = "Waiting for approval";
        elseif ($project_status['status'] === 'approved') $display_status = "Approved";
        else $display_status = formatStatusLabel($project_status['status']);
    }
?>
<tr>
<td><?= formatProjectCode($c['project_id']) ?></td>
<td><?= htmlspecialchars($c['contractor']) ?></td>
<td><?= htmlspecialchars($c['project']) ?></td>
<td><?= $display_status ?></td>
</tr>
<?php endwhile; ?>

</table>
</div>
</div>
</div>

</div>
</div>

<?php include "footer.php"; ?>

<script>
/* PIE */
new Chart(statusPie,{
type:'pie',
data:{
labels:['Pending','Approved','In Progress','Completed','Denied'],
datasets:[{
data:[
<?= $counts['pending'] ?>,
<?= $counts['approved'] ?>,
<?= $counts['in_progress'] ?>,
<?= $counts['completed'] ?>,
<?= $counts['denied'] ?>
],
backgroundColor:['#f57c00','#2e7d32','#0288d1','#1565c0','#d32f2f']
}]
}
});

/* LINE */
new Chart(budgetLine,{
type:'line',
data:{
labels:<?= json_encode($budget_labels) ?>,
datasets:[
{
label:'Allocated',
data:<?= json_encode($allocated_data) ?>,
tension:0.4
},
{
label:'Spent',
data:<?= json_encode($spent_data) ?>,
tension:0.4
}
]
}
});
</script>

</body>
</html>
