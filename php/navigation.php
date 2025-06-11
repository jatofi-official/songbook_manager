<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-music-note-beamed me-2"></i>
            Songbook Manager
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php if($website=="index") echo "active"?>" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($website=="songs") echo "active"?>" href="songs.php">Songs</a>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link  <?php if($website=="songbooks") echo "active"?>" href="#">Songbooks</a>
                </li> -->
                <li class="nav-item">
                    <a href="user_settings.php" class="nav-link <?php if($website=="user") echo "active"?>">
                        <?php echo isset($_SESSION["name"]) ? $_SESSION["name"] : "Log in"; ?>
                    </a>
                </li>
            </ul>
    </div>
</nav>
