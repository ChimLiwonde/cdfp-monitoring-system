<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$report_type = normalizeReportType($_GET['type'] ?? 'progress');
$report_rows = [];
$summary_cards = [];
$mapData = [];

if ($report_type === 'financial') {
    $sql = "
    SELECT
        p.id,
        p.title,
        p.district,
        p.status AS project_status,
        u.username AS field_officer,
        (p.estimated_budget + p.contractor_fee) AS total_budget,
        COALESCE(stage_totals.allocated_total, 0) AS allocated_total,
        COALESCE(expense_totals.spent_total, 0) AS spent_total,
        COALESCE(expense_totals.expense_count, 0) AS expense_count,
        COALESCE(stage_alerts.over_budget_items, 0) AS over_budget_items
    FROM projects p
    JOIN users u ON u.id = p.created_by
    LEFT JOIN (
        SELECT project_id, SUM(allocated_budget) AS allocated_total
        FROM project_stages
        GROUP BY project_id
    ) stage_totals ON stage_totals.project_id = p.id
    LEFT JOIN (
        SELECT project_id, SUM(amount) AS spent_total, COUNT(*) AS expense_count
        FROM project_expenses
        GROUP BY project_id
    ) expense_totals ON expense_totals.project_id = p.id
    LEFT JOIN (
        SELECT project_id, SUM(CASE WHEN spent_budget > allocated_budget THEN 1 ELSE 0 END) AS over_budget_items
        FROM project_stages
        GROUP BY project_id
    ) stage_alerts ON stage_alerts.project_id = p.id
    ORDER BY p.created_at DESC
    ";

    $result = $conn->query($sql);
    $total_budget_all = 0;
    $total_spent_all = 0;
    $over_budget_project_count = 0;
    $over_budget_stage_count = 0;

    while ($row = $result->fetch_assoc()) {
        $row['remaining_budget'] = (float) $row['total_budget'] - (float) $row['spent_total'];
        $row['alert_status'] = $row['remaining_budget'] < 0 ? 'Over Budget' : 'Within Budget';
        $row['final_status'] = formatStatusLabel($row['project_status']);

        $total_budget_all += (float) $row['total_budget'];
        $total_spent_all += (float) $row['spent_total'];
        $over_budget_stage_count += (int) $row['over_budget_items'];
        if ($row['remaining_budget'] < 0) {
            $over_budget_project_count++;
        }

        $report_rows[] = $row;
    }

    $summary_cards = [
        ['label' => 'Total Budget', 'value' => 'MWK ' . number_format($total_budget_all, 2)],
        ['label' => 'Total Recorded Spending', 'value' => 'MWK ' . number_format($total_spent_all, 2)],
        ['label' => 'Over Budget Projects', 'value' => $over_budget_project_count],
        ['label' => 'Over Budget Status Items', 'value' => $over_budget_stage_count],
    ];
} else {
    $sql = "
    SELECT
        p.id,
        p.title,
        p.district,
        p.location,
        p.status AS project_status,
        u.username AS field_officer,
        pm.latitude,
        pm.longitude,
        COUNT(ps.id) AS total_items,
        SUM(CASE WHEN ps.status = 'completed' THEN 1 ELSE 0 END) AS completed_items,
        SUM(CASE WHEN ps.status = 'in_progress' THEN 1 ELSE 0 END) AS active_items,
        SUM(CASE WHEN ps.actual_end IS NOT NULL AND ps.actual_end > ps.planned_end THEN 1 ELSE 0 END) AS overdue_items
    FROM projects p
    JOIN users u ON u.id = p.created_by
    LEFT JOIN project_stages ps ON ps.project_id = p.id
    LEFT JOIN project_maps pm ON pm.project_id = p.id
    GROUP BY p.id, p.title, p.district, p.location, p.status, u.username, pm.latitude, pm.longitude
    ORDER BY p.created_at DESC
    ";

    $result = $conn->query($sql);
    $active_project_count = 0;
    $completed_project_count = 0;
    $overdue_item_count = 0;

    while ($row = $result->fetch_assoc()) {
        $row['progress_percent'] = calculateProgressPercent(
            $row['total_items'],
            $row['completed_items'],
            $row['active_items']
        );
        $row['final_status'] = formatStatusLabel($row['project_status']);

        if (in_array($row['project_status'], ['approved', 'in_progress'], true)) {
            $active_project_count++;
        }
        if ($row['project_status'] === 'completed') {
            $completed_project_count++;
        }
        $overdue_item_count += (int) $row['overdue_items'];

        if ($row['latitude'] && $row['longitude']) {
            $mapData[] = [
                'title' => $row['title'],
                'district' => $row['district'],
                'location' => $row['location'],
                'lat' => $row['latitude'],
                'lng' => $row['longitude']
            ];
        }

        $report_rows[] = $row;
    }

    $summary_cards = [
        ['label' => 'Projects in Active Progress', 'value' => $active_project_count],
        ['label' => 'Completed Projects', 'value' => $completed_project_count],
        ['label' => 'Overdue Status Items', 'value' => $overdue_item_count],
        ['label' => 'Projects in Report', 'value' => count($report_rows)],
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<title><?= htmlspecialchars(formatReportTypeLabel($report_type)) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/flexible.css">
<?php if ($report_type === 'progress'): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<style>
.report-switch {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:16px;
}
.report-link {
    display:inline-block;
    padding:10px 16px;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
    background:#e3f2fd;
}
.report-link.active {
    background:#1565c0;
    color:#fff;
}
.summary-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
    gap:12px;
    margin-bottom:18px;
}
.summary-card {
    background:#f7fbff;
    border:1px solid #d6eafc;
    border-radius:10px;
    padding:16px;
}
.summary-card strong {
    display:block;
    color:#0d47a1;
    margin-bottom:8px;
}
.export-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    background:#f5f7fa;
    padding:12px 16px;
    border-radius:6px;
    margin-bottom:15px;
    border:1px solid #e0e0e0;
    gap:12px;
    flex-wrap:wrap;
}
.export-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn{
    padding:8px 16px;
    border-radius:5px;
    font-size:14px;
    font-weight:600;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
}
.btn-excel{
    background:#1f7a1f;
    color:#fff;
}
.btn-pdf{
    background:#c62828;
    color:#fff;
}
#projectMap {
    height:380px;
    margin-bottom:15px;
    border-radius:6px;
}
</style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "adminmenu.php"; ?></div>
<div class="col-9">

