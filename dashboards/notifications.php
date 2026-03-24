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
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <style>
        .notification-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .notification-card {
            background: #f7fbff;
            border: 1px solid #d6eafc;
            border-radius: 10px;
            padding: 16px;
        }
        .notification-card strong {
            display: block;
            color: #0d47a1;
            margin-bottom: 8px;
        }
        .notification-item {
            border: 1px solid #dfeaf5;
            border-left: 4px solid #90caf9;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            background: #fff;
        }
        .notification-item.unread {
            border-left-color: #1565c0;
            background: #f8fbff;
        }
        .notification-meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .notification-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .filter-link {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            background: #e3f2fd;
            font-weight: 600;
        }
        .filter-link.active {
            background: #1565c0;
            color: #fff;
        }
    </style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include $menuFile; ?></div>
    <div class="col-9">
        <div class="form-card">
            <h3>Notifications</h3>

            <?php if ($message !== ''): ?>
                <div class="msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="notification-grid">
                <div class="notification-card">
                    <strong>Total Notifications</strong>
                    <div><?= $totalNotifications ?></div>
                </div>
                <div class="notification-card">
                    <strong>Unread Notifications</strong>
                    <div><?= $unreadNotifications ?></div>
                </div>
            </div>

            <div class="filter-row">
                <a class="filter-link <?= $filter === 'all' ? 'active' : '' ?>" href="notifications.php?filter=all">All</a>
                <a class="filter-link <?= $filter === 'unread' ? 'active' : '' ?>" href="notifications.php?filter=unread">Unread</a>
                <?php if ($unreadNotifications > 0): ?>
                    <form method="POST" style="margin:0;">
                        <?= csrfInput('notifications_actions_form') ?>
                        <input type="submit" name="mark_all_read" value="Mark All Read">
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($notifications->num_rows === 0): ?>
                <p>No notifications found for this filter.</p>
            <?php else: ?>
                <?php while ($notification = $notifications->fetch_assoc()): ?>
                    <div class="notification-item <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">
                        <div class="notification-meta">
                            <?= htmlspecialchars(formatNotificationTypeLabel($notification['notification_type'])) ?>
                            | <?= date("d M Y, H:i", strtotime($notification['created_at'])) ?>
                            | <?= (int) $notification['is_read'] === 0 ? 'Unread' : 'Read' ?>
                        </div>

                        <h4 style="margin:0 0 8px 0;"><?= htmlspecialchars($notification['title']) ?></h4>
                        <div><?= nl2br(htmlspecialchars($notification['message'])) ?></div>

                        <div class="notification-actions">
                            <?php if (!empty($notification['link'])): ?>
                                <form method="POST" action="open_notification.php" style="margin:0;">
                                    <?= csrfInput('open_notification_form') ?>
                                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                    <button type="submit">Open</button>
                                </form>
                            <?php endif; ?>
                            <?php if ((int) $notification['is_read'] === 0): ?>
                                <form method="POST" style="margin:0;">
                                    <?= csrfInput('notifications_actions_form') ?>
                                    <button type="submit" name="mark_read_id" value="<?= $notification['id'] ?>">Mark Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
