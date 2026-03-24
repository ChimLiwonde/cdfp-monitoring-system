<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
    $budget_labels[] = $b['title'];
    $allocated_data[] = $b['allocated'];
    $spent_data[] = $b['spent'];
}

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

    $progress_units = ($completed * 1) + ($in_progress * 0.5);
    $percent = $total_stages > 0 ? round(($progress_units / $total_stages) * 100) : 0;

    $progress_data[] = [
        'id' => $pid,
        'title' => $proj['title'],
        'percent' => $percent
    ];
}

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

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
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

<div class="col-9 dashboard-main">

<div class="form-card page-hero">
    <div class="page-hero__grid">
        <div class="page-hero__copy">
            <span class="eyebrow">Project Delivery</span>
            <h3>Manage project progress, spending, resources, and collaboration in one panel.</h3>
            <p>This workspace keeps your pipeline visible from project creation and approval through assignments, monitoring, and expenditure tracking.</p>
            <div class="hero-actions">
                <a class="back-btn" href="create_project.php">Create Project</a>
                <a class="button-link btn-secondary" href="add_stage.php">Update Project Status</a>
            </div>
        </div>
        <div class="hero-pills">
            <div class="hero-pill"><strong><?= $totalProjects ?></strong>&nbsp; Total</div>
            <div class="hero-pill"><strong><?= $counts['approved'] + $counts['in_progress'] ?></strong>&nbsp; Active</div>
            <div class="hero-pill"><strong><?= $counts['completed'] ?></strong>&nbsp; Completed</div>
        </div>
    </div>
</div>

<?php if ($success_message): ?>
<div class="form-card">
    <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="form-card">
    <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
</div>
<?php endif; ?>

<?php
$cards = [
    ['label' => 'Total Projects', 'value' => $totalProjects, 'class' => 'card-total', 'meta' => 'All created projects'],
    ['label' => 'Pending', 'value' => $counts['pending'], 'class' => 'card-pending', 'meta' => 'Waiting for admin review'],
    ['label' => 'Approved', 'value' => $counts['approved'], 'class' => 'card-approved', 'meta' => 'Ready to deliver'],
    ['label' => 'In Progress', 'value' => $counts['in_progress'], 'class' => 'card-info', 'meta' => 'Currently active'],
    ['label' => 'Completed', 'value' => $counts['completed'], 'class' => 'card-completed', 'meta' => 'Closed successfully'],
    ['label' => 'Denied', 'value' => $counts['denied'], 'class' => 'card-denied', 'meta' => 'Needs revision']
];
?>
<div class="summary-cards">
<?php foreach ($cards as $card): ?>
    <div class="summary-card <?= $card['class'] ?>">
        <h3><?= $card['label'] ?></h3>
        <h2><?= $card['value'] ?></h2>
        <p class="metric-meta"><?= $card['meta'] ?></p>
    </div>
<?php endforeach; ?>
</div>

<div class="section-grid">
<div class="chart-card">
    <div class="section-header">
        <div>
            <span class="section-kicker">Status Mix</span>
            <h3>Project Status</h3>
        </div>
        <p>See the current balance between pending, approved, active, completed, and denied work.</p>
    </div>
    <div class="chart-shell">
        <canvas id="statusPie"></canvas>
    </div>
</div>

<div class="chart-card">
    <div class="section-header">
        <div>
            <span class="section-kicker">Finance Overview</span>
            <h3>Budget vs Spending</h3>
        </div>
        <p>Compare planned allocation and actual spending across approved, active, and completed projects.</p>
    </div>
    <div class="chart-shell">
        <canvas id="budgetLine"></canvas>
    </div>
</div>
</div>

