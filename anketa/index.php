<?php
session_start();
// Universal include path (works no matter where the project root is placed)
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';

$website = "none";

include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>



<div class="container mt-5 text-center">
<?php 
if (!isset($_SESSION['user'])): ?>
    <p class="mb-3">Musíš byť prihlásený</p>
    <a href="<?= $projectUrl ?>/user_settings.php" class="btn btn-primary me-2">Log in</a>
    <a href="<?= $projectUrl ?>/register_user.php" class="btn btn-success">Register</a>
<?php else: 
    $userId = $_SESSION['id'];
    $group = get_user_group($mysqli, $userId);

    if ($group != 1): ?>
        <p class="text-danger">Anketa je prístupná iba pre členov 24. zboru Mustangy</p>
    <?php else: 
        // User is authorized
        $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
        
        // Fetch all latest layouts per category
        $stmt = $mysqli->prepare("
            SELECT t1.*
            FROM songbook_category_layouts t1
            INNER JOIN (
                SELECT category_id, MAX(id) AS max_id
                FROM songbook_category_layouts
                GROUP BY category_id
            ) t2 ON t1.id = t2.max_id
        ");
        $stmt->execute();
        $layouts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Extract category IDs
        $allCategoryIds = array_column($layouts, 'category_id');

        // Fetch category names from songbook id 3
        $stmtSB = $mysqli->prepare("SELECT categories FROM songbooks WHERE id = 3");
        $stmtSB->execute();
        $songbook = $stmtSB->get_result()->fetch_assoc();

        // Parse categories string into array: id => name
        $categoryNames = [];
        if ($songbook && !empty($songbook['categories'])) {
            $cats = explode(',', $songbook['categories']);
            foreach ($cats as $cat) {
                $parts = explode('-', $cat, 2);
                if (count($parts) === 2) {
                    $categoryNames[intval($parts[0])] = trim($parts[1]);
                }
            }
        }




        if (!$categoryId): ?>
            <!-- Introduction page -->
            <div class="card mx-auto my-3 p-3" style="max-width: 600px;">
                <h3>Vitajte v ankete!</h3>
                <p>Tento formulár ti umožní ohodnotiť piesne v jednotlivých kategóriách spevníku Mustangy. Hodnotenie bude prebiehať po kategóriach. Po ohodnotení každej kategórií sa výsledky uložia.</p>
                <p>Hodnotí sa ako často sa pesnička hrala a ako veľmi sa ti páči</p>
                <a href="?category_id=1" class="btn btn-primary">Začať</a>
            </div>
        <?php else: 
            // Load category layout
            $stmt = $mysqli->prepare("SELECT * FROM songbook_category_layouts WHERE category_id = ? LIMIT 1");
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $layout = $stmt->get_result()->fetch_assoc();

            if (!$layout):
                echo "<p>Kategória nenájdená.</p>";
            else:
                ?><h2><?= htmlspecialchars($categoryNames[$categoryId] ?? 'Kategória') ?></h2> <?php

                $orderedIds = explode(',', $layout['ordered_ids']);

                // Fetch songs
                $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
                $types = str_repeat('i', count($orderedIds));
                $stmtSongs = $mysqli->prepare("SELECT id, title, artist FROM songs WHERE id IN ($placeholders)");
                $stmtSongs->bind_param($types, ...$orderedIds);
                $stmtSongs->execute();
                $songs = $stmtSongs->get_result()->fetch_all(MYSQLI_ASSOC);

                // Sort songs in order
                $songsById = [];
                foreach ($songs as $song) {
                    $songsById[$song['id']] = $song;
                }
                $songsOrdered = [];
                foreach ($orderedIds as $id) {
                    if (isset($songsById[$id])) {
                        $songsOrdered[] = $songsById[$id];
                    }
                }

                // Render survey cards
                foreach ($songsOrdered as $song): ?>
                    <div class="card mx-auto my-3 p-3" style="max-width: 500px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                                <h4 class="mb-0"><?= htmlspecialchars($song['title']) ?></h4>
                                <?php if (!empty($song['artist'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($song['artist']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted fst-italic"></small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm btn-text" data-song-id="<?= $song['id'] ?>">Text</button>
                                <?php if (!empty($song['youtube_link'])): ?>
                                    <a href="<?= htmlspecialchars($song['youtube_link']) ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-youtube"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Container for song content (empty initially) -->
                        <div id="song-text-<?= $song['id'] ?>" class="mb-3 song-text d-none"
                            style="white-space: pre-wrap; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: 'Fira Mono', monospace; text-align: left;">
                        </div>

                        <!-- Feedback questions (dropdown + rating) -->
                        <div class="mb-3">
                            <label class="form-label"><strong>Ako často sa hral?</strong></label>
                            <select class="form-select">
                                <option value="">-- Vyber --</option>
                                <option value="0">Ani raz</option>
                                <option value="1">Jeden krát</option>
                                <option value="2">Viackrát</option>
                                <option value="3">Skoro stále</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Ako veľmi sa ti páči?</strong></label>
                            <div class="d-flex justify-content-between mt-2">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <div class="rating-circle" data-value="<?= $i ?>"><?= $i ?></div>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" class="rating-value" name="rating-<?= $song['id'] ?>" value="">
                            <div class="d-flex justify-content-between mt-1 text-muted" style="font-size: 0.85rem;">
                                <span>vôbec</span>
                                <span></span>
                                <span>neutrálne/<br>neviem</span>
                                <span></span>
                                <span>veľmi</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach;?>

<div class="d-grid gap-2 mt-3" style="max-width: 500px; margin: auto;">
    <button id="next-btn" class="btn btn-success">Ďalej</button>
</div>

<script>
document.querySelectorAll('.rating-circle').forEach(circle => {
    circle.addEventListener('click', () => {
        const parent = circle.parentElement;
        parent.querySelectorAll('.rating-circle').forEach(c => c.classList.remove('selected'));
        circle.classList.add('selected');
        parent.nextElementSibling.value = circle.dataset.value;
    });
});
</script>


<!-- Include song_render.js -->
<script src="<?= $projectUrl ?>/js/song_render.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.btn-text').forEach(btn => {
        btn.addEventListener('click', () => {
            const songId = btn.dataset.songId;
            const div = document.getElementById(`song-text-${songId}`);

            // Close all other song text divs
            document.querySelectorAll('.song-text').forEach(d => {
                if (d !== div) {
                    d.classList.add('d-none');
                    d.innerHTML = '';
                }
            });

            // Toggle this div
            if (div.classList.contains('d-none')) {
                // Fetch content via AJAX
                fetch(`<?= $projectUrl ?>/php/subpage/get_song_content.php?id=${songId}`)
                    .then(res => res.text())
                    .then(content => {
                        div.innerHTML = content;
                        div.classList.remove('d-none');
                        enhanceSongDiv(div); // enhanced rendering for this div only
                        adjustFontSizeToFitWidth(div); // shrink to fit width

                    })
                    .catch(err => {
                        div.innerHTML = "<p>Error loading song text.</p>";
                        div.classList.remove('d-none');
                        console.error(err);
                    });
            } else {
                div.classList.add('d-none');
                div.innerHTML = '';
            }
        });
    });
});
</script>



<script>
const currentCategoryId = <?= $categoryId ?? 1 ?>;
const categoryOrder = <?= json_encode($allCategoryIds ?? [1]) ?>; // array of all category IDs in order
const username = "<?= $_SESSION['user'] ?>"; // current logged in user

document.getElementById('next-btn').addEventListener('click', () => {
    const results = [];

    document.querySelectorAll('.card').forEach(card => {
        const songIdAttr = card.querySelector('.song-text');
        if (!songIdAttr) return;

        const songIdMatch = songIdAttr.id.match(/song-text-(\d+)/);
        if (!songIdMatch) return;

        const songId = songIdMatch[1];

        const freqSelect = card.querySelector('select');
        const freqValue = freqSelect ? freqSelect.value : '';

        const ratingInput = card.querySelector('.rating-value');
        const ratingValue = ratingInput ? ratingInput.value : '';

        results.push(`${songId}-${freqValue}&${ratingValue}`);
    });

    const resultString = results.join(',');

    // AJAX POST to save answers
    fetch("<?= $projectUrl ?>/php/subpage/save_category_answers.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            username: username,
            category_id: currentCategoryId,
            answers: resultString
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            console.log("Saved successfully:", resultString);

            // Determine next step
            const currentIndex = categoryOrder.indexOf(currentCategoryId);
            const isLastCategory = currentIndex === categoryOrder.length - 1;

            if (isLastCategory) {
                window.location.href = "<?= $projectUrl ?>/anketa/thanks.php";
            } else {
                const nextCategoryId = categoryOrder[currentIndex + 1];
                window.location.href = `?category_id=${nextCategoryId}`;
            }
        } else {
            alert("Chyba pri ukladaní výsledkov!");
            console.error(data.error);
        }
    })
    .catch(err => {
        alert("Chyba pri ukladaní výsledkov!");
        console.error(err);
    });
});

// Update button label if last category
const currentIndex = categoryOrder.indexOf(currentCategoryId);
document.getElementById('next-btn').textContent = currentIndex === categoryOrder.length - 1 ? "Ukončiť" : "Ďalej";
</script>



<style>
.card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Rating circles */
.rating-circle {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.2s;
    user-select: none;
    color: white;
}

.rating-circle[data-value="1"] { background-color: #dc3545; }
.rating-circle[data-value="2"] { background-color: #f28b82; }
.rating-circle[data-value="3"] { background-color: #6c757d; color: #fff; }
.rating-circle[data-value="4"] { background-color: #90ee90; color: #212529; }
.rating-circle[data-value="5"] { background-color: #28a745; }

.rating-circle.selected {
    box-shadow: 0 0 0 3px #00000033;
    transform: scale(1.2);
}
</style>
            <?php endif;
        endif;
    
    ?>



    <?php
    
    endif;
endif; ?>
</div>










<?php include $phpBase . 'footer.php'; ?>
