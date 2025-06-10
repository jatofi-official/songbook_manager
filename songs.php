<?php
session_start();
include('php/db.php');          
include('php/data.php');    

$website = "songs";

include('php/functions.php'); 
?>
<script>
document.querySelector('.navbar-toggler')?.addEventListener('click', () => {
  console.log("Navbar toggler clicked");
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const songItems = document.querySelectorAll(".song-list-item");
    const detailContainer = document.querySelector(".song-detail-container");

    songItems.forEach(item => {
        item.addEventListener("click", () => {
            const id = item.getAttribute("data-id");
            // Highlight selected
            songItems.forEach(el => el.classList.remove("active"));
            item.classList.add("active");

            fetch(`php/subpage/get_song.php?id=${id}`)
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
// Pagination setup
$perPage = 10; // Songs per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page (default: 1)

// Fetch paginated songs
$songData = get_all_songs($mysqli, $perPage, $page);
$songs = $songData['songs'];
$totalPages = $songData['totalPages'];

// Display songs
if (!empty($songs)) {
    foreach ($songs as $song) {
        echo "<div class='song-list-item' data-id='{$song['id']}'>";
        echo "<div class='song-title'>" . htmlspecialchars($song['title']) . "</div>";
        echo "<div class='song-artist'>" . htmlspecialchars($song['artist']) . "</div>";
        echo "<div class='mt-2'>";

        // Admin-only: Visibility badge
        if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
            echo "<span class='badge " . ($song['is_public'] ? 'bg-success' : 'bg-secondary') . " visibility-badge'>" 
                . ($song['is_public'] ? "Public" : "Private") . "</span>";    
        }

        // Tags (if any)
        if (!empty($song['tags']) && trim($song['tags']) !== '') {
            foreach (explode(',', $song['tags']) as $tag) {
                $trimmedTag = trim($tag);
                if (!empty($trimmedTag)) {
                    echo "<span class='tag'>" . htmlspecialchars($trimmedTag) . "</span> ";
                }
            }
        }

        // Instruments (if any)
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

    // --- Pagination Controls ---
    echo "<div class='pagination mt-4'>";
    
    // Previous button (disabled if on page 1)
    if ($page > 1) {
        echo "<a href='?page=" . ($page - 1) . "' class='btn btn-sm btn-outline-primary'>Previous</a> ";
    } else {
        echo "<span class='btn btn-sm btn-outline-secondary disabled'>Previous</span> ";
    }

    // Page numbers (e.g., "1 2 3 ... 10")
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            echo "<span class='btn btn-sm btn-primary active'>$i</span> ";
        } else {
            echo "<a href='?page=$i' class='btn btn-sm btn-outline-primary'>$i</a> ";
        }
    }

    // Next button (disabled if on last page)
    if ($page < $totalPages) {
        echo "<a href='?page=" . ($page + 1) . "' class='btn btn-sm btn-outline-primary'>Next</a>";
    } else {
        echo "<span class='btn btn-sm btn-outline-secondary disabled'>Next</span>";
    }

    echo "</div>";

} else {
    echo "<p>No songs found.</p>";
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