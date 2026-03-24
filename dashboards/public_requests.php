<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

$userStmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$district = $user['location'] ?? 'Unknown';

$areasByDistrict = [
    "Blantyre" => ["Ndirande", "Chilomoni", "Machinjiri", "Bangwe", "Limbe", "Kameza", "Soche", "Chichiri"],
    "Lilongwe" => ["Area 18", "Area 23", "Area 25", "Kawale", "Chinsapo"],
    "Mzuzu" => ["Chibanja", "Zolozolo", "Masasa"],
    "Zomba" => ["Likangala", "Sadzi", "Chinamwali"],
    "Kasungu" => ["New Lines", "Lukongwe"],
    "Mangochi" => ["Monkey Bay", "Namwera"],
    "Salima" => ["Chipoka", "Lifuwu"],
    "Dedza" => ["Lobi", "Mtakataka"],
    "Ntcheu" => ["Tsangano"],
    "Karonga" => ["Wiliro Beach"],
    "Mzimba" => ["Euthini"],
    "Balaka" => ["Ulongwe"]
];

$availableAreas = $areasByDistrict[$district] ?? [];

if (isset($_POST['submit'])) {
    if (!isValidCsrfToken('public_request_form', $_POST['_csrf_token'] ?? '')) {
        $error = "Your session expired. Please submit the request again.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $area = trim($_POST['area'] ?? '');

        if ($title === '' || $desc === '' || !in_array($area, $availableAreas, true)) {
            $error = "Fill in the request form correctly before submitting.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO community_requests (user_id, district, area, title, description, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("issss", $user_id, $district, $area, $title, $desc);
            $stmt->execute();

            createRoleNotifications(
                $conn,
                'admin',
                'community_request_submitted',
                'New Community Request Submitted',
                "{$title} was submitted from {$district} - {$area} and is waiting for review.",
                'admin_community_requests.php?status=pending'
            );

            $message = "Request submitted successfully and awaiting review.";
        }
    }
}

$requests = $conn->prepare("
    SELECT cr.*, reviewer.username AS reviewed_by_name
    FROM community_requests cr
    LEFT JOIN users reviewer ON reviewer.id = cr.reviewed_by
    WHERE cr.user_id = ?
    ORDER BY cr.created_at DESC
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
        <div class="form-card">
            <h3>Community Needs / Requests</h3>

            <?php if ($message): ?>
                <div class="msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="msg error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfInput('public_request_form') ?>
                <label>Request Title</label>
                <input type="text" name="title" required>

                <label>District</label>
                <input type="text" value="<?= htmlspecialchars($district) ?>" readonly>

                <label>Area / Location</label>
                <select name="area" required>
                    <option value="">-- Select Area --</option>
                    <?php foreach ($availableAreas as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Description</label>
                <textarea name="description" required></textarea>

                <br>
                <input type="submit" name="submit" value="Submit Request">
            </form>
        </div>

        <div class="form-card">
            <h3>My Submitted Requests</h3>

            <?php if ($requests->num_rows == 0): ?>
                <p>No requests submitted yet.</p>
            <?php endif; ?>

            <?php while ($r = $requests->fetch_assoc()): ?>
                <div class="public-comment">
                    <strong><?= htmlspecialchars($r['title']) ?></strong><br>
                    <?= nl2br(htmlspecialchars($r['description'])) ?><br><br>

                    <small>
                        <?= htmlspecialchars($r['district']) ?> - <?= htmlspecialchars($r['area']) ?><br>
                        Status:
                        <?= $r['status'] === 'reviewed'
                            ? "<span class='status-approved'>Reviewed</span>"
                            : "<span class='status-pending'>Pending</span>" ?>
                        <?php if ($r['status'] === 'reviewed' && !empty($r['reviewed_at'])): ?>
                            <br>Reviewed On: <?= date("d M Y, H:i", strtotime($r['reviewed_at'])) ?>
                            <?php if (!empty($r['reviewed_by_name'])): ?>
                                <br>Reviewed By: <?= htmlspecialchars($r['reviewed_by_name']) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </small>

                    <?php if (!empty($r['review_notes'])): ?>
                        <div class="public-reply" style="margin-top:10px;">
                            <strong>Review Note:</strong><br>
                            <?= nl2br(htmlspecialchars($r['review_notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
