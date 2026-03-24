<?php
require_once __DIR__ . '/../config/helpers.php';

$notificationCount = 0;
if (isset($conn, $_SESSION['user_id'])) {
    $notificationCount = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>
<div class="form-card">
    <h3>Menu</h3>
    <ul class="menu-list">
    <li><a href="home.php">Dashboard</a></li>
    <li><a href="notifications.php">Notifications<?= $notificationCount > 0 ? ' (' . $notificationCount . ')' : '' ?></a></li>
    <li><a href="adminprojects.php">Project Approvals</a></li>
    <li><a href="admin_add_officer.php">Create Project Lead</a></li>
    <li><a href="admin_community_requests.php">Community Requests</a></li>
    <li><a href="admin_project_comments.php">Project Comments</a></li>
    <li><a href="project_collaboration.php">Collaboration</a></li>
    <li><a href="admin_project_report.php">Reports</a></li>
    <li><a href="manage_users.php">Manage Users</a></li>
    <li><a href="../Pages/logout.php">Logout</a></li>
</ul>
</div>

