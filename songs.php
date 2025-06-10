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
document.querySelector('.navbar-toggler')?.addEventListener('click', () => {
  console.log("Navbar toggler clicked");
});
</script>

<script>

document.addEventListener("DOMContentLoaded", () => {
    const songItems = document.querySelectorAll(".song-list-item");
    const detailContainer = document.querySelector(".song-detail-container");
    const newSongBtn = document.getElementById("newSongBtn");

    function attachFormSubmitHandler() {
        const form = detailContainer.querySelector("form.song-form");
        if (!form) return;

        form.addEventListener("submit", (e) => {
            e.preventDefault();

            const formData = new FormData(form);

            fetch("php/actions/save_song.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    detailContainer.innerHTML = data.html;

                    // Optional: refresh song list
                    // refreshSongList(); // implement this function later if needed

                    // Update URL
                    const url = new URL(window.location);
                    url.searchParams.set("id", data.id);
                    url.searchParams.delete("new");
                    window.history.replaceState(null, "", url.toString());
                } else {
                    alert("Failed to save song.");
                }
            })
            .catch(() => {
                alert("Error submitting form.");
            });
        });
    }

    songItems.forEach(item => {
        item.addEventListener("click", () => {
            const id = item.getAttribute("data-id");

            songItems.forEach(el => el.classList.remove("active"));
            item.classList.add("active");

            fetch(`php/subpage/get_song.php?id=${id}`)
                .then(res => res.text())
                .then(html => {
                    detailContainer.innerHTML = html;
                })
                .catch(() => {
                    detailContainer.innerHTML = "<p>Error loading song details.</p>";
                });

            const url = new URL(window.location);
            url.searchParams.delete('new');
            window.history.replaceState(null, '', url.toString());
        });
    });

    newSongBtn.addEventListener("click", (e) => {
        e.preventDefault();

        document.querySelectorAll(".song-list-item").forEach(el => el.classList.remove("active"));

        fetch('php/subpage/song_form.php?embedded=1')
            .then(res => res.text())
            .then(html => {
                detailContainer.innerHTML = html;

                const url = new URL(window.location);
                url.searchParams.set('new', '1');
                url.searchParams.delete('id');
                window.history.replaceState(null, '', url.toString());

                attachFormSubmitHandler(); // Attach the submit handler after form loads
            })
            .catch(() => {
                detailContainer.innerHTML = "<p>Error loading new song form.</p>";
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
                <a href="?new=1" class="btn btn-sm btn-success w-100" id="newSongBtn">
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
$perPage = 10; // Songs per page
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
    
    // Previous button (disabled if on page 1)
    if ($page > 1) {
        echo "<a href='?page=" . ($page - 1) . "' class='btn btn-sm btn-outline-primary'><</a> ";
    } else {
        echo "<span class='btn btn-sm btn-outline-secondary disabled'><</span> ";
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
        echo "<a href='?page=" . ($page + 1) . "' class='btn btn-sm btn-outline-primary'>></a>";
    } else {
        echo "<span class='btn btn-sm btn-outline-secondary disabled'>></span>";
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