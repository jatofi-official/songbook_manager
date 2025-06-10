<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark w-100">
    <!-- No more container here: it limits width! -->
    <a class="navbar-brand ms-3" href="index.php">
        <i class="bi bi-music-note-beamed me-2"></i>
        Songbook Manager
    </a>
    <button class="navbar-toggler me-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end me-3" id="navbarNav">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link <?php if($website=="index") echo "active"?>" href="index.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if($website=="songs") echo "active"?>" href="songs.php">Songs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link  <?php if($website=="songbooks") echo "active"?>" href="#">Songbooks</a>
            </li>
            <li class="nav-item">
                <a href="user_settings.php" class="nav-link <?php if($website=="user") echo "active"?>">
                    <?php echo isset($_SESSION["name"]) ? $_SESSION["name"] : "Log in"; ?>
                </a>
            </li>
        </ul>
    </div>
</nav>
