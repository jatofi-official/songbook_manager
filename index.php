<?php
session_start();
include('php/db.php');          
include('php/data.php');      
include('php/functions.php'); 
include("php/header.php");
include('php/navigation.php');
include("php/categories.php");

if(isset($_SESSION["user"])){
    // echo"Logged in <br>";
}


// Show songs

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];

   
    $song = get_song($mysqli,$id);
    $can_display = FALSE;

    if($song["is_public"]==0){
        if(isset($_SESSION["user"]) &&( $_SESSION["admin"]==1||$song["owner"]==$_SESSION["user"])){
            $can_display = TRUE;
        }
    }
    else{
        $can_display=TRUE;
    }
    
    if($can_display){
        echo "<h2>" . htmlspecialchars($song['title']) . "</h2>";
        echo "<p><strong>Author:</strong> " . htmlspecialchars($song['author']) . "</p>";
        echo "<p><strong>Category:</strong> " . htmlspecialchars($song['category']) . "</p>";
        echo "<p><strong>Capo:</strong> " . htmlspecialchars($song['capo']) . "</p>";
        echo "<p><strong>Owner:</strong> " . htmlspecialchars($song['owner']) . "</p>";
        echo "<p><strong>Public:</strong> " . ($song['is_public'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Created:</strong> " . htmlspecialchars($song['create_date']) . "</p>";
        echo "<p><strong>Modified:</strong> " . htmlspecialchars($song['modified_date']) . "</p>";
        echo "<p><strong>Tags:</strong> " . htmlspecialchars($song['tags']) . "</p>";
        echo "<p><strong>Instruments:</strong> " . htmlspecialchars($song['instruments']) . "</p>";
        echo "<hr>";
        echo "<pre>" . htmlspecialchars($song['content']) . "</pre>";
    }
    else{
        echo "<p>You don't have access to this song</p>";
    }




    echo "<p><a href='?'>Back to list</a></p>";

} else {
    $results_per_page = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $results_per_page;

    $count_result = $mysqli->query("SELECT COUNT(*) AS total FROM songs");
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $results_per_page);

    $sql = "SELECT id, category, title, author, owner, is_public FROM songs ORDER BY title ASC LIMIT $results_per_page OFFSET $offset";
    $result = $mysqli->query($sql);

    echo "<h2>Songs list (page $page of $total_pages)</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Title</th>";
    echo "<th>Author</th>";
    echo "<th>Category</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    while ($row = $result->fetch_assoc()) {
        $title = htmlspecialchars($row['title']);
        $author = htmlspecialchars($row['author']);
        $category = htmlspecialchars($row['category']);
        $id = (int)$row['id'];
        echo "<tr>";
        echo "<td><a href='?id=$id'>$title</a></td>";
        echo "<td>$author</td>";
        echo "<td>$categories[$category]</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";

    echo "<div style='margin-top: 10px;'>";
    if ($page > 1) {
        $prev = $page - 1;
        echo "<a href='?page=$prev'>&laquo; Previous</a> ";
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $page) {
            echo "<strong>$i</strong> ";
        } else {
            echo "<a href='?page=$i'>$i</a> ";
        }
    }
    if ($page < $total_pages) {
        $next = $page + 1;
        echo "<a href='?page=$next'>Next &raquo;</a>";
    }
    echo "</div>";
}



include('php/footer.php');
?>