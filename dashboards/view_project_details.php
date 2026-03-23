<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* SECURITY */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$project_id = intval($_GET['id'] ?? 0);
if ($project_id <= 0) {
    header("Location: my_projects.php");
    exit();
}

/* =========================
   FETCH PROJECT
========================= */
$project = $conn->query("
    SELECT id, title, district, location, estimated_budget, contractor_fee
    FROM projects
    WHERE id = $project_id AND created_by = $user_id
")->fetch_assoc();

if (!$project) {
    header("Location: my_projects.php");
    exit();
}

/* =========================
   FETCH ASSIGNED CONTRACTORS
========================= */
$contractors = $conn->query("
    SELECT c.name, c.phone, c.company
    FROM contractor_projects cp
    JOIN contractors c ON cp.contractor_id = c.id
    WHERE cp.project_id = $project_id
");

/* =========================
   STAGE FINANCIAL TOTALS
========================= */
$totals = $conn->query("
    SELECT 
        COALESCE(SUM(allocated_budget),0) AS allocated,
        COALESCE(SUM(spent_budget),0) AS spent
    FROM project_stages
    WHERE project_id = $project_id
")->fetch_assoc();

$allocated = $totals['allocated'];
$spent     = $totals['spent'];
$balance   = $allocated - $spent;

$expenses = $conn->query("
    SELECT expense_title, category, amount, expense_date, vendor_name, notes
    FROM project_expenses
    WHERE project_id = $project_id
    ORDER BY expense_date DESC, id DESC
");

$assignments = $conn->query("
    SELECT ps.stage_name, ptm.full_name, ptm.role_title, ptm.contact_info, psa.assignment_notes
    FROM project_stage_assignments psa
    JOIN project_stages ps ON ps.id = psa.stage_id
    JOIN project_team_members ptm ON ptm.id = psa.team_member_id
    WHERE ps.project_id = $project_id
    ORDER BY ps.planned_start ASC, psa.assigned_at DESC
");

$collaboration_messages = $conn->query("
    SELECT pcm.message, pcm.sender_role, pcm.created_at, u.username
    FROM project_collaboration_messages pcm
    JOIN users u ON u.id = pcm.sender_id
    WHERE pcm.project_id = $project_id
    ORDER BY pcm.created_at DESC, pcm.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Summary</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
<div class="col-3"><?php include "menu.php"; ?></div>

<div class="col-9">
<div class="form-card">

    <a href="my_projects.php" class="back-btn">Back to My Projects</a>

    <h3><?= formatProjectCode($project['id']) ?> - <?= htmlspecialchars($project['title']) ?> Summary</h3>

    <p><strong>Project ID:</strong> <?= formatProjectCode($project['id']) ?></p>
    <p><strong>District:</strong> <?= htmlspecialchars($project['district']) ?></p>
    <p><strong>Location:</strong> <?= htmlspecialchars($project['location']) ?></p>
    <p><a href="project_collaboration.php?project_id=<?= $project['id'] ?>">Open Internal Collaboration Channel</a></p>

    <hr>

    <h4>Assigned Contractor(s)</h4>

    <?php if ($contractors->num_rows > 0): ?>
        <ul>
            <?php while ($c = $contractors->fetch_assoc()): ?>
                <li>
                    <strong><?= htmlspecialchars($c['name']) ?></strong><br>
                    Company: <?= htmlspecialchars($c['company']) ?><br>
                    Phone: <?= htmlspecialchars($c['phone']) ?>
                </li>
                <br>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p style="color:#999;">No contractor assigned</p>
    <?php endif; ?>

    <hr>

    <h4>Financial Summary</h4>
    <p><strong>Estimated Budget:</strong> MWK <?= number_format($project['estimated_budget'],2) ?></p>
    <p><strong>Contractor Fee:</strong> MWK <?= number_format($project['contractor_fee'],2) ?></p>

    <p><strong>Total Allocated (Status Items):</strong> MWK <?= number_format($allocated,2) ?></p>
    <p><strong>Total Spent:</strong> MWK <?= number_format($spent,2) ?></p>

    <?php if ($spent > $allocated): ?>
        <p style="color:red;"><strong>Overspent by:</strong> MWK <?= number_format(abs($balance),2) ?></p>
    <?php else: ?>
        <p style="color:green;"><strong>Within Budget</strong></p>
    <?php endif; ?>

    <hr>

    <h4>Task Assignments</h4>
    <?php if ($assignments->num_rows > 0): ?>
        <table class="dashboard-table">
            <tr>
                <th>Status Item</th>
                <th>Assigned To</th>
                <th>Notes</th>
            </tr>
            <?php while ($assignment = $assignments->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($assignment['stage_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($assignment['full_name']) ?><br>
                        <small><?= htmlspecialchars($assignment['role_title']) ?></small><br>
                        <small><?= htmlspecialchars($assignment['contact_info'] ?: 'N/A') ?></small>
                    </td>
                    <td><?= nl2br(htmlspecialchars($assignment['assignment_notes'] ?: 'No notes')) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p style="color:#999;">No task assignments recorded yet.</p>
    <?php endif; ?>

    <hr>

    <h4>Expenditure Ledger</h4>
    <?php if ($expenses->num_rows > 0): ?>
        <table class="dashboard-table">
            <tr>
                <th>Date</th>
                <th>Expense</th>
                <th>Vendor</th>
                <th>Amount</th>
                <th>Notes</th>
            </tr>
            <?php while ($expense = $expenses->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                    <td>
                        <?= htmlspecialchars($expense['expense_title']) ?><br>
                        <small><?= htmlspecialchars($expense['category']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($expense['vendor_name'] ?: 'N/A') ?></td>
                    <td><?= number_format((float) $expense['amount'],2) ?></td>
                    <td><?= nl2br(htmlspecialchars($expense['notes'] ?: 'No notes')) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p style="color:#999;">No expenses recorded yet.</p>
    <?php endif; ?>

    <hr>

    <h4>Internal Collaboration</h4>
    <?php if ($collaboration_messages->num_rows > 0): ?>
        <table class="dashboard-table">
            <tr>
                <th>From</th>
                <th>Message</th>
                <th>When</th>
            </tr>
            <?php while ($chat = $collaboration_messages->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($chat['username']) ?> (<?= htmlspecialchars(formatStatusLabel($chat['sender_role'])) ?>)</td>
                    <td><?= nl2br(htmlspecialchars($chat['message'])) ?></td>
                    <td><?= date("d M Y, H:i", strtotime($chat['created_at'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p style="color:#999;">No internal collaboration messages yet.</p>
    <?php endif; ?>

    <hr>

    <h4>Project Status History</h4>
    <table class="dashboard-table">
        <tr>
            <th>Status Item</th>
            <th>Allocated</th>
            <th>Spent</th>
            <th>Status</th>
        </tr>

        <?php
        $stages = $conn->query("
            SELECT stage_name, allocated_budget, spent_budget, status
            FROM project_stages
            WHERE project_id = $project_id
        ");

        while ($s = $stages->fetch_assoc()):
        ?>
        <tr>
            <td><?= htmlspecialchars($s['stage_name']) ?></td>
            <td><?= number_format($s['allocated_budget'],2) ?></td>
            <td><?= number_format($s['spent_budget'],2) ?></td>
            <td><?= htmlspecialchars(formatStatusLabel($s['status'])) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

</div>
</div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
