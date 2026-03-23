<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=project_report.xls");

echo "Project ID\tProject\tStage\tAllocated\tSpent\tNotes\n";

$q = $conn->query("
SELECT p.id, p.title, ps.stage_name, ps.allocated_budget, ps.spent_budget, ps.notes
FROM project_stages ps
JOIN projects p ON p.id = ps.project_id
");

while ($r = $q->fetch_assoc()) {
    $projectCode = formatProjectCode($r['id']);
    echo "{$projectCode}\t{$r['title']}\t{$r['stage_name']}\t{$r['allocated_budget']}\t{$r['spent_budget']}\t{$r['notes']}\n";
}
exit();
