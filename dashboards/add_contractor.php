<?php
session_start();
require "../config/db.php";

/* ===========================
   SECURITY CHECK
=========================== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

/* ===========================
   FETCH AVAILABLE PROJECTS
   - Pending or Approved
   - Not completed
   - Not already assigned to an ACTIVE contractor
=========================== */
$projects = $conn->query("
    SELECT p.id, p.title
    FROM projects p
    WHERE p.created_by = $user_id
      AND (p.status = 'pending' OR p.status = 'approved')
      AND p.id NOT IN (
          SELECT cp.project_id
          FROM contractor_projects cp
          JOIN project_stages ps ON ps.project_id = cp.project_id
          GROUP BY cp.project_id
          HAVING SUM(ps.status = 'completed') < COUNT(*)
      )
");

/* ===========================
   FETCH AVAILABLE CONTRACTORS
   (ONLY CREATED BY THIS OFFICER)
=========================== */
$contractors = $conn->query("
    SELECT DISTINCT c.id, c.name
    FROM contractors c
    LEFT JOIN contractor_projects cp ON c.id = cp.contractor_id
    LEFT JOIN projects p ON cp.project_id = p.id
    LEFT JOIN project_stages ps ON p.id = ps.project_id
    WHERE c.created_by = $user_id
    GROUP BY c.id
    HAVING
        COUNT(cp.id) = 0
        OR MAX(
            CASE
                WHEN p.status = 'denied' THEN 1
                WHEN ps.status = 'completed' THEN 1
                ELSE 0
            END
        ) = 1
");

/* ===========================
   ASSIGN EXISTING CONTRACTOR
=========================== */
if (isset($_POST['assign_contractor'])) {

    $project_id    = intval($_POST['project_id']);
    $contractor_id = intval($_POST['contractor_id']);

    $stmt = $conn->prepare("
        INSERT INTO contractor_projects
        (contractor_id, project_id, assigned_by)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iii", $contractor_id, $project_id, $user_id);

    if ($stmt->execute()) {
        $msg = "Contractor assigned successfully.";
    } else {
        $msg = "Failed to assign contractor.";
    }
}

/* ===========================
   ADD NEW CONTRACTOR
=========================== */
if (isset($_POST['add_new_contractor'])) {

    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $company = trim($_POST['company']);
    $address = trim($_POST['address']);

    $stmt = $conn->prepare("
        INSERT INTO contractors
        (name, phone, company, address, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssi", $name, $phone, $company, $address, $user_id);

    if ($stmt->execute()) {
        $msg = "New contractor added. You can now assign them.";
    } else {
        $msg = "Error adding contractor.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add / Assign Contractor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

<div class="col-3">
    <?php include "menu.php"; ?>
</div>

<div class="col-9">
<div class="form-card">

<h3>Assign Contractor to Project</h3>

<?php if ($msg != "") echo "<div class='msg'>$msg</div>"; ?>

<form method="POST">

    Contractor
    <select name="contractor_id" required>
        <option value="">-- Select Contractor --</option>
        <?php
        if ($contractors->num_rows == 0) {
            echo "<option disabled>No available contractors</option>";
        } else {
            while ($c = $contractors->fetch_assoc()) {
                echo "<option value='{$c['id']}'>" .
                     htmlspecialchars($c['name']) .
                     "</option>";
            }
        }
        ?>
    </select>

    Project
    <select name="project_id" required>
        <option value="">-- Select Project --</option>
        <?php
        if ($projects->num_rows == 0) {
            echo "<option disabled>No available projects</option>";
        } else {
            while ($p = $projects->fetch_assoc()) {
                echo "<option value='{$p['id']}'>" .
                     htmlspecialchars($p['title']) .
                     "</option>";
            }
        }
        ?>
    </select>

    <input type="submit" name="assign_contractor" value="Assign Contractor">
</form>

<hr>

<h3>Add New Contractor</h3>

<form method="POST">

    Name
    <input type="text" name="name" required>

    Phone
    <input type="text" name="phone" required>

    Company
    <input type="text" name="company">

    Address
    <input type="text" name="address">

    <input type="submit" name="add_new_contractor" value="Add Contractor">
</form>

</div>
</div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
