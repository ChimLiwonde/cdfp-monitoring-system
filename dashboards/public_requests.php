<?php
session_start();
require "../config/db.php";

/* =====================
   SECURITY
===================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

/* =====================
   GET USER DISTRICT
===================== */
$userStmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$district = $user['location'] ?? 'Unknown';

/* =====================
   DISTRICT → AREAS MAP
===================== */
$areasByDistrict = [
    "Blantyre" => ["Ndirande", "Chilomoni", "Machinjiri", "Bangwe", "Limbe", "Kameza", "Soche", "Chichiri"],
    "Lilongwe" => ["Area 18", "Area 23", "Area 25", "Kawale", "Chinsapo"],
    "Mzuzu"    => ["Chibanja", "Zolozolo", "Masasa"],
    "Zomba"    => ["Likangala", "Sadzi", "Chinamwali"],
    "Kasungu"  => ["New Lines", "Lukongwe"],
    "Mangochi" => ["Monkey Bay", "Namwera"],
    "Salima"   => ["Chipoka", "Lifuwu"],
    "Dedza"    => ["Lobi", "Mtakataka"],
    "Ntcheu"   => ["Tsangano"],
    "Karonga"  => ["Wiliro Beach"],
    "Mzimba"   => ["Euthini"],
    "Balaka"   => ["Ulongwe"]
];

$availableAreas = $areasByDistrict[$district] ?? [];

/* =====================
   SUBMIT REQUEST
===================== */
if (isset($_POST['submit'])) {
    $title = $_POST['title'];
    $desc  = $_POST['description'];
    $area  = $_POST['area'];

    $stmt = $conn->prepare("
        INSERT INTO community_requests (user_id, district, area, title, description, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("issss", $user_id, $district, $area, $title, $desc);
    $stmt->execute();

    $message = "✅ Request submitted successfully and awaiting review";
}

/* =====================
   FETCH USER REQUESTS
===================== */
$requests = $conn->prepare("
    SELECT * FROM community_requests
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$requests->bind_param("i", $user_id);
$requests->execute();
$requests = $requests->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>Community Requests</title>
<link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "publicmenu.php"; ?></div>

    <div class="col-9 public-dashboard">

        <!-- SUBMIT REQUEST -->
        <div class="form-card">
            <h3>📢 Community Needs / Requests</h3>

            <?php if ($message): ?>
                <div class="msg"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">

                <label>Request Title</label>
                <input type="text" name="title" required>

                <label>District</label>
                <input type="text" value="<?= htmlspecialchars($district) ?>" readonly>

                <label>Area / Location</label>
                <select name="area" required>
                    <option value="">-- Select Area --</option>
                    <?php foreach ($availableAreas as $a): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Description</label>
                <textarea name="description" required></textarea>

                <br>
                <input type="submit" name="submit" value="Submit Request">
            </form>
        </div>

        <!-- MY REQUESTS -->
        <div class="form-card">
            <h3>📄 My Submitted Requests</h3>

            <?php if ($requests->num_rows == 0): ?>
                <p>No requests submitted yet.</p>
            <?php endif; ?>

            <?php while ($r = $requests->fetch_assoc()): ?>
                <div class="public-comment">

                    <strong><?= htmlspecialchars($r['title']) ?></strong><br>

                    <?= nl2br(htmlspecialchars($r['description'])) ?><br><br>

                    <small>
                        📍 <?= htmlspecialchars($r['district']) ?> — <?= htmlspecialchars($r['area']) ?><br>
                        Status:
                        <?= $r['status'] === 'reviewed'
                            ? "<span class='status-approved'>Reviewed</span>"
                            : "<span class='status-pending'>Pending</span>" ?>
                    </small>

                </div>
            <?php endwhile; ?>
        </div>

    </div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
