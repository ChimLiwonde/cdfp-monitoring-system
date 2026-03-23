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
    } elseif (!in_array($project['status'], ['approved', 'in_progress', 'completed'], true)) {
        $message = "Expenses can only be recorded for approved, active, or completed projects.";
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
                $stage_spent = syncStageSpentBudget($conn, $stage_id);

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

                $warning = $stage_spent > (float) $stage['allocated_budget']
                    ? " Warning: that status item is now over budget."
                    : '';

                $_SESSION['success_message'] = "Expense recorded for " . formatProjectCode($project_id) . "." . $warning;
                header("Location: project_expenses.php?project_id={$project_id}&stage_id={$stage_id}");
                exit();
            }

            $message = "Failed to record the expense.";
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
$totals = [
    'allocated' => 0,
    'spent' => 0,
];

if ($selected_project) {
    $stage_stmt = $conn->prepare("
        SELECT id, stage_name, allocated_budget, spent_budget, status
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

    $totals_stmt = $conn->prepare("
        SELECT
            (SELECT COALESCE(SUM(allocated_budget), 0) FROM project_stages WHERE project_id = ?) AS allocated,
            (SELECT COALESCE(SUM(amount), 0) FROM project_expenses WHERE project_id = ?) AS spent
    ");
    $totals_stmt->bind_param("ii", $selected_project_id, $selected_project_id);
    $totals_stmt->execute();
    $totals = $totals_stmt->get_result()->fetch_assoc();

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
    ? (float) $selected_project['estimated_budget'] + (float) $selected_project['contractor_fee']
    : 0;
$remaining_budget = $project_total_budget - (float) $totals['spent'];
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
                    <p><strong>Allocated to Status Items:</strong> MWK <?= number_format((float) $totals['allocated'], 2) ?></p>
                    <p><strong>Total Recorded Expenses:</strong> MWK <?= number_format((float) $totals['spent'], 2) ?></p>
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
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="project_id" value="<?= $selected_project['id'] ?>">

                            Status Item
                            <select name="stage_id" required>
                                <option value="">-- Select Status Item --</option>
                                <?php foreach ($stage_options as $stage): ?>
                                    <option value="<?= $stage['id'] ?>" <?= $stage['id'] === $selected_stage_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($stage['stage_name'] . ' | Allocated MWK ' . number_format((float) $stage['allocated_budget'], 2)) ?>
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
