<?php
session_start();
require "../config/db.php";

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$comments = $conn->query("
    SELECT pc.*, p.title, u.username
    FROM project_comments pc
    JOIN projects p ON p.id = pc.project_id
    JOIN users u ON u.id = pc.user_id
    ORDER BY pc.created_at DESC
");
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
<h2>💬 Public Project Comments</h2>

<?php while($c = $comments->fetch_assoc()): ?>
<div class="project-card">
<strong>Project:</strong> <?= htmlspecialchars($c['title']) ?><br>
<strong>User:</strong> <?= htmlspecialchars($c['username']) ?><br>
<p><?= htmlspecialchars($c['comment']) ?></p>

<form method="POST" action="reply_comment.php">
<input type="hidden" name="id" value="<?= $c['id'] ?>">
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
