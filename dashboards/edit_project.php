<?php
session_start();
require "../config/db.php";

/* SECURITY */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? 0;

/* FETCH ONLY OWN PROJECT */
$stmt = $conn->prepare("
    SELECT * FROM projects 
    WHERE id = ? AND created_by = ?
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my_projects.php");
    exit();
}

$project = $result->fetch_assoc();

/* BLOCK EDIT IF APPROVED */
if ($project['status'] === 'approved') {
    header("Location: my_projects.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Project</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<div class="row">
    <header class="col-12">
        <h2>Edit Project</h2>
    </header>
</div>

<div class="row">
    <div class="col-12">
        <div class="form-card">

            <form action="update_project.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $project['id']; ?>">

                Title
                <input type="text" name="title" value="<?php echo $project['title']; ?>" required>

                Description
                <input type="text" name="description" value="<?php echo $project['description']; ?>" required>

                District
                <input type="text" name="district" value="<?php echo $project['district']; ?>" required>

                Project Location
                <input type="text" name="location" value="<?php echo $project['location']; ?>" required>

                Estimated Project Budget (MWK)
                <input type="number" step="0.01" name="estimated_budget"
                       value="<?php echo $project['estimated_budget']; ?>" required>

                Contractor Payment (MWK)
                <input type="number" step="0.01" name="contractor_fee"
                       value="<?php echo $project['contractor_fee']; ?>" required>

                <input type="submit" name="update_project" value="Update Project">
            </form>

            <div style="text-align:center;">
                <a href="my_projects.php" class="back-btn">← Back to My Projects</a>
            </div>

        </div>
    </div>
</div>
<?php include "footer.php"; ?>
</body>
</html>
