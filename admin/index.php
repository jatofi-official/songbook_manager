<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once('../php/data.php');      
$website = "admin";
$title = "Admin dashboard";
include_once('../php/auth.php'); 
include_once('../php/db.php');          
include_once('../php/functions.php'); 
include_once("../php/header.php");
include_once('../php/navigation.php');

// Admin-only access
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    ?>
    <div class="container mt-5 text-center">
        <p class="text-danger">Táto stránka je prístupná iba administrátorom.</p>
    </div>
<?php
    include '../php/footer.php';
    exit;
}

?>
   <!-- Dashboard Header -->
    <div class="dashboard-header">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-0">Admin dashboard</h1>
            <p class="text-muted mb-0">Important information about page activity</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center w-100 w-md-auto">

        </div>
    </div>
    </div>

    <!-- Main Part -->
    <div class="container">

    <?php
    $songsCounts = get_number_of_songs($mysqli);

    // Total songs
    $totalSongs = $songsCounts['total'];

    // Fixed songs
    $finishedSongs = $songsCounts['fixed'];
    ?>
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="../songs.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo $totalSongs ?></div>
                    <div class="label">Total Songs</div>
                </div>
            </a>
        </div>



        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="log.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_recent_activity_number($mysqli);?></div>
                    <div class="label">Recent activity</div>
                </div>
            </a>
        </div>

        <?php

        // Progress percentage
        $progressPercent = ($totalSongs > 0) ? ($finishedSongs / $totalSongs) * 100 : 0;

        // Example: Adjust the session variable name if different
        $isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == true;
        $hrefLink = "../songs.php?finished=0";
        ?>

        <div class="col-6 col-md-3 mb-3 mb-md-0">
        <a href="<?= htmlspecialchars($hrefLink) ?>" class="stat-card-link" style="text-decoration: none;">
            <div class="stat-card p-3 d-flex flex-column justify-content-between" style="height: 100%;">
            <div class="d-flex justify-content-center align-items-baseline mb-2">
                <div class="text-muted small"><?= $finishedSongs ?> / <?= $totalSongs ?></div>
            </div>

            <div class="progress mb-2" style="height: 20px;">
                <div class="progress-bar bg-success" role="progressbar"
                style="width: <?= round($progressPercent) ?>%;"
                aria-valuenow="<?= round($progressPercent) ?>"
                aria-valuemin="0" aria-valuemax="100">
                <?= round($progressPercent) ?>%
                </div>
            </div>

            <div class="label text-center">Finished Songs</div>
            </div>
        </a>
        </div>

        <div class="col-6 col-md-3">
            <a href="../users.php?sort=new" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_recent_activity_number($mysqli,"new_user"); ?></div>
                    <div class="label">New users</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Graphs -->
    <div class="row">
        <!-- Activity Last Week -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Activity past week</div>
                <div class="card-body">
                    <div class="chart-placeholder" style="height:250px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;">
                        Graph Placeholder
                    </div>
                </div>
            </div>
        </div>

        <!-- User Logins Last Week -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Logins pasts week</div>
                <div class="card-body">
                    <div class="chart-placeholder" style="height:250px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;">
                        Graph Placeholder
                    </div>
                </div>
            </div>
        </div>
    </div>



<style>
.hover-scale:hover {
    transform: scale(1.03);
}
</style>


<?php
include('../php/footer.php');
?>