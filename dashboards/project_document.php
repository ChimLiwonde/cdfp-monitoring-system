<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$projectId = (int) ($_GET['id'] ?? 0);
if ($projectId <= 0) {
    http_response_code(404);
    exit('Document not found.');
}

$stmt = $conn->prepare("
    SELECT id, title, document, created_by
    FROM projects
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project || empty($project['document'])) {
    http_response_code(404);
    exit('Document not found.');
}

$role = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAuthorized = $role === 'admin'
    || (isProjectLeadRole($role) && $userId === (int) $project['created_by']);

if (!$isAuthorized) {
    http_response_code(403);
    exit('Unauthorized');
}

$absolutePath = getProjectDocumentAbsolutePath($project['document']);
if ($absolutePath === null) {
    http_response_code(404);
    exit('Document not found.');
}

$extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
$downloadName = formatProjectCode($projectId) . '-document' . ($extension !== '' ? '.' . $extension : '');

header('Content-Type: ' . getProjectDocumentMimeType($absolutePath));
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . basename($downloadName) . '"');
header('X-Content-Type-Options: nosniff');

readfile($absolutePath);
exit();
