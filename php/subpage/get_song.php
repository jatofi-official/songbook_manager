<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
include_once('../db.php');
include_once('../categories.php');

$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == 1;
$isAdvancedView = (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true)
    || (isset($_SESSION["advanced_view"]) && $_SESSION["advanced_view"] == true);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $currentUsername = $_SESSION['user'] ?? '';
    $isSiteAdmin = !empty($_SESSION['admin']) ? 1 : 0;
    $stmt = $mysqli->prepare("SELECT * FROM songs WHERE id = ? AND (is_public = 1 OR owner = ? OR ? = 1)");
    $stmt->bind_param("isi", $id, $currentUsername, $isSiteAdmin);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();

    if ($song) {
        $visibleSongbooks = get_visible_songbooks_for_song($mysqli, $id);
        // Fetch the creator's user info by owner ID
        $stmtUser = $mysqli->prepare("SELECT id, name FROM users WHERE username = ?");
        $stmtUser->bind_param("s", $song['owner']);
        $stmtUser->execute();
        $userResult = $stmtUser->get_result();
        $user = $userResult->fetch_assoc();


        // Increment song view
        increment_song_view($mysqli,$id);

        ob_start(); ?>
        <div class="song-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2><?= htmlspecialchars($song['title']) ?></h2>
                    <h4 class="text-muted"><?= htmlspecialchars($song['artist'] ?? '') ?></h4>
                </div>
                <div>

                    <?php
                    $canEditDelete = (($_SESSION["admin_view"] ?? false) == true) || ($song['owner'] === ($_SESSION["user"] ?? ''));
                    ?>

                    <div class="d-flex gap-2 align-items-center">
                        <?php if (!empty($song['youtube_link'])): ?>
                            <a href="<?= htmlspecialchars(
                                preg_match('/^(http|https):\/\//', $song['youtube_link']) 
                                    ? $song['youtube_link'] 
                                    : 'https://www.youtube.com/watch?v=' . $song['youtube_link']
                            ) ?>" 
                            target="_blank" class="btn btn-outline-danger" title="Watch on YouTube">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                                    class="bi bi-youtube" viewBox="0 0 16 16">
                                    <path d="M8.051 1.999h-.002C4.42 1.996 1.582 2.167 1.151 2.226 0.51 2.312 0 2.847 0 3.49v9.021c0 .643.51 1.178 1.151 1.264.431.059 3.27.229 6.899.227h.002c3.63 0 6.468-.168 6.898-.227.641-.086 1.15-.62 1.15-1.263V3.49c0-.643-.51-1.178-1.15-1.264-.43-.059-3.268-.23-6.898-.227zM6.545 10.568V5.432l4.273 2.568-4.273 2.568z"/>
                                </svg>
                            </a>
                        <?php endif; ?>

                        <button type="button" class="btn btn-primary" data-export-song-pdf data-song-id="<?= (int)$id ?>" title="Download song PDF">
                            <i class="bi bi-printer"></i>
                        </button>

                        <?php if ($isEmbedded): ?>
                            <a href="song.php?id=<?= (int)$id ?>" class="btn btn-primary">
                                Fullscreen
                            </a>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION["user"]) && $canEditDelete){ ?>
                            <!-- Edit Button -->
                            <form method="GET" action="song.php" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int)$id ?>">
                                <input type="hidden" name="edit" value="1">
                                <button type="submit" class="btn btn-secondary">
                                    Edit
                                </button>
                            </form>

                            <!-- Delete Button -->
                            <form method="POST" action="php/subpage/delete_song.php"
                                onsubmit="return confirm('Are you sure you want to delete this song?');"
                                class="d-inline">
                                <input type="hidden" name="id" value="<?= $id ?>">

                                <input type="hidden" name="redirect_params" value="<?= htmlspecialchars(http_build_query(array_diff_key($_GET, ['id' => '']))) ?>">

                                <button type="submit" class="btn btn-danger">
                                    Delete
                                </button>
                            </form>
                        <?php } ?>

                        <?php if (($_SESSION["admin_view"] ?? false) === true): ?>
                            <!-- History Button (admin only) -->
                            <button type="button" class="btn btn-outline-secondary" data-song-history-id="<?= (int)$id ?>">
                                History
                            </button>
                        <?php endif; ?>

                    </div>

                </div>
            </div>
            <div class="song-meta">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Category:</strong> <?= htmlspecialchars($categories[$song['category']] ?? 'Unknown') ?></div>

                        <div class="mb-2"><strong>Created by:</strong>
                            <?php if ($user): ?>
                                <a href="user.php?id=<?= (int)$user['id'] ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars($user['name']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($song['owner']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2"><strong>Added:</strong> <?= htmlspecialchars(time_elapsed_string($song['create_date'])) ?></div>
                        <?php if (!empty($song['modified_date']) && $isAdvancedView): ?>
                            <div class="mb-2"><strong>Last modified:</strong> <?= htmlspecialchars(time_elapsed_string($song['modified_date'])) ?></div>
                        <?php endif; ?>
                        <div class="mb-2"><strong>Views:</strong> <?= (int)($song['views'] ?? 0) ?></div>
                        <div class="mb-2 d-flex align-items-center">
                            <?php
                            $capoValue = $song['capo'] ?? 0;
                            $capoClass = $capoValue == 0 ? 'capo-circle capo-circle-muted' : 'capo-circle';
                            ?>
                            <strong class="me-2">Capo:</strong>
                            <div class="<?= $capoClass ?>"><?= htmlspecialchars($capoValue) ?></div>
                            <?php if ($song["capo_alt"] != NULL) { ?>
                                <div class="capo-circle-alt"><?= htmlspecialchars($song['capo_alt']) ?></div>
                            <?php } ?>
                        </div>


                    </div>
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Instruments:</strong>
                            <?php foreach (explode(',', $song['instruments']) as $inst): ?>
                                <span class="badge instrument-badge"><?= htmlspecialchars(trim($inst)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php 
                        $output = filter_difficulty_tags($song["tags"]);
                        
                        $difficultyRaw = $output["difficulty"] ?? null;
                        ?>
                        <div class="mb-2"><strong>Difficulty:</strong>
                            <?php
                            display_difficulty_span($difficultyRaw);
                            ?>
                        </div>

                        <div class="mb-2"><strong>Tags:</strong>
                            <?php
                            if ($output["tags"] == "") {
                                echo "No tags";
                            } else {
                                foreach (explode(',', $output["tags"]) as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                <?php endforeach;
                            } ?>
                        </div>
                        <?php
                        // Translator/Chord source only matter for spiritual/parody
                        // songs (typically translations or adaptations of an
                        // original) - hidden for every other category.
                        $isSpiritualOrParody = in_array($categories[$song['category']] ?? '', ['Duchovné', 'Paródie'], true);
                        ?>
                        <?php if ($isAdvancedView): ?>
                        <div class="mb-2"><strong>Visibility:</strong>
                            <span class="badge <?= $song['is_public'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $song['is_public'] ? 'Public' : 'Private' ?>
                            </span>
                        </div>
                        <?php if ($isSpiritualOrParody): ?>
                        <div class="mb-2"><strong>Chord source:</strong>
                            <?= htmlspecialchars(ucfirst($song['chord_source'] ?? 'unknown')) ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($song['original_artist'])): ?>
                            <div class="mb-2"><strong>Original artist:</strong> <?= htmlspecialchars($song['original_artist']) ?></div>
                        <?php endif; ?>
                        <?php if ($isSpiritualOrParody && !empty($song['translator'])): ?>
                            <div class="mb-2"><strong>Translator:</strong> <?= htmlspecialchars($song['translator']) ?></div>
                        <?php endif; ?>
                        <?php if ($isAdvancedView): ?>
                        <div class="mb-2"><strong>Songbooks:</strong>
                            <?php if (!$visibleSongbooks): ?>
                                None visible
                            <?php else: ?>
                                <?php foreach ($visibleSongbooks as $book): ?>
                                    <a href="songbook.php?id=<?= $book['id'] ?>" class="badge bg-secondary text-decoration-none">
                                        <?= htmlspecialchars($book['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 transpose-controls">
            <strong>Transposition:</strong>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-transpose="down">-1</button>
            <span data-transpose-label>0</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-transpose="up">+1</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-transpose="reset">Reset</button>
        </div>

        <?php if (!$isEmbedded): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 auto-scroll-controls">
                <button type="button" id="auto-scroll-toggle" class="btn btn-outline-primary btn-sm" aria-pressed="false">
                    Start auto-scroll
                </button>
                <label for="auto-scroll-speed" class="mb-0">Speed</label>
                <input type="range" id="auto-scroll-speed" class="form-range" min="1" max="5" step="1" value="2"
                       aria-label="Auto-scroll speed" style="width: 9rem;">
            </div>
        <?php endif; ?>

        <?php
            $songFont = $_SESSION['song_font'] ?? 'monospace';
            if (!in_array($songFont, ['monospace', 'calibri', 'corbel', 'candara', 'arial', 'verdana', 'georgia', 'times'], true)) {
                $songFont = 'monospace';
            }
        ?>
        <div id="song-content-js" class="song-content mt-3 song-font-<?= $songFont ?>"
             data-song-font="<?= $songFont ?>" style="white-space: pre-wrap;"><?=
            htmlspecialchars($song['content'])
        ?></div>

        <?php
        echo ob_get_clean();
    } else {
        echo "<p>Song not found.</p>";
    }
} else {
    echo "<p>Invalid request.</p>";
}
