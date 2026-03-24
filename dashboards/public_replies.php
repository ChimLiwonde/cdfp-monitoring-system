<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

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
$comments_result = $stmt->get_result();

$comments = [];
$replied_count = 0;

while ($row = $comments_result->fetch_assoc()) {
    if (!empty($row['admin_reply'])) {
        $replied_count++;
    }
    $comments[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>My Project Chats</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/flexible.css">
</head>

<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "publicmenu.php"; ?></div>

<div class="col-9 public-dashboard dashboard-main">
    <div class="form-card page-hero">
        <div class="page-hero__grid">
            <div class="page-hero__copy">
                <span class="eyebrow">Project Replies</span>
                <h3>Follow your project conversations</h3>
                <p>Track each comment you have posted on approved projects and see whether the admin team has already responded.</p>
            </div>
            <div class="hero-pills">
                <div class="hero-pill"><strong><?= count($comments) ?></strong>&nbsp; Comments</div>
                <div class="hero-pill"><strong><?= $replied_count ?></strong>&nbsp; Replies</div>
            </div>
        </div>
    </div>

    <div class="data-card">
        <div class="section-header">
            <div>
                <span class="section-kicker">Conversation History</span>
                <h3>My Project Chats</h3>
            </div>
            <p>Each project thread keeps your original feedback together with the latest admin response.</p>
        </div>

        <?php if (count($comments) === 0): ?>
            <div class="empty-state">No comments yet. Visit a project page to start the conversation.</div>
        <?php else: ?>
            <div class="conversation-stack">
                <?php foreach ($comments as $comment): ?>
                    <div class="activity-card">
                        <div class="activity-head">
                            <div class="thread-project-label"><?= formatProjectCode($comment['project_id']) ?> - <?= htmlspecialchars($comment['title']) ?></div>
                            <small><?= date("d M Y, H:i", strtotime($comment['created_at'])) ?></small>
                        </div>

                        <div class="chat-box">
                            <div class="masg masg-user">
                                <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                <div class="masg-time">
                                    <?= date("d M Y, H:i", strtotime($comment['created_at'])) ?>
                                </div>
                            </div>

                            <div class="chat-clear"></div>

                            <?php if (!empty($comment['admin_reply'])): ?>
                                <div class="masg masg-admin">
                                    <?= nl2br(htmlspecialchars($comment['admin_reply'])) ?>
                                    <div class="masg-time">
                                        <?= date("d M Y, H:i", strtotime($comment['replied_at'])) ?>
                                    </div>
                                </div>
                                <div class="chat-clear"></div>
                            <?php else: ?>
                                <div class="empty-note space-top-sm">Awaiting admin reply.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include "footer.php"; ?>
</body>
</html>
