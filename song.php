<?php
session_start();
if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

include('php/db.php');
include('php/data.php');

$website = "songs";

include('php/functions.php');
include("php/header.php");
include('php/navigation.php');
include("php/categories.php");

$userId = $_SESSION["user"] ?? null;  // null if not logged in
$isAdmin = $_SESSION["admin_view"] ?? false;

$errors = [];
$successMessage = "";

$song = [
    "id" => null,
    "title" => "",
    "artist" => "",
    "capo" => 0,
    "capo_alt" => 0,
    "category" => "",
    "instruments" => [],
    "tags" => "",
    "content" => "",
    "is_public" => 1,
    "youtube_link" => "",
    "nsfw" => 0,
];

// Determine mode:
$addingNew = isset($_GET['new']) && $_GET['new'] == 1 && $userId !== null;
$id        = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editMode  = isset($_GET['edit']) && $_GET['edit'] == 1 && $userId !== null;
$editing   = false;


// POST submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userId === null) {
        echo '<div class="alert alert-danger">You must be logged in to submit changes.</div>';
        include("php/footer.php");
        exit;
    }    // Collect and sanitize input
    $id        = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $title     = trim($_POST["title"] ?? "");
    $artist    = trim($_POST["artist"] ?? "");
    $capo      = (int)($_POST["capo"] ?? 0);
    $capo_alt  = isset($_POST["capo_alt"]) && $_POST["capo_alt"] !== "" ? (int)$_POST["capo_alt"] : NULL;
    $category  = $_POST["category"] ?? "";
    $tags      = trim($_POST["tags"] ?? "");
    $instruments = $_POST["instruments"] ?? [];
    $content   = trim($_POST["content"] ?? "");
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $youtube_link = trim($_POST["youtube_link"] ?? "");
    $nsfw = isset($_POST["nsfw"]) ? 1 : 0;

    // Validate
    if (!$title)   $errors[] = "Title is required.";
    if (!$content) $errors[] = "Content is required.";

    if (empty($errors)) {
        $data = [
            "title"       => $title,
            "artist"      => $artist, 
            "capo"        => $capo,
            "capo_alt"    => $capo_alt,
            "category"    => $category,
            "tags"        => $tags,
            "instruments" => implode(",", $instruments),
            "content"     => $content,
            "is_public"   => $is_public,
            "owner"       => $userId,
            "youtube_link" => $youtube_link,
            "is_nsfw"         => $nsfw,

        ];

        if ($id) {
            // Editing existing song: verify ownership or admin
            $stmt = $mysqli->prepare(
                "SELECT id FROM songs WHERE id = ? AND (owner = ? OR ? = 1)"
            );
            $stmt->bind_param("isi", $id, $userId, $isAdmin);
            $stmt->execute();
            $res = $stmt->get_result();

            if (!$res || $res->num_rows === 0) {
                $errors[] = "Song not found or no permission to edit.";
            } else {
                $res->close();
                $ok = edit_song($mysqli, $id, $data);
                if ($ok === true) {
                    add_activity(
                        $mysqli,
                        $userId,
                        "edit",
                        "Edited '{$title}' by {$artist}",
                        $is_public
                    );
                    $successMessage = "Song updated successfully.";
                    // reload for display
                    $song = get_song($mysqli, $id);
                    $song['instruments'] = explode(',', $song['instruments']);
                } else {
                    $errors[] = $ok;
                }
            }
            $stmt->close();
        } else {
            // Add new song, now returns inserted ID
            $newId = add_song($mysqli, $data);
            if (is_int($newId)) {
                add_activity(
                    $mysqli,
                    $userId,
                    "add",
                    "Added '{$title}' by {$artist}",
                    $is_public
                );
                header("Location: song.php?id=$newId");
                exit;
            } else {
                $errors[] = $newId;  // error message
            }
        }
    }
} else {
    // GET: decide if adding or viewing/editing
    if ($addingNew) {
        // Only allow adding if logged in
        if ($userId === null) {
            echo '<div class="alert alert-danger">You must be logged in to add a new song.</div>';
            include("php/footer.php");
            exit;
        }
        $editing = true;
    } elseif ($id) {
        $stmt = $mysqli->prepare(
            "SELECT * FROM songs WHERE id = ? AND (is_public = 1 OR owner = ? OR ? = 1)"
        );
        $ownerId = $userId ?? 0;
        $isAdminInt = $isAdmin ? 1 : 0;
        $stmt->bind_param("iii", $id, $ownerId, $isAdminInt);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $song = $res->fetch_assoc();
            $song['instruments'] = explode(',', $song['instruments']);
            // Only allow editing if logged in AND edit mode requested
            $editing = ($editMode && $userId !== null);
        } else {
            echo '<div class="alert alert-danger">Song not found or no permission.</div>';
            include("php/footer.php");
            exit;
        }
        $stmt->close();
    } else {
        if ($userId === null){
            echo '<div class="alert alert-danger">You must be logged in to submit changes.</div>';
        }
        else{
            echo '<div class="alert alert-danger">No song specified.</div>';
        
        }
        include("php/footer.php");
        exit;
    }

}

?>

