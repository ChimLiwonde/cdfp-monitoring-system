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
    $_SESSION['error_message'] = "Invalid community request selected.";
    header("Location: admin_community_requests.php");
    exit();
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
    $_SESSION['error_message'] = "Community request not found.";
    header("Location: admin_community_requests.php");
    exit();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>
    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Request Review</span>
                    <h3>Review <?= htmlspecialchars($request['title']) ?></h3>
                    <p>Confirm the citizen request details below, add a clear review note if needed, and then mark the request as reviewed.</p>
                    <div class="hero-actions">
                        <a href="admin_community_requests.php?status=pending" class="back-btn">Back to Pending Requests</a>
                    </div>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= htmlspecialchars($request['district']) ?></strong>&nbsp; District</div>
                    <div class="hero-pill"><strong><?= htmlspecialchars($request['area']) ?></strong>&nbsp; Area</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Citizen Request</span>
                    <h3>Request Summary</h3>
                </div>
                <p>Everything you need for the review is visible here before you send a note back to the citizen.</p>
            </div>

            <div class="detail-grid">
                <div class="detail-card">
                    <strong>Citizen</strong>
                    <span><?= htmlspecialchars($request['username']) ?></span>
                </div>
                <div class="detail-card">
                    <strong>District</strong>
                    <span><?= htmlspecialchars($request['district']) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Area</strong>
                    <span><?= htmlspecialchars($request['area']) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Status</strong>
                    <span class="status-badge pending">Pending Review</span>
                </div>
            </div>

            <div class="detail-card space-top-sm">
                <strong>Description</strong>
                <span><?= nl2br(htmlspecialchars($request['description'])) ?></span>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Review Note</span>
                    <h3>Complete Review</h3>
                </div>
                <p>Add an optional note to guide the citizen, then mark this request as reviewed.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfInput('review_community_request_form') ?>
                <input type="hidden" name="id" value="<?= $request['id'] ?>">

                <label>Review Note (Optional)</label>
                <textarea name="review_notes"><?= htmlspecialchars($reviewNotes) ?></textarea>

                <div class="hero-actions">
                    <input type="submit" value="Mark Reviewed">
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
