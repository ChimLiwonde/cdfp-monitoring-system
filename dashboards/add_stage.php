<?php
session_start();
require "../config/db.php";

// Ensure user is a field officer
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'field_officer'){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Fetch approved projects for dropdown along with total cost
$projects_stmt = $conn->prepare("
    SELECT id, title, estimated_budget, contractor_fee
    FROM projects
    WHERE created_by=? AND status='approved'
");
$projects_stmt->bind_param("i", $user_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();

// Handle form submission
if(isset($_POST['add_stage'])){
    $project_id = intval($_POST['project_id']);
    $stage_name = trim($_POST['stage_name']);
    $planned_start = $_POST['planned_start'];
    $planned_end = $_POST['planned_end'];

    // Automatically calculate allocated_budget as total project cost divided by existing stages + 1
    $project_total = $conn->query("SELECT estimated_budget + contractor_fee as total_cost FROM projects WHERE id=$project_id")->fetch_assoc()['total_cost'];

    $existing_stages = $conn->query("SELECT COUNT(*) as cnt FROM project_stages WHERE project_id=$project_id")->fetch_assoc()['cnt'];
    $allocated_budget = $existing_stages > 0 ? round($project_total / ($existing_stages + 1), 2) : $project_total;

    // Simple validation
    if(empty($project_id) || empty($stage_name) || empty($planned_start) || empty($planned_end)){
        $msg = "Please fill in all fields correctly.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO project_stages (project_id, stage_name, planned_start, planned_end, allocated_budget, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("isssd", $project_id, $stage_name, $planned_start, $planned_end, $allocated_budget);

        if($stmt->execute()){
            $msg = "Stage added successfully. Allocated Budget: MWK " . number_format($allocated_budget,2);
        } else {
            $msg = "Error adding stage. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Project Stage</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>
<?php include "header.php"; ?>
<div class="row">
    <div class="col-3"><?php include "menu.php"; ?></div>
    <div class="col-9">
        <div class="form-card">
            <h3>Add Stage to Project</h3>
            <?php if($msg!="") echo "<div class='msg'>$msg</div>"; ?>

            <form method="POST">
                <label>Select Project</label>
                <select name="project_id" required onchange="calculateBudget(this.value)">
                    <option value="">-- Select Project --</option>
                    <?php while($row = $projects->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" data-total="<?php echo $row['estimated_budget'] + $row['contractor_fee']; ?>">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Stage Name</label>
                <input type="text" name="stage_name" required>

                <label>Planned Start Date</label>
                <input type="date" name="planned_start" required>

                <label>Planned End Date</label>
                <input type="date" name="planned_end" required>

                <label>Allocated Budget (Auto-calculated)</label>
                <input type="number" name="allocated_budget" id="allocated_budget" step="0.01" readonly>

                <input type="submit" name="add_stage" value="Add Stage">
            </form>
        </div>
    </div>
</div>
<?php include "footer.php"; ?>

<script>
function calculateBudget(projectId){
    var select = document.querySelector('select[name="project_id"]');
    var option = select.options[select.selectedIndex];
    var total = parseFloat(option.getAttribute('data-total'));
    
    // Count existing stages via AJAX (optional)
    // For simplicity, we just fill allocated_budget with total cost here
    document.getElementById('allocated_budget').value = total.toFixed(2);
}
</script>
</body>
</html>
