<?php
session_start();
require "../config/db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ================= VALIDATE COMMENT ID ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid comment ID");
}

$comment_id = (int)$_GET['id'];

/* ================= HANDLE FORM SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['admin_reply'])) {
        $error = "Reply cannot be empty";
    } else {
        $reply = trim($_POST['admin_reply']);

        $stmt = $conn->prepare("
            UPDATE project_comments
            SET admin_reply = ?, replied_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $reply, $comment_id);
        $stmt->execute();

        header("Location: admin_project_comments.php");
        exit();
    }
}

/* ================= FETCH COMMENT ================= */
$stmt = $conn->prepare("
    SELECT 
        pc.comment,
        pc.admin_reply,
        u.username,
        p.title AS project_title
    FROM project_comments pc
    JOIN users u ON u.id = pc.user_id
    JOIN projects p ON p.id = pc.project_id
    WHERE pc.id = ?
");
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();

if (!$comment) {
    die("Comment not found");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reply to Comment</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9">
        <div class="form-card">
            <h3>Reply to Project Comment</h3>

            <p>
                <strong>Project:</strong>
                <?= htmlspecialchars($comment['project_title']) ?>
            </p>

            <p>
                <strong>User:</strong>
                <?= htmlspecialchars($comment['username']) ?>
            </p>

            <hr>

            <p><strong>User Comment:</strong></p>
            <div class="public-comment">
                <?= nl2br(htmlspecialchars($comment['comment'])) ?>
            </div>

            <hr>

            <?php if (!empty($error)): ?>
                <p style="color:red;"><?= $error ?></p>
            <?php endif; ?>

            <form method="POST">
                <label><strong>Admin Reply</strong></label>
                <textarea name="admin_reply" required
                          placeholder="Write your reply here..."
                          style="min-height:120px;"><?= htmlspecialchars($comment['admin_reply'] ?? '') ?></textarea>

                <br><br>

                <input type="submit" value="Send Reply">
                <a href="admin_project_comments.php" style="margin-left:10px;">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
