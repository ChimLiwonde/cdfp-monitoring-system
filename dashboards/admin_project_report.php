<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$sql = "
SELECT
    p.id,
    p.title,
    p.district,
    p.location,
    p.status AS project_status,
    u.username AS field_officer,
    c.name AS contractor_name,
    c.company,
    ps.stage_name,
    ps.planned_end,
    ps.actual_end,
    ps.allocated_budget,
    ps.spent_budget,
    ps.status AS stage_status,
    pm.latitude,
    pm.longitude
FROM projects p
JOIN users u ON u.id = p.created_by
LEFT JOIN contractor_projects cp ON cp.project_id = p.id
LEFT JOIN contractors c ON c.id = cp.contractor_id
LEFT JOIN project_stages ps ON ps.project_id = p.id
LEFT JOIN project_maps pm ON pm.project_id = p.id
ORDER BY p.created_at DESC, ps.planned_end ASC
";

$result = $conn->query($sql);
$projects = [];
$mapData = [];
$mappedProjects = [];

while ($row = $result->fetch_assoc()) {
    $risk = '-';
    if (!in_array($row['project_status'], ['pending', 'denied'], true) && $row['stage_name']) {
        $delayed = !empty($row['actual_end']) && !empty($row['planned_end']) && strtotime($row['actual_end']) > strtotime($row['planned_end']);
        $overspent = (float) $row['spent_budget'] > (float) $row['allocated_budget'];

        if ($delayed && $overspent) {
            $risk = 'RED';
        } elseif ($delayed || $overspent) {
            $risk = 'YELLOW';
        } else {
            $risk = 'GREEN';
        }
    }

    $row['final_status'] = formatStatusLabel($row['project_status']);
    $row['completed'] = $row['project_status'] === 'completed' ? 'Yes' : 'No';
    $row['risk'] = $risk;

    if ($row['latitude'] && $row['longitude'] && !isset($mappedProjects[$row['id']])) {
        $mapData[] = [
            'title' => $row['title'],
            'district' => $row['district'],
            'location' => $row['location'],
            'lat' => $row['latitude'],
            'lng' => $row['longitude']
        ];
        $mappedProjects[$row['id']] = true;
    }

    $projects[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Project Performance Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/flexible.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
#projectMap { height:380px; margin-bottom:15px; border-radius:6px; }
.export-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#f5f7fa;
    padding:12px 16px;
    border-radius:6px;
    margin-bottom:15px;
    border:1px solid #e0e0e0;
}
.export-label{
    font-weight:600;
    color:#333;
    font-size:15px;
}
.export-actions{
    display:flex;
    gap:10px;
}
.btn{
    padding:8px 16px;
    border-radius:5px;
    font-size:14px;
    font-weight:600;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:6px;
    transition:0.2s ease-in-out;
}
.btn-excel{
    background:#1f7a1f;
    color:#fff;
}
.btn-excel:hover{
    background:#155d15;
}
.btn-pdf{
    background:#c62828;
    color:#fff;
}
.btn-pdf:hover{
    background:#8e1c1c;
}
</style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "adminmenu.php"; ?></div>
<div class="col-9">

<div class="form-card">

<h2 style="margin-bottom:20px;color:#0d47a1;">Project Status, Risk and Completion</h2>

<div class="export-bar">
    <div class="export-label">Export Project Report</div>
    <div class="export-actions">
        <a href="export_report_excel.php" class="btn btn-excel">Export Excel</a>
        <a href="export_report_pdf.php" target="_blank" class="btn btn-pdf">Export PDF</a>
    </div>
</div>

<div id="projectMap"></div>

<table class="dashboard-table">
<tr>
<th>Project ID</th>
<th>Project</th>
<th>District</th>
<th>Location</th>
<th>Status Item</th>
<th>Status</th>
<th>Completed</th>
<th>Budget</th>
<th>Responsible</th>
<th>Risk</th>
</tr>

<?php if (count($projects) === 0): ?>
<tr>
<td colspan="10" style="text-align:center;color:gray;">No projects available for reporting yet.</td>
</tr>
<?php endif; ?>

<?php foreach($projects as $project): ?>
<tr>
<td><a href="admin_project_details.php?id=<?= $project['id'] ?>"><?= formatProjectCode($project['id']) ?></a></td>
<td><a href="admin_project_details.php?id=<?= $project['id'] ?>"><?= htmlspecialchars($project['title']) ?></a></td>
<td><?= htmlspecialchars($project['district']) ?></td>
<td><?= htmlspecialchars($project['location']) ?></td>
<td><?= htmlspecialchars($project['stage_name'] ?: 'No status item yet') ?></td>
<td><?= htmlspecialchars($project['final_status']) ?></td>
<td><?= htmlspecialchars($project['completed']) ?></td>
<td>
Alloc: <?= number_format((float) $project['allocated_budget'], 2) ?><br>
Spent: <?= number_format((float) $project['spent_budget'], 2) ?>
</td>
<td>
Officer: <?= htmlspecialchars($project['field_officer']) ?><br>
Contractor: <?= htmlspecialchars($project['contractor_name'] ?: '-') ?><br>
<?= htmlspecialchars($project['company'] ?: '') ?>
</td>
<td><?= htmlspecialchars($project['risk']) ?></td>
</tr>
<?php endforeach; ?>
</table>

</div>
</div>
</div>

<?php include "footer.php"; ?>

<script>
const mapData = <?= json_encode($mapData) ?>;
const map = L.map('projectMap').setView([-13.2543, 34.3015], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

mapData.forEach(function (project) {
    L.marker([project.lat, project.lng]).addTo(map)
        .bindPopup('<b>' + project.title + '</b><br>' + project.district + '<br>' + project.location);
});
</script>

</body>
</html>
