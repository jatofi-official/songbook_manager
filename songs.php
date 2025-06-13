<?php
session_start();
if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){  
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
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
            const isSmallScreen = window.matchMedia("(max-width: 767.98px)").matches;

            if (isSmallScreen) {
                // Redirect to full song page on small screens
                window.location.href = `song.php?id=${id}`;
            } else {
                // Existing behavior: load via AJAX on bigger screens
                songItems.forEach(el => el.classList.remove("active"));
                item.classList.add("active");

                fetch(`php/subpage/get_song.php?id=${id}&embedded=1`)
                    .then(res => res.text())
                    .then(html => {
                        detailContainer.innerHTML = html;
                    })
                    .catch(() => {
                        detailContainer.innerHTML = "<p>Error loading song details.</p>";
                    });

                // Remove any 'new' param from URL without reloading
                const url = new URL(window.location);
                url.searchParams.delete('new');
                window.history.replaceState(null, '', url.toString());
            }
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
<div class="row g-0">
    <!-- Song List Column -->
    <div class="col-md-5 col-lg-4 song-list-container">
        
        <div class="search-box p-2">
            <div class="pb-2">
                <a href="song.php?new=1" class="btn btn-sm btn-success w-100 <?= isset($_SESSION["user"]) ? '' : 'disabled style="opacity: 0.2; cursor: not-allowed;"' ?>">
                    <i class="bi bi-plus-circle me-1"></i> New Song
                </a>
            </div>


            <form id="songFilterForm" method="get">
                <div class="input-group mb-3">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search songs..."
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">                    
                        <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="d-flex mb-2">
                <!-- Category filter -->
                <select name="category" class="form-select form-select-sm me-2">
                    <option value="">All Categories</option>
                    <?php 
                        $index = 1;
                        foreach($categories as $category){
                            $selected = (isset($_GET['category']) && $_GET['category'] == $index) ? 'selected' : '';
                            echo "<option value='$index' $selected>$category</option>";
                            $index++;
                        }
                    ?>
                </select>

                <!-- Instruments filter (single select) -->
                <select name="instruments" class="form-select form-select-sm">
                    <option value="">All Instruments</option>
                    <?php 
                        $selectedInstrument = $_GET['instruments'] ?? '';
                        foreach($instruments as $instrument){
                            $name = $instrument["name"];
                            $selected = ($selectedInstrument === $name) ? 'selected' : '';
                            echo "<option value='$name' $selected>$name</option>";
                        }
                    ?>
                </select>
            </div>

            <input type="hidden" name="page" value="1">
            </form>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.getElementById("songFilterForm");

            form.querySelectorAll("select").forEach(sel => {
                sel.addEventListener("change", () => {
                    form.querySelector("input[name='page']").value = 1;
                    form.submit();
                });
            });
        });
        </script>


        <div class="song-list">

<?php
// Pagination setup
$perPage = PER_PAGE; // Songs per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page (default: 1)

$search      = $_GET['search']      ?? '';
$category    = $_GET['category']    ?? '';

$instrumentsRaw = $_GET['instruments'] ?? '0';

// Convert to array for compatibility with function
if (is_array($instrumentsRaw)) {
    $selectedInstruments = $instrumentsRaw;
} else {
    // if "0" or empty => no filter
    if ($instrumentsRaw === '0' || $instrumentsRaw === '') {
        $selectedInstruments = [];
    } else {
        $selectedInstruments = [$instrumentsRaw];
    }
}



$songData = get_all_songs($mysqli, $perPage, $page, $search, $category, $selectedInstruments);

$songs = $songData['songs'];
$totalPages = $songData["totalPages"];


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

    // Helper function to generate page link or active span
    function pagination_button($i, $page) {
        if ($i == $page) {
            return "<span class='btn btn-sm btn-primary active'>$i</span> ";
        } else {
            return "<a href='?" . http_build_query(array_merge($_GET, ['page' => $i])) . "' class='btn btn-sm btn-outline-primary'>$i</a> ";
        }
    }

    // Previous button
    if ($page > 1) {
        echo "<a href='?" . http_build_query(array_merge($_GET, ['page' => $page - 1])) . "' class='btn btn-sm btn-outline-primary'>&lt;</a> ";
    } else {
        echo "<span class='btn btn-sm btn-outline-secondary disabled'>&lt;</span> ";
    }

    $displayRange = 2; // how many pages before/after current page to show

    if ($totalPages <= 10) {
        // show all pages
        for ($i = 1; $i <= $totalPages; $i++) {
            echo pagination_button($i, $page);
        }
    } else {
        // Always show first page
        echo pagination_button(1, $page);

        // Show ellipsis if needed
        if ($page - $displayRange > 2) {
            echo "<span class='btn btn-sm disabled'>...</span> ";
        }

        // Pages around the current page
        $start = max(2, $page - $displayRange);
        $end = min($totalPages - 1, $page + $displayRange);
        for ($i = $start; $i <= $end; $i++) {
            echo pagination_button($i, $page);
        }

        // Show ellipsis before last page
        if ($page + $displayRange < $totalPages - 1) {
            echo "<span class='btn btn-sm disabled'>...</span> ";
        }

        // Always show last page
        echo pagination_button($totalPages, $page);
    }

    // Next button
    if ($page < $totalPages) {
        echo "<a href='?" . http_build_query(array_merge($_GET, ['page' => $page + 1])) . "' class='btn btn-sm btn-outline-primary'>&gt;</a>";
    } else {
        echo "<span class='btn btn-sm btn-outline-secondary disabled'>&gt;</span>";
    }

    echo "</div>";

} else {
    echo "<p>No songs found.</p>";
}
?>
</div>

    </div>
    
    <!-- Song Detail Column -->
    <div class="col-md-7 col-lg-8 p-0 song-detail-container d-none d-md-block">
        <?php 
        if (isset($_GET['new']) && $_GET['new'] == 1) {
            $_GET['embedded'] = 1;
            include('php/subpage/song_form.php');

        } elseif (isset($_GET['id'])) {
            // Load song details for the given id
            $songId = (int)$_GET['id'];
            $_GET['embedded'] = 1;

            include("php/subpage/get_song.php");
            

        }
        else{

        

        
        
        ?>
        <div class="p-4 text-muted">Select a song from the list to view details.</div>

        <?php } ?>
    </div>

<?php
include("php/footer.php");
?>