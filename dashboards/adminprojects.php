<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$status = $_GET['status'] ?? 'pending';
$allowed_statuses = ['all', 'pending', 'approved', 'in_progress', 'completed', 'denied'];

if (!in_array($status, $allowed_statuses, true)) {
    $status = 'pending';
}

$baseSql = "
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
LEFT JOIN project_maps pm ON pm.project_id = p.id
LEFT JOIN contractor_projects cp ON cp.project_id = p.id
LEFT JOIN contractors c ON c.id = cp.contractor_id
";

$result = false;
$query_error = '';

if ($status === 'all') {
    $result = $conn->query($baseSql . " ORDER BY p.created_at DESC");
} else {
    $stmt = $conn->prepare($baseSql . " WHERE p.status = ? ORDER BY p.created_at DESC");
    if ($stmt) {
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

if (!$result) {
    $query_error = 'Projects could not be loaded right now.';
}

$heading_map = [
    'all' => 'All Projects',
    'pending' => 'Pending Project Approvals',
    'approved' => 'Approved Projects',
    'in_progress' => 'Projects In Progress',
    'completed' => 'Completed Projects',
    'denied' => 'Denied Projects'
];

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $heading_map[$status] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Project Review Queue</span>
                    <h3><?= $heading_map[$status] ?></h3>
                    <p>Every project stays tied to the project lead who created it, so approvals, status changes, and review history remain centralized and clear.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $status))) ?></strong>&nbsp; Filter</div>
                    <div class="hero-pill"><strong>Admin</strong>&nbsp; Oversight</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($success_message): ?>
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if ($query_error): ?>
                <div class="msg error"><?= htmlspecialchars($query_error) ?></div>
            <?php endif; ?>

            <div class="toolbar">
                <?php foreach ($allowed_statuses as $filter): ?>
                    <a class="toolbar-link <?= $status === $filter ? 'active' : '' ?>" href="adminprojects.php?status=<?= urlencode($filter) ?>">
                        <?= htmlspecialchars($heading_map[$filter]) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <p class="section-copy">Review project ownership, location, contractor linkage, budget size, and attached documents without losing the approval workflow context.</p>

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

                    <?php if (!$result || $result->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" style="text-align:center;color:gray;">No projects found for this filter.</td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($result && ($row = $result->fetch_assoc())): ?>
                        <?php
                        $total_budget = ($row['estimated_budget'] ?? 0) + ($row['contractor_fee'] ?? 0);
                        $mapLat = normalizeCoordinate($row['latitude'], -90, 90);
                        $mapLng = normalizeCoordinate($row['longitude'], -180, 180);
                        ?>
                        <tr>
                            <td><a href="admin_project_details.php?id=<?= $row['project_id'] ?>"><?= formatProjectCode($row['project_id']) ?></a></td>
                            <td><a href="admin_project_details.php?id=<?= $row['project_id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
                            <td><?= htmlspecialchars($row['district']) ?></td>
                            <td><span class="status-badge <?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(formatStatusLabel($row['status'])) ?></span></td>
                            <td>
                                <?php if ($mapLat !== null && $mapLng !== null): ?>
                                    <div
                                        id="map-<?= $row['project_id'] ?>"
                                        class="map-box"
                                        data-lat="<?= htmlspecialchars($mapLat) ?>"
                                        data-lng="<?= htmlspecialchars($mapLng) ?>">
                                    </div>
                                <?php else: ?>
                                    <span class="muted">No location</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['field_officer']) ?></td>
                            <td>
                                <?php if ($row['contractor_name']): ?>
                                    <?= htmlspecialchars($row['contractor_name']) ?><br>
                                    <small><?= htmlspecialchars($row['contractor_phone']) ?></small>
                                <?php else: ?>
                                    <span class="muted">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($total_budget, 2) ?></td>
                            <td>
                                <?php if (!empty($row['document'])): ?>
                                    <a href="project_document.php?id=<?= $row['project_id'] ?>" target="_blank" rel="noopener noreferrer">View Document</a>
                                <?php else: ?>
                                    <span class="muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <a href="admin_project_details.php?id=<?= $row['project_id'] ?>">View</a>
                                    |
                                    <a href="admin_update_project_status.php?id=<?= $row['project_id'] ?>&status=approved&return_to=projects">Approve</a>
                                    |
                                    <a href="admin_update_project_status.php?id=<?= $row['project_id'] ?>&status=denied&return_to=projects" onclick="return confirm('Are you sure you want to deny this project?');">Deny</a>
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
    const lat = parseFloat(mapDiv.dataset.lat);
    const lng = parseFloat(mapDiv.dataset.lng);
    const map = L.map(mapDiv).setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
});
</script>

</body>
</html>
