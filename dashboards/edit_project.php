<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

/* SECURITY */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
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
                <?= csrfInput('edit_project_form') ?>
                <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">

                Title
                <input type="text" name="title" value="<?= htmlspecialchars($project['title']) ?>" required>

                Description
                <input type="text" name="description" value="<?= htmlspecialchars($project['description']) ?>" required>

                District
                <input type="text" name="district" value="<?= htmlspecialchars($project['district']) ?>" required>

                Project Location
                <input type="text" name="location" value="<?= htmlspecialchars($project['location']) ?>" required>

                Estimated Project Budget (MWK)
                <input type="number" step="0.01" name="estimated_budget"
                       value="<?= htmlspecialchars((string) $project['estimated_budget']) ?>" required>

                Contractor Payment (MWK)
                <input type="number" step="0.01" name="contractor_fee"
                       value="<?= htmlspecialchars((string) $project['contractor_fee']) ?>" required>

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
