<?php
session_start();
if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

include('php/db.php');
include('php/data.php');

$website = "songs";

include('php/functions.php');
include("php/header.php");
include('php/navigation.php');
include("php/categories.php");
include("php/instruments.php");

$userId = $_SESSION["user"];
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
];

// Determine mode:
$addingNew = isset($_GET['new']) && $_GET['new'] == 1;
$id        = isset($_GET['id'])   ? (int)$_GET['id'] : null;
$editMode  = isset($_GET['edit']) && $_GET['edit'] == 1;
$editing   = false;

// POST submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $id        = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $title     = trim($_POST["title"] ?? "");
    $artist    = trim($_POST["artist"] ?? "");
    $capo      = (int)($_POST["capo"] ?? 0);
    $capo_alt  = isset($_POST["capo_alt"]) && $_POST["capo_alt"] !== "" ? (int)$_POST["capo_alt"] : 0;
    $category  = $_POST["category"] ?? "";
    $tags      = trim($_POST["tags"] ?? "");
    $instruments = $_POST["instruments"] ?? [];
    $content   = trim($_POST["content"] ?? "");
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Validate
    if (!$title)   $errors[] = "Title is required.";
    if (!$artist)  $errors[] = "Artist is required.";
    if (!$content) $errors[] = "Content is required.";

    // Prepare $song for redisplay
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
        "owner" => null,  // add this line
    ];


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
                        "Edited song",
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
                    "Added song",
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
        $editing = true;
    } elseif ($id) {
        $stmt = $mysqli->prepare(
            "SELECT * FROM songs WHERE id = ? AND (is_public = 1 OR owner = ? OR ? = 1)"
        );
        $stmt->bind_param("iii", $id, $userId, $isAdmin);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $song = $res->fetch_assoc();
            $song['instruments'] = explode(',', $song['instruments']);
            $editing = $editMode;
        } else {
            echo '<div class="alert alert-danger">Song not found or no permission.</div>';
            include("php/footer.php");
            exit;
        }
        $stmt->close();
    } else {
        echo '<div class="alert alert-danger">No song specified.</div>';
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
                       class="form-control" maxlength="60" required
                       value="<?= htmlspecialchars($song["artist"]) ?>">
            </div>

            <div class="mb-3">
                <label for="capo" class="form-label">Capo</label>
                <select name="capo" id="capo" class="form-select">
                    <?php for ($i = 0; $i <= 7; $i++): ?>
                        <option value="<?= $i ?>" <?= $song['capo'] == $i ? "selected" : "" ?>>
                            <?= $i === 0 ? "No capo" : $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="capo_alt" class="form-label">Capo alt</label>
                <select name="capo_alt" id="capo_alt" class="form-select">
                    <option value="0">No alt</option>
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <option value="<?= $i ?>" <?= $song['capo_alt'] == $i ? "selected" : "" ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

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
                    <?php foreach ($instruments as $inst): ?>
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
                       value="<?= htmlspecialchars($song["tags"]) ?>">
            </div>

<div class="mb-3">
    <label for="content" class="form-label">Song Content *</label>
    <textarea name="content" id="content" rows="12"
              class="form-control auto-resize" required 
              style="min-height: 200px;"><?= htmlspecialchars($song["content"]) ?></textarea>
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
    document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('.auto-resize');
    
    textareas.forEach(textarea => {
        // Set initial height
        adjustHeight(textarea);
        
        // Add input event listener
        textarea.addEventListener('input', function() {
            adjustHeight(this);
        });
    });
    
    function adjustHeight(element) {
        element.style.height = 'auto';
        // Set height to scrollHeight, but ensure it's at least the min-height
        element.style.height = Math.max(element.scrollHeight, parseInt(element.style.minHeight || 200)) + 'px';
    }
});
    </script>
<?php endif; ?>

</div>

<?php include("php/footer.php"); ?>
