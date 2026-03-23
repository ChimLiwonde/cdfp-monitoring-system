<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* SECURITY: ONLY PROJECT LEAD ROLES */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

if (isset($_POST['save_project'])) {

    /* FORM DATA */
    $title       = $_POST['title'];
    $description = $_POST['description'];
    $district    = $_POST['district'];
    $location    = $_POST['location'];
    $budget      = $_POST['estimated_budget'];
    $contractFee = $_POST['contractor_fee'];
    $latitude    = $_POST['latitude'];
    $longitude   = $_POST['longitude'];
    $user_id     = $_SESSION['user_id'];

    /* FILE UPLOAD */
    $docName = "";
    if (!empty($_FILES['document']['name'])) {
        $docName = time() . "_" . basename($_FILES['document']['name']);
        move_uploaded_file(
            $_FILES['document']['tmp_name'],
            "../uploads/" . $docName
        );
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

    $stmt->execute();
    $project_id = $stmt->insert_id;

    /* INSERT MAP DATA */
    if (!empty($latitude) && !empty($longitude)) {
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

    $_SESSION['success_message'] = "Project " . formatProjectCode($project_id) . " created successfully.";

    header("Location: field_officer.php");
    exit();
}
