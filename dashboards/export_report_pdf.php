<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';
require "../assets/fpdf/fpdf.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$report_type = normalizeReportType($_GET['type'] ?? 'progress');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, formatReportTypeLabel($report_type), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);

if ($report_type === 'financial') {
    $query = $conn->query("
        SELECT
            p.id,
            p.title,
            u.username AS field_officer,
            p.status AS project_status,
            (p.estimated_budget + p.contractor_fee) AS total_budget,
            COALESCE(stage_totals.allocated_total, 0) AS allocated_total,
            COALESCE(expense_totals.spent_total, 0) AS spent_total,
            COALESCE(expense_totals.expense_count, 0) AS expense_count
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
        ORDER BY p.created_at DESC
    ");

    while ($row = $query->fetch_assoc()) {
        $remaining_budget = (float) $row['total_budget'] - (float) $row['spent_total'];
        $alert_status = $remaining_budget < 0 ? 'Over Budget' : 'Within Budget';

        $pdf->Ln(2);
        $pdf->MultiCell(0, 7,
            "Project ID: " . formatProjectCode($row['id']) . "\n" .
            "Project: " . $row['title'] . "\n" .
            "Field Officer: " . $row['field_officer'] . "\n" .
            "Status: " . formatStatusLabel($row['project_status']) . "\n" .
            "Total Budget: MWK " . number_format((float) $row['total_budget'], 2) . "\n" .
            "Allocated to Status Items: MWK " . number_format((float) $row['allocated_total'], 2) . "\n" .
            "Recorded Expenses: MWK " . number_format((float) $row['spent_total'], 2) . "\n" .
            "Remaining Budget: MWK " . number_format($remaining_budget, 2) . "\n" .
            "Expense Entries: " . $row['expense_count'] . "\n" .
            "Alert: " . $alert_status
        );
    }
} else {
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

        $pdf->Ln(2);
        $pdf->MultiCell(0, 7,
            "Project ID: " . formatProjectCode($row['id']) . "\n" .
            "Project: " . $row['title'] . "\n" .
            "Field Officer: " . $row['field_officer'] . "\n" .
            "Status: " . formatStatusLabel($row['project_status']) . "\n" .
            "Total Status Items: " . $row['total_items'] . "\n" .
            "Completed Items: " . $row['completed_items'] . "\n" .
            "Active Items: " . $row['active_items'] . "\n" .
            "Progress: " . $progress_percent . "%\n" .
            "Overdue Items: " . $row['overdue_items']
        );
    }
}

$pdf->Output();
