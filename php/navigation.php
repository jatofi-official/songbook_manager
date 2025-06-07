</nav>
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
                        <a class="nav-link active" href="#">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Songs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Songbooks</a>
                    </li>
                    <li class="nav-item">
                         <a href="user_settings.php" class="nav-link ml-auto"><?php if(isset($_SESSION["name"])){
                            echo $_SESSION["name"];
                        }
                        else{
                            echo "Log in";
                        }
                        ?></a>
                    </li>
            </ul>
            </div>
    </div>
</nav>