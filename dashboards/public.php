<?php
session_start();
require "../config/db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ================= USER LOCATION ================= */
$userStmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$user_location = $user['location'];

/* ================= DASHBOARD COUNTS ================= */

// Projects in user's location
$projectStmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM projects 
    WHERE LOWER(district) = LOWER(?)
");
$projectStmt->bind_param("s", $user_location);
$projectStmt->execute();
$total_projects = $projectStmt->get_result()->fetch_assoc()['total'];

// Community requests in user's city
$requestStmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM community_requests 
    WHERE LOWER(district) = LOWER(?)
");
$requestStmt->bind_param("s", $user_location);
$requestStmt->execute();
$community_requests = $requestStmt->get_result()->fetch_assoc()['total'];

// Admin replies to user's comments
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
    <link rel="stylesheet" href="../assets/css/flexible.css">

    <style>
        .simple-dashboard {
            max-width: 900px;
            margin: auto;
        }

        .simple-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .simple-card h1 {
            margin: 0;
            font-size: 32px;
            color: #0c90b4;
        }

        .simple-card p {
            margin-top: 8px;
            color: #555;
            font-size: 15px;
        }
    </style>
</head>

<body>

<?php include "header.php"; ?>

<div class="row">

    <!-- MENU -->
    <div class="col-3">
        <?php include "publicmenu.php"; ?>
    </div>

    <!-- DASHBOARD CONTENT -->
    <div class="col-9 simple-dashboard">

        <div class="row">

            <div class="col-4">
                <div class="simple-card">
                    <h1><?= $total_projects ?></h1>
                    <p>🏗 Projects in <?= htmlspecialchars($user_location) ?></p>
                </div>
            </div>

            <div class="col-4">
                <div class="simple-card">
                    <h1><?= $community_requests ?></h1>
                    <p>📢 Community Requests</p>
                </div>
            </div>

            <div class="col-4">
                <div class="simple-card">
                    <h1><?= $admin_replies ?></h1>
                    <p>💬 Admin Replies to You</p>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
