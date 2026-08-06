<?php
// Ensure $projectUrl exists, fallback if not
if (!isset($projectUrl)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $projectUrl = $protocol . "://" . $host . str_replace("/anketa", "", $scriptDir);
}
?>

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= $projectUrl ?>/index.php">
            <i class="bi bi-music-note-beamed me-2"></i>
            Songbook Manager
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php if($website=="songs") echo "active"?>" href="<?= $projectUrl ?>/songs.php">Songs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($website=="songbooks") echo "active"?>" href="<?= $projectUrl ?>/songbooks.php">Songbooks</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($website=="users") echo "active"?>" href="<?= $projectUrl ?>/users.php">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($website=="about") echo "active"?>" href="<?= $projectUrl ?>/about.php">About</a>
                </li>
                <?php if (isset($_SESSION['id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php if($website=="reports") echo "active"?>" href="<?= $projectUrl ?>/song_reports.php">Reports</a>
                    </li>
                <?php endif; ?>

                <?php if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){ ?>
                    <li class="nav-item">
                        <a class="nav-link <?php if($website=="admin") echo "active";?>" href="<?= $projectUrl ?>/admin/index.php">Admin</a>
                    </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="<?= $projectUrl ?>/user_settings.php" class="nav-link <?php if($website=="user") echo "active"?>">
                        <?= isset($_SESSION["name"]) ? $_SESSION["name"] : "Log in"; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
