<?php
require_once __DIR__ . '/../config/helpers.php';

$notificationCount = 0;
if (isset($conn, $_SESSION['user_id'])) {
    $notificationCount = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>
<div class="public-menu">
    <h3>Menu</h3>
    <ul>
        <li><a href="home.php">Dashboard</a></li>
        <li><a href="notifications.php">Notifications<?= $notificationCount > 0 ? ' (' . $notificationCount . ')' : '' ?></a></li>
        <li><a href="user_city_project.php">City Projects</a></li>
        <li><a href="public_replies.php">My Replies</a></li>
        <li><a href="public_requests.php">Community Requests</a></li>
        <li><a href="public_settings.php">Account Settings</a></li>
        <li><a href="../Pages/logout.php">Logout</a></li>
    </ul>
</div>
