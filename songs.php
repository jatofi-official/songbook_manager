<?php
session_start();
include('php/auth.php'); 
include('php/db.php');
include_once('php/data.php');
include_once('php/functions.php');
$website = "songs";

load_user_settings_into_session($mysqli);
$allTags = get_all_tags($mysqli);
$categories = get_all_categories($mysqli);
$songOwnerUsernames = get_song_owner_usernames($mysqli);
?>
<?php include("php/header.php"); ?>
<?php include('php/navigation.php');?>
<?php include("php/categories.php");?>

<script src="js/song_render.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const songItems = document.querySelectorAll(".song-list-item");
    const detailContainer = document.querySelector(".song-detail-container");

    songItems.forEach(item => {
        item.addEventListener("click", () => {
            const id = item.getAttribute("data-id");
            const isSmallScreen = window.matchMedia("(max-width: 767.98px)").matches;

            if (isSmallScreen) {
                window.location.href = `song.php?id=${id}`;
            } else {
                songItems.forEach(el => el.classList.remove("active"));
                item.classList.add("active");

                fetch(`php/subpage/get_song.php?id=${id}&embedded=1`)
                    .then(res => res.text())
                .then(html => {
                    detailContainer.innerHTML = html;
                    enhanceSongView();
                })

                    .catch(() => detailContainer.innerHTML = "<p>Error loading song details.</p>");
            }
        });
    });
});
</script>

