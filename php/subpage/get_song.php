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

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $mysqli->prepare("SELECT * FROM songs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();

    if ($song) {
        // Fetch the creator's user info by owner ID
        $stmtUser = $mysqli->prepare("SELECT id, name FROM users WHERE username = ?");
        $stmtUser->bind_param("s", $song['owner']);
        $stmtUser->execute();
        $userResult = $stmtUser->get_result();
        $user = $userResult->fetch_assoc();

        ob_start(); ?>
        <div class="song-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2><?= htmlspecialchars($song['title']) ?></h2>
                    <h4 class="text-muted"><?= htmlspecialchars($song['artist']) ?></h4>
                </div>
                <div>

                    <?php
                    $canEditDelete = (($_SESSION["admin_view"] ?? false) === true) || ($song['owner'] === ($_SESSION["user"] ?? ''));
                    ?>

                    <div class="d-flex gap-2">
                        <?php if ($isEmbedded): ?>
                            <a href="song.php?id=<?= (int)$id ?>" class="btn btn-primary mb-3">
                                Full view
                            </a>
                        <?php endif; ?>

                        <!-- Edit Button -->
                        <form method="GET" action="song.php" class="d-inline">
                            <input type="hidden" name="id" value="<?= (int)$id ?>">
                            <input type="hidden" name="edit" value="1">
                            <button type="submit"
                                    class="btn btn-secondary"
                                    <?= !$canEditDelete ? 'disabled style="opacity: 0.3; cursor: not-allowed;"' : '' ?>>
                                Edit
                            </button>
                        </form>

                        <!-- Delete Button -->
                        <form method="POST" action="php/subpage/delete_song.php"
                              onsubmit="return confirm('Are you sure you want to delete this song?');"
                              class="d-inline">
                            <input type="hidden" name="id" value="<?= $id ?>">

                            <input type="hidden" name="redirect_params" value="<?= htmlspecialchars(http_build_query(array_diff_key($_GET, ['id' => '']))) ?>">

                            <button type="submit" class="btn btn-danger" <?= !$canEditDelete ? 'disabled style="opacity: 0.3; cursor: not-allowed;"' : '' ?>>
                                Delete
                            </button>
                        </form>

                    </div>

                </div>
            </div>
            <div class="song-meta">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Category:</strong> <?= htmlspecialchars($categories[$song['category']] ?? 'Unknown') ?></div>
                        <div class="mb-2"><strong>Capo:</strong>
                            <span class="capo-display fw-bold"><?= htmlspecialchars($song['capo']) ?>th fret</span>
                            <?php if ($song["capo_alt"] != NULL) { ?>
                                <span class="capo-display fw-bold">(alternative): <?= htmlspecialchars($song['capo_alt']) ?>th fret</span>
                            <?php } ?>
                        </div>

                        <div class="mb-2"><strong>Created by:</strong>
                            <?php if ($user): ?>
                                <a href="user.php?id=<?= (int)$user['id'] ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars($user['name']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($song['owner']) ?>
                            <?php endif; ?>
                        </div>

                    </div>
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Instruments:</strong>
                            <?php foreach (explode(',', $song['instruments']) as $inst): ?>
                                <span class="badge instrument-badge"><?= htmlspecialchars(trim($inst)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-2"><strong>Tags:</strong>
                            <?php
                            if ($song["tags"] == "") {
                                echo "No tags";
                            } else {
                                foreach (explode(',', $song['tags']) as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                <?php endforeach;
                            } ?>
                        </div>
                        <div class="mb-2"><strong>Visibility:</strong>
                            <span class="badge <?= $song['is_public'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $song['is_public'] ? 'Public' : 'Private' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="song-content mt-3">
            <pre><?= htmlspecialchars($song['content']) ?></pre>
        </div>
        <?php
        echo ob_get_clean();
    } else {
        echo "<p>Song not found.</p>";
    }
} else {
    echo "<p>Invalid request.</p>";
}
