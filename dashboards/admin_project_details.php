<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$project_id = intval($_GET['id'] ?? 0);
if ($project_id <= 0) {
    header("Location: adminprojects.php?status=all");
    exit();
}

$project_stmt = $conn->prepare("
    SELECT
        p.id,
        p.title,
        p.description,
        p.district,
        p.location,
        p.status,
        p.estimated_budget,
        p.contractor_fee,
        p.created_at,
        u.username AS field_officer,
        u.email AS field_officer_email,
        pm.latitude,
        pm.longitude
    FROM projects p
    JOIN users u ON u.id = p.created_by
    LEFT JOIN project_maps pm ON pm.project_id = p.id
    WHERE p.id = ?
");
$project_stmt->bind_param("i", $project_id);
$project_stmt->execute();
$project = $project_stmt->get_result()->fetch_assoc();

if (!$project) {
    header("Location: adminprojects.php?status=all");
    exit();
}

$contractors_stmt = $conn->prepare("
    SELECT c.name, c.phone, c.company
    FROM contractor_projects cp
    JOIN contractors c ON cp.contractor_id = c.id
    WHERE cp.project_id = ?
");
$contractors_stmt->bind_param("i", $project_id);
$contractors_stmt->execute();
$contractors_result = $contractors_stmt->get_result();
$contractors = [];
while ($row = $contractors_result->fetch_assoc()) {
    $contractors[] = $row;
}

$totals_stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(allocated_budget), 0) AS allocated,
        COALESCE(SUM(spent_budget), 0) AS spent
    FROM project_stages
    WHERE project_id = ?
");
$totals_stmt->bind_param("i", $project_id);
$totals_stmt->execute();
$totals = $totals_stmt->get_result()->fetch_assoc();

$comments_stmt = $conn->prepare("
    SELECT pc.comment, pc.admin_reply, pc.created_at, u.username
    FROM project_comments pc
    JOIN users u ON u.id = pc.user_id
    WHERE pc.project_id = ?
    ORDER BY pc.created_at DESC
");
$comments_stmt->bind_param("i", $project_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
$comments = [];
while ($row = $comments_result->fetch_assoc()) {
    $comments[] = $row;
}

$expenses_stmt = $conn->prepare("
    SELECT
        pe.expense_title,
        pe.category,
        pe.vendor_name,
        pe.amount,
        pe.expense_date,
        pe.notes,
        ps.stage_name
    FROM project_expenses pe
    JOIN project_stages ps ON ps.id = pe.stage_id
    WHERE pe.project_id = ?
    ORDER BY pe.expense_date DESC, pe.id DESC
");
$expenses_stmt->bind_param("i", $project_id);
$expenses_stmt->execute();
$expenses_result = $expenses_stmt->get_result();
$expenses = [];
while ($row = $expenses_result->fetch_assoc()) {
    $expenses[] = $row;
}

$assignments_stmt = $conn->prepare("
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
$assignments_stmt->bind_param("i", $project_id);
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();
$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}

$collaboration_stmt = $conn->prepare("
    SELECT pcm.message, pcm.sender_role, pcm.created_at, u.username
    FROM project_collaboration_messages pcm
    JOIN users u ON u.id = pcm.sender_id
    WHERE pcm.project_id = ?
    ORDER BY pcm.created_at DESC, pcm.id DESC
");
$collaboration_stmt->bind_param("i", $project_id);
$collaboration_stmt->execute();
$collaboration_result = $collaboration_stmt->get_result();
$collaboration_messages = [];
while ($row = $collaboration_result->fetch_assoc()) {
    $collaboration_messages[] = $row;
}

$stages_stmt = $conn->prepare("
    SELECT stage_name, planned_start, planned_end, actual_start, actual_end, allocated_budget, spent_budget, status, notes
    FROM project_stages
    WHERE project_id = ?
    ORDER BY planned_start ASC, id ASC
");
$stages_stmt->bind_param("i", $project_id);
$stages_stmt->execute();
$stages_result = $stages_stmt->get_result();
$stages = [];
while ($row = $stages_result->fetch_assoc()) {
    $stages[] = $row;
}

$activities = [];

$activity_stmt = $conn->prepare("
    SELECT
        pal.event_type,
        pal.actor_role,
        pal.old_status,
        pal.new_status,
        pal.notes,
        pal.created_at,
        u.username AS actor_name
    FROM project_activity_log pal
    LEFT JOIN users u ON u.id = pal.actor_id
    WHERE pal.project_id = ?
    ORDER BY pal.created_at DESC, pal.id DESC
");

if ($activity_stmt) {
    $activity_stmt->bind_param("i", $project_id);
    $activity_stmt->execute();
    $activity_result = $activity_stmt->get_result();
    while ($row = $activity_result->fetch_assoc()) {
        $activities[] = $row;
    }
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$project_budget_total = (float) $project['estimated_budget'] + (float) $project['contractor_fee'];
$remaining_budget = $project_budget_total - (float) $totals['spent'];
$completed_items = 0;
$active_items = 0;

foreach ($stages as $stage) {
    if ($stage['status'] === 'completed') {
        $completed_items++;
    }
    if ($stage['status'] === 'in_progress') {
        $active_items++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Project Details</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #projectDetailMap {
            height: 320px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .status-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #e3f2fd;
            color: #0d47a1;
            font-weight: 600;
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 20px 0;
        }
        .quick-stat {
            background: #f7fbff;
            border: 1px solid #d6eafc;
            border-radius: 10px;
            padding: 14px;
        }
        .quick-stat strong {
            display: block;
            color: #0d47a1;
            margin-bottom: 6px;
        }
        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0 5px;
        }
        .action-link {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            color: #fff !important;
            font-weight: 600;
            text-decoration: none;
        }
        .action-approve {
            background: #2e7d32;
        }
        .action-deny {
            background: #c62828;
        }
        .activity-card {
            border: 1px solid #e4eef7;
            border-radius: 10px;
            padding: 14px;
            background: #fafcff;
            margin-bottom: 12px;
        }
        .activity-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .activity-head strong {
            color: #0d47a1;
        }
        .muted {
            color: #666;
        }
    </style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>

    <div class="col-9">
        <div class="form-card">
            <a href="adminprojects.php?status=all" class="back-btn">Back to Projects</a>

            <h3><?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?></h3>

            <?php if ($success_message): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <p><span class="status-pill"><?= formatStatusLabel($project['status']) ?></span></p>
            <p><strong>Project Lead:</strong> <?= htmlspecialchars($project['field_officer']) ?> (<?= htmlspecialchars($project['field_officer_email']) ?>)</p>
            <p><strong>District:</strong> <?= htmlspecialchars($project['district']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($project['location']) ?></p>
            <p><strong>Created:</strong> <?= date("d M Y, H:i", strtotime($project['created_at'])) ?></p>
            <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($project['description'])) ?></p>
            <p><a href="project_collaboration.php?project_id=<?= $project['id'] ?>">Open Internal Collaboration Channel</a></p>

            <?php if ($project['status'] === 'pending'): ?>
                <div class="action-row">
                    <a class="action-link action-approve" href="admin_update_project_status.php?id=<?= $project['id'] ?>&status=approved&return_to=detail">Approve Project</a>
                    <a class="action-link action-deny" href="admin_update_project_status.php?id=<?= $project['id'] ?>&status=denied&return_to=detail" onclick="return confirm('Are you sure you want to deny this project?');">Deny Project</a>
                </div>
            <?php endif; ?>

            <div class="quick-stats">
                <div class="quick-stat">
                    <strong>Project Budget</strong>
                    MWK <?= number_format($project_budget_total, 2) ?>
                </div>
                <div class="quick-stat">
                    <strong>Total Spent</strong>
                    MWK <?= number_format((float) $totals['spent'], 2) ?>
                </div>
                <div class="quick-stat">
                    <strong>Remaining Budget</strong>
                    MWK <?= number_format($remaining_budget, 2) ?>
                </div>
                <div class="quick-stat">
                    <strong>Status Items</strong>
                    <?= count($stages) ?> total, <?= $completed_items ?> completed, <?= $active_items ?> active
                </div>
            </div>

            <?php if (!empty($project['latitude']) && !empty($project['longitude'])): ?>
                <div id="projectDetailMap"></div>
            <?php endif; ?>

            <hr>

            <h4>Financial Summary</h4>
            <p><strong>Estimated Budget:</strong> MWK <?= number_format((float) $project['estimated_budget'], 2) ?></p>
            <p><strong>Contractor Fee:</strong> MWK <?= number_format((float) $project['contractor_fee'], 2) ?></p>
            <p><strong>Total Allocated:</strong> MWK <?= number_format((float) $totals['allocated'], 2) ?></p>
            <p><strong>Total Spent:</strong> MWK <?= number_format((float) $totals['spent'], 2) ?></p>

            <hr>

            <h4>Assigned Contractors</h4>
            <?php if (count($contractors) === 0): ?>
                <p class="muted">No contractor assigned.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($contractors as $contractor): ?>
                        <li>
                            <strong><?= htmlspecialchars($contractor['name']) ?></strong><br>
                            Company: <?= htmlspecialchars($contractor['company'] ?: 'N/A') ?><br>
                            Phone: <?= htmlspecialchars($contractor['phone'] ?: 'N/A') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <hr>

            <h4>Task Assignments</h4>
            <?php if (count($assignments) === 0): ?>
                <p class="muted">No status items have been assigned yet.</p>
            <?php else: ?>
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
            <?php endif; ?>

            <hr>

            <h4>Internal Collaboration</h4>
            <?php if (count($collaboration_messages) === 0): ?>
                <p class="muted">No internal collaboration messages yet.</p>
            <?php else: ?>
                <table class="dashboard-table">
                    <tr>
                        <th>From</th>
                        <th>Message</th>
                        <th>When</th>
                    </tr>
                    <?php foreach ($collaboration_messages as $chat): ?>
                        <tr>
                            <td><?= htmlspecialchars($chat['username']) ?> (<?= htmlspecialchars(formatRoleLabel($chat['sender_role'])) ?>)</td>
                            <td><?= nl2br(htmlspecialchars($chat['message'])) ?></td>
                            <td><?= date("d M Y, H:i", strtotime($chat['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <hr>

            <h4>Workflow / Approval History</h4>
            <?php if (count($activities) === 0): ?>
                <p class="muted">No workflow activity has been recorded for this project yet.</p>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                    <?php
                    $actor_label = 'System';
                    if (!empty($activity['actor_name'])) {
                        $actor_label = $activity['actor_name'];
                        if (!empty($activity['actor_role'])) {
                            $actor_label .= ' (' . formatRoleLabel($activity['actor_role']) . ')';
                        }
                    } elseif (!empty($activity['actor_role'])) {
                        $actor_label = formatRoleLabel($activity['actor_role']);
                    }
                    ?>
                    <div class="activity-card">
                        <div class="activity-head">
                            <strong><?= htmlspecialchars(formatActivityLabel($activity['event_type'])) ?></strong>
                            <span class="muted"><?= date("d M Y, H:i", strtotime($activity['created_at'])) ?></span>
                        </div>
                        <p><strong>Actor:</strong> <?= htmlspecialchars($actor_label ?: 'System') ?></p>
                        <?php if ($activity['old_status'] !== null || $activity['new_status'] !== null): ?>
                            <p>
                                <strong>Transition:</strong>
                                <?= htmlspecialchars(formatStatusLabel($activity['old_status'])) ?>
                                to
                                <?= htmlspecialchars(formatStatusLabel($activity['new_status'])) ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($activity['notes'])): ?>
                            <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($activity['notes'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <hr>

            <h4>Expenditure Ledger</h4>
            <?php if (count($expenses) === 0): ?>
                <p class="muted">No expenses recorded yet.</p>
            <?php else: ?>
                <table class="dashboard-table">
                    <tr>
                        <th>Date</th>
                        <th>Status Item</th>
                        <th>Expense</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Notes</th>
                    </tr>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                            <td><?= htmlspecialchars($expense['stage_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($expense['expense_title']) ?><br>
                                <small><?= htmlspecialchars($expense['category']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($expense['vendor_name'] ?: 'N/A') ?></td>
                            <td>MWK <?= number_format((float) $expense['amount'], 2) ?></td>
                            <td><?= nl2br(htmlspecialchars($expense['notes'] ?: 'No notes')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <hr>

            <h4>Project Status Timeline</h4>
            <table class="dashboard-table">
                <tr>
                    <th>Status Item</th>
                    <th>Planned</th>
                    <th>Actual</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
                <?php if (count($stages) === 0): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:gray;">No status items added yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($stages as $stage): ?>
                    <tr>
                        <td><?= htmlspecialchars($stage['stage_name']) ?></td>
                        <td><?= htmlspecialchars($stage['planned_start']) ?> to <?= htmlspecialchars($stage['planned_end']) ?></td>
                        <td><?= htmlspecialchars($stage['actual_start'] ?: 'N/A') ?> to <?= htmlspecialchars($stage['actual_end'] ?: 'N/A') ?></td>
                        <td>Alloc: <?= number_format((float) $stage['allocated_budget'], 2) ?><br>Spent: <?= number_format((float) $stage['spent_budget'], 2) ?></td>
                        <td><?= htmlspecialchars(formatStatusLabel($stage['status'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($stage['notes'] ?: '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <hr>

            <h4>Citizen Comments</h4>
            <?php if (count($comments) === 0): ?>
                <p class="muted">No comments on this project yet.</p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="public-comment" style="margin-bottom:12px;">
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        <small><?= date("d M Y, H:i", strtotime($comment['created_at'])) ?></small><br>
                        <?= nl2br(htmlspecialchars($comment['comment'])) ?>

                        <?php if (!empty($comment['admin_reply'])): ?>
                            <div class="public-reply">
                                <strong>Admin Reply:</strong><br>
                                <?= nl2br(htmlspecialchars($comment['admin_reply'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<?php if (!empty($project['latitude']) && !empty($project['longitude'])): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('projectDetailMap').setView([<?= json_encode($project['latitude']) ?>, <?= json_encode($project['longitude']) ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
L.marker([<?= json_encode($project['latitude']) ?>, <?= json_encode($project['longitude']) ?>]).addTo(map);
</script>
<?php endif; ?>

</body>
</html>