<div class="row g-0">
    <!-- Song List -->
    <div class="col-md-5 col-lg-4 song-list-container">
        <div class="search-box p-2">
            <div class="pb-2">
                <a href="song.php?new=1" class="btn btn-sm btn-success w-100 <?= isset($_SESSION["user"]) ? '' : 'disabled style="opacity: 0.2; cursor: not-allowed;"' ?>">
                    <i class="bi bi-plus-circle me-1"></i> New Song
                </a>
                <?php if(!empty($_SESSION["import_enabled"])){ ?>
                <a href="import.php" class="btn btn-sm btn-outline-primary w-100 mt-1">
                    <i class="bi bi-cloud-download me-1"></i> Import Song
                </a>
                <?php } ?>
            </div>

            <form id="songFilterForm" method="get">
                <div class="input-group mb-3">
                    <input type="text" name="search" class="form-control" placeholder="Search songs..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                </div>

                <!-- Filters row: Category - Reset - Advanced -->
                <div class="d-flex align-items-center gap-2 mb-2">
                    <!-- Category filter -->
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php 
                            foreach($categories as $index=>$category){
                                $selected = (isset($_GET['category']) && $_GET['category'] == $index) ? 'selected' : '';
                                echo "<option value='$index' $selected>$category</option>";

                            }
                        ?>
                    </select>

                    <!-- Sort order -->
                    <?php
                    $sortBy = $_GET['sort'] ?? 'views';
                    $sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
                    $toggledDir = $sortDir === 'asc' ? 'desc' : 'asc';
                    ?>
                    <select name="sort" class="form-select form-select-sm">
                        <option value="category" <?= $sortBy === 'category' ? 'selected' : '' ?>>Category</option>
                        <option value="modified" <?= $sortBy === 'modified' ? 'selected' : '' ?>>Recently modified</option>
                        <option value="created" <?= $sortBy === 'created' ? 'selected' : '' ?>>Recently added</option>
                        <option value="views" <?= $sortBy === 'views' ? 'selected' : '' ?>>Most views</option>
                    </select>
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($sortDir) ?>">
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['dir' => $toggledDir, 'page' => 1]))) ?>"
                       class="btn btn-outline-secondary btn-sm d-flex align-items-center"
                       title="<?= $sortDir === 'asc' ? 'Ascending (click for descending)' : 'Descending (click for ascending)' ?>">
                        <i class="bi <?= $sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down' ?>"></i>
                    </a>

                    <!-- Reset button -->
                    <a href="songs.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-x-circle me-1"></i> Reset
                    </a>

                    <!-- Advanced toggle -->
                    <button id="toggleAdvanced" class="btn btn-outline-secondary btn-sm d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="false" aria-controls="advancedFilters">
                        <i class="bi bi-sliders me-1"></i> Advanced
                    </button>
                    <script>
                    document.getElementById("toggleAdvanced").addEventListener("click", function () {
                        fetch("php/toggle_advanced.php")
                            .then(response => response.json())
                            .then(data => {
                                console.log("Advanced search is now " + (data.advanced_search ? "ON" : "OFF"));
                            })
                            .catch(error => {
                                console.error("Error toggling Advanced search:", error);
                            });
                    });
                    </script>

                </div>

                <!-- Advanced options -->
                <?php
                if(isset($_SESSION["advanced_search"])&&$_SESSION["advanced_search"]==true){
                    $advancedOpen = true;
                }
                else{
                    $advancedOpen=false;
                }
                ?>


                <div class="collapse<?= $advancedOpen ? ' show' : '' ?>" id="advancedFilters">
                    <div class="rounded p-2 mb-2" id="advancedFilterBox">
                        <div class="mb-2">
                            <label class="form-label mb-1">Instrument</label>
                            <select name="instruments" class="form-select form-select-sm">
                                <option value="">All Instruments</option>
                                <?php
                                $selectedInstrument = $_GET['instruments'] ?? '';
                                foreach (get_all_instruments($mysqli) as $instrument) {
                                    $name = $instrument["name"];
                                    $selected = ($selectedInstrument === $name) ? 'selected' : '';
                                    echo "<option value='$name' $selected>$name</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label d-block">Tags</label>
                            <div class="d-flex flex-wrap gap-1">
                                <?php

                                $rawTags = $_GET['selected_tags'] ?? "";
                                $selectedTags = explode(",",$rawTags) ?? [];
                                // if (!is_array($selectedTags)) $selectedTags = [];

                                // foreach ($allTags as $tag) {
                                //     $isSelected = in_array($tag, $selectedTags);
                                //     $checked = $isSelected ? 'checked' : '';
                                //     echo "<div class='form-check form-check-inline'>";
                                //     echo "<input class='form-check-input' type='checkbox' name='tags[]' value='" . htmlspecialchars($tag) . "' id='tag_$tag' $checked>";
                                //     echo "<label class='form-check-label' for='tag_$tag'>$tag</label>";
                                //     echo "</div>";
                                // }
                                display_tag_selector($mysqli,implode(",",$selectedTags),FALSE);

                                ?>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="owner" class="form-label mb-1">User</label>
                            <input type="text" name="owner" id="owner" class="form-control form-control-sm" list="ownerSuggestions" autocomplete="off" value="<?= htmlspecialchars($_GET['owner'] ?? '') ?>">
                            <datalist id="ownerSuggestions">
                                <?php foreach ($songOwnerUsernames as $ownerUsername): ?>
                                    <option value="<?= htmlspecialchars($ownerUsername) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="mb-2">
                            <label class="form-label mb-1">Need to be fixed</label>
                            <!-- Finished filter -->
                            <select name="finished" class="form-select form-select-sm">
                                <option value="" <?php  echo ((!isset($_GET["finished"])) || ($_GET["finished"]!=1 && $_GET["finished"] != 0)) ? "selected" : "" ;?> >All songs</option>
                                <option value="1" <?php  echo (isset($_GET['finished']) && $_GET['finished'] == 1) ? 'selected' : '' ;?> >Finished sons</option>
                                <option value="0" <?php  echo (isset($_GET['finished']) && $_GET['finished'] == 0) ? 'selected' : '' ;?> >Unfinished songs</option>

                            </select>

                        </div>
                    </div>
                </div>

                <input type="hidden" name="page" value="1">
            </form>
        </div>


<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("songFilterForm");

    // Get ALL filter inputs (including tags, instruments, owner)
    const allFilterInputs = form.querySelectorAll(
        "select[name='category'], " +
        "select[name='sort'], " +
        "select[name='instruments'], " +
        "input[name='owner'], " +
        "select[name='finished'], " +
        "input[name='search']" 
    );

    // Auto-submit on change for ALL fields except search
    allFilterInputs.forEach(input => {
        if (input.name === 'search') {
            // Search only submits on Enter (keep original behavior)
            input.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    form.querySelector("input[name='page']").value = 1;
                    form.submit();
                }
            });
        } else {
            // All other fields auto-submit on change (including tags)
            input.addEventListener("change", () => {
                form.querySelector("input[name='page']").value = 1;
                form.submit(); // Tags will submit automatically via normal form behavior
            });
        }
    });


    // Listen for changes to selected_tags and submit the form
    const tagInput = document.querySelector("input[name='selected_tags']");
    if (tagInput) {
        const observer = new MutationObserver(() => {
            form.querySelector("input[name='page']").value = 1;
            form.submit();
        });

        observer.observe(tagInput, { attributes: true, attributeFilter: ['value'] });

        // Optional fallback: also listen for manual change
        tagInput.addEventListener("change", () => {
            form.querySelector("input[name='page']").value = 1;
            form.submit();
        });
    }

});

