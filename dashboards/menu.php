<?php
require_once __DIR__ . '/../config/helpers.php';

$notificationCount = 0;
if (isset($conn, $_SESSION['user_id'])) {
    $notificationCount = getUnreadNotificationCount($conn, $_SESSION['user_id']);
}
?>
<aside class="nav-card">
    <div class="nav-card__head">
        <span class="nav-card__kicker">Project Lead Navigation</span>
        <h3>Delivery Workspace</h3>
        <p>Plan, assign, update, and monitor projects from one focused control panel.</p>
    </div>
    <ul class="menu-list">
        <li><a href="home.php"><span class="nav-label">Project Panel</span></a></li>
        <li><a href="notifications.php"><span class="nav-label">Notifications</span><?= $notificationCount > 0 ? '<span class="nav-count">' . $notificationCount . '</span>' : '' ?></a></li>
        <li><a href="create_project.php"><span class="nav-label">Create Project</span></a></li>
        <li><a href="add_stage.php"><span class="nav-label">Project Status</span></a></li>
        <li><a href="task_assignments.php"><span class="nav-label">Task Assignments</span></a></li>
        <li><a href="project_expenses.php"><span class="nav-label">Project Expenses</span></a></li>
        <li><a href="project_collaboration.php"><span class="nav-label">Collaboration</span></a></li>
        <li><a href="my_projects.php"><span class="nav-label">My Projects</span></a></li>
        <li><a href="add_contractor.php"><span class="nav-label">Add Contractor</span></a></li>
        <li><a href="Officer_settings.php"><span class="nav-label">Settings</span></a></li>
        <li><a href="../Pages/logout.php"><span class="nav-label">Logout</span></a></li>
    </ul>
</aside>
