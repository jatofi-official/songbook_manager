<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once('php/auth.php'); 
include_once('php/db.php');          
include_once('php/data.php');      
$website = "index";
include_once('php/functions.php'); 
include_once("php/header.php");
include_once('php/navigation.php');



?>
   <!-- Dashboard Header -->
    <div class="dashboard-header">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-0">Dashboard</h1>
            <p class="text-muted mb-0">Manage songs and songbooks</p>
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
    $dashboardLeaders = get_xp_leaderboard($mysqli, 'week', 5);

    ?>
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="songs.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo $totalSongs ?></div>
                    <div class="label">Songs</div>
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

        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="songbooks.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_number_of_songbooks($mysqli); ?></div>
                    <div class="label">Songbooks</div>
                </div>
            </a>
        </div>        
        

        <div class="col-6 col-md-3">
            <a href="users.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_total_users($mysqli); ?></div>
                    <div class="label">Users</div>
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
                    <a href="songs.php" class="btn w-100 h-100 d-flex justify-content-center align-items-center fs-4 rounded border border-primary text-primary bg-white">
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

    </div>

<div class="row mt-4">
    <div class="col-12">
        <a href="<?= $projectUrl ?>/wishlist.php" class="text-decoration-none">
            <div class="card text-white bg-primary shadow-lg hover-scale rounded-4 p-4 d-flex justify-content-center align-items-center text-center" style="transition: transform 0.2s;">
                <div class="fs-3 fw-bold mb-2"><i class="bi bi-heart"></i> Song Wishlist</div>
                <div class="fs-6">Click here to request a song or vote for songs others want added</div>
            </div>
        </a>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Weekly XP leaderboard</strong>
                <a href="users.php" class="btn btn-sm btn-outline-primary">Full leaderboard</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($dashboardLeaders as $index => $leader): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between" href="user.php?id=<?= (int)$leader['id'] ?>">
                        <span><?= $index + 1 ?>. <?= htmlspecialchars($leader['name']) ?></span>
                        <strong><?= number_format((int)$leader['xp']) ?> XP</strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Activities</strong></div>
            <div class="card-body d-grid gap-2">
                <a class="btn btn-outline-primary" href="wishlist.php">Song wishlist</a>
                <a class="btn btn-outline-secondary" href="users.php?sort=xp">Users by XP</a>
            </div>
        </div>
    </div>
</div>

<!-- <?php if (isset($_SESSION["id"]) && get_user_group($mysqli, $_SESSION["id"])==1){ ?>
<div class="row mt-4">
    <div class="col-12">
        <a href="<?= $projectUrl ?>/anketa/index.php" class="text-decoration-none">
            <div class="card text-white bg-primary shadow-lg hover-scale rounded-4 p-4 d-flex justify-content-center align-items-center text-center" style="transition: transform 0.2s;">
                <div class="fs-3 fw-bold mb-2"><i class="bi bi-list-check"></i> Vyplň anketu 2025</div>
                <div class="fs-6">Klikni sem a začni vyplňovať potáborovú anketu spevníku Mustangy</div>
            </div>
        </a>
    </div>
</div> -->

<?php }?>

<style>
.hover-scale:hover {
    transform: scale(1.03);
}
</style>


<?php
include('php/footer.php');
?>
