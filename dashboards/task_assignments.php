<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_project_id = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$selected_stage_id = (int) ($_GET['stage_id'] ?? $_POST['stage_id'] ?? 0);
$message = '';

function fetchOfficerProject($conn, $projectId, $userId)
{
    if ($projectId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, title, status, district, location
        FROM projects
        WHERE id = ? AND created_by = ?
    ");
    $stmt->bind_param("ii", $projectId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if (isset($_POST['add_team_member'])) {
    if (!isValidCsrfToken('add_team_member_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try adding the team member again.";
    } else {
        $project_id = (int) ($_POST['project_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $role_title = trim($_POST['role_title'] ?? '');
        $contact_info = trim($_POST['contact_info'] ?? '');

        $project = fetchOfficerProject($conn, $project_id, $user_id);

        if (!$project) {
            $message = "The selected project was not found.";
        } elseif ($project['status'] === 'completed') {
            $message = "Completed projects are read-only for new task assignments.";
        } elseif ($full_name === '' || $role_title === '') {
            $message = "Please provide the team member name and role.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO project_team_members (project_id, full_name, role_title, contact_info, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssi", $project_id, $full_name, $role_title, $contact_info, $user_id);

            if ($stmt->execute()) {
                logProjectActivity(
                    $conn,
                    $project_id,
                    'team_member_added',
                    $user_id,
                    $_SESSION['role'] ?? 'field_officer',
                    null,
                    null,
                    $full_name . ' added as ' . $role_title . '.'
                );

                $_SESSION['success_message'] = $full_name . " added to " . formatProjectCode($project_id) . ".";
                header("Location: task_assignments.php?project_id={$project_id}");
                exit();
            }

            $message = "Failed to add the team member.";
        }
    }
}

if (isset($_POST['assign_task'])) {
    if (!isValidCsrfToken('assign_task_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try saving the task assignment again.";
    } else {
        $project_id = (int) ($_POST['project_id'] ?? 0);
        $stage_id = (int) ($_POST['stage_id'] ?? 0);
        $team_member_id = (int) ($_POST['team_member_id'] ?? 0);
        $assignment_notes = trim($_POST['assignment_notes'] ?? '');

        $project = fetchOfficerProject($conn, $project_id, $user_id);

        if (!$project) {
            $message = "The selected project was not found.";
        } elseif ($project['status'] === 'completed') {
            $message = "Completed projects are read-only for new task assignments.";
        } else {
            $stage_stmt = $conn->prepare("
                SELECT id, stage_name
                FROM project_stages
                WHERE id = ? AND project_id = ?
            ");
            $stage_stmt->bind_param("ii", $stage_id, $project_id);
            $stage_stmt->execute();
            $stage = $stage_stmt->get_result()->fetch_assoc();

            $member_stmt = $conn->prepare("
                SELECT id, full_name
                FROM project_team_members
                WHERE id = ? AND project_id = ?
            ");
            $member_stmt->bind_param("ii", $team_member_id, $project_id);
            $member_stmt->execute();
            $team_member = $member_stmt->get_result()->fetch_assoc();

            if (!$stage || !$team_member) {
                $message = "Please select a valid status item and team member.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO project_stage_assignments (stage_id, team_member_id, assigned_by, assignment_notes)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iiis", $stage_id, $team_member_id, $user_id, $assignment_notes);

                if ($stmt->execute()) {
                    logProjectActivity(
                        $conn,
                        $project_id,
                        'task_assigned',
                        $user_id,
                        $_SESSION['role'] ?? 'field_officer',
                        null,
                        null,
                        $stage['stage_name'] . ' assigned to ' . $team_member['full_name'] . '.'
                    );

                    $_SESSION['success_message'] = "Task assignment saved for " . formatProjectCode($project_id) . ".";
                    header("Location: task_assignments.php?project_id={$project_id}&stage_id={$stage_id}");
                    exit();
                }

                $message = ((int) $conn->errno === 1062)
                    ? "That team member is already assigned to this status item."
                    : "Failed to save the task assignment.";
            }
        }
    }
}

$projects_stmt = $conn->prepare("
    SELECT id, title, status
    FROM projects
    WHERE created_by = ? AND status IN ('approved', 'in_progress', 'completed')
    ORDER BY created_at DESC
");
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

$selected_project = fetchOfficerProject($conn, $selected_project_id, $user_id);
$project_locked = $selected_project && $selected_project['status'] === 'completed';

$team_members = [];
$stage_options = [];
$assignments = [];

if ($selected_project) {
    $team_stmt = $conn->prepare("
        SELECT id, full_name, role_title, contact_info
        FROM project_team_members
        WHERE project_id = ?
        ORDER BY full_name ASC
    ");
    $team_stmt->bind_param("i", $selected_project_id);
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    while ($row = $team_result->fetch_assoc()) {
        $team_members[] = $row;
    }

    $stage_stmt = $conn->prepare("
        SELECT id, stage_name, status
        FROM project_stages
        WHERE project_id = ?
        ORDER BY planned_start ASC, id ASC
    ");
    $stage_stmt->bind_param("i", $selected_project_id);
    $stage_stmt->execute();
    $stage_result = $stage_stmt->get_result();
    while ($row = $stage_result->fetch_assoc()) {
        $stage_options[] = $row;
    }

    $assign_stmt = $conn->prepare("
        SELECT
            ps.stage_name,
            ps.status AS stage_status,
            ptm.full_name,
            ptm.role_title,
            ptm.contact_info,
            psa.assignment_notes,
            psa.assigned_at
        FROM project_stage_assignments psa
        JOIN project_stages ps ON ps.id = psa.stage_id
        JOIN project_team_members ptm ON ptm.id = psa.team_member_id
        WHERE ps.project_id = ?
        ORDER BY ps.planned_start ASC, psa.assigned_at DESC
    ");
    $assign_stmt->bind_param("i", $selected_project_id);
    $assign_stmt->execute();
    $assign_result = $assign_stmt->get_result();
    while ($row = $assign_result->fetch_assoc()) {
        $assignments[] = $row;
    }
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Task Assignments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>
    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Task Planning</span>
                    <h3>Assign team members to project status items with clearer planning flow.</h3>
                    <p>This page keeps team setup, task ownership, and assignment history connected to the selected project instead of scattering them across different screens.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong>Team</strong>&nbsp; Setup</div>
                    <div class="hero-pill"><strong>Task</strong>&nbsp; Assignment</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($success_message): ?>
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            <?php elseif ($message !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="GET">
                <label for="project_id">Select Project</label>
                <select id="project_id" name="project_id" onchange="this.form.submit()" required>
                    <option value="">-- Select Approved or Active Project --</option>
                    <?php while ($project = $projects->fetch_assoc()): ?>
                        <option value="<?= $project['id'] ?>" <?= $project['id'] === $selected_project_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars(formatProjectCode($project['id']) . ' - ' . $project['title'] . ' (' . formatStatusLabel($project['status']) . ')') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if (!$selected_project): ?>
            <div class="data-card">
                <div class="empty-state">Select a project to manage its team members and task assignments.</div>
            </div>
        <?php else: ?>
            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Project Assignment View</span>
                        <h3><?= formatProjectCode($selected_project['id']) ?> - <?= htmlspecialchars($selected_project['title']) ?></h3>
                    </div>
                    <p>Use this project context to add team members and link each one to the right status item.</p>
                </div>

                <div class="detail-grid">
                    <div class="detail-card">
                        <strong>Status</strong>
                        <span class="status-badge <?= htmlspecialchars($selected_project['status']) ?>"><?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>District</strong>
                        <span><?= htmlspecialchars($selected_project['district']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Location</strong>
                        <span><?= htmlspecialchars($selected_project['location']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Team Members</strong>
                        <span><?= count($team_members) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Task Assignments</strong>
                        <span><?= count($assignments) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Project State</strong>
                        <span><?= $project_locked ? 'View only' : 'Editable' ?></span>
                    </div>
                </div>

                <?php if ($project_locked): ?>
                    <div class="msg error" style="margin-top:16px;">Completed projects are view-only for new task assignments.</div>
                <?php endif; ?>
            </div>

            <div class="section-grid">
                <div class="data-card">
                    <div class="section-header">
                        <div>
                            <span class="section-kicker">Team Setup</span>
                            <h3>Add Team Member</h3>
                        </div>
                        <p>Add people to the selected project before assigning them to a status item.</p>
                    </div>

                    <form method="POST">
                        <?= csrfInput('add_team_member_form') ?>
                        <input type="hidden" name="project_id" value="<?= $selected_project['id'] ?>">

                        <div class="form-grid">
                            <div>
                                <label for="full_name">Full Name</label>
                                <input id="full_name" type="text" name="full_name" required <?= $project_locked ? 'disabled' : '' ?>>
                            </div>

                            <div>
                                <label for="role_title">Role / Responsibility</label>
                                <input id="role_title" type="text" name="role_title" required <?= $project_locked ? 'disabled' : '' ?>>
                            </div>

                            <div class="full-span">
                                <label for="contact_info">Contact Info</label>
                                <input id="contact_info" type="text" name="contact_info" placeholder="Phone or email" <?= $project_locked ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <input type="submit" name="add_team_member" value="Add Team Member" <?= $project_locked ? 'disabled' : '' ?>>
                    </form>
                </div>

                <div class="data-card">
                    <div class="section-header">
                        <div>
                            <span class="section-kicker">Assignment Flow</span>
                            <h3>Assign Team Member to Status Item</h3>
                        </div>
                        <p>Link the right team member to the right project status item with optional notes.</p>
                    </div>

                    <form method="POST">
                        <?= csrfInput('assign_task_form') ?>
                        <input type="hidden" name="project_id" value="<?= $selected_project['id'] ?>">

                        <div class="form-grid">
                            <div class="full-span">
                                <label for="stage_id">Status Item</label>
                                <select id="stage_id" name="stage_id" required <?= $project_locked ? 'disabled' : '' ?>>
                                    <option value="">-- Select Status Item --</option>
                                    <?php foreach ($stage_options as $stage): ?>
                                        <option value="<?= $stage['id'] ?>" <?= $stage['id'] === $selected_stage_id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($stage['stage_name'] . ' (' . formatStatusLabel($stage['status']) . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="full-span">
                                <label for="team_member_id">Team Member</label>
                                <select id="team_member_id" name="team_member_id" required <?= $project_locked ? 'disabled' : '' ?>>
                                    <option value="">-- Select Team Member --</option>
                                    <?php foreach ($team_members as $member): ?>
                                        <option value="<?= $member['id'] ?>">
                                            <?= htmlspecialchars($member['full_name'] . ' - ' . $member['role_title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="full-span">
                                <label for="assignment_notes">Assignment Notes</label>
                                <textarea id="assignment_notes" name="assignment_notes" <?= $project_locked ? 'disabled' : '' ?>></textarea>
                            </div>
                        </div>

                        <input type="submit" name="assign_task" value="Assign Task" <?= $project_locked ? 'disabled' : '' ?>>
                    </form>
                </div>
            </div>

            <div class="section-grid">
                <div class="data-card">
                    <div class="section-header">
                        <div>
                            <span class="section-kicker">Project Team</span>
                            <h3>Team Members</h3>
                        </div>
                        <p>Everyone currently added to the selected project.</p>
                    </div>
                    <?php if (count($team_members) === 0): ?>
                        <div class="empty-state">No team members added yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="dashboard-table">
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                </tr>
                                <?php foreach ($team_members as $member): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($member['full_name']) ?></td>
                                        <td><?= htmlspecialchars($member['role_title']) ?></td>
                                        <td><?= htmlspecialchars($member['contact_info'] ?: 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="data-card">
                    <div class="section-header">
                        <div>
                            <span class="section-kicker">Assignment History</span>
                            <h3>Current Task Assignments</h3>
                        </div>
                        <p>Review who is assigned to each status item and when the assignment was made.</p>
                    </div>
                    <?php if (count($assignments) === 0): ?>
                        <div class="empty-state">No status items have been assigned yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="dashboard-table">
                                <tr>
                                    <th>Status Item</th>
                                    <th>Assigned To</th>
                                    <th>Notes</th>
                                </tr>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($assignment['stage_name']) ?><br>
                                            <small><?= htmlspecialchars(formatStatusLabel($assignment['stage_status'])) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($assignment['full_name']) ?><br>
                                            <small><?= htmlspecialchars($assignment['role_title']) ?></small><br>
                                            <small><?= htmlspecialchars($assignment['contact_info'] ?: 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <?= nl2br(htmlspecialchars($assignment['assignment_notes'] ?: 'No notes')) ?><br>
                                            <small><?= date("d M Y, H:i", strtotime($assignment['assigned_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
