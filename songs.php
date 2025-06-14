<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('php/db.php');
include('php/data.php');
include('php/functions.php');
$website = "songs";

$allTags = get_all_tags($mysqli);
$categories = $categories ?? [];

?>
<?php include("php/header.php"); ?>
<?php include('php/navigation.php'); ?>
<?php include("php/categories.php"); ?>

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
                    .then(html => detailContainer.innerHTML = html)
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
                            $index = 1;
                            foreach($categories as $category){
                                $selected = (isset($_GET['category']) && $_GET['category'] == $index) ? 'selected' : '';
                                echo "<option value='$index' $selected>$category</option>";
                                $index++;
                            }
                        ?>
                    </select>

                    <!-- Reset button -->
                    <a href="songs.php" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-x-circle me-1"></i> Reset
                    </a>

                    <!-- Advanced toggle -->
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="false" aria-controls="advancedFilters">
                        <i class="bi bi-sliders me-1"></i> Advanced
                    </button>
                </div>



                <div class="collapse<?= (isset($_GET['advanced']) && $_GET['advanced'] == '1') ? ' show' : '' ?>" id="advancedFilters">
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
                                $selectedTags = $_GET['tags'] ?? [];
                                if (!is_array($selectedTags)) $selectedTags = [];

                                foreach ($allTags as $tag) {
                                    $isSelected = in_array($tag, $selectedTags);
                                    $checked = $isSelected ? 'checked' : '';
                                    echo "<div class='form-check form-check-inline'>";
                                    echo "<input class='form-check-input' type='checkbox' name='tags[]' value='" . htmlspecialchars($tag) . "' id='tag_$tag' $checked>";
                                    echo "<label class='form-check-label' for='tag_$tag'>$tag</label>";
                                    echo "</div>";
                                }
                                ?>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="owner" class="form-label mb-1">User</label>
                            <input type="text" name="owner" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['owner'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <input type="hidden" name="page" value="1">
            </form>
        </div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("songFilterForm");
    const advToggle = document.querySelector("[data-bs-target='#advancedFilters']");
    const advBox = document.getElementById("advancedFilters");

    let openedManually = false;

    advToggle.addEventListener("click", () => {
        openedManually = !advBox.classList.contains("show");
    });

    function ensureAdvancedInput() {
        if ((advBox.classList.contains("show") || openedManually) && !form.querySelector("input[name='advanced']")) {
            const hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = "advanced";
            hidden.value = "1";
            form.appendChild(hidden);
        }
    }

    form.addEventListener("submit", () => {
        ensureAdvancedInput();
    });

    form.querySelectorAll("select").forEach(select => {
        select.addEventListener("change", () => {
            form.querySelector("input[name='page']").value = 1;
            ensureAdvancedInput();
            form.submit();
        });
    });

    form.querySelectorAll("input[name='tags[]']").forEach(tag => {
        tag.addEventListener("change", () => {
            form.querySelector("input[name='page']").value = 1;
            ensureAdvancedInput();
            form.submit();
        });
    });
});
</script>


        <div class="song-list">
            <?php
            $perPage = PER_PAGE;
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            $instrumentsRaw = $_GET['instruments'] ?? '';
            $selectedTags = $_GET['tags'] ?? [];
            $owner = isset($_GET['owner']) ? trim($_GET['owner']) : '';

            $selectedInstruments = [];
            if (!empty($instrumentsRaw)) {
                $selectedInstruments = is_array($instrumentsRaw) ? $instrumentsRaw : [$instrumentsRaw];
            }

            $songData = get_all_songs($mysqli, $perPage, $page, $search, $category, $selectedInstruments, $selectedTags, $owner);
            $songs = $songData['songs'];
            $totalPages = $songData['totalPages'];

            if (!empty($songs)) {
                foreach ($songs as $song) {
                    echo "<div class='song-list-item' data-id='{$song['id']}'>";
                    echo "<div class='song-title'>" . htmlspecialchars($song['title']) . "</div>";
                    echo "<div class='song-artist'>" . htmlspecialchars($song['artist']) . "</div><div class='mt-2'>";

                    if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
                        echo "<span class='badge " . ($song['is_public'] ? 'bg-success' : 'bg-secondary') . "'>" . ($song['is_public'] ? "Public" : "Private") . "</span> ";
                    }

                    $tags = $song['tags'] ?? '';
                    foreach (explode(',', $tags) as $tag) {
                        $trimmedTag = trim($tag);
                        if (!empty($trimmedTag)) {
                            echo "<span class='tag'>" . htmlspecialchars($trimmedTag) . "</span> ";
                        }
                    }

                    $instruments = $song['instruments'] ?? '';
                    foreach (explode(',', $instruments) as $inst) {
                        $trimmedInst = trim($inst);
                        if (!empty($trimmedInst)) {
                            echo "<span class='badge instrument-badge'>" . htmlspecialchars($trimmedInst) . "</span> ";
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
