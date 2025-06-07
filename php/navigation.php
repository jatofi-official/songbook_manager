
<nav class="row">
    <ul class="nav">
            <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
            <!-- <li class="nav-item"><a href="test2.php" class="nav-link">Graphs</a></li> -->
    </ul>
    <ul class="nav">
            <li class="nav-item"><a href="add_song.php" class="nav-link">Add Song</a></li>
            <!-- <li class="nav-item"><a href="test2.php" class="nav-link">Graphs</a></li> -->
    </ul>

    <a href="user_settings.php" class="nav-link ml-auto"><?php if(isset($_SESSION["name"])){
        echo $_SESSION["name"];
    }
    else{
        echo "Log in";
    }
    
    
    ?></a>

</nav>