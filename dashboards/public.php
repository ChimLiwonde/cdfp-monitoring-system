<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$userStmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$user_location = $user['location'];

$projectStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM projects
    WHERE LOWER(district) = LOWER(?)
");
$projectStmt->bind_param("s", $user_location);
$projectStmt->execute();
$total_projects = $projectStmt->get_result()->fetch_assoc()['total'];

$requestStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM community_requests
    WHERE LOWER(district) = LOWER(?)
");
$requestStmt->bind_param("s", $user_location);
$requestStmt->execute();
$community_requests = $requestStmt->get_result()->fetch_assoc()['total'];

$replyStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM project_comments
    WHERE user_id = ?
    AND admin_reply IS NOT NULL
");
$replyStmt->bind_param("i", $user_id);
$replyStmt->execute();
$admin_replies = $replyStmt->get_result()->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Public Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>

<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3">
        <?php include "publicmenu.php"; ?>
    </div>

    <div class="col-9 simple-dashboard">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Citizen Overview</span>
                    <h3>Stay informed about projects and responses in <?= htmlspecialchars($user_location) ?>.</h3>
                    <p>Use this panel to keep up with district projects, community requests, and replies from administrators without losing context.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $total_projects ?></strong>&nbsp; Local Projects</div>
                    <div class="hero-pill"><strong><?= $community_requests ?></strong>&nbsp; Requests</div>
                    <div class="hero-pill"><strong><?= $admin_replies ?></strong>&nbsp; Replies</div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="simple-card">
                <span class="section-kicker">District Projects</span>
                <h1><?= $total_projects ?></h1>
                <p>Projects currently tied to <strong><?= htmlspecialchars($user_location) ?></strong>.</p>
            </div>

            <div class="simple-card">
                <span class="section-kicker">Community Requests</span>
                <h1><?= $community_requests ?></h1>
                <p>Requests submitted in your district that help show local needs and priorities.</p>
            </div>

            <div class="simple-card">
                <span class="section-kicker">Admin Replies</span>
                <h1><?= $admin_replies ?></h1>
                <p>Responses already sent back to your project comments and questions.</p>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Quick Actions</span>
                    <h3>What you can do next</h3>
                </div>
                <p>Follow projects in your city, submit requests, and check your replies from one citizen panel.</p>
            </div>
            <div class="chip-list">
                <span class="chip">View City Projects</span>
                <span class="chip">Submit Community Requests</span>
                <span class="chip">Read Notifications</span>
                <span class="chip">Track Admin Replies</span>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