<div class="form-card">

<h2 style="margin-bottom:20px;color:#0d47a1;"><?= htmlspecialchars(formatReportTypeLabel($report_type)) ?></h2>

<div class="report-switch">
    <a class="report-link <?= $report_type === 'progress' ? 'active' : '' ?>" href="admin_project_report.php?type=progress">Project Progress</a>
    <a class="report-link <?= $report_type === 'financial' ? 'active' : '' ?>" href="admin_project_report.php?type=financial">Financial Budgets</a>
</div>

<div class="export-bar">
    <div>
        <strong>Export <?= htmlspecialchars(formatReportTypeLabel($report_type)) ?></strong><br>
        <small><?= $report_type === 'financial' ? 'Budget control, expenses, and overrun tracking.' : 'Project completion, active work, and overdue progress tracking.' ?></small>
    </div>
    <div class="export-actions">
        <a href="export_report_excel.php?type=<?= urlencode($report_type) ?>" class="btn btn-excel">Export Excel</a>
        <a href="export_report_pdf.php?type=<?= urlencode($report_type) ?>" target="_blank" class="btn btn-pdf">Export PDF</a>
    </div>
</div>

<div class="summary-grid">
    <?php foreach ($summary_cards as $card): ?>
        <div class="summary-card">
            <strong><?= htmlspecialchars($card['label']) ?></strong>
            <div><?= htmlspecialchars((string) $card['value']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($report_type === 'progress'): ?>
    <div id="projectMap"></div>
    <table class="dashboard-table">
        <tr>
            <th>Project ID</th>
            <th>Project</th>
            <th>District</th>
            <th>Location</th>
            <th>Project Lead</th>
            <th>Status</th>
            <th>Status Items</th>
            <th>Progress</th>
            <th>Overdue Items</th>
        </tr>
        <?php if (count($report_rows) === 0): ?>
            <tr>
                <td colspan="9" style="text-align:center;color:gray;">No projects available for reporting yet.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($report_rows as $row): ?>
            <tr>
                <td><a href="admin_project_details.php?id=<?= $row['id'] ?>"><?= formatProjectCode($row['id']) ?></a></td>
                <td><a href="admin_project_details.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
                <td><?= htmlspecialchars($row['district']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['field_officer']) ?></td>
                <td><?= htmlspecialchars($row['final_status']) ?></td>
                <td>
                    Total: <?= (int) $row['total_items'] ?><br>
                    Completed: <?= (int) $row['completed_items'] ?><br>
                    Active: <?= (int) $row['active_items'] ?>
                </td>
                <td><?= (int) $row['progress_percent'] ?>%</td>
                <td><?= (int) $row['overdue_items'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <table class="dashboard-table">
        <tr>
            <th>Project ID</th>
            <th>Project</th>
            <th>Project Lead</th>
            <th>Status</th>
            <th>Total Budget</th>
            <th>Allocated to Status Items</th>
            <th>Recorded Expenses</th>
            <th>Remaining Budget</th>
            <th>Expense Entries</th>
            <th>Alert</th>
        </tr>
        <?php if (count($report_rows) === 0): ?>
            <tr>
                <td colspan="10" style="text-align:center;color:gray;">No projects available for financial reporting yet.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($report_rows as $row): ?>
            <tr>
                <td><a href="admin_project_details.php?id=<?= $row['id'] ?>"><?= formatProjectCode($row['id']) ?></a></td>
                <td><a href="admin_project_details.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
                <td><?= htmlspecialchars($row['field_officer']) ?></td>
                <td><?= htmlspecialchars($row['final_status']) ?></td>
                <td>MWK <?= number_format((float) $row['total_budget'], 2) ?></td>
                <td>MWK <?= number_format((float) $row['allocated_total'], 2) ?></td>
                <td>MWK <?= number_format((float) $row['spent_total'], 2) ?></td>
                <td style="color:<?= $row['remaining_budget'] < 0 ? '#d32f2f' : '#2e7d32' ?>;">
                    MWK <?= number_format((float) $row['remaining_budget'], 2) ?>
                </td>
                <td><?= (int) $row['expense_count'] ?></td>
                <td>
                    <?= htmlspecialchars($row['alert_status']) ?><br>
                    <small>Over-budget status items: <?= (int) $row['over_budget_items'] ?></small>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

</div>
</div>
</div>

<?php include "footer.php"; ?>

<?php if ($report_type === 'progress'): ?>
<script>
const mapData = <?= json_encode($mapData) ?>;
const map = L.map('projectMap').setView([-13.2543, 34.3015], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
mapData.forEach(function (project) {
    L.marker([project.lat, project.lng]).addTo(map)
        .bindPopup('<b>' + project.title + '</b><br>' + project.district + '<br>' + project.location);
});
</script>
<?php endif; ?>

</body>
</html>
