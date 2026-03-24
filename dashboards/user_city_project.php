<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'public') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = pullSessionMessage('success_message');
$error_message = pullSessionMessage('error_message');

$userStmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$user_location = $user['location'];

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

$reactionTypes = [
    "\u{1F44D}",
    "\u{1F602}",
    "\u{1F62E}",
    "\u{1F622}",
    "\u{1F525}"
];
?>
<!DOCTYPE html>
<html>
<head>
<title>City Projects</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/flexible.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
</head>

<body>

<?php include "header.php"; ?>

<div class="row">

    <div class="col-3">
        <?php include "publicmenu.php"; ?>
    </div>

    <div class="col-9 public-dashboard dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">District Project Feed</span>
                    <h3>Projects in <?= htmlspecialchars($user_location) ?></h3>
                    <p>See what is being planned or delivered in your area, read project details, and leave feedback while comments are still open.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $projects->num_rows ?></strong>&nbsp; Projects</div>
                    <div class="hero-pill"><strong>Citizen</strong>&nbsp; Feedback</div>
                </div>
            </div>
        </div>

        <?php if ($success_message !== ''): ?>
            <div class="data-card">
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="data-card">
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($projects->num_rows == 0): ?>
            <div class="data-card">
                <div class="empty-state">No projects found in your district yet.</div>
            </div>
        <?php endif; ?>

        <?php while ($p = $projects->fetch_assoc()): ?>
            <?php
            $can_comment = in_array($p['status'], ['pending', 'approved'], true);
            $mapLat = normalizeCoordinate($p['latitude'], -90, 90);
            $mapLng = normalizeCoordinate($p['longitude'], -180, 180);
            $hasMap = $mapLat !== null && $mapLng !== null;
            ?>

            <div class="form-card public-project-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Project Record</span>
                        <h3><?= formatProjectCode($p['id']) ?> - <?= htmlspecialchars($p['title']) ?></h3>
                    </div>
                    <div class="section-actions">
                        <span class="status-badge <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst($p['status'])) ?></span>
                        <?php if ($p['is_completed']): ?>
                            <span class="status-badge completed">Completed</span>
                        <?php endif; ?>
                    </div>
                </div>

                <p><?= nl2br(htmlspecialchars($p['description'])) ?></p>

                <div class="detail-grid" style="margin-top:18px;">
                    <div class="detail-card">
                        <strong>Location</strong>
                        <span><?= htmlspecialchars($p['location']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>District</strong>
                        <span><?= htmlspecialchars($p['district']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Project Lead</strong>
                        <span><?= htmlspecialchars($p['field_officer']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Contractor</strong>
                        <span><?= htmlspecialchars($p['contractor_name'] ?? 'Not assigned') ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Company</strong>
                        <span><?= htmlspecialchars($p['contractor_company'] ?? 'N/A') ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Actual Timeline</strong>
                        <span><?= htmlspecialchars($p['actual_start'] ?? 'N/A') ?> to <?= htmlspecialchars($p['actual_end'] ?? 'N/A') ?></span>
                    </div>
                </div>

                <?php if ($hasMap): ?>
                    <div
                        id="map<?= $p['id'] ?>"
                        class="public-map"
                        data-lat="<?= htmlspecialchars($mapLat) ?>"
                        data-lng="<?= htmlspecialchars($mapLng) ?>">
                    </div>
                <?php endif; ?>

                <div class="data-stack" style="margin-top:20px;">
                    <div class="data-card">
                        <div class="section-header">
                            <div>
                                <span class="section-kicker">Citizen Comment</span>
                                <h4>Share Feedback</h4>
                            </div>
                            <p><?= $can_comment ? 'Comments are open for this project.' : 'Comments are closed for this project, but existing feedback remains visible.' ?></p>
                        </div>

                        <?php if ($can_comment): ?>
                            <form method="POST" action="submit_comment.php">
                                <?= csrfInput('public_comment_form') ?>
                                <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                <label for="comment-<?= $p['id'] ?>">Comment</label>
                                <textarea id="comment-<?= $p['id'] ?>" name="comment" required placeholder="Write your comment..."></textarea>
                                <input type="submit" value="Submit Comment">
                            </form>
                        <?php else: ?>
                            <div class="empty-state">Comments are closed for this project. You can still read the discussion below.</div>
                        <?php endif; ?>
                    </div>

                    <div class="data-card">
                        <div class="section-header">
                            <div>
                                <span class="section-kicker">Discussion</span>
                                <h4>Project Comments</h4>
                            </div>
                            <p>Community comments and admin replies stay linked to the exact project record.</p>
                        </div>

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

                        <?php if ($comments->num_rows === 0): ?>
                            <div class="empty-state">No comments on this project yet.</div>
                        <?php endif; ?>

                        <?php while ($c = $comments->fetch_assoc()): ?>
                            <div class="public-comment" id="comment-thread-<?= $c['id'] ?>">
                                <strong><?= htmlspecialchars($c['username']) ?>:</strong><br>
                                <?= nl2br(htmlspecialchars($c['comment'])) ?>

                                <?php if ($c['admin_reply']): ?>
                                    <div class="public-reply">
                                        <strong>Admin Reply:</strong><br>
                                        <?= nl2br(htmlspecialchars($c['admin_reply'])) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="emoji-reactions">
                                    <?php foreach ($reactionTypes as $emoji): ?>
                                        <?php
                                        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM comment_reactions WHERE comment_id=? AND emoji=?");
                                        $countStmt->bind_param("is", $c['id'], $emoji);
                                        $countStmt->execute();
                                        $count = $countStmt->get_result()->fetch_assoc()['total'];

                                        $rstmt = $conn->prepare("SELECT id FROM comment_reactions WHERE comment_id=? AND user_id=? AND emoji=?");
                                        $rstmt->bind_param("iis", $c['id'], $user_id, $emoji);
                                        $rstmt->execute();
                                        $alreadyReacted = $rstmt->get_result()->num_rows > 0;
                                        ?>
                                        <button type="button" class="emoji-btn <?= $alreadyReacted ? 'reacted' : '' ?>" data-comment="<?= $c['id'] ?>" data-emoji="<?= htmlspecialchars($emoji) ?>">
                                            <?= htmlspecialchars($emoji) ?> <span class="emoji-count"><?= $count ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>

    </div>
</div>

<?php include "footer.php"; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.querySelectorAll('.public-map[data-lat]').forEach(function (mapDiv) {
    const lat = parseFloat(mapDiv.dataset.lat);
    const lng = parseFloat(mapDiv.dataset.lng);
    const map = L.map(mapDiv).setView([lat, lng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
});

const emojiCsrfToken = <?= json_encode(getCsrfToken('emoji_react_form')) ?>;

$(document).on('click', '.emoji-btn', function(){
    const btn = $(this);
    const commentId = btn.data('comment');
    const emoji = btn.data('emoji');

    $.post('emoji_react.php', {
        comment_id: commentId,
        emoji: emoji,
        _csrf_token: emojiCsrfToken
    }, function(res){
        if(res.status === 'added'){
            btn.addClass('reacted');
        } else {
            btn.removeClass('reacted');
        }
        btn.find('.emoji-count').text(res.count);
    }, 'json');
});
</script>

</body>
</html>
