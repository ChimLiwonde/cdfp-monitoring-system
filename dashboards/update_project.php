<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

/* SECURITY */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    if (!isValidCsrfToken('edit_project_form', $_POST['_csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Your session expired. Please edit the project again.';
        header("Location: my_projects.php");
        exit();
    }

    $user_id     = $_SESSION['user_id'];
    $id          = (int) ($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $district    = trim($_POST['district'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $budget      = (float) ($_POST['estimated_budget'] ?? 0);
    $contractFee = (float) ($_POST['contractor_fee'] ?? 0);

    /* UPDATE ONLY OWN + PENDING PROJECT */
    $stmt = $conn->prepare("
        UPDATE projects 
        SET title=?, description=?, district=?, location=?, 
            estimated_budget=?, contractor_fee=?
        WHERE id=? AND created_by=? AND status='pending'
    ");

    $stmt->bind_param(
        "ssssddii",
        $title,
        $desc,
        $district,
        $location,
        $budget,
        $contractFee,
        $id,
        $user_id
    );

    $stmt->execute();
}

header("Location: field_officer.php");
exit();
