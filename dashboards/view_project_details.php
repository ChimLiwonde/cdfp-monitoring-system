<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$project_id = intval($_GET['id'] ?? 0);
if ($project_id <= 0) {
    header("Location: my_projects.php");
    exit();
}

$project = $conn->query("
    SELECT p.id, p.title, p.district, p.location, p.status, p.review_notes, p.reviewed_at,
           p.estimated_budget, p.contractor_fee, reviewer.username AS reviewed_by_name
    FROM projects p
    LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by
    WHERE p.id = $project_id AND p.created_by = $user_id
")->fetch_assoc();

if (!$project) {
    header("Location: my_projects.php");
    exit();
}

$contractors_result = $conn->query("
    SELECT c.name, c.phone, c.company
    FROM contractor_projects cp
    JOIN contractors c ON cp.contractor_id = c.id
    WHERE cp.project_id = $project_id
");
$contractors = [];
while ($row = $contractors_result->fetch_assoc()) {
    $contractors[] = $row;
}

$totals = $conn->query("
    SELECT
        COALESCE(SUM(allocated_budget),0) AS allocated,
        COALESCE(SUM(spent_budget),0) AS spent
    FROM project_stages
    WHERE project_id = $project_id
")->fetch_assoc();

$allocated = $totals['allocated'];
$spent = $totals['spent'];
$balance = $allocated - $spent;
$project_total_budget = (float) $project['estimated_budget'] + (float) $project['contractor_fee'];
$remaining_to_allocate = $project_total_budget - (float) $allocated;
$remaining_to_spend = $project_total_budget - (float) $spent;

$expenses_result = $conn->query("
    SELECT expense_title, category, amount, expense_date, vendor_name, notes
    FROM project_expenses
    WHERE project_id = $project_id
    ORDER BY expense_date DESC, id DESC
");
$expenses = [];
while ($row = $expenses_result->fetch_assoc()) {
    $expenses[] = $row;
}

$assignments_result = $conn->query("
    SELECT ps.stage_name, ptm.full_name, ptm.role_title, ptm.contact_info, psa.assignment_notes
    FROM project_stage_assignments psa
    JOIN project_stages ps ON ps.id = psa.stage_id
    JOIN project_team_members ptm ON ptm.id = psa.team_member_id
    WHERE ps.project_id = $project_id
    ORDER BY ps.planned_start ASC, psa.assigned_at DESC
");
$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}

$collaboration_result = $conn->query("
    SELECT pcm.message, pcm.sender_role, pcm.created_at, u.username
    FROM project_collaboration_messages pcm
    JOIN users u ON u.id = pcm.sender_id
    WHERE pcm.project_id = $project_id
    ORDER BY pcm.created_at DESC, pcm.id DESC
");
$collaboration_messages = [];
while ($row = $collaboration_result->fetch_assoc()) {
    $collaboration_messages[] = $row;
}

$stages_result = $conn->query("
    SELECT stage_name, allocated_budget, spent_budget, status
    FROM project_stages
    WHERE project_id = $project_id
");
$stages = [];
while ($row = $stages_result->fetch_assoc()) {
    $stages[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Summary</title>
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
                <span class="eyebrow">Project Summary</span>
                <h3><?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?></h3>
                <p>Review project status, assigned resources, expenditure, collaboration, and stage-level budget health from one detailed summary.</p>
                <div class="hero-actions">
                    <a href="my_projects.php" class="back-btn">Back to My Projects</a>
                    <a href="project_collaboration.php?project_id=<?= $project['id'] ?>" class="button-link btn-secondary">Open Collaboration</a>
                </div>
            </div>
            <div class="hero-pills">
                <div class="hero-pill"><strong><?= formatStatusLabel($project['status']) ?></strong>&nbsp; Status</div>
                <div class="hero-pill"><strong>MWK <?= number_format($project_total_budget, 0) ?></strong>&nbsp; Budget</div>
            </div>
        </div>
    </div>

    <div class="data-card">
        <div class="detail-grid">
            <div class="detail-card">
                <strong>Project ID</strong>
                <span><?= formatProjectCode($project['id']) ?></span>
            </div>
            <div class="detail-card">
                <strong>Status</strong>
                <span class="status-badge <?= htmlspecialchars($project['status']) ?>"><?= htmlspecialchars(formatStatusLabel($project['status'])) ?></span>
            </div>
            <div class="detail-card">
                <strong>District</strong>
                <span><?= htmlspecialchars($project['district']) ?></span>
            </div>
            <div class="detail-card">
                <strong>Location</strong>
                <span><?= htmlspecialchars($project['location']) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($project['reviewed_at']) || !empty($project['review_notes'])): ?>
        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Admin Review</span>
                    <h3>Review Notes</h3>
                </div>
                <p>Keep the review history visible alongside the project summary.</p>
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

    <div class="section-grid">
        <div class="data-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Contractor View</span>
                    <h3>Assigned Contractor(s)</h3>
                </div>
                <p>See every contractor currently linked to this project.</p>
            </div>
            <?php if (count($contractors) === 0): ?>
                <div class="empty-state">No contractor assigned.</div>
            <?php else: ?>
                <div class="detail-grid">
                    <?php foreach ($contractors as $contractor): ?>
                        <div class="detail-card">
                            <strong><?= htmlspecialchars($contractor['name']) ?></strong>
                            <span>Company: <?= htmlspecialchars($contractor['company']) ?></span><br>
                            <span>Phone: <?= htmlspecialchars($contractor['phone']) ?></span>
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
                <p>Compare total project budget, allocations, and spending without mixing them into project status.</p>
            </div>
            <div class="detail-grid">
                <div class="detail-card">
                    <strong>Estimated Budget</strong>
                    <span>MWK <?= number_format($project['estimated_budget'], 2) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Contractor Fee</strong>
                    <span>MWK <?= number_format($project['contractor_fee'], 2) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Total Project Budget</strong>
                    <span>MWK <?= number_format($project_total_budget, 2) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Total Allocated</strong>
                    <span>MWK <?= number_format($allocated, 2) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Remaining to Allocate</strong>
                    <span>MWK <?= number_format($remaining_to_allocate, 2) ?></span>
                </div>
                <div class="detail-card">
                    <strong>Remaining to Spend</strong>
                    <span style="color:<?= $spent > $allocated ? '#b74b3d' : '#1d7a4c' ?>;">MWK <?= number_format($remaining_to_spend, 2) ?></span>
                </div>
            </div>
            <p class="section-copy" style="margin-top:16px;color:<?= $spent > $allocated ? '#b74b3d' : '#1d7a4c' ?>;">
                <strong><?= $spent > $allocated ? 'Overspent by:' : 'Within budget:' ?></strong>
                MWK <?= number_format(abs($balance), 2) ?>
            </p>
        </div>
    </div>

    <div class="data-card">
        <div class="section-header">
            <div>
                <span class="section-kicker">Task Assignments</span>
                <h3>Assigned Team Members</h3>
            </div>
            <p>Track which team member owns each project status item.</p>
        </div>
        <?php if (count($assignments) === 0): ?>
            <div class="empty-state">No task assignments recorded yet.</div>
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
                            <td><?= htmlspecialchars($assignment['stage_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($assignment['full_name']) ?><br>
                                <small><?= htmlspecialchars($assignment['role_title']) ?></small><br>
                                <small><?= htmlspecialchars($assignment['contact_info'] ?: 'N/A') ?></small>
                            </td>
                            <td><?= nl2br(htmlspecialchars($assignment['assignment_notes'] ?: 'No notes')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="data-card">
        <div class="section-header">
            <div>
                <span class="section-kicker">Expenditure Ledger</span>
                <h3>Recorded Expenses</h3>
            </div>
            <p>Review all expense entries linked to this project.</p>
        </div>
        <?php if (count($expenses) === 0): ?>
            <div class="empty-state">No expenses recorded yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="dashboard-table">
                    <tr>
                        <th>Date</th>
                        <th>Expense</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Notes</th>
                    </tr>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= htmlspecialchars($expense['expense_date']) ?></td>
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
                <span class="section-kicker">Internal Collaboration</span>
                <h3>Stakeholder Messages</h3>
            </div>
            <p>Messages shared internally about this project.</p>
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
                <span class="section-kicker">Status History</span>
                <h3>Project Status Timeline</h3>
            </div>
            <p>Budget and status progression for each project status item.</p>
        </div>
        <div class="table-wrap">
            <table class="dashboard-table">
                <tr>
                    <th>Status Item</th>
                    <th>Allocated</th>
                    <th>Spent</th>
                    <th>Remaining</th>
                    <th>Status</th>
                </tr>

                <?php if (count($stages) === 0): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;color:gray;">No status items added yet.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($stages as $stage): ?>
                    <?php $stage_remaining = (float) $stage['allocated_budget'] - (float) $stage['spent_budget']; ?>
                    <tr>
                        <td><?= htmlspecialchars($stage['stage_name']) ?></td>
                        <td>MWK <?= number_format($stage['allocated_budget'], 2) ?></td>
                        <td>MWK <?= number_format($stage['spent_budget'], 2) ?></td>
                        <td>MWK <?= number_format($stage_remaining, 2) ?></td>
                        <td><?= htmlspecialchars(formatStatusLabel($stage['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