<div class="container mt-4">

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($editing): ?>
        <h2><?= $song['id'] ? "Edit Song" : "Add New Song" ?></h2>
        <form method="post" action="song.php<?= $song['id'] ? '?id=' . $song['id'] . '&edit=1' : '?new=1' ?>">
            <?php if ($song['id']): ?>
                <input type="hidden" name="id" value="<?= (int)$song['id'] ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="title" class="form-label">Title</label>
                <input type="text" name="title" id="title"
                       class="form-control" maxlength="60" required
                       value="<?= htmlspecialchars($song["title"]) ?>">
            </div>

            <div class="mb-3">
                <label for="artist" class="form-label">Artist</label>
                <input type="text" name="artist" id="artist"
                       class="form-control" maxlength="60"
                       value="<?= htmlspecialchars($song["artist"]) ?>">
            </div>

            <style>
            .capo-control {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .capo-input-group {
                display: flex;
            }
            .capo-input-group .btn {
                width: 40px;
                padding: 0;
            }
            .capo-input-group .form-control {
                flex-grow: 0;
                width: 60px;
                text-align: center;
            }
            .capo-reset {
                margin-left: 10px;
                white-space: nowrap;
            }
            </style>

            <div class="mb-3">
                <!-- <label class="form-label">Capo Settings</label> -->
                <div class="capo-control">
                    <!-- Main Capo -->
                    <span> Capo:</span>
                    <div class="capo-input-group">
                        <button type="button" class="btn btn-outline-secondary capo-decrement" data-target="capo">-</button>
                        <input type="number" name="capo" id="capo" class="form-control" 
                            min="0" max="7" value="<?= $song['capo'] ?? 0 ?>" readonly>
                        <button type="button" class="btn btn-outline-secondary capo-increment" data-target="capo">+</button>
                    </div>
                    
                    <span> Alternative:</span>
                    
                    <!-- Alternative Capo -->
                    <div class="capo-input-group">
                        <button type="button" class="btn btn-outline-secondary capo-decrement" data-target="capo_alt">-</button>
                        <input type="number" name="capo_alt" id="capo_alt" class="form-control" 
                            min="0" max="7" value="<?= $song['capo_alt'] ?? '' ?>" readonly>
                        <button type="button" class="btn btn-outline-secondary capo-increment" data-target="capo_alt">+</button>
                    </div>
                    
                    <!-- Reset Button -->
                    <button type="button" class="btn btn-outline-danger capo-reset">No Alt</button>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle increment buttons
                document.querySelectorAll('.capo-increment').forEach(button => {
                    button.addEventListener('click', function() {
                        const targetId = this.getAttribute('data-target');
                        const input = document.getElementById(targetId);
                        let value = parseInt(input.value) || 0;
                        if (value < 7) {
                            input.value = value + 1;
                        }
                    });
                });

                // Handle decrement buttons
                document.querySelectorAll('.capo-decrement').forEach(button => {
                    button.addEventListener('click', function() {
                        const targetId = this.getAttribute('data-target');
                        const input = document.getElementById(targetId);
                        let value = parseInt(input.value) || 0;
                        if (value > (targetId === 'capo' ? 0 : 1)) {
                            input.value = value - 1;
                        }
                    });
                });

                // Handle reset button
                document.querySelector('.capo-reset').addEventListener('click', function() {
                    document.getElementById('capo_alt').value = '';
                });
            });
            </script>

            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <select name="category" id="category" class="form-select" required>
                    <?php foreach ($categories as $catId => $label): ?>
                        <option value="<?= htmlspecialchars($catId) ?>"
                            <?= $song["category"] == $catId ? "selected" : "" ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Instruments</label>
                <div>
                    <?php foreach (get_all_instruments($mysqli) as $inst): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox"
                                   id="inst_<?= $inst['id'] ?>"
                                   name="instruments[]"
                                   value="<?= htmlspecialchars($inst['name']) ?>"
                                   <?= in_array($inst['name'], $song['instruments']) ? "checked" : "" ?> <?= (strcasecmp($inst['name'], 'guitar') === 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="inst_<?= $inst['id'] ?>">
                                <?= htmlspecialchars($inst['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="tags" class="form-label">Tags</label>
                <input type="text" name="tags" id="tags"
                       class="form-control" maxlength="50"
                        value="<?= isset($song["tags"]) ? htmlspecialchars($song["tags"]) : '' ?>">
            </div>

            <div class="mb-3">
                <label for="youtube_link" class="form-label">YouTube Link</label>
                <input type="url" name="youtube_link" id="youtube_link"
                    class="form-control"
                    value="<?= isset($song["youtube_link"]) ? htmlspecialchars($song["youtube_link"]) : '' ?>">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="nsfw" name="nsfw" value="1"
                    <?= !empty($song["is_nsfw"]) ? "checked" : "" ?>>
                <label class="form-check-label" for="nsfw">NSFW (explicit content)</label>
            </div>
            
            <br>

            <div class="mb-3 textarea-container">
                <h2 class="h5 mb-2"> <!-- Uses h2 semantic but h5 size -->
                    <label for="content" class="form-label">Song Content *</label>
                </h2>
                <textarea name="content" id="content" rows="12"
                        class="form-control" required><?= htmlspecialchars($song["content"]) ?></textarea>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1"
                       <?= $song["is_public"] ? "checked" : "" ?>>
                <label class="form-check-label" for="is_public">
                    Public (others can see this)
                </label>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $song['id'] ? "Save Changes" : "Add Song" ?>
            </button>
        </form>

<?php else: ?>

    <a href="songs.php" class="btn btn-outline-primary mb-3">
        ← Back to All Songs
    </a>
    <div id="song-display">
        <p>Loading song...</p>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const id = <?= (int)$song['id'] ?>;
        fetch("php/subpage/get_song.php?id=" + id)
            .then(response => response.text())
            .then(html => {
                document.getElementById("song-display").innerHTML = html;
            })
            .catch(err => {
                document.getElementById("song-display").innerHTML = "<p>Error loading song.</p>";
                console.error(err);
            });
    });
    </script>
<?php endif; ?>


</div>

<?php include("php/footer.php"); ?>
