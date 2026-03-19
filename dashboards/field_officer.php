<?php
session_start();
require "../config/db.php";

/* ======================= SECURITY ======================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ======================= PROJECT COUNTS ======================= */
$counts = [
    'pending'   => 0,
    'approved'  => 0,
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
      AND p.status = 'approved'
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
?>
<!DOCTYPE html>
<html>
<head>
<title>Field Officer Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/flexible.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "menu.php"; ?></div>

<div class="col-9">

<!-- ======================= SUMMARY CARDS ======================= -->
<div class="row">
<?php
$cards = [
    'Total Projects' => $totalProjects,
    'Pending'        => $counts['pending'],
    'Approved'       => $counts['approved'],
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
<h3>Budget vs Spending (Approved)</h3>
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
                            <strong><?= htmlspecialchars($p['title']) ?></strong>
                            <div style="background:#ddd;width:100%;height:20px;border-radius:5px;margin-bottom:5px;">
                                <div style="
                                    width:<?= $p['percent'] ?>%;
                                    background:<?= $p['percent']==100?'#1565c0':'#2e7d32' ?>;
                                    height:100%;
                                    border-radius:5px;
                                "></div>
                            </div>
                            <span><?= $p['percent'] ?>% Completed</span> &nbsp;|&nbsp;
                            <a href="view_stages.php?project_id=<?= $p['id'] ?>">View Stages</a>
                            <br><br>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
<th>Contractor</th>
<th>Project</th>
<th>Status</th>
</tr>

<?php while($c = $contractor_status->fetch_assoc()): 
    // Get project stage info
    $stage_info = $conn->query("
        SELECT COUNT(*) total,
               SUM(status='completed') completed
        FROM project_stages
        WHERE project_id = {$c['project_id']}
    ")->fetch_assoc();

    if ($stage_info['total'] > 0 && $stage_info['total'] == $stage_info['completed']) {
        $display_status = "🏁 Completed";
    } else {
        $project_status = $conn->query("
            SELECT status FROM projects WHERE id={$c['project_id']}
        ")->fetch_assoc();
        if ($project_status['status'] === 'pending') $display_status = "⏳ Waiting for approval";
        elseif ($project_status['status'] === 'approved') $display_status = "✅ Approved";
        else $display_status = ucfirst($project_status['status']);
    }
?>
<tr>
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
labels:['Pending','Approved','Completed','Denied'],
datasets:[{
data:[
<?= $counts['pending'] ?>,
<?= $counts['approved'] ?>,
<?= $counts['completed'] ?>,
<?= $counts['denied'] ?>
],
backgroundColor:['#f57c00','#2e7d32','#1565c0','#d32f2f']
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
