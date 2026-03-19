<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
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
ORDER BY p.created_at DESC
";

$result = $conn->query($sql);
$projects = [];
$mapData = [];

while ($r = $result->fetch_assoc()) {

    $completed = ($r['stage_status'] === 'completed');

    if ($r['project_status'] === 'denied') {
        $status = "Denied";
    } elseif ($r['project_status'] === 'pending') {
        $status = "Pending";
    } elseif ($r['project_status'] === 'approved' && $completed) {
        $status = "Completed";
    } else {
        $status = "Approved";
    }

    $risk = "—";
    if ($status === "Approved" || $status === "Completed") {
        $delayed = $r['actual_end'] && strtotime($r['actual_end']) > strtotime($r['planned_end']);
        $overspent = $r['spent_budget'] > $r['allocated_budget'];

        if ($delayed && $overspent) $risk = "RED";
        elseif ($delayed || $overspent) $risk = "YELLOW";
        else $risk = "GREEN";
    }

    $r['final_status'] = $status;
    $r['completed'] = $completed ? "Yes" : "No";
    $r['risk'] = $risk;

    if ($r['latitude'] && $r['longitude']) {
        $mapData[] = [
            'title' => $r['title'],
            'district' => $r['district'],
            'location' => $r['location'],
            'lat' => $r['latitude'],
            'lng' => $r['longitude']
        ];
    }

    $projects[] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Project Performance Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/flexible.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
#projectMap { height:380px; margin-bottom:15px; border-radius:6px; }

/* EXPORT BAR STYLES */
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

<h2>📊 Project Status, Risk & Completion</h2>

<!-- EXPORT BUTTONS -->
<div class="export-bar">
    <div class="export-label">📤 Export Project Report</div>
    <div class="export-actions">
        <a href="export_report_excel.php" class="btn btn-excel">📊 Export Excel</a>
        <a href="export_report_pdf.php" target="_blank" class="btn btn-pdf">📄 Export PDF</a>
    </div>
</div>

<!-- MAP -->
<div id="projectMap"></div>

<!-- PROJECT TABLE -->
<table class="dashboard-table">
<tr>
<th>Project</th>
<th>District</th>
<th>Location</th>
<th>Stage</th>
<th>Status</th>
<th>Completed</th>
<th>Budget</th>
<th>Responsible</th>
<th>Risk</th>
</tr>

<?php foreach($projects as $p): ?>
<tr>
<td><?= htmlspecialchars($p['title']) ?></td>
<td><?= htmlspecialchars($p['district']) ?></td>
<td><?= htmlspecialchars($p['location']) ?></td>
<td><?= htmlspecialchars($p['stage_name']) ?></td>
<td><?= $p['final_status'] ?></td>
<td><?= $p['completed'] ?></td>
<td>
Alloc: <?= number_format($p['allocated_budget'],2) ?><br>
Spent: <?= number_format($p['spent_budget'],2) ?>
</td>
<td>
Officer: <?= htmlspecialchars($p['field_officer']) ?><br>
Contractor: <?= htmlspecialchars($p['contractor_name'] ?? '—') ?><br>
<?= htmlspecialchars($p['company'] ?? '') ?>
</td>
<td><?= $p['risk'] ?></td>
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

mapData.forEach(p=>{
    L.marker([p.lat,p.lng]).addTo(map)
     .bindPopup(`<b>${p.title}</b><br>${p.district}<br>${p.location}`);
});
</script>

</body>
</html>
