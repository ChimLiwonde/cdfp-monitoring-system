<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$request_id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($request_id <= 0) {
    die("Invalid request ID");
}

$stmt = $conn->prepare("
    SELECT
        cr.id,
        cr.title,
        cr.description,
        cr.area,
        cr.district,
        cr.status,
        cr.review_notes,
        cr.user_id,
        u.username
    FROM community_requests cr
    JOIN users u ON u.id = cr.user_id
    WHERE cr.id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    die("Community request not found");
}

if ($request['status'] !== 'pending') {
    $_SESSION['success_message'] = "Only pending community requests can be reviewed.";
    header("Location: admin_community_requests.php");
    exit();
}

$reviewNotes = trim($_POST['review_notes'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken('review_community_request_form', $_POST['_csrf_token'] ?? '')) {
        $error = "Your session expired. Please open the review form again.";
    } else {
        $update = $conn->prepare("
            UPDATE community_requests
            SET status = 'reviewed', review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $update->bind_param("sii", $reviewNotes, $adminUserId, $request_id);
        $update->execute();

        createUserNotification(
            $conn,
            (int) $request['user_id'],
            'community_request_reviewed',
            'Community Request Reviewed',
            "Your community request \"" . $request['title'] . "\" was reviewed." . ($reviewNotes !== '' ? "\n\nReview note:\n" . $reviewNotes : ''),
            'public_requests.php'
        );

        $_SESSION['success_message'] = "Community request marked as reviewed.";
        header("Location: admin_community_requests.php?status=reviewed");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Review Community Request</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>
    <div class="col-9">
        <div class="form-card">
            <a href="admin_community_requests.php" class="back-btn">Back to Community Requests</a>

            <h3>Review Community Request</h3>
            <p><strong>Citizen:</strong> <?= htmlspecialchars($request['username']) ?></p>
            <p><strong>Title:</strong> <?= htmlspecialchars($request['title']) ?></p>
            <p><strong>District:</strong> <?= htmlspecialchars($request['district']) ?></p>
            <p><strong>Area:</strong> <?= htmlspecialchars($request['area']) ?></p>
            <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($request['description'])) ?></p>

            <?php if ($error !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfInput('review_community_request_form') ?>
                <input type="hidden" name="id" value="<?= $request['id'] ?>">

                <label>Review Note (Optional)</label>
                <textarea name="review_notes" style="width:100%;min-height:140px;padding:12px;border:1px solid #90caf9;border-radius:8px;"><?= htmlspecialchars($reviewNotes) ?></textarea>

                <br>
                <input type="submit" value="Mark Reviewed">
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
