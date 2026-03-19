<?php
session_start();
require "../config/db.php";

/* SECURITY */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../login.php");
    exit();
}

if (isset($_POST['update_project'])) {

    $user_id     = $_SESSION['user_id'];
    $id          = $_POST['id'];
    $title       = $_POST['title'];
    $desc        = $_POST['description'];
    $district    = $_POST['district'];
    $location    = $_POST['location'];
    $budget      = $_POST['estimated_budget'];
    $contractFee = $_POST['contractor_fee'];

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
