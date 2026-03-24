<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (($_SESSION['role'] ?? null) !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

/* ================= DASHBOARD STATS ================= */
$total_projects = $conn->query("SELECT COUNT(*) total FROM projects")->fetch_assoc()['total'];
$pending_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='pending'")->fetch_assoc()['total'];
$approved_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='approved'")->fetch_assoc()['total'];
$in_progress_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='in_progress'")->fetch_assoc()['total'];
$completed_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='completed'")->fetch_assoc()['total'];
$denied_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='denied'")->fetch_assoc()['total'];

/* ================= COMMUNITY REQUESTS ================= */
$pending_requests = $conn->query("
    SELECT COUNT(*) total 
    FROM community_requests 
    WHERE status = 'pending'
")->fetch_assoc()['total'];

$over_budget_projects = $conn->query("
    SELECT COUNT(*) AS total
    FROM (
        SELECT p.id
        FROM projects p
        LEFT JOIN project_expenses pe ON pe.project_id = p.id
        GROUP BY p.id, p.estimated_budget, p.contractor_fee
        HAVING COALESCE(SUM(pe.amount), 0) > (p.estimated_budget + p.contractor_fee)
    ) alert_projects
")->fetch_assoc()['total'];

$over_budget_stages = $conn->query("
    SELECT COUNT(*) AS total
    FROM project_stages
    WHERE spent_budget > allocated_budget
")->fetch_assoc()['total'];

$collaboration_messages = $conn->query("
    SELECT COUNT(*) AS total
    FROM project_collaboration_messages
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

$summary_cards = [
    [
        'label' => 'Total Projects',
        'value' => $total_projects,
        'class' => 'card-total',
        'href'  => 'adminprojects.php?status=all'
    ],
    [
        'label' => 'Pending Projects',
        'value' => $pending_projects,
        'class' => 'card-pending',
        'href'  => 'adminprojects.php?status=pending'
    ],
    [
        'label' => 'Approved Projects',
        'value' => $approved_projects,
        'class' => 'card-approved',
        'href'  => 'adminprojects.php?status=approved'
    ],
    [
        'label' => 'Projects In Progress',
        'value' => $in_progress_projects,
        'class' => 'card-info',
        'href'  => 'adminprojects.php?status=in_progress'
    ],
    [
        'label' => 'Completed Projects',
        'value' => $completed_projects,
        'class' => 'card-completed',
        'href'  => 'adminprojects.php?status=completed'
    ],
    [
        'label' => 'Denied Projects',
        'value' => $denied_projects,
        'class' => 'card-denied',
        'href'  => 'adminprojects.php?status=denied'
    ],
    [
        'label' => 'Pending Community Requests',
        'value' => $pending_requests,
        'class' => 'card-community',
        'href'  => 'admin_community_requests.php?status=pending'
    ]
];

$alert_cards = [
    [
        'label' => 'Over Budget Projects',
        'value' => $over_budget_projects,
        'class' => 'card-denied',
        'href'  => 'admin_project_report.php?type=financial'
    ],
    [
        'label' => 'Over Budget Status Items',
        'value' => $over_budget_stages,
        'class' => 'card-pending',
        'href'  => 'admin_project_report.php?type=financial'
    ],
    [
        'label' => 'Internal Collaboration Messages',
        'value' => $collaboration_messages,
        'class' => 'card-total',
        'href'  => 'project_collaboration.php'
    ]
];
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
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .summary-card-link {
            flex: 1;
            min-width: 180px;
            text-decoration: none;
            color: inherit;
        }
        .summary-card-link:hover .summary-card {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.14);
        }
        .card-total { background:#0c90b4; }
        .card-pending { background:#fbc02d; color:#000; }
        .card-approved { background:#388e3c; }
        .card-info { background:#0288d1; }
        .card-completed { background:#1565c0; }
        .card-denied { background:#d32f2f; }
        .card-community { background:#ffff; color:#000; }
        .alert-grid {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
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

    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9">

        <div class="summary-cards">
            <?php foreach ($summary_cards as $card): ?>
                <a class="summary-card-link" href="<?= $card['href'] ?>">
                    <div class="summary-card <?= $card['class'] ?>">
                        <h3><?= $card['label'] ?></h3>
                        <h2><?= $card['value'] ?></h2>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <h3>Budget Alerts and Collaboration</h3>
        <div class="alert-grid">
            <?php foreach ($alert_cards as $card): ?>
                <a class="summary-card-link" href="<?= $card['href'] ?>">
                    <div class="summary-card <?= $card['class'] ?>">
                        <h3><?= $card['label'] ?></h3>
                        <h2><?= $card['value'] ?></h2>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

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
                            ? titles.map(t => '- ' + t).join('\n')
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