<div class="data-card">
    <div class="section-header">
        <div>
            <span class="section-kicker">Progress Tracker</span>
            <h3>Project Progress</h3>
        </div>
        <p>Keep each active project tied to a visual completion bar and direct access to its status timeline.</p>
    </div>
    <?php if (empty($progress_data)): ?>
        <div class="empty-state">No approved or active projects yet.</div>
    <?php else: ?>
        <div class="progress-list">
            <?php foreach ($progress_data as $p): ?>
                <div class="progress-item">
                    <div class="progress-item__head">
                        <div><strong><?= formatProjectCode($p['id']) ?> - <?= htmlspecialchars($p['title']) ?></strong></div>
                        <div class="progress-item__meta"><?= $p['percent'] ?>% complete</div>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill<?= $p['percent'] == 100 ? ' is-complete' : '' ?>" style="width:<?= $p['percent'] ?>%;"></div>
                    </div>
                    <div class="progress-foot">
                        <span><?= $p['percent'] == 100 ? 'Completed project timeline' : 'Progress based on project status items' ?></span>
                        <a href="view_stages.php?project_id=<?= $p['id'] ?>">View Status</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="alert-grid">
    <div class="summary-card card-denied">
        <h3>Over Budget Projects</h3>
        <h2><?= $over_budget_projects_count ?></h2>
        <p class="metric-meta"><a href="project_expenses.php">Review expenses</a></p>
    </div>
    <div class="summary-card card-pending">
        <h3>Over Budget Status Items</h3>
        <h2><?= $over_budget_status_items_count ?></h2>
        <p class="metric-meta"><a href="add_stage.php">Manage project status</a></p>
    </div>
    <div class="summary-card card-info">
        <h3>Internal Messages</h3>
        <h2><?= $collaboration_message_count ?></h2>
        <p class="metric-meta"><a href="project_collaboration.php">Open collaboration</a></p>
    </div>
</div>

<div class="section-grid">
<div class="data-card">
<div class="section-header">
    <div>
        <span class="section-kicker">Finance Watchlist</span>
        <h3>Budget Watchlist</h3>
    </div>
    <p>Compare allocation, remaining budget, and actual spending for active project work.</p>
</div>
<div class="table-wrap">
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
<td style="color:<?= $remaining_budget < 0 ? '#b74b3d' : '#1d7a4c' ?>;">
<?= $budget_state ?>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>

<div class="data-card">
<div class="section-header">
    <div>
        <span class="section-kicker">Team Visibility</span>
        <h3>Resource Allocation</h3>
    </div>
    <p>See how many team members, assigned tasks, and contractors are supporting each project.</p>
</div>
<div class="table-wrap">
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

<div class="data-card">
<div class="section-header">
    <div>
        <span class="section-kicker">Contractor Overview</span>
        <h3>Contractor Assignments</h3>
    </div>
    <p>Track the contractor linked to each project together with the current workflow status.</p>
</div>
<div class="table-wrap">
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
        if ($project_status['status'] === 'pending') {
            $display_status = "Waiting for approval";
        } elseif ($project_status['status'] === 'approved') {
            $display_status = "Approved";
        } else {
            $display_status = formatStatusLabel($project_status['status']);
        }
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

<?php include "footer.php"; ?>

<script>
new Chart(statusPie, {
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
backgroundColor:['#d89b2f','#2b7a52','#2d6f9f','#3554a0','#b74b3d']
}]
},
options:{
responsive:true,
maintainAspectRatio:false,
plugins:{
legend:{
labels:{ color:'#17333b' }
}
}
}
});

new Chart(budgetLine, {
type:'line',
data:{
labels:<?= json_encode($budget_labels) ?>,
datasets:[
{
label:'Allocated',
data:<?= json_encode($allocated_data) ?>,
tension:0.4,
borderColor:'#0f766e',
backgroundColor:'rgba(15,118,110,0.12)',
fill:false
},
{
label:'Spent',
data:<?= json_encode($spent_data) ?>,
tension:0.4,
borderColor:'#c96b3a',
backgroundColor:'rgba(201,107,58,0.12)',
fill:false
}
]
},
options:{
responsive:true,
maintainAspectRatio:false,
plugins:{
legend:{ labels:{ color:'#17333b' } }
},
scales:{
x:{ ticks:{ color:'#5e7379' }, grid:{ color:'rgba(198,186,166,0.22)' } },
y:{ beginAtZero:true, ticks:{ color:'#5e7379' }, grid:{ color:'rgba(198,186,166,0.22)' } }
}
}
});
</script>

</body>
</html>
