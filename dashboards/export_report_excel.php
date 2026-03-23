<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$report_type = normalizeReportType($_GET['type'] ?? 'progress');

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=" . $report_type . "_report.xls");

if ($report_type === 'financial') {
    echo "Project ID\tProject\tProject Lead\tStatus\tTotal Budget\tAllocated\tSpent\tRemaining\tExpense Entries\tOver Budget Status Items\n";

    $query = $conn->query("
        SELECT
            p.id,
            p.title,
            u.username AS field_officer,
            p.status AS project_status,
            (p.estimated_budget + p.contractor_fee) AS total_budget,
            COALESCE(stage_totals.allocated_total, 0) AS allocated_total,
            COALESCE(expense_totals.spent_total, 0) AS spent_total,
            COALESCE(expense_totals.expense_count, 0) AS expense_count,
            COALESCE(stage_alerts.over_budget_items, 0) AS over_budget_items
        FROM projects p
        JOIN users u ON u.id = p.created_by
        LEFT JOIN (
            SELECT project_id, SUM(allocated_budget) AS allocated_total
            FROM project_stages
            GROUP BY project_id
        ) stage_totals ON stage_totals.project_id = p.id
        LEFT JOIN (
            SELECT project_id, SUM(amount) AS spent_total, COUNT(*) AS expense_count
            FROM project_expenses
            GROUP BY project_id
        ) expense_totals ON expense_totals.project_id = p.id
        LEFT JOIN (
            SELECT project_id, SUM(CASE WHEN spent_budget > allocated_budget THEN 1 ELSE 0 END) AS over_budget_items
            FROM project_stages
            GROUP BY project_id
        ) stage_alerts ON stage_alerts.project_id = p.id
        ORDER BY p.created_at DESC
    ");

    while ($row = $query->fetch_assoc()) {
        $remaining_budget = (float) $row['total_budget'] - (float) $row['spent_total'];
        echo formatProjectCode($row['id']) . "\t" .
            $row['title'] . "\t" .
            $row['field_officer'] . "\t" .
            formatStatusLabel($row['project_status']) . "\t" .
            $row['total_budget'] . "\t" .
            $row['allocated_total'] . "\t" .
            $row['spent_total'] . "\t" .
            $remaining_budget . "\t" .
            $row['expense_count'] . "\t" .
            $row['over_budget_items'] . "\n";
    }
} else {
    echo "Project ID\tProject\tProject Lead\tStatus\tTotal Status Items\tCompleted Items\tActive Items\tProgress Percent\tOverdue Items\n";

    $query = $conn->query("
        SELECT
            p.id,
            p.title,
            u.username AS field_officer,
            p.status AS project_status,
            COUNT(ps.id) AS total_items,
            SUM(CASE WHEN ps.status = 'completed' THEN 1 ELSE 0 END) AS completed_items,
            SUM(CASE WHEN ps.status = 'in_progress' THEN 1 ELSE 0 END) AS active_items,
            SUM(CASE WHEN ps.actual_end IS NOT NULL AND ps.actual_end > ps.planned_end THEN 1 ELSE 0 END) AS overdue_items
        FROM projects p
        JOIN users u ON u.id = p.created_by
        LEFT JOIN project_stages ps ON ps.project_id = p.id
        GROUP BY p.id, p.title, u.username, p.status
        ORDER BY p.created_at DESC
    ");

    while ($row = $query->fetch_assoc()) {
        $progress_percent = calculateProgressPercent(
            $row['total_items'],
            $row['completed_items'],
            $row['active_items']
        );

        echo formatProjectCode($row['id']) . "\t" .
            $row['title'] . "\t" .
            $row['field_officer'] . "\t" .
            formatStatusLabel($row['project_status']) . "\t" .
            $row['total_items'] . "\t" .
            $row['completed_items'] . "\t" .
            $row['active_items'] . "\t" .
            $progress_percent . "%\t" .
            $row['overdue_items'] . "\n";
    }
}

exit();
