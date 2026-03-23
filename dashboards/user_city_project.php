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

/* ================= USER LOCATION ================= */
$userStmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$user_location = $user['location'];

/* ================= FETCH PROJECTS ================= */
$sql = "
SELECT 
    p.*,
    u.username AS field_officer,
    c.name AS contractor_name,
    c.company AS contractor_company,
    pm.latitude,
    pm.longitude,
    MAX(CASE WHEN ps.status = 'completed' THEN 1 ELSE 0 END) AS is_completed,
    MIN(ps.actual_start) AS actual_start,
    MAX(ps.actual_end) AS actual_end
FROM projects p
JOIN users u ON u.id = p.created_by
LEFT JOIN contractor_projects cp ON cp.project_id = p.id
LEFT JOIN contractors c ON c.id = cp.contractor_id
LEFT JOIN project_maps pm ON pm.project_id = p.id
LEFT JOIN project_stages ps ON ps.project_id = p.id
WHERE LOWER(p.district) = LOWER(?)
GROUP BY p.id
ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_location);
$stmt->execute();
$projects = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>City Project</title>

<link rel="stylesheet" href="../assets/css/flexible.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

</head>

<body>

<?php include "header.php"; ?>

<div class="row">

    <!-- MENU -->
    <div class="col-3">
        <?php include "publicmenu.php"; ?>
    </div>

    <!-- CONTENT -->
    <div class="col-9 public-dashboard">

        <?php if ($projects->num_rows == 0): ?>
            <div class="form-card">
                No projects found in your district.
            </div>
        <?php endif; ?>

        <?php while ($p = $projects->fetch_assoc()): ?>

        <?php
        $can_comment = in_array($p['status'], ['pending', 'approved']);
        ?>

        <div class="form-card public-project-card">

            <!-- TITLE + STATUS -->
            <div class="row">
                <div class="col-8">
                    <h3><?= formatProjectCode($p['id']) ?> - <?= htmlspecialchars($p['title']) ?></h3>
                </div>
                <div class="col-4" style="text-align:right;">
                    <span class="public-badge <?= $p['status'] ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>

                    <?php if ($p['is_completed']): ?>
                        <br><br>
                        <span class="public-badge completed">Completed</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DESCRIPTION -->
            <p><?= nl2br(htmlspecialchars($p['description'])) ?></p>

            <hr>

            <!-- DETAILS -->
            <div class="row">
                <div class="col-6">
                    <p>
                        <strong>📍 Location:</strong> <?= htmlspecialchars($p['location']) ?><br>
                        <strong>🏘 District:</strong> <?= htmlspecialchars($p['district']) ?><br>
                        <strong>Project Lead:</strong> <?= htmlspecialchars($p['field_officer']) ?><br>
                    </p>
                </div>

                <div class="col-6">
                    <p>
                        <strong>Contractor:</strong> <?= htmlspecialchars($p['contractor_name'] ?? 'Not assigned') ?><br>
                        <strong>Company:</strong> <?= htmlspecialchars($p['contractor_company'] ?? 'N/A') ?><br>
                        <strong>Actual Start:</strong> <?= $p['actual_start'] ?? 'N/A' ?><br>
                        <strong>Actual End:</strong> <?= $p['actual_end'] ?? 'N/A' ?>
                    </p>
                </div>
            </div>

            <!-- MAP -->
            <?php if ($p['latitude'] && $p['longitude']): ?>
                <div id="map<?= $p['id'] ?>" class="public-map"></div>
            <?php endif; ?>

            <hr>

            <!-- COMMENT FORM -->
            <?php if ($can_comment): ?>
                <form method="POST" action="submit_comment.php">
                    <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                    <textarea name="comment" required placeholder="Write your comment..."></textarea>
                    <br><br>
                    <input type="submit" value="Submit Comment">
                </form>
            <?php else: ?>
                <div class="msg">
                    Comments are closed for this project. You can read feedback below.
                </div>
            <?php endif; ?>

            <!-- COMMENTS -->
            <?php
            $cstmt = $conn->prepare("
                SELECT pc.id, pc.comment, pc.admin_reply, pc.user_id, u.username
                FROM project_comments pc
                JOIN users u ON u.id = pc.user_id
                WHERE pc.project_id = ?
                ORDER BY pc.created_at DESC
            ");
            $cstmt->bind_param("i", $p['id']);
            $cstmt->execute();
            $comments = $cstmt->get_result();
            ?>

            <?php while ($c = $comments->fetch_assoc()): ?>
                <div class="public-comment" id="comment-<?= $c['id'] ?>">
                    <strong><?= htmlspecialchars($c['username']) ?>:</strong><br>
                    <?= nl2br(htmlspecialchars($c['comment'])) ?>

                    <?php if ($c['admin_reply']): ?>
                        <div class="public-reply">
                            <strong>Admin Reply:</strong><br>
                            <?= nl2br(htmlspecialchars($c['admin_reply'])) ?>
                        </div>
                    <?php endif; ?>

                    <!-- EMOJI REACTIONS WITH COUNTS -->
                    <div class="emoji-reactions">
                        <?php
                        $reactionTypes = ['👍','😂','😮','😢','🔥'];
                        foreach ($reactionTypes as $emoji):
                            // Count total reactions
                            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM comment_reactions WHERE comment_id=? AND emoji=?");
                            $countStmt->bind_param("is", $c['id'], $emoji);
                            $countStmt->execute();
                            $count = $countStmt->get_result()->fetch_assoc()['total'];

                            // Check if current user reacted
                            $rstmt = $conn->prepare("SELECT id FROM comment_reactions WHERE comment_id=? AND user_id=? AND emoji=?");
                            $rstmt->bind_param("iis", $c['id'], $user_id, $emoji);
                            $rstmt->execute();
                            $alreadyReacted = $rstmt->get_result()->num_rows > 0;
                        ?>
                            <button class="emoji-btn <?= $alreadyReacted ? 'reacted' : '' ?>" 
                                    data-comment="<?= $c['id'] ?>" data-emoji="<?= $emoji ?>">
                                <?= $emoji ?> <span class="emoji-count"><?= $count ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endwhile; ?>

        </div>

        <?php if ($p['latitude'] && $p['longitude']): ?>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
        L.map("map<?= $p['id'] ?>")
            .setView([<?= $p['latitude'] ?>, <?= $p['longitude'] ?>], 14)
            .addLayer(
                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png")
            )
            .addLayer(
                L.marker([<?= $p['latitude'] ?>, <?= $p['longitude'] ?>])
            );
        </script>
        <?php endif; ?>

        <?php endwhile; ?>

    </div>
</div>

<?php include "footer.php"; ?>

<!-- JQUERY + AJAX FOR EMOJI REACTIONS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).on('click', '.emoji-btn', function(){
    let btn = $(this);
    let commentId = btn.data('comment');
    let emoji = btn.data('emoji');

    $.post('emoji_react.php', {comment_id: commentId, emoji: emoji}, function(res){
        res = JSON.parse(res);
        if(res.status === 'added'){
            btn.addClass('reacted');
        } else {
            btn.removeClass('reacted');
        }
        btn.find('.emoji-count').text(res.count);
    });
});
</script>

</body>
</html>
