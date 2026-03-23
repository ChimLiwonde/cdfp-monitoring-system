<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* ======================= SECURITY ======================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

/* ======================= FETCH PROJECTS ======================= */
$status = $_GET['status'] ?? 'pending';
$allowed_statuses = ['all', 'pending', 'approved', 'in_progress', 'completed', 'denied'];

if (!in_array($status, $allowed_statuses, true)) {
    $status = 'pending';
}

$sql = "
SELECT 
    p.id AS project_id,
    p.title,
    p.district,
    p.status,
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
";

if ($status !== 'all') {
    $safe_status = $conn->real_escape_string($status);
    $sql .= " WHERE p.status = '{$safe_status}'";
}

$sql .= " ORDER BY p.created_at DESC";

$result = $conn->query($sql);
if (!$result) {
    die("SQL ERROR: " . $conn->error);
}

$heading_map = [
    'all' => 'All Projects',
    'pending' => 'Pending Project Approvals',
    'approved' => 'Approved Projects',
    'in_progress' => 'Projects In Progress',
    'completed' => 'Completed Projects',
    'denied' => 'Denied Projects'
];

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $heading_map[$status] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/flexible.css">
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
            <h3><?= $heading_map[$status] ?></h3>

            <?php if ($success_message): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <p>
                <a href="adminprojects.php?status=all">All</a> |
                <a href="adminprojects.php?status=pending">Pending</a> |
                <a href="adminprojects.php?status=approved">Approved</a> |
                <a href="adminprojects.php?status=in_progress">In Progress</a> |
                <a href="adminprojects.php?status=completed">Completed</a> |
                <a href="adminprojects.php?status=denied">Denied</a>
            </p>

            <p style="margin-bottom: 15px; color: #555;">
                Each project remains linked to the project lead who created it, so approval history stays tied to one owner.
            </p>

            <div class="table-wrap">
                <table class="dashboard-table">
                    <tr>
                        <th>Project ID</th>
                        <th>Project</th>
                        <th>District</th>
                        <th>Status</th>
                        <th>Map Location</th>
                        <th>Project Lead</th>
                        <th>Contractor</th>
                        <th>Total Budget (MWK)</th>
                        <th>Document</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" style="text-align:center;color:gray;">
                                No projects found for this filter
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($row = $result->fetch_assoc()):
                        $total_budget = ($row['estimated_budget'] ?? 0) + ($row['contractor_fee'] ?? 0);
                    ?>
                    <tr>
                        <td><a href="admin_project_details.php?id=<?= $row['project_id'] ?>"><?= formatProjectCode($row['project_id']) ?></a></td>
                        <td><a href="admin_project_details.php?id=<?= $row['project_id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
                        <td><?= htmlspecialchars($row['district']) ?></td>
                        <td><?= htmlspecialchars(formatStatusLabel($row['status'])) ?></td>
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
                            <?php if ($row['status'] === 'pending'): ?>
                                <a href="admin_project_details.php?id=<?= $row['project_id'] ?>">
                                    View
                                </a>
                                |
                                <a href="admin_update_project_status.php?id=<?= $row['project_id'] ?>&status=approved&return_to=projects">
                                    Approve
                                </a>
                                |
                                <a href="admin_update_project_status.php?id=<?= $row['project_id'] ?>&status=denied&return_to=projects"
                                   onclick="return confirm('Are you sure you want to deny this project?');">
                                    Deny
                                </a>
                            <?php else: ?>
                                <a href="admin_project_details.php?id=<?= $row['project_id'] ?>">View Details</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                </table>
            </div>

        </div>
    </div>
</div>

<?php include "footer.php"; ?>

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
