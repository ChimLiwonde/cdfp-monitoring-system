<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* SECURITY */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../Pages/login.php");
    exit();
}

$project_id = intval($_GET['id'] ?? 0);
if ($project_id <= 0) {
    header("Location: my_projects.php");
    exit();
}

/* =========================
   FETCH PROJECT
========================= */
$project = $conn->query("
    SELECT id, title, district, location, estimated_budget, contractor_fee
    FROM projects
    WHERE id = $project_id
")->fetch_assoc();

if (!$project) {
    header("Location: my_projects.php");
    exit();
}

/* =========================
   FETCH ASSIGNED CONTRACTORS
========================= */
$contractors = $conn->query("
    SELECT c.name, c.phone, c.company
    FROM contractor_projects cp
    JOIN contractors c ON cp.contractor_id = c.id
    WHERE cp.project_id = $project_id
");

/* =========================
   STAGE FINANCIAL TOTALS
========================= */
$totals = $conn->query("
    SELECT 
        COALESCE(SUM(allocated_budget),0) AS allocated,
        COALESCE(SUM(spent_budget),0) AS spent
    FROM project_stages
    WHERE project_id = $project_id
")->fetch_assoc();

$allocated = $totals['allocated'];
$spent     = $totals['spent'];
$balance   = $allocated - $spent;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Summary</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "menu.php"; ?></div>

<div class="col-9">
<div class="form-card">

    <a href="my_projects.php" class="back-btn">Back to My Projects</a>

    <h3><?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?> Summary</h3>

    <p><strong>Project ID:</strong> <?= formatProjectCode($project['id']) ?></p>
    <p><strong>District:</strong> <?= htmlspecialchars($project['district']) ?></p>
    <p><strong>Location:</strong> <?= htmlspecialchars($project['location']) ?></p>

    <hr>

    <h4>Assigned Contractor(s)</h4>

    <?php if ($contractors->num_rows > 0): ?>
        <ul>
            <?php while ($c = $contractors->fetch_assoc()): ?>
                <li>
                    <strong><?= htmlspecialchars($c['name']) ?></strong><br>
                    Company: <?= htmlspecialchars($c['company']) ?><br>
                    Phone: <?= htmlspecialchars($c['phone']) ?>
                </li>
                <br>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p style="color:#999;">No contractor assigned</p>
    <?php endif; ?>

    <hr>

    <h4>Financial Summary</h4>
    <p><strong>Estimated Budget:</strong> MWK <?= number_format($project['estimated_budget'],2) ?></p>
    <p><strong>Contractor Fee:</strong> MWK <?= number_format($project['contractor_fee'],2) ?></p>

    <p><strong>Total Allocated (Status Items):</strong> MWK <?= number_format($allocated,2) ?></p>
    <p><strong>Total Spent:</strong> MWK <?= number_format($spent,2) ?></p>

    <?php if ($spent > $allocated): ?>
        <p style="color:red;"><strong>Overspent by:</strong> MWK <?= number_format(abs($balance),2) ?></p>
    <?php else: ?>
        <p style="color:green;"><strong>Within Budget</strong></p>
    <?php endif; ?>

    <hr>

    <h4>Project Status History</h4>
    <table class="dashboard-table">
        <tr>
            <th>Status Item</th>
            <th>Allocated</th>
            <th>Spent</th>
            <th>Status</th>
        </tr>

        <?php
        $stages = $conn->query("
            SELECT stage_name, allocated_budget, spent_budget, status
            FROM project_stages
            WHERE project_id = $project_id
        ");

        while ($s = $stages->fetch_assoc()):
        ?>
        <tr>
            <td><?= htmlspecialchars($s['stage_name']) ?></td>
            <td><?= number_format($s['allocated_budget'],2) ?></td>
            <td><?= number_format($s['spent_budget'],2) ?></td>
            <td><?= ucfirst($s['status']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

</div>
</div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