</script>



        <div class="song-list">
            <?php
            $perPage = PER_PAGE;
            $page = (int)($_GET['page'] ?? 1);

            if (isset($_GET['new']) && $_GET['new'] == '1') {
                $songData = get_new_songs($mysqli, $perPage, $page);
            } 
            else{
                $search = $_GET['search'] ?? '';
                $category = $_GET['category'] ?? '';
                $instrumentsRaw = $_GET['instruments'] ?? '';
                
                $rawTags = $_GET['selected_tags'] ?? "";
                $selectedTags = explode(",",$rawTags) ?? [];                
                
                $owner = isset($_GET['owner']) ? trim($_GET['owner']) : '';
                $finished = isset($_GET['finished']) ? trim($_GET['finished']) : null;
                $selectedInstruments = [];
                if (!empty($instrumentsRaw)) {
                    $selectedInstruments = is_array($instrumentsRaw) ? $instrumentsRaw : [$instrumentsRaw];
                }

                $songData = get_all_songs($mysqli, $perPage, $page, $search, $category, $selectedInstruments, $selectedTags, $owner, $finished, $sortBy, $sortDir);
            }
            $songs = $songData['songs'];
            $totalPages = $songData['totalPages'];

            if (!empty($songs)) {
                foreach ($songs as $song) {
                    echo "<div class='song-list-item' data-id='{$song['id']}'>";

                    // Title
                    echo "<div class='song-title'>" . htmlspecialchars($song['title']); 
                    if ($song['is_nsfw'] == true) {
                        echo " <span class='badge bg-danger align-middle'>nsfw</span>";
                    } 
                    echo "</div>";


                    echo "<div class='song-artist'>" .
                        (isset($song['artist']) && trim($song['artist']) !== ''
                            ? htmlspecialchars($song['artist'])
                            : '&nbsp;') .
                        "<span class='song-views text-muted small float-end'><i class='bi bi-eye'></i> " . (int)($song['views'] ?? 0) . "</span>" .
                        "</div><div class='mt-2'>";


                    // Middle row


                    // 3-stavová viditeľnosť: Bez kontroly / Private (Public bez badge v liste)
                    if (!empty($song['is_unchecked'])) {
                        echo "<span class='badge bg-warning text-dark'>Bez kontroly</span> ";
                    } elseif (!$song['is_public']) {
                        echo "<span class='badge bg-dark'>Private</span> ";
                    }
                    


                    $tagsRaw = $song['tags'] ?? '';
                    $tagsFiltered = filter_difficulty_tags($tagsRaw);
                    $tags =$tagsFiltered["tags"];
                    $difficulty = $tagsFiltered["difficulty"];

                    // Difficulty
                    if ((isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true)|| (isset($_SESSION["advanced_view"])&&$_SESSION["advanced_view"]==true)){
                        display_difficulty_span($difficulty,FALSE);
                    }


                    // Show tags
                    foreach (explode(',', $tags) as $tag) {
                        $trimmedTag = trim($tag);
                        if (!empty($trimmedTag)) {
                            echo "<span class='tag'>" . htmlspecialchars($trimmedTag) . "</span> ";
                        }
                    }
                    
                    // Bottom Row
                    // Show instruments
                    if ((isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true)|| (isset($_SESSION["advanced_view"])&&$_SESSION["advanced_view"]==true)){
                        $instruments = $song['instruments'] ?? '';
                        foreach (explode(',', $instruments) as $inst) {
                            $trimmedInst = trim($inst);
                            if (!empty($trimmedInst)) {
                                echo "<span class='badge instrument-badge'>" . htmlspecialchars($trimmedInst) . "</span> ";
                            }
                        }
                    }


                    echo "</div></div>";
                }

                echo "<div class='pagination mt-4'>";
                function page_button($i, $page) {
                    return $i == $page
                        ? "<span class='btn btn-sm btn-primary active'>$i</span> "
                        : "<a href='?" . http_build_query(array_merge($_GET, ['page' => $i])) . "' class='btn btn-sm btn-outline-primary'>$i</a> ";
                }

                if ($page > 1) {
                    echo "<a href='?" . http_build_query(array_merge($_GET, ['page' => $page - 1])) . "' class='btn btn-sm btn-outline-primary'>&lt;</a> ";
                } else {
                    echo "<span class='btn btn-sm btn-outline-secondary disabled'>&lt;</span> ";
                }

                $range = 2;
                $start = max(1, $page - $range);
                $end = min($totalPages, $page + $range);

                if ($start > 1) echo page_button(1, $page) . "<span class='btn btn-sm disabled'>...</span> ";
                for ($i = $start; $i <= $end; $i++) echo page_button($i, $page);
                if ($end < $totalPages) echo "<span class='btn btn-sm disabled'>...</span> " . page_button($totalPages, $page);

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

    <!-- Song Detail -->
    <div class="col-md-7 col-lg-8 p-0 song-detail-container d-none d-md-block">
        <?php
        if (isset($_GET['id'])) {
            echo file_get_contents("php/subpage/get_song.php?id=" . (int)$_GET['id'] . "&embedded=1");
        } else {
            echo "<div class='p-4 text-muted'>Select a song from the list to view details.</div>";
        }
        ?>
    </div>
</div>

<?php include("php/footer.php"); ?>
