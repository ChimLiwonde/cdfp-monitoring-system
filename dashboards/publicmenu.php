<?php
require_once __DIR__ . '/../config/helpers.php';

$notificationCount = 0;
if (isset($conn, $_SESSION['user_id'])) {
    $notificationCount = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>
<aside class="public-menu">
    <div class="public-menu__head">
        <span class="nav-card__kicker">Citizen Navigation</span>
        <h3>Community Access</h3>
        <p>Follow projects in your district, send requests, and track replies in one place.</p>
    </div>
    <ul>
        <li><a href="home.php"><span class="nav-label">Dashboard</span></a></li>
        <li><a href="notifications.php"><span class="nav-label">Notifications</span><?= $notificationCount > 0 ? '<span class="nav-count">' . $notificationCount . '</span>' : '' ?></a></li>
        <li><a href="user_city_project.php"><span class="nav-label">City Projects</span></a></li>
        <li><a href="public_replies.php"><span class="nav-label">My Replies</span></a></li>
        <li><a href="public_requests.php"><span class="nav-label">Community Requests</span></a></li>
        <li><a href="public_settings.php"><span class="nav-label">Account Settings</span></a></li>
        <li><a href="../Pages/logout.php"><span class="nav-label">Logout</span></a></li>
    </ul>
</aside>
