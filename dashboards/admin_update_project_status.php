<?php
session_start();
require "../config/db.php";
require "../config/mail.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$status = $_POST['status'] ?? $_GET['status'] ?? '';
$return_to = $_POST['return_to'] ?? $_GET['return_to'] ?? 'projects';
$redirectUrl = ($return_to === 'detail')
    ? "admin_project_details.php?id={$id}"
    : "adminprojects.php?status=all";

$allowed = ['approved', 'denied'];
if ($id <= 0 || !in_array($status, $allowed, true)) {
    header("Location: adminprojects.php?status=all");
    exit();
}

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.title,
        p.status AS current_status,
        p.review_notes,
        p.created_by,
        u.email,
        u.username
    FROM projects p
    JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    die("Project not found.");
}

if ($project['current_status'] !== 'pending') {
    $_SESSION['success_message'] = "Only pending projects can be approved or denied.";
    header("Location: " . $redirectUrl);
    exit();
}

$statusLabel = formatStatusLabel($status);
$statusVerb = $status === 'approved' ? 'approve' : 'deny';
$error = '';
$reviewNotes = trim($_POST['review_notes'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($status === 'denied' && $reviewNotes === '') {
        $error = "A review note is required when denying a project.";
    } else {
        $update = $conn->prepare("
            UPDATE projects
            SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
        $update->bind_param("ssii", $status, $reviewNotes, $adminUserId, $id);
        $update->execute();

        $activityNotes = "Project status updated to {$statusLabel} by admin.";
        if ($reviewNotes !== '') {
            $activityNotes .= " Review note: {$reviewNotes}";
        }

        logProjectActivity(
            $conn,
            $id,
            'project_status_changed',
            $adminUserId,
            $_SESSION['role'] ?? 'admin',
            $project['current_status'],
            $status,
            $activityNotes
        );

        $notificationTitle = "Project " . formatProjectCode($id) . " {$statusLabel}";
        $notificationMessage = "Your project \"" . $project['title'] . "\" was {$statusLabel}.";
        if ($reviewNotes !== '') {
            $notificationMessage .= "\n\nAdmin review note:\n" . $reviewNotes;
        }

        createUserNotification(
            $conn,
            (int) $project['created_by'],
            'project_reviewed',
            $notificationTitle,
            $notificationMessage,
            "view_project_details.php?id={$id}"
        );

        $subject = "Project " . formatProjectCode($id) . " {$statusLabel}";
        $body = "
            <h3>Hello {$project['username']},</h3>
            <p>Your project <b>" . formatProjectCode($id) . " - {$project['title']}</b> has been <b>{$statusLabel}</b>.</p>
            " . ($reviewNotes !== '' ? "<p><b>Admin review note:</b><br>" . nl2br(htmlspecialchars($reviewNotes)) . "</p>" : "") . "
            <p>Regards,<br>CDF Admin</p>
        ";

        sendEmail($project['email'], $subject, $body);

        $_SESSION['success_message'] = "Project " . formatProjectCode($id) . " marked as {$statusLabel}.";
        header("Location: " . $redirectUrl);
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($statusLabel) ?> Project</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>
    <div class="col-9">
        <div class="form-card">
            <a href="<?= htmlspecialchars($redirectUrl) ?>" class="back-btn">Back</a>

            <h3><?= htmlspecialchars($statusLabel) ?> Project Review</h3>
            <p><strong>Project:</strong> <?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?></p>
            <p><strong>Project Lead:</strong> <?= htmlspecialchars($project['username']) ?></p>
            <p><strong>Current Status:</strong> <?= htmlspecialchars(formatStatusLabel($project['current_status'])) ?></p>

            <?php if ($error !== ''): ?>
                <div class="msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id" value="<?= $project['id'] ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">

                <label>Review Note <?= $status === 'denied' ? '(Required for denial)' : '(Optional)' ?></label>
                <textarea name="review_notes" <?= $status === 'denied' ? 'required' : '' ?> style="width:100%;min-height:140px;padding:12px;border:1px solid #90caf9;border-radius:8px;"><?= htmlspecialchars($reviewNotes) ?></textarea>

                <br>
                <input type="submit" value="<?= ucfirst($statusVerb) ?> Project">
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
