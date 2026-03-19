<?php
require "../config/db.php";
require "../assets/fpdf/fpdf.php";

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Project Risk Report',0,1,'C');

$pdf->SetFont('Arial','',11);

$q = $conn->query("
SELECT p.title, ps.stage_name, ps.notes
FROM project_stages ps
JOIN projects p ON p.id = ps.project_id
");

while ($r = $q->fetch_assoc()) {
    $pdf->Ln(3);
    $pdf->MultiCell(0,8,
        "Project: {$r['title']}\n".
        "Stage: {$r['stage_name']}\n".
        "Notes: {$r['notes']}"
    );
}

$pdf->Output();
