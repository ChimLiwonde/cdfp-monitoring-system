<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (($_SESSION['role'] ?? null) !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$comments = $conn->query("
    SELECT pc.*, p.id AS project_id, p.title, u.username
    FROM project_comments pc
    JOIN projects p ON p.id = pc.project_id
    JOIN users u ON u.id = pc.user_id
    ORDER BY pc.created_at DESC
");

$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Comments</title>
<link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "adminmenu.php"; ?></div>
<div class="col-9">

<div class="form-card">
<h2>Public Project Comments</h2>

<?php if ($success_message !== ''): ?>
<div class="msg"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<?php if ($error_message !== ''): ?>
<div class="msg error"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php while($c = $comments->fetch_assoc()): ?>
<div class="project-card">
<strong>Project:</strong> <a href="admin_project_details.php?id=<?= (int) $c['project_id'] ?>"><?= formatProjectCode($c['project_id']) ?> - <?= htmlspecialchars($c['title']) ?></a><br>
<strong>User:</strong> <?= htmlspecialchars($c['username']) ?><br>
<p><?= htmlspecialchars($c['comment']) ?></p>

<form method="POST" action="reply_comment.php">
<input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(getCsrfToken('admin_comments_reply_form')) ?>">
<input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
<textarea name="reply" placeholder="Admin reply..."><?= htmlspecialchars($c['admin_reply']) ?></textarea>
<br><br>
<input type="submit" value="Save Reply">
</form>
</div>
<?php endwhile; ?>

</div>
</div>
</div>

</body>
</html>
