<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

/* SECURITY: ONLY PROJECT LEAD ROLES */
if (!isset($_SESSION['role']) || !isProjectLeadRole($_SESSION['role'])) {
    header("Location: ../Pages/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Project</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- MAIN CSS -->
    <link rel="stylesheet" href="../assets/css/flexible.css">

    <!-- LEAFLET (FREE MAP) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>

<?php include "header.php"; ?>

<div class="row">

    <!-- SIDE MENU -->
    <div class="col-3">
        <?php include "menu.php"; ?>
    </div>

    <!-- MAIN CONTENT -->
    <div class="col-9">
        <div class="form-card">
            <h3>Create Project</h3>

            <form action="save_project.php" method="POST" enctype="multipart/form-data">

                <!-- PROJECT INFO -->
                Project Title
                <input type="text" name="title" required>

                Description
                <input type="text" name="description" required>

                District
                <input type="text" name="district" required>

                Project Location (Name)
                <input type="text" name="location" required>

                <!-- 💰 BUDGET SECTION -->
                Estimated Project Budget (MWK)
                <input type="number" name="estimated_budget" step="0.01" required>

                Contractor Payment Amount (MWK)
                <input type="number" name="contractor_fee" step="0.01" required>

                <!-- DOCUMENT UPLOAD -->
                Upload Supporting Document (PDF / Image)
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">

                <!-- MAP COORDINATES (HIDDEN) -->
                <input type="hidden" name="latitude" id="lat">
                <input type="hidden" name="longitude" id="lng">

                <label><strong>Select Project Location on Map</strong></label>
                <div id="map" style="height: 400px; margin-top:10px;"></div>

                <br>
                <input type="submit" name="save_project" value="Submit Project">
            </form>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

<!-- LEAFLET JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    /* DEFAULT VIEW: MALAWI */
    var map = L.map('map').setView([-13.9626, 33.7741], 6);

    /* FREE OPENSTREETMAP TILES */
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker;

    /* CLICK TO PLACE MARKER */
    map.on('click', function(e) {

        if (marker) {
            map.removeLayer(marker);
        }

        marker = L.marker(e.latlng).addTo(map);

        // Save coordinates to hidden fields
        document.getElementById('lat').value = e.latlng.lat;
        document.getElementById('lng').value = e.latlng.lng;
    });
</script>
</body>
</html>
