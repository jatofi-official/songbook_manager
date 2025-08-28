<?php
session_start();
include('php/auth.php'); 
include('php/db.php');          
include('php/data.php');      
$website = "index";
include('php/functions.php'); 
include("php/header.php");
include('php/navigation.php');


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
   <!-- Dashboard Header -->
    <div class="dashboard-header">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-muted mb-0">Manage your songs and songbooks</p>
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
            <a href="songs.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo $totalSongs ?></div>
                    <div class="label">Total Songs</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="songs.php?new=1" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_new_songs_count($mysqli); ?></div>
                    <div class="label">New Songs</div>
                </div>
            </a>
        </div>

        <?php

        // Progress percentage
        $progressPercent = ($totalSongs > 0) ? ($finishedSongs / $totalSongs) * 100 : 0;

        // Example: Adjust the session variable name if different
        $isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == true;
        $hrefLink = $isAdmin ? "songs.php?finished=0" : "songs.php?finished=1";
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
            <a href="users.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_total_users($mysqli); ?></div>
                    <div class="label">Total users</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <!-- Right Column (Shows first on small screens) -->
        <div class="col-12 col-md-4 order-1 order-md-2 mb-4 mb-md-0">
            <div class="all_songs_card h-100">
                <div class="card-body p-0 h-100">
                    <a href="songs.php" class="btn w-100 h-100 d-flex justify-content-center align-items-center fs-4 rounded border border-primary text-primary bg-transparent">
                        All songs
                    </a>
                </div>
            </div>
        </div>

        <!-- Left Column -->
        <div class="col-12 col-md-8 order-2 order-md-1">
            <!-- Recent Activity -->
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="list-group">
                        <?php
                        $activities = get_recent_activity($mysqli, 5);

                        foreach ($activities as $activity) {
                            $timeAgo = time_elapsed_string($activity['time']);
                            ?>
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php 
                                        $typeLabels = [
                                            'edit' => 'Edited Song',
                                            'add' => 'Added Song',
                                            'delete' => 'Deleted Song',
                                            "new_user" => "New user"
                                        ];

                                        $type = $activity['type'];
                                        echo htmlspecialchars($typeLabels[$type] ?? ucfirst($type));
                                        ?>

                                    
                                </h6>
                                    <small><?php echo $timeAgo; ?></small>
                                </div>
                                <p class="mb-1 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recently Added Songs -->
        <!-- <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recently Added Songs</h5>
                        <a href="#" class="btn btn-sm btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Artist</th>
                                        <th>Genre</th>
                                        <th>Key</th>
                                        <th>Tempo</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Blinding Lights</td>
                                        <td>The Weeknd</td>
                                        <td>Synth-pop</td>
                                        <td>F minor</td>
                                        <td>171 BPM</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Dancing Queen</td>
                                        <td>ABBA</td>
                                        <td>Disco</td>
                                        <td>A major</td>
                                        <td>101 BPM</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Bohemian Rhapsody</td>
                                        <td>Queen</td>
                                        <td>Rock</td>
                                        <td>B♭ major</td>
                                        <td>72 BPM</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Watermelon Sugar</td>
                                        <td>Harry Styles</td>
                                        <td>Pop</td>
                                        <td>E major</td>
                                        <td>95 BPM</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->


    </div>

<?php
include('php/footer.php');
?>