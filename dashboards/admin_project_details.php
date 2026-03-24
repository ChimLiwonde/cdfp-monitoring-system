<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

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
        p.review_notes,
        p.reviewed_at,
        p.estimated_budget,
        p.contractor_fee,
        p.created_at,
        u.username AS field_officer,
        u.email AS field_officer_email,
        reviewer.username AS reviewed_by_name,
        pm.latitude,
        pm.longitude
    FROM projects p
    JOIN users u ON u.id = p.created_by
    LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by
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
$remaining_to_allocate = $project_budget_total - (float) $totals['allocated'];
$mapLat = normalizeCoordinate($project['latitude'] ?? null, -90, 90);
$mapLng = normalizeCoordinate($project['longitude'] ?? null, -180, 180);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "adminmenu.php"; ?></div>

    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Admin Project Detail</span>
                    <h3><?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?></h3>
                    <p>Review ownership, finances, status history, collaboration, and public feedback from one complete project record.</p>
                    <div class="hero-actions">
                        <a href="adminprojects.php?status=all" class="back-btn">Back to Projects</a>
                        <a href="project_collaboration.php?project_id=<?= $project['id'] ?>" class="button-link btn-secondary">Open Collaboration</a>
                    </div>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= formatStatusLabel($project['status']) ?></strong>&nbsp; Status</div>
                    <div class="hero-pill"><strong><?= count($stages) ?></strong>&nbsp; Status Items</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($success_message): ?>
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <div class="detail-grid">
                <div class="detail-card">
                    <strong>Project Lead</strong>
                    <span><?= htmlspecialchars($project['field_officer']) ?> (<?= htmlspecialchars($project['field_officer_email']) ?>)</span>
                </div>
                <div class="detail-card">
                    <strong>District</strong>
                    <span><?= htmlspecialchars($project['district']) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Location</strong>
                    <span><?= htmlspecialchars($project['location']) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Created</strong>
                    <span><?= date("d M Y, H:i", strtotime($project['created_at'])) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Status</strong>
                    <span class="status-badge <?= htmlspecialchars($project['status']) ?>"><?= htmlspecialchars(formatStatusLabel($project['status'])) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Description</strong>
                    <span><?= nl2br(htmlspecialchars($project['description'])) ?></span>
                </div>
            </div>

            <?php if ($project['status'] === 'pending'): ?>
                <div class="action-strip" style="margin-top:18px;">
                    <a class="button-link btn-secondary" href="admin_update_project_status.php?id=<?= $project['id'] ?>&status=approved&return_to=detail">Approve Project</a>
                    <a class="back-btn" href="admin_update_project_status.php?id=<?= $project['id'] ?>&status=denied&return_to=detail" onclick="return confirm('Are you sure you want to deny this project?');">Deny Project</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($project['reviewed_at']) || !empty($project['review_notes'])): ?>
            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Admin Review</span>
                        <h3>Review Record</h3>
                    </div>
                    <p>Keep the official review notes visible alongside the full project record.</p>
                </div>
                <div class="detail-grid">
                    <?php if (!empty($project['reviewed_at'])): ?>
                        <div class="detail-card">
                            <strong>Reviewed On</strong>
                            <span><?= date("d M Y, H:i", strtotime($project['reviewed_at'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($project['reviewed_by_name'])): ?>
                        <div class="detail-card">
                            <strong>Reviewed By</strong>
                            <span><?= htmlspecialchars($project['reviewed_by_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-card">
                        <strong>Review Note</strong>
                        <span><?= nl2br(htmlspecialchars($project['review_notes'] ?: 'No review note recorded.')) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="summary-cards">
            <div class="summary-card card-total">
                <h3>Project Budget</h3>
                <h2>MWK <?= number_format($project_budget_total, 2) ?></h2>
            </div>
            <div class="summary-card card-info">
                <h3>Total Spent</h3>
                <h2>MWK <?= number_format((float) $totals['spent'], 2) ?></h2>
            </div>
            <div class="summary-card <?= $remaining_budget < 0 ? 'card-denied' : 'card-approved' ?>">
                <h3>Remaining Budget</h3>
                <h2>MWK <?= number_format($remaining_budget, 2) ?></h2>
            </div>
            <div class="summary-card card-community">
                <h3>Remaining to Allocate</h3>
                <h2>MWK <?= number_format($remaining_to_allocate, 2) ?></h2>
            </div>
            <div class="summary-card card-completed">
                <h3>Status Items</h3>
                <h2><?= count($stages) ?></h2>
                <p class="metric-meta"><?= $completed_items ?> completed, <?= $active_items ?> active</p>
            </div>
        </div>

        <?php if ($mapLat !== null && $mapLng !== null): ?>
            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Project Map</span>
                        <h3>Location View</h3>
                    </div>
                    <p>Use the mapped coordinates to verify the project location visually.</p>
                </div>
                <div id="projectDetailMap" class="map-panel"></div>
            </div>
        <?php endif; ?>

        <div class="section-grid">
            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Contractor View</span>
                        <h3>Assigned Contractors</h3>
                    </div>
                    <p>Every contractor currently attached to this project.</p>
                </div>
                <?php if (count($contractors) === 0): ?>
                    <div class="empty-state">No contractor assigned.</div>
                <?php else: ?>
                    <div class="detail-grid">
                        <?php foreach ($contractors as $contractor): ?>
                            <div class="detail-card">
                                <strong><?= htmlspecialchars($contractor['name']) ?></strong>
                                <span>Company: <?= htmlspecialchars($contractor['company'] ?: 'N/A') ?></span><br>
                                <span>Phone: <?= htmlspecialchars($contractor['phone'] ?: 'N/A') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Financial Summary</span>
                        <h3>Budget Position</h3>
                    </div>
                    <p>Compare allocations and spending across the full project budget.</p>
                </div>
                <div class="detail-grid">
                    <div class="detail-card">
                        <strong>Estimated Budget</strong>
                        <span>MWK <?= number_format((float) $project['estimated_budget'], 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Contractor Fee</strong>
                        <span>MWK <?= number_format((float) $project['contractor_fee'], 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Total Allocated</strong>
                        <span>MWK <?= number_format((float) $totals['allocated'], 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Total Spent</strong>
                        <span>MWK <?= number_format((float) $totals['spent'], 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Remaining to Allocate</strong>
                        <span>MWK <?= number_format($remaining_to_allocate, 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Remaining to Spend</strong>
                        <span style="color:<?= $remaining_budget < 0 ? '#b74b3d' : '#1d7a4c' ?>;">MWK <?= number_format($remaining_budget, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Task Assignments</span>
                    <h3>Assigned Team Members</h3>
                </div>
                <p>See which people are assigned to each status item.</p>
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

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Internal Collaboration</span>
                    <h3>Stakeholder Messages</h3>
                </div>
                <p>Messages exchanged internally about this project.</p>
            </div>
            <?php if (count($collaboration_messages) === 0): ?>
                <div class="empty-state">No internal collaboration messages yet.</div>
            <?php else: ?>
                <div class="table-wrap">
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
                </div>
            <?php endif; ?>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Workflow History</span>
                    <h3>Approval and Activity Timeline</h3>
                </div>
                <p>Follow approvals, status transitions, and system events for this project.</p>
            </div>
            <?php if (count($activities) === 0): ?>
                <div class="empty-state">No workflow activity has been recorded for this project yet.</div>
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
                            <p><strong>Transition:</strong> <?= htmlspecialchars(formatStatusLabel($activity['old_status'])) ?> to <?= htmlspecialchars(formatStatusLabel($activity['new_status'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($activity['notes'])): ?>
                            <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($activity['notes'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Expenditure Ledger</span>
                    <h3>Recorded Expenses</h3>
                </div>
                <p>All expense entries linked to this project.</p>
            </div>
            <?php if (count($expenses) === 0): ?>
                <div class="empty-state">No expenses recorded yet.</div>
            <?php else: ?>
                <div class="table-wrap">
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
                </div>
            <?php endif; ?>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Status Timeline</span>
                    <h3>Project Status History</h3>
                </div>
                <p>Planned dates, actual dates, and budget movement for each status item.</p>
            </div>
            <div class="table-wrap">
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
                        <?php $stage_remaining = (float) $stage['allocated_budget'] - (float) $stage['spent_budget']; ?>
                        <tr>
                            <td><?= htmlspecialchars($stage['stage_name']) ?></td>
                            <td><?= htmlspecialchars($stage['planned_start']) ?> to <?= htmlspecialchars($stage['planned_end']) ?></td>
                            <td><?= htmlspecialchars($stage['actual_start'] ?: 'N/A') ?> to <?= htmlspecialchars($stage['actual_end'] ?: 'N/A') ?></td>
                            <td>
                                Alloc: <?= number_format((float) $stage['allocated_budget'], 2) ?><br>
                                Spent: <?= number_format((float) $stage['spent_budget'], 2) ?><br>
                                Remaining: <?= number_format($stage_remaining, 2) ?>
                            </td>
                            <td><?= htmlspecialchars(formatStatusLabel($stage['status'])) ?></td>
                            <td><?= nl2br(htmlspecialchars($stage['notes'] ?: '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Citizen Feedback</span>
                    <h3>Project Comments</h3>
                </div>
                <p>Public-facing comments and admin replies linked to this project.</p>
            </div>
            <?php if (count($comments) === 0): ?>
                <div class="empty-state">No comments on this project yet.</div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="public-comment">
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

<?php if ($mapLat !== null && $mapLng !== null): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('projectDetailMap').setView([<?= json_encode((float) $mapLat) ?>, <?= json_encode((float) $mapLng) ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
L.marker([<?= json_encode((float) $mapLat) ?>, <?= json_encode((float) $mapLng) ?>]).addTo(map);
</script>
<?php endif; ?>

</body>
</html>
