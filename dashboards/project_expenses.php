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
    if (!isValidCsrfToken('record_expense_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try recording the expense again.";
    } else {
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

$project_total_budget = $selected_project ? (float) $project_budget_summary['total_budget'] : 0;
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
                    <span class="eyebrow">Expenditure Ledger</span>
                    <h3>Track project spending without mixing it into project status updates.</h3>
                    <p>This ledger keeps budget totals, status-item allocations, and every recorded expense clearly separated and easy to review.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong>Separate</strong>&nbsp; Finance Flow</div>
                    <div class="hero-pill"><strong>Budget</strong>&nbsp; Controls</div>
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
                <div class="form-grid">
                    <div class="full-span">
                        <select id="project_id" name="project_id" onchange="this.form.submit()" required>
                            <option value="">-- Select Approved or Active Project --</option>
                            <?php while ($project = $projects->fetch_assoc()): ?>
                                <option value="<?= $project['id'] ?>" <?= $project['id'] === $selected_project_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(formatProjectCode($project['id']) . ' - ' . $project['title'] . ' (' . formatStatusLabel($project['status']) . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!$selected_project): ?>
            <div class="data-card">
                <div class="empty-state">Select a project to record or review expenditure entries.</div>
            </div>
        <?php else: ?>
            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Project Budget Summary</span>
                        <h3><?= formatProjectCode($selected_project['id']) ?> - <?= htmlspecialchars($selected_project['title']) ?></h3>
                    </div>
                    <p>Use this summary to understand the full project budget before adding more expenditure entries.</p>
                </div>

                <div class="detail-grid">
                    <div class="detail-card">
                        <strong>Status</strong>
                        <span class="status-badge <?= htmlspecialchars($selected_project['status']) ?>"><?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Total Budget</strong>
                        <span>MWK <?= number_format($project_total_budget, 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Allocated to Status Items</strong>
                        <span>MWK <?= number_format((float) $project_budget_summary['allocated_total'], 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Remaining to Allocate</strong>
                        <span>MWK <?= number_format($remaining_allocatable_budget, 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Total Recorded Expenses</strong>
                        <span>MWK <?= number_format((float) $project_budget_summary['spent_total'], 2) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Remaining Budget</strong>
                        <span style="color:<?= $remaining_budget < 0 ? '#b74b3d' : '#1d7a4c' ?>;">
                            MWK <?= number_format($remaining_budget, 2) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">New Expense</span>
                        <h3>Record New Expense</h3>
                    </div>
                    <p>Only approved or active projects with available budget can accept new expenditure entries.</p>
                </div>

                <?php if (count($stage_options) === 0): ?>
                    <div class="empty-state">Add project status items before recording expenses.</div>
                <?php elseif (!in_array($selected_project['status'], ['approved', 'in_progress'], true)): ?>
                    <div class="empty-state">This project is <?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?>, so expenditure entries are locked. You can still review the ledger below.</div>
                <?php elseif ($remaining_budget <= 0.01): ?>
                    <div class="empty-state">This project has no remaining budget available for new expenses.</div>
                <?php elseif (!$can_record_expense): ?>
                    <div class="empty-state">Every current status item has exhausted its allocated budget. Add a new status-item allocation before recording more spending.</div>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfInput('record_expense_form') ?>
                        <input type="hidden" name="project_id" value="<?= $selected_project['id'] ?>">

                        <div class="form-grid">
                            <div class="full-span">
                                <label for="stage_id">Status Item</label>
                                <select id="stage_id" name="stage_id" required>
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
                            </div>

                            <div>
                                <label for="expense_title">Expense Title</label>
                                <input id="expense_title" type="text" name="expense_title" required>
                            </div>

                            <div>
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Materials">Materials</option>
                                    <option value="Labour">Labour</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Administration">Administration</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label for="vendor_name">Vendor / Payee</label>
                                <input id="vendor_name" type="text" name="vendor_name">
                            </div>

                            <div>
                                <label for="amount">Amount (MWK)</label>
                                <input id="amount" type="number" name="amount" step="0.01" min="0.01" required>
                            </div>

                            <div>
                                <label for="expense_date">Expense Date</label>
                                <input id="expense_date" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="full-span">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes"></textarea>
                            </div>
                        </div>

                        <input type="submit" name="record_expense" value="Record Expense">
                    </form>
                <?php endif; ?>
            </div>

            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Budget Control</span>
                        <h3>Status Item Budget Control</h3>
                    </div>
                    <p>Monitor whether each status item is within budget, exhausted, or already over budget.</p>
                </div>
                <div class="table-wrap">
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
                                <td style="color:<?= $stage_remaining < 0 ? '#b74b3d' : '#1d7a4c' ?>;">MWK <?= number_format($stage_remaining, 2) ?></td>
                                <td><?= $stage_remaining < 0 ? 'Over Budget' : ($stage_remaining <= 0.01 ? 'Budget Exhausted' : 'Within Budget') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Expense History</span>
                        <h3>Recorded Expenses</h3>
                    </div>
                    <p>Review every expense entry tied to this project, including the related status item and who recorded it.</p>
                </div>
                <?php if (count($expenses) === 0): ?>
                    <div class="empty-state">No expenses recorded for this project yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
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
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
