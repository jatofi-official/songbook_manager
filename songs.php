<?php
session_start();
include('php/db.php');          
include('php/data.php');    

$website = "songs";

include('php/functions.php'); 
?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const songItems = document.querySelectorAll(".song-list-item");
    const detailContainer = document.querySelector(".song-detail-container");

    songItems.forEach(item => {
        item.addEventListener("click", () => {
            const id = item.getAttribute("data-id");
            console.log("Clicked song with ID:", id); // Add this
            // Highlight selected
            songItems.forEach(el => el.classList.remove("active"));
            item.classList.add("active");

            fetch(`php/get_song.php?id=${id}`)
                .then(res => res.text())
                .then(html => {
                    detailContainer.innerHTML = html;
                })
                .catch(err => {
                    detailContainer.innerHTML = "<p>Error loading song details.</p>";
                });
        });
    });
});
</script>
<?php

include("php/header.php");
include('php/navigation.php');
include("php/categories.php");
include("php/instruments.php");

?>
<div class="row h-100">
    <!-- Song List Column -->
    <div class="col-md-5 col-lg-4 p-0 song-list-container">
        <div class="search-box">
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Search songs...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <div class="d-flex">
                <select class="form-select form-select-sm me-2">

                    <option value = "all">All Categories</option>
                    <?php 
                        $index = 1;
                        foreach($categories as $category){
                            echo "<option value='$index'>$category</option>";
                            $index +=1;
                        }
                    ?>
                </select>
                <select class="form-select form-select-sm">
                    <option value="0">All Instruments</option>
                    <?php 
                        foreach($instruments as $instrument){
                            echo "<option value='".$instrument["id"]."'>".$instrument["name"]."</option>";
                        }
                    ?>
                </select>
            </div>
        </div>
        
        <div class="song-list">

<?php
$songQuery = $mysqli->query("SELECT * FROM songs ORDER BY category, title");
while ($song = $songQuery->fetch_assoc()) {
    echo "<div class='song-list-item' data-id='{$song['id']}'>";
    echo "<div class='song-title'>" . htmlspecialchars($song['title']) . "</div>";
    echo "<div class='song-artist'>" . htmlspecialchars($song['artist']) . "</div>";
    echo "<div class='mt-2'>";

    if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
        echo "<span class='badge " . ($song['is_public'] ? 'bg-success' : 'bg-secondary') . " visibility-badge'>" . ($song['is_public'] ? "Public" : "Private") . "</span>";    
    }
    
    // Only display tags if they exist and aren't empty
    if (!empty($song['tags']) && trim($song['tags']) !== '') {
        foreach (explode(',', $song['tags']) as $tag) {
            $trimmedTag = trim($tag);
            if (!empty($trimmedTag)) {
                echo "<span class='tag'>" . htmlspecialchars($trimmedTag) . "</span> ";
            }
        }
    }

    // Only display instruments if they exist and aren't empty
    if (!empty($song['instruments']) && trim($song['instruments']) !== '') {
        foreach (explode(',', $song['instruments']) as $inst) {
            $trimmedInst = trim($inst);
            if (!empty($trimmedInst)) {
                echo "<span class='badge instrument-badge'>" . htmlspecialchars($trimmedInst) . "</span> ";
            }
        }
    }

    echo "</div></div>";
}
?>
</div>

    </div>
    
    <!-- Song Detail Column -->
    <div class="col-md-7 col-lg-8 p-0 song-detail-container">
        <div class="p-4 text-muted">Select a song from the list to view details.</div>
    </div>

<?php
echo "tu";
include("php/footer.php");
?>