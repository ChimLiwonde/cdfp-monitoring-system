<?php
require "../config/db.php";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=project_report.xls");

echo "Project\tStage\tAllocated\tSpent\tNotes\n";

$q = $conn->query("
SELECT p.title, ps.stage_name, ps.allocated_budget, ps.spent_budget, ps.notes
FROM project_stages ps
JOIN projects p ON p.id = ps.project_id
");

while ($r = $q->fetch_assoc()) {
    echo "{$r['title']}\t{$r['stage_name']}\t{$r['allocated_budget']}\t{$r['spent_budget']}\t{$r['notes']}\n";
}
exit();
