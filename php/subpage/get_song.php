<?php
session_start();
if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){  
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
include_once('../db.php');
include_once('../categories.php');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $mysqli->prepare("SELECT * FROM songs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();

    if ($song) {
        

    ob_start(); ?>
        <div class="song-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2><?= htmlspecialchars($song['title']) ?></h2>
                    <h4 class="text-muted"><?= htmlspecialchars($song['artist']) ?></h4>
                </div>
                <div>
                    <!-- Current solution, is not optimal yet -->
                <?php if(isset($_SESSION["admin"])) ?>
                <form method="GET" action="edit_song_page.php">

                    <input type="hidden" name="id" value="<?= $id ?>">

                    <button type="submit" class="btn btn-outline-secondary me-2">Edit</button>

                </form>
                </div>
            </div>
            <div class="song-meta">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2"><strong>Category:</strong> <?= htmlspecialchars($categories[$song['category'] ] ?? 'Unknown') ?></div>
                        <div class="mb-2"><strong>Capo:</strong>
                            <span class="capo-display fw-bold">Original: <?= htmlspecialchars($song['capo']) ?>th fret</span>
                            <span class="capo-display fw-bold">Alt: <?= htmlspecialchars($song['capo_alt']) ?>th fret</span>
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
                            if($song["tags"]==""){
                                echo "No tags";
                            } 
                            else{
                                foreach (explode(',', $song['tags']) as $tag): ?>
                                    <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                            <?php endforeach; }?>
                            
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
