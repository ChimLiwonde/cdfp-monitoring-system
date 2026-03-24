<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_project_id = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$selected_stage_id = (int) ($_GET['stage_id'] ?? $_POST['stage_id'] ?? 0);
$message = '';

function fetchOwnedProject($conn, $projectId, $userId)
{
    if ($projectId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, title, status, district, location, estimated_budget, contractor_fee
        FROM projects
        WHERE id = ? AND created_by = ?
    ");
    $stmt->bind_param("ii", $projectId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if (isset($_POST['record_expense'])) {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $stage_id = (int) ($_POST['stage_id'] ?? 0);
    $expense_title = trim($_POST['expense_title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $expense_date = trim($_POST['expense_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $project = fetchOwnedProject($conn, $project_id, $user_id);

    if (!$project) {
        $message = "The selected project was not found.";
    } elseif (!in_array($project['status'], ['approved', 'in_progress'], true)) {
        $message = "Expenses can only be recorded for approved or active projects. Completed projects remain view-only.";
    } elseif ($expense_title === '' || $category === '' || $amount <= 0 || $expense_date === '') {
        $message = "Please complete all required expense fields.";
    } else {
        $stage_stmt = $conn->prepare("
            SELECT id, stage_name, allocated_budget
            FROM project_stages
            WHERE id = ? AND project_id = ?
        ");
        $stage_stmt->bind_param("ii", $stage_id, $project_id);
        $stage_stmt->execute();
        $stage = $stage_stmt->get_result()->fetch_assoc();

        if (!$stage) {
            $message = "Please select a valid status item for this expense.";
        } else {
            $project_budget_summary = getProjectBudgetSummary($conn, $project_id);
            $stage_budget_summary = getStageBudgetSummary($conn, $stage_id);
            $remaining_project_budget = (float) $project_budget_summary['remaining_budget'];
            $remaining_stage_budget = (float) $stage_budget_summary['remaining_budget'];

            if ($remaining_project_budget <= 0) {
                $message = "This project has no remaining budget available for new expenses.";
            } elseif ($amount - $remaining_project_budget > 0.01) {
                $message = "This expense exceeds the remaining project budget of MWK " . number_format($remaining_project_budget, 2) . ".";
            } elseif ($remaining_stage_budget <= 0) {
                $message = "The selected status item has no remaining allocated budget.";
            } elseif ($amount - $remaining_stage_budget > 0.01) {
                $message = "This expense exceeds the remaining budget for " . $stage_budget_summary['stage_name'] . " by going above MWK " . number_format($remaining_stage_budget, 2) . ".";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO project_expenses
                    (project_id, stage_id, expense_title, category, vendor_name, amount, expense_date, notes, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iisssdssi",
                    $project_id,
                    $stage_id,
                    $expense_title,
                    $category,
                    $vendor_name,
                    $amount,
                    $expense_date,
                    $notes,
                    $user_id
                );

                if ($stmt->execute()) {
                    syncStageSpentBudget($conn, $stage_id);
                    $updated_project_budget = getProjectBudgetSummary($conn, $project_id);
                    $updated_stage_budget = getStageBudgetSummary($conn, $stage_id);

                    logProjectActivity(
                        $conn,
                        $project_id,
                        'expense_recorded',
                        $user_id,
                        $_SESSION['role'] ?? 'field_officer',
                        null,
                        null,
                        $expense_title . ' recorded under ' . $stage['stage_name'] . ' for MWK ' . number_format($amount, 2) . '.'
                    );

                    $_SESSION['success_message'] =
                        "Expense recorded for " . formatProjectCode($project_id) . ". Remaining project budget: MWK " .
                        number_format(max((float) $updated_project_budget['remaining_budget'], 0), 2) .
                        ". Remaining status-item budget: MWK " .
                        number_format(max((float) $updated_stage_budget['remaining_budget'], 0), 2) . ".";
                    header("Location: project_expenses.php?project_id={$project_id}&stage_id={$stage_id}");
                    exit();
                }

                $message = "Failed to record the expense.";
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

$selected_project = fetchOwnedProject($conn, $selected_project_id, $user_id);
$stage_options = [];
$expenses = [];
$project_budget_summary = getProjectBudgetSummary($conn, $selected_project_id);
$project_stage_budget_rows = [];

if ($selected_project) {
    $stage_stmt = $conn->prepare("
        SELECT
            ps.id,
            ps.stage_name,
            ps.allocated_budget,
            ps.status,
            COALESCE(SUM(pe.amount), 0) AS spent_total
        FROM project_stages ps
        LEFT JOIN project_expenses pe ON pe.stage_id = ps.id
        WHERE ps.project_id = ?
        GROUP BY ps.id, ps.stage_name, ps.allocated_budget, ps.status
        ORDER BY ps.planned_start ASC, ps.id ASC
    ");
    $stage_stmt->bind_param("i", $selected_project_id);
    $stage_stmt->execute();
    $stage_result = $stage_stmt->get_result();
    while ($row = $stage_result->fetch_assoc()) {
        $row['remaining_budget'] = (float) $row['allocated_budget'] - (float) $row['spent_total'];
        $stage_options[] = $row;
        $project_stage_budget_rows[] = $row;
    }

    $expense_stmt = $conn->prepare("
        SELECT
            pe.expense_title,
            pe.category,
            pe.vendor_name,
            pe.amount,
            pe.expense_date,
            pe.notes,
            pe.created_at,
            ps.stage_name,
            u.username AS recorded_by_name
        FROM project_expenses pe
        JOIN project_stages ps ON ps.id = pe.stage_id
        LEFT JOIN users u ON u.id = pe.recorded_by
        WHERE pe.project_id = ?
        ORDER BY pe.expense_date DESC, pe.id DESC
    ");
    $expense_stmt->bind_param("i", $selected_project_id);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result();
    while ($row = $expense_result->fetch_assoc()) {
        $expenses[] = $row;
    }
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$project_total_budget = $selected_project
    ? (float) $project_budget_summary['total_budget']
    : 0;
$remaining_budget = $selected_project ? (float) $project_budget_summary['remaining_budget'] : 0;
$remaining_allocatable_budget = $selected_project ? (float) $project_budget_summary['remaining_allocatable_budget'] : 0;
$can_record_expense = $selected_project
    && in_array($selected_project['status'], ['approved', 'in_progress'], true)
    && $remaining_budget > 0.01
    && count(array_filter($stage_options, function ($stage) {
        return (float) $stage['remaining_budget'] > 0.01;
    })) > 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Expenses</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>
    <div class="col-9">
        <div class="form-card">
            <h3>Project Expenditure Ledger</h3>

            <?php if ($success_message): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php elseif ($message !== ''): ?>
                <div class="msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="GET" style="margin-bottom:20px;">
                <label>Select Project</label>
                <select name="project_id" onchange="this.form.submit()" required>
                    <option value="">-- Select Approved or Active Project --</option>
                    <?php while ($project = $projects->fetch_assoc()): ?>
                        <option value="<?= $project['id'] ?>" <?= $project['id'] === $selected_project_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars(formatProjectCode($project['id']) . ' - ' . $project['title'] . ' (' . formatStatusLabel($project['status']) . ')') ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>

            <?php if (!$selected_project): ?>
                <p>Select a project to record or review expenditure entries.</p>
            <?php else: ?>
                <div class="form-card" style="margin:0 0 20px 0;">
                    <h4><?= formatProjectCode($selected_project['id']) ?> - <?= htmlspecialchars($selected_project['title']) ?></h4>
                    <p><strong>Status:</strong> <?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?></p>
                    <p><strong>Total Budget:</strong> MWK <?= number_format($project_total_budget, 2) ?></p>
                    <p><strong>Allocated to Status Items:</strong> MWK <?= number_format((float) $project_budget_summary['allocated_total'], 2) ?></p>
                    <p><strong>Remaining to Allocate:</strong> MWK <?= number_format($remaining_allocatable_budget, 2) ?></p>
                    <p><strong>Total Recorded Expenses:</strong> MWK <?= number_format((float) $project_budget_summary['spent_total'], 2) ?></p>
                    <?php if ($remaining_budget < 0): ?>
                        <p style="color:#d32f2f;"><strong>Budget Overrun:</strong> MWK <?= number_format(abs($remaining_budget), 2) ?></p>
                    <?php else: ?>
                        <p style="color:#2e7d32;"><strong>Remaining Budget:</strong> MWK <?= number_format($remaining_budget, 2) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-card" style="margin-top:0;">
                    <h4>Record New Expense</h4>
                    <?php if (count($stage_options) === 0): ?>
                        <p>Add project status items before recording expenses.</p>
                    <?php elseif (!in_array($selected_project['status'], ['approved', 'in_progress'], true)): ?>
                        <p>This project is <?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?>, so expenditure entries are locked. You can still review the ledger below.</p>
                    <?php elseif ($remaining_budget <= 0.01): ?>
                        <p>This project has no remaining budget available for new expenses.</p>
                    <?php elseif (!$can_record_expense): ?>
                        <p>Every current status item has exhausted its allocated budget. Add a new status item allocation before recording more spending.</p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="project_id" value="<?= $selected_project['id'] ?>">

                            Status Item
                            <select name="stage_id" required>
                                <option value="">-- Select Status Item --</option>
                                <?php foreach ($stage_options as $stage): ?>
                                    <option value="<?= $stage['id'] ?>" <?= $stage['id'] === $selected_stage_id ? 'selected' : '' ?> <?= (float) $stage['remaining_budget'] <= 0.01 ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars(
                                            $stage['stage_name'] .
                                            ' | Allocated MWK ' . number_format((float) $stage['allocated_budget'], 2) .
                                            ' | Spent MWK ' . number_format((float) $stage['spent_total'], 2) .
                                            ' | Remaining MWK ' . number_format(max((float) $stage['remaining_budget'], 0), 2)
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            Expense Title
                            <input type="text" name="expense_title" required>

                            Category
                            <select name="category" required>
                                <option value="">-- Select Category --</option>
                                <option value="Materials">Materials</option>
                                <option value="Labour">Labour</option>
                                <option value="Transport">Transport</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Administration">Administration</option>
                                <option value="Other">Other</option>
                            </select>

                            Vendor / Payee
                            <input type="text" name="vendor_name">

                            Amount (MWK)
                            <input type="number" name="amount" step="0.01" min="0.01" required>

                            Expense Date
                            <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>

                            Notes
                            <textarea name="notes" style="width:100%;min-height:100px;padding:12px;border:1px solid #90caf9;border-radius:8px;"></textarea>

                            <input type="submit" name="record_expense" value="Record Expense">
                        </form>
                    <?php endif; ?>
                </div>

                <div class="form-card">
                    <h4>Status Item Budget Control</h4>
                    <table class="dashboard-table">
                        <tr>
                            <th>Status Item</th>
                            <th>Status</th>
                            <th>Allocated</th>
                            <th>Spent</th>
                            <th>Remaining</th>
                            <th>Control State</th>
                        </tr>
                        <?php foreach ($project_stage_budget_rows as $stage_row): ?>
                            <?php $stage_remaining = (float) $stage_row['remaining_budget']; ?>
                            <tr>
                                <td><?= htmlspecialchars($stage_row['stage_name']) ?></td>
                                <td><?= htmlspecialchars(formatStatusLabel($stage_row['status'])) ?></td>
                                <td>MWK <?= number_format((float) $stage_row['allocated_budget'], 2) ?></td>
                                <td>MWK <?= number_format((float) $stage_row['spent_total'], 2) ?></td>
                                <td style="color:<?= $stage_remaining < 0 ? '#d32f2f' : '#2e7d32' ?>;">
                                    MWK <?= number_format($stage_remaining, 2) ?>
                                </td>
                                <td><?= $stage_remaining < 0 ? 'Over Budget' : ($stage_remaining <= 0.01 ? 'Budget Exhausted' : 'Within Budget') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="form-card">
                    <h4>Recorded Expenses</h4>
                    <?php if (count($expenses) === 0): ?>
                        <p>No expenses recorded for this project yet.</p>
                    <?php else: ?>
                        <table class="dashboard-table">
                            <tr>
                                <th>Date</th>
                                <th>Status Item</th>
                                <th>Expense</th>
                                <th>Vendor</th>
                                <th>Amount</th>
                                <th>Recorded By</th>
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
                                    <td><?= htmlspecialchars($expense['recorded_by_name'] ?: 'System') ?></td>
                                    <td>
                                        <?= nl2br(htmlspecialchars($expense['notes'] ?: 'No notes')) ?><br>
                                        <small><?= date("d M Y, H:i", strtotime($expense['created_at'])) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
