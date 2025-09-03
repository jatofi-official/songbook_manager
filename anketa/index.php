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

        if (!$categoryId): ?>
            <!-- Introduction page -->
            <div class="card mx-auto my-3 p-3" style="max-width: 600px;">
                <h3>Vitajte v ankete!</h3>
                <p>Tento formulár vám umožní ohodnotiť piesne v jednotlivých kategóriách vášho songbooku.</p>
                <p>Po odoslaní hodnotenia jednej kategórie budete presmerovaní na ďalšiu.</p>
                <a href="?category_id=1" class="btn btn-primary">Začať ankety</a>
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
                <?php endforeach;
            endif;
        endif;
    endif;
endif; ?>
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

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.btn-text').forEach(btn => {
        btn.addEventListener('click', () => {
            const songId = btn.dataset.songId;
            const div = document.getElementById(`song-text-${songId}`);

            // Close other opened song texts
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
                        enhanceSongView(); // apply bold chords
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



<!-- Include song_render.js -->
<script src="<?= $projectUrl ?>/js/song_render.js"></script>


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











<?php include $phpBase . 'footer.php'; ?>
