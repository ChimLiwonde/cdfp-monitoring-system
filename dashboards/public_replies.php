<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ================= FETCH COMMENTS ================= */
$stmt = $conn->prepare("
    SELECT 
        p.id AS project_id,
        pc.comment,
        pc.admin_reply,
        pc.created_at,
        pc.replied_at,
        p.title
    FROM project_comments pc
    JOIN projects p ON p.id = pc.project_id
    WHERE pc.user_id = ?
    ORDER BY pc.created_at ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$comments = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>My Project Chats</title>
<link rel="stylesheet" href="../assets/css/flexible.css">
</head>

<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "publicmenu.php"; ?></div>

<div class="col-9 public-dashboard">
<div class="form-card">
<h3>💬 My Project Chats</h3>

<?php if ($comments->num_rows == 0): ?>
    <p>No comments yet.</p>
<?php endif; ?>

<div class="chat-box">
<?php while ($c = $comments->fetch_assoc()): ?>

    <div class="chat-project">
        <?= formatProjectCode($c['project_id']) ?> - <?= htmlspecialchars($c['title']) ?>
    </div>

    <!-- USER COMMENT -->
    <div class="masg masg-user">
        <?= nl2br(htmlspecialchars($c['comment'])) ?>
        <div class="masg-time">
            <?= date("d M Y, H:i", strtotime($c['created_at'])) ?>
        </div>
    </div>

    <div style="clear:both;"></div>

    <!-- ADMIN REPLY -->
    <?php if (!empty($c['admin_reply'])): ?>
        <div class="masg masg-admin">
            <?= nl2br(htmlspecialchars($c['admin_reply'])) ?>
            <div class="masg-time">
                <?= date("d M Y, H:i", strtotime($c['replied_at'])) ?>
            </div>
        </div>
        <div style="clear:both;"></div>
    <?php endif; ?>

<?php endwhile; ?>
</div>

</div>
</div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
