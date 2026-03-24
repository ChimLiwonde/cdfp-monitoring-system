<?php
require_once __DIR__ . '/../config/helpers.php';

$notificationCount = 0;
if (isset($conn, $_SESSION['user_id'])) {
    $notificationCount = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>
<aside class="nav-card">
    <div class="nav-card__head">
        <span class="nav-card__kicker">Admin Navigation</span>
        <h3>Control Center</h3>
        <p>Review submissions, watch budgets, and keep the whole system moving.</p>
    </div>
    <ul class="menu-list">
        <li><a href="home.php"><span class="nav-label">Dashboard</span></a></li>
        <li><a href="notifications.php"><span class="nav-label">Notifications</span><?= $notificationCount > 0 ? '<span class="nav-count">' . $notificationCount . '</span>' : '' ?></a></li>
        <li><a href="adminprojects.php"><span class="nav-label">Project Approvals</span></a></li>
        <li><a href="admin_add_officer.php"><span class="nav-label">Create Project Lead</span></a></li>
        <li><a href="admin_community_requests.php"><span class="nav-label">Community Requests</span></a></li>
        <li><a href="admin_project_comments.php"><span class="nav-label">Project Comments</span></a></li>
        <li><a href="project_collaboration.php"><span class="nav-label">Collaboration</span></a></li>
        <li><a href="admin_project_report.php"><span class="nav-label">Reports</span></a></li>
        <li><a href="manage_users.php"><span class="nav-label">Manage Users</span></a></li>
        <li><a href="../Pages/logout.php"><span class="nav-label">Logout</span></a></li>
    </ul>
</aside>

