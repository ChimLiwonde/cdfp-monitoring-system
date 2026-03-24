<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (($_SESSION['role'] ?? null) !== 'admin') {
    header("Location: ../Pages/login.php");
    exit();
}

$total_projects = $conn->query("SELECT COUNT(*) total FROM projects")->fetch_assoc()['total'];
$pending_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='pending'")->fetch_assoc()['total'];
$approved_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='approved'")->fetch_assoc()['total'];
$in_progress_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='in_progress'")->fetch_assoc()['total'];
$completed_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='completed'")->fetch_assoc()['total'];
$denied_projects = $conn->query("SELECT COUNT(*) total FROM projects WHERE status='denied'")->fetch_assoc()['total'];

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
        'href'  => 'adminprojects.php?status=all',
        'meta' => 'Open all project records'
    ],
    [
        'label' => 'Pending Projects',
        'value' => $pending_projects,
        'class' => 'card-pending',
        'href'  => 'adminprojects.php?status=pending',
        'meta' => 'Review new submissions'
    ],
    [
        'label' => 'Approved Projects',
        'value' => $approved_projects,
        'class' => 'card-approved',
        'href'  => 'adminprojects.php?status=approved',
        'meta' => 'Projects ready to deliver'
    ],
    [
        'label' => 'Projects In Progress',
        'value' => $in_progress_projects,
        'class' => 'card-info',
        'href'  => 'adminprojects.php?status=in_progress',
        'meta' => 'Active delivery work'
    ],
    [
        'label' => 'Completed Projects',
        'value' => $completed_projects,
        'class' => 'card-completed',
        'href'  => 'adminprojects.php?status=completed',
        'meta' => 'Closed and completed'
    ],
    [
        'label' => 'Denied Projects',
        'value' => $denied_projects,
        'class' => 'card-denied',
        'href'  => 'adminprojects.php?status=denied',
        'meta' => 'Projects needing rework'
    ],
    [
        'label' => 'Pending Community Requests',
        'value' => $pending_requests,
        'class' => 'card-community',
        'href'  => 'admin_community_requests.php?status=pending',
        'meta' => 'Citizen requests waiting for review'
    ]
];

$alert_cards = [
    [
        'label' => 'Over Budget Projects',
        'value' => $over_budget_projects,
        'class' => 'card-denied',
        'href'  => 'admin_project_report.php?type=financial',
        'meta' => 'Financial attention needed'
    ],
    [
        'label' => 'Over Budget Status Items',
        'value' => $over_budget_stages,
        'class' => 'card-pending',
        'href'  => 'admin_project_report.php?type=financial',
        'meta' => 'Project status allocations to review'
    ],
    [
        'label' => 'Internal Collaboration Messages',
        'value' => $collaboration_messages,
        'class' => 'card-total',
        'href'  => 'project_collaboration.php',
        'meta' => 'Open stakeholder discussion'
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
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3">
        <?php include "adminmenu.php"; ?>
    </div>

    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Admin Overview</span>
                    <h3>See the full monitoring system at a glance.</h3>
                    <p>Review incoming projects, community requests, budget alerts, and stakeholder activity from one polished command view.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $total_projects ?></strong>&nbsp; Projects</div>
                    <div class="hero-pill"><strong><?= $pending_requests ?></strong>&nbsp; Requests</div>
                    <div class="hero-pill"><strong><?= $collaboration_messages ?></strong>&nbsp; Messages</div>
                </div>
            </div>
        </div>

        <div class="summary-cards">
            <?php foreach ($summary_cards as $card): ?>
                <a class="summary-card-link" href="<?= $card['href'] ?>">
                    <div class="summary-card <?= $card['class'] ?>">
                        <h3><?= $card['label'] ?></h3>
                        <h2><?= $card['value'] ?></h2>
                        <p class="metric-meta"><?= $card['meta'] ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="alert-grid">
            <?php foreach ($alert_cards as $card): ?>
                <a class="summary-card-link" href="<?= $card['href'] ?>">
                    <div class="summary-card <?= $card['class'] ?>">
                        <h3><?= $card['label'] ?></h3>
                        <h2><?= $card['value'] ?></h2>
                        <p class="metric-meta"><?= $card['meta'] ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="chart-card">
            <div class="section-header">
                <div>
                    <span class="section-kicker">Activity Trend</span>
                    <h3>Projects Created in the Last 7 Days</h3>
                </div>
                <p>Use this trend view to spot quiet periods, bursts of submissions, and the exact project titles created on each day.</p>
            </div>
            <div class="chart-shell">
                <canvas id="projectsLineChart"></canvas>
            </div>
        </div>
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
            borderColor: '#0f766e',
            backgroundColor: 'rgba(15,118,110,0.16)',
            fill: true,
            tension: 0.3,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: '#17333b'
                }
            },
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
            x: {
                ticks: { color: '#5e7379' },
                grid: { color: 'rgba(198,186,166,0.22)' }
            },
            y: {
                beginAtZero: true,
                ticks: { color: '#5e7379' },
                grid: { color: 'rgba(198,186,166,0.22)' }
            }
        }
    }
});
</script>

</body>
</html>
