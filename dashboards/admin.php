<?php
session_start();
require "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ================= DASHBOARD STATS ================= */
$total_projects = $conn->query("SELECT COUNT(*) total FROM projects")->fetch_assoc()['total'];
$pending_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='pending'")->fetch_assoc()['total'];
$approved_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='approved'")->fetch_assoc()['total'];
$denied_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='denied'")->fetch_assoc()['total'];

/* ================= COMMUNITY REQUESTS ================= */
$pending_requests = $conn->query("
    SELECT COUNT(*) total 
    FROM community_requests 
    WHERE status = 'pending'
")->fetch_assoc()['total'];

/* ================= PROJECTS LAST 7 DAYS ================= */
$projects_per_day = [];
$projects_titles_per_day = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $q = $conn->query("SELECT title FROM projects WHERE DATE(created_at)='$date'");
    $titles = [];
    while ($r = $q->fetch_assoc()) {
        $titles[] = $r['title'];
    }
    $projects_per_day[$date] = count($titles);
    $projects_titles_per_day[$date] = $titles;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .summary-cards {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .summary-card {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            color: #fff;
            text-align: center;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        .card-total { background:#0c90b4; }
        .card-pending { background:#fbc02d; color:#000; }
        .card-approved { background:#388e3c; }
        .card-denied { background:#d32f2f; }
        .card-community { background:#ffff; color:#000; }
        canvas {
            background:#fff;
            border-radius:8px;
            padding:10px;
            margin-top:20px;
        }
    </style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <!-- SIDEBAR -->
    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <!-- MAIN CONTENT -->
    <div class="col-9">

        <!-- SUMMARY CARDS -->
        <div class="summary-cards">

            <div class="summary-card card-total">
                <h3>Total Projects</h3>
                <h2><?= $total_projects ?></h2>
            </div>

            <div class="summary-card card-pending">
                <h3>Pending Projects</h3>
                <h2><?= $pending_projects ?></h2>
            </div>

            <div class="summary-card card-approved">
                <h3>Approved Projects</h3>
                <h2><?= $approved_projects ?></h2>
            </div>

            <div class="summary-card card-denied">
                <h3>Denied Projects</h3>
                <h2><?= $denied_projects ?></h2>
            </div>

            <!-- ✅ NEW CARD (ADDED ONLY) -->
            <div class="summary-card card-community">
                <h3>Pending Community Requests</h3>
                <h2><?= $pending_requests ?></h2>
            </div>

        </div>

        <!-- LINE CHART -->
        <h3>Projects Created in Last 7 Days</h3>
        <canvas id="projectsLineChart"></canvas>

    </div>
</div>

<?php include "footer.php"; ?>

<script>
const ctx = document.getElementById('projectsLineChart').getContext('2d');
const projectsPerDay = <?= json_encode(array_values($projects_per_day)) ?>;
const projectDates = <?= json_encode(array_keys($projects_per_day)) ?>;
const projectsTitles = <?= json_encode(array_values($projects_titles_per_day)) ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: projectDates,
        datasets: [{
            label: 'Projects per Day',
            data: projectsPerDay,
            borderColor: '#0c90b4',
            backgroundColor: 'rgba(12,144,180,0.2)',
            fill: true,
            tension: 0.3,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const titles = projectsTitles[context.dataIndex];
                        return titles.length
                            ? titles.map(t => `• ${t}`).join('\n')
                            : 'No projects';
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>
