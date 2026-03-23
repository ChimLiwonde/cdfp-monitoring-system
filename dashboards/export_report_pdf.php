<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';
require "../assets/fpdf/fpdf.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Project Risk Report',0,1,'C');

$pdf->SetFont('Arial','',11);

$q = $conn->query("
SELECT p.id, p.title, ps.stage_name, ps.notes
FROM project_stages ps
JOIN projects p ON p.id = ps.project_id
");

while ($r = $q->fetch_assoc()) {
    $pdf->Ln(3);
    $projectCode = formatProjectCode($r['id']);
    $pdf->MultiCell(0,8,
        "Project ID: {$projectCode}\n".
        "Project: {$r['title']}\n".
        "Stage: {$r['stage_name']}\n".
        "Notes: {$r['notes']}"
    );
}

$pdf->Output();
