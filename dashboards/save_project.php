<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

/* SECURITY: ONLY PROJECT LEAD ROLES */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['save_project'])) {
    header("Location: create_project.php");
    exit();
}

if (!isValidCsrfToken('create_project_form', $_POST['_csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Your session expired. Please submit the project form again.';
    header("Location: create_project.php");
    exit();
}

/* FORM DATA */
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$district    = trim($_POST['district'] ?? '');
$location    = trim($_POST['location'] ?? '');
$budget      = (float) ($_POST['estimated_budget'] ?? 0);
$contractFee = (float) ($_POST['contractor_fee'] ?? 0);
$rawLatitude = trim((string) ($_POST['latitude'] ?? ''));
$rawLongitude = trim((string) ($_POST['longitude'] ?? ''));
$latitude    = normalizeCoordinate($rawLatitude, -90, 90);
$longitude   = normalizeCoordinate($rawLongitude, -180, 180);
$user_id     = (int) $_SESSION['user_id'];

$hasCoordinateInput = ($rawLatitude !== '' || $rawLongitude !== '');
if ($title === '' || $description === '' || $district === '' || $location === '') {
    $_SESSION['error_message'] = 'Fill in all required project details before submitting.';
    header("Location: create_project.php");
    exit();
}

if ($budget < 0 || $contractFee < 0) {
    $_SESSION['error_message'] = 'Budget amounts must be zero or greater.';
    header("Location: create_project.php");
    exit();
}

if ($hasCoordinateInput && !hasValidCoordinatePair($rawLatitude, $rawLongitude)) {
    $_SESSION['error_message'] = 'Select a valid map location before submitting the project.';
    header("Location: create_project.php");
    exit();
}

$uploadError = null;
$docName = storeProjectDocumentUpload($_FILES['document'] ?? [], $uploadError);
if ($docName === false) {
    $_SESSION['error_message'] = $uploadError ?: 'The supporting document could not be uploaded.';
    header("Location: create_project.php");
    exit();
}

/* INSERT PROJECT */
$stmt = $conn->prepare("
    INSERT INTO projects 
    (title, description, district, location, estimated_budget, contractor_fee, document, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssddsi",
    $title,
    $description,
    $district,
    $location,
    $budget,
    $contractFee,
    $docName,
    $user_id
);

if (!$stmt->execute()) {
    $_SESSION['error_message'] = 'The project could not be saved. Please try again.';
    header("Location: create_project.php");
    exit();
}

$project_id = $stmt->insert_id;

/* INSERT MAP DATA */
if ($latitude !== null && $longitude !== null) {
    $map = $conn->prepare("
        INSERT INTO project_maps (project_id, latitude, longitude)
        VALUES (?, ?, ?)
    ");
    $map->bind_param("iss", $project_id, $latitude, $longitude);
    $map->execute();
}

logProjectActivity(
    $conn,
    $project_id,
    'project_created',
    $user_id,
    $_SESSION['role'] ?? 'field_officer',
    null,
    'pending',
    'Project created and submitted for review.'
);

createRoleNotifications(
    $conn,
    'admin',
    'project_submitted',
    'New Project Awaiting Review',
    formatProjectCode($project_id) . " - {$title} was submitted and is waiting for admin review.",
    'adminprojects.php?status=pending'
);

$_SESSION['success_message'] = "Project " . formatProjectCode($project_id) . " created successfully.";

header("Location: field_officer.php");
exit();
