<?php
session_start();
require "../config/db.php";

/* ======================= SECURITY ======================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ======================= FETCH PENDING PROJECTS ======================= */
$sql = "
SELECT 
    p.id AS project_id,
    p.title,
    p.district,
    p.estimated_budget,
    p.contractor_fee,
    p.document,

    u.username AS field_officer,

    pm.latitude,
    pm.longitude,

    c.name AS contractor_name,
    c.phone AS contractor_phone

FROM projects p
JOIN users u ON u.id = p.created_by

LEFT JOIN project_maps pm 
    ON pm.project_id = p.id

LEFT JOIN contractor_projects cp 
    ON cp.project_id = p.id

LEFT JOIN contractors c 
    ON c.id = cp.contractor_id

WHERE p.status = 'pending'
ORDER BY p.created_at DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("SQL ERROR: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pending Project Approvals</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/flexible.css">

    <!-- LEAFLET -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

    <style>
        .map-box {
            width: 220px;
            height: 150px;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9">
        <div class="form-card">
            <h3>Pending Project Approvals</h3>

            <div class="table-wrap">
                <table class="dashboard-table">
                    <tr>
                        <th>ID</th>
                        <th>Project</th>
                        <th>District</th>
                        <th>Map Location</th>
                        <th>Field Officer</th>
                        <th>Contractor</th>
                        <th>Total Budget (MWK)</th>
                        <th>Document</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;color:gray;">
                                No pending projects
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($row = $result->fetch_assoc()):
                        $total_budget = 
                            ($row['estimated_budget'] ?? 0) + 
                            ($row['contractor_fee'] ?? 0);
                    ?>
                    <tr>
                        <td><?= $row['project_id'] ?></td>

                        <td><?= htmlspecialchars($row['title']) ?></td>

                        <td><?= htmlspecialchars($row['district']) ?></td>

                        <!-- MAP COLUMN -->
                        <td>
                            <?php if ($row['latitude'] && $row['longitude']): ?>
                                <div 
                                    id="map-<?= $row['project_id'] ?>" 
                                    class="map-box"
                                    data-lat="<?= $row['latitude'] ?>"
                                    data-lng="<?= $row['longitude'] ?>">
                                </div>
                            <?php else: ?>
                                <span style="color:gray;">No location</span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($row['field_officer']) ?></td>

                        <td>
                            <?php if ($row['contractor_name']): ?>
                                <?= htmlspecialchars($row['contractor_name']) ?><br>
                                <small><?= htmlspecialchars($row['contractor_phone']) ?></small>
                            <?php else: ?>
                                <span style="color:gray;">Not Assigned</span>
                            <?php endif; ?>
                        </td>

                        <td><?= number_format($total_budget, 2) ?></td>

                        <td>
                            <?php if (!empty($row['document'])): ?>
                                <a href="../uploads/<?= urlencode($row['document']) ?>" target="_blank">
                                    View
                                </a>
                            <?php else: ?>
                                <span style="color:gray;">None</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a href="admin_update_project_status.php?id=<?= $row['project_id'] ?>&status=approved">
                                Approve
                            </a>
                            |
                            <a href="admin_update_project_status.php?id=<?= $row['project_id'] ?>&status=denied"
                               onclick="return confirm('Are you sure you want to deny this project?');">
                                Deny
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                </table>
            </div>

        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<!-- LEAFLET JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
document.querySelectorAll('.map-box').forEach(function (mapDiv) {

    let lat = mapDiv.dataset.lat;
    let lng = mapDiv.dataset.lng;

    let map = L.map(mapDiv).setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
});
</script>

</body>
</html>
