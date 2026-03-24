<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

$role = $_SESSION['role'] ?? null;
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($role === null || $userId <= 0) {
    header("Location: ../Pages/login.php");
    exit();
}

$message = '';
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'unread'], true)) {
    $filter = 'all';
}

if (isset($_POST['mark_all_read'])) {
    if (!isValidCsrfToken('notifications_actions_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try again.";
    } else {
        $stmt = $conn->prepare("
            UPDATE user_notifications
            SET is_read = 1
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $message = "All notifications marked as read.";
    }
}

if (isset($_POST['mark_read_id'])) {
    if (!isValidCsrfToken('notifications_actions_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try again.";
    } else {
        $notificationId = (int) ($_POST['mark_read_id'] ?? 0);
        if ($notificationId > 0) {
            $stmt = $conn->prepare("
                UPDATE user_notifications
                SET is_read = 1
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $notificationId, $userId);
            $stmt->execute();
            $message = "Notification marked as read.";
        }
    }
}

$countsStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_notifications,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_notifications
    FROM user_notifications
    WHERE user_id = ?
");
$countsStmt->bind_param("i", $userId);
$countsStmt->execute();
$counts = $countsStmt->get_result()->fetch_assoc();
$totalNotifications = (int) ($counts['total_notifications'] ?? 0);
$unreadNotifications = (int) ($counts['unread_notifications'] ?? 0);

$sql = "
    SELECT id, notification_type, title, message, link, is_read, created_at
    FROM user_notifications
    WHERE user_id = ?
";
if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
}
$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result();

$menuFile = $role === 'admin' ? 'adminmenu.php' : ($role === 'public' ? 'publicmenu.php' : 'menu.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notifications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include $menuFile; ?></div>
    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Notification Center</span>
                    <h3>Stay on top of project activity</h3>
                    <p>Track reviews, status changes, replies, and reminders in one inbox so each role sees what needs attention next.</p>
                    <div class="hero-actions">
                        <a href="home.php" class="back-btn">Back to Home</a>
                    </div>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $totalNotifications ?></strong>&nbsp; Total</div>
                    <div class="hero-pill"><strong><?= $unreadNotifications ?></strong>&nbsp; Unread</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($message !== ''): ?>
                <div class="msg success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <span class="section-kicker">Mailbox Summary</span>
                    <h3>Notifications Overview</h3>
                </div>
                <p>Use the filters to focus on unread items, then open the related workflow directly from the notification card.</p>
            </div>

            <div class="notification-grid">
                <div class="notification-card">
                    <strong>Total Notifications</strong>
                    <div class="metric-value"><?= $totalNotifications ?></div>
                    <div class="metric-meta">All activity routed to your account.</div>
                </div>
                <div class="notification-card">
                    <strong>Unread Notifications</strong>
                    <div class="metric-value"><?= $unreadNotifications ?></div>
                    <div class="metric-meta">Items still waiting for a response or review.</div>
                </div>
            </div>

            <div class="toolbar">
                <a class="toolbar-link <?= $filter === 'all' ? 'active' : '' ?>" href="notifications.php?filter=all">All notifications</a>
                <a class="toolbar-link <?= $filter === 'unread' ? 'active' : '' ?>" href="notifications.php?filter=unread">Unread only</a>
                <?php if ($unreadNotifications > 0): ?>
                    <form method="POST" class="inline-form">
                        <?= csrfInput('notifications_actions_form') ?>
                        <input type="submit" name="mark_all_read" value="Mark All Read" class="action-chip action-chip--primary">
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Inbox</span>
                    <h3><?= $filter === 'unread' ? 'Unread Notifications' : 'Recent Notifications' ?></h3>
                </div>
                <p><?= $filter === 'unread' ? 'These items still need your attention.' : 'Recent activity from across the system appears here in reverse chronological order.' ?></p>
            </div>

            <?php if ($notifications->num_rows === 0): ?>
                <div class="empty-state">No notifications found for this filter.</div>
            <?php else: ?>
                <div class="stack-list">
                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                        <div class="notification-item <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">
                            <div class="notification-meta">
                                <?= htmlspecialchars(formatNotificationTypeLabel($notification['notification_type'])) ?>
                                | <?= date("d M Y, H:i", strtotime($notification['created_at'])) ?>
                                | <?= (int) $notification['is_read'] === 0 ? 'Unread' : 'Read' ?>
                            </div>

                            <h4><?= htmlspecialchars($notification['title']) ?></h4>
                            <div><?= nl2br(htmlspecialchars($notification['message'])) ?></div>

                            <div class="notification-actions">
                                <?php if (!empty($notification['link'])): ?>
                                    <form method="POST" action="open_notification.php" class="inline-form">
                                        <?= csrfInput('open_notification_form') ?>
                                        <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                        <button type="submit" class="action-chip action-chip--primary">Open Linked Page</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <form method="POST" class="inline-form">
                                        <?= csrfInput('notifications_actions_form') ?>
                                        <button type="submit" name="mark_read_id" value="<?= (int) $notification['id'] ?>" class="action-chip">Mark Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
