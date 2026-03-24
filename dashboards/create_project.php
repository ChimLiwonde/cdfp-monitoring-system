<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}

$error_message = pullSessionMessage('error_message');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Project</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3">
        <?php include "menu.php"; ?>
    </div>

    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Project Creation</span>
                    <h3>Start a new project with clearer inputs and location capture.</h3>
                    <p>Create the project record, define its budget, attach supporting documents, and pin the exact location on the map in one clean flow.</p>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong>Budget</strong>&nbsp; Setup</div>
                    <div class="hero-pill"><strong>Map</strong>&nbsp; Pin</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($error_message !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <span class="section-kicker">New Project Form</span>
                    <h3>Create Project</h3>
                </div>
                <p>Fill in the project details first, then click the map to save the project coordinates before submitting.</p>
            </div>

            <form action="save_project.php" method="POST" enctype="multipart/form-data">
                <?= csrfInput('create_project_form') ?>

                <div class="form-grid">
                    <div class="full-span">
                        <label for="title">Project Title</label>
                        <input id="title" type="text" name="title" required>
                    </div>

                    <div class="full-span">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>

                    <div>
                        <label for="district">District</label>
                        <input id="district" type="text" name="district" required>
                    </div>

                    <div>
                        <label for="location">Project Location (Name)</label>
                        <input id="location" type="text" name="location" required>
                    </div>

                    <div>
                        <label for="estimated_budget">Estimated Project Budget (MWK)</label>
                        <input id="estimated_budget" type="number" name="estimated_budget" step="0.01" required>
                    </div>

                    <div>
                        <label for="contractor_fee">Contractor Payment Amount (MWK)</label>
                        <input id="contractor_fee" type="number" name="contractor_fee" step="0.01" required>
                    </div>

                    <div class="full-span">
                        <label for="document">Upload Supporting Document (PDF / Image)</label>
                        <input id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>

                <input type="hidden" name="latitude" id="lat">
                <input type="hidden" name="longitude" id="lng">

                <div class="data-card" style="margin-top:18px;">
                    <div class="section-header">
                        <div>
                            <span class="section-kicker">Map Selection</span>
                            <h4>Select Project Location on Map</h4>
                        </div>
                        <p>Click once on the map to drop the location marker and store the coordinates.</p>
                    </div>
                    <div id="map" class="map-panel"></div>
                </div>

                <input type="submit" name="save_project" value="Submit Project" style="margin-top:18px;">
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([-13.9626, 33.7741], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let marker;

map.on('click', function(e) {
    if (marker) {
        map.removeLayer(marker);
    }

    marker = L.marker(e.latlng).addTo(map);
    document.getElementById('lat').value = e.latlng.lat;
    document.getElementById('lng').value = e.latlng.lng;
});
</script>
</body>
</html>
