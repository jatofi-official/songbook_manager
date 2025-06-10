<?php
session_start();
require_once '../db.php';
include_once '../categories.php';   // sets $categories array
include_once '../instruments.php';  // sets $instruments array
include_once "../functions.php";

// User must be logged in
if (!isset($_SESSION["user"])) {
    echo "You need to be logged in!";
    exit;
}

$editing = false;
$song = [
    "title" => "",
    "artist" => "",
    "capo" => 0,
    "capo_alt" => "",
    "category" => "",
    "instruments" => [],
    "content" => "",
    "is_public" => 1,
];

// Check if editing an existing song
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $mysqli->prepare("SELECT * FROM songs WHERE id = ? AND (owner = ? OR ? = 1)");
    $user = $_SESSION["user"];
    $isAdmin = $_SESSION["admin"] ?? false;
    $stmt->bind_param("isi", $id, $user, $isAdmin);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $song = $result->fetch_assoc();
        $song['instruments'] = explode(',', $song['instruments']);
        $editing = true;
    } else {
        echo '<div class="alert alert-danger">Song not found or you do not have permission to edit it.</div>';
        exit;
    }
    $stmt->close();
}

$submitted = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $submitted = true;

    // Sanitize inputs
    $title = osetri($_POST["title"]);
    $artist = osetri($_POST["artist"]);
    $capo = (int)$_POST["capo"];
    $capo_alt = $_POST["capo_alt"] === "" ? null : $_POST["capo_alt"];
    $category = $_POST["category"];
    $instruments = isset($_POST["instruments"]) ? $_POST["instruments"] : [];
    $content = osetri($_POST["content"]);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Validate required fields (example)
    if (!$title) $errors[] = "Title is required";
    if (!$artist) $errors[] = "Artist is required";
    if (!$category) $errors[] = "Category is required";
    if (!$content) $errors[] = "Content is required";

    if (empty($errors)) {
        $data = [
            "title" => $title,
            "artist" => $artist,
            "capo" => $capo,
            "capo_alt" => $capo_alt,
            "category" => $category,
            "instruments" => implode(",", $instruments),
            "content" => $content,
            "is_public" => $is_public,
            "owner" => $_SESSION["user"],
        ];

        if ($editing) {
            // Update existing song
            $stmt = $mysqli->prepare("UPDATE songs SET title = ?, artist = ?, capo = ?, capo_alt = ?, category = ?, instruments = ?, content = ?, is_public = ? WHERE id = ? AND (owner = ? OR ? = 1)");
            $stmt->bind_param(
                "ssisissisis",
                $data["title"], $data["artist"], $data["capo"], $data["capo_alt"],
                $data["category"], $data["instruments"], $data["content"],
                $data["is_public"], $id, $data["owner"], $isAdmin
            );
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                add_activity($mysqli, $data["owner"], "Edited song", "Edited '".$data["title"]."' by ".$data["artist"], $data["is_public"]);
                echo '<div class="alert alert-success">Song updated successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Failed to update the song.</div>';
            }
        } else {
            // Insert new song
            $ok = add_song($mysqli, $data);
            if ($ok) {
                add_activity($mysqli, $data["owner"], "Added song", "Added '".$data["title"]."' by ".$data["artist"], $data["is_public"]);
                echo '<div class="alert alert-success">Song added successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">Failed to add the song.</div>';
            }
        }

        // Reload page to clear POST data or reset $editing to false if needed
        // header("Location: make_song.php" . ($editing ? "?id=$id" : ""));
        // exit;
    } else {
        foreach ($errors as $error) {
            echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>';
        }
    }
}

// Now display the form with values (old or submitted on error)
?>

<div class="container mt-4">
    <h2><?= $editing ? "Edit Song" : "Add New Song" ?></h2>

    <form method="post" action="">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input
                type="text" name="title" id="title" class="form-control" maxlength="60" required
                value="<?= htmlspecialchars($_POST["title"] ?? $song["title"]) ?>"
            >
        </div>

        <div class="mb-3">
            <label for="artist" class="form-label">Artist</label>
            <input
                type="text" name="artist" id="artist" class="form-control" maxlength="60" required
                value="<?= htmlspecialchars($_POST["artist"] ?? $song["artist"]) ?>"
            >
        </div>

        <div class="mb-3">
            <label for="capo" class="form-label">Capo</label>
            <select name="capo" id="capo" class="form-select" required>
                <option value="0" <?= ((isset($_POST['capo']) ? $_POST['capo'] : $song['capo']) == 0) ? "selected" : "" ?>>No capo</option>
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <option value="<?= $i ?>" <?= ((isset($_POST['capo']) ? $_POST['capo'] : $song['capo']) == $i) ? "selected" : "" ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="capo_alt" class="form-label">Capo alt</label>
            <select name="capo_alt" id="capo_alt" class="form-select">
                <option value="" <?= ((isset($_POST['capo_alt']) ? $_POST['capo_alt'] : $song['capo_alt']) === "" ? "selected" : "") ?>>No alt</option>
                <?php for ($i = 0; $i <= 7; $i++): ?>
                    <option value="<?= $i ?>" <?= ((isset($_POST['capo_alt']) ? $_POST['capo_alt'] : $song['capo_alt']) == $i) ? "selected" : "" ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select name="category" id="category" class="form-select" required>
                <?php 
                $catSelected = $_POST["category"] ?? $song["category"];
                foreach ($categories as $id => $label): ?>
                    <option value="<?= htmlspecialchars($id) ?>" <?= ($catSelected == $id) ? "selected" : "" ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Instruments</label>
            <div>
                <?php
                // Determine selected instruments from POST or existing data
                $selectedInstruments = $_POST["instruments"] ?? $song["instruments"];
                if (!is_array($selectedInstruments)) $selectedInstruments = [];

                foreach ($instruments as $instrument): 
                    $checked = in_array($instrument['name'], $selectedInstruments, true) ? 'checked' : '';
                ?>
                    <div class="form-check form-check-inline">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="inst_<?= htmlspecialchars($instrument['id']) ?>"
                            name="instruments[]"
                            value="<?= htmlspecialchars($instrument['name']) ?>"
                            <?= $checked ?>
                        >
                        <label class="form-check-label" for="inst_<?= htmlspecialchars($instrument['id']) ?>">
                            <?= htmlspecialchars($instrument['name']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="content" class="form-label">Song Content</label>
            <textarea name="content" id="content" rows="6" class="form-control" required><?= htmlspecialchars($_POST["content"] ?? $song["content"]) ?></textarea>
        </div>

        <div class="mb-3 form-check">
            <?php 
            $isPublicChecked = isset($_POST['is_public']) ? (bool)$_POST['is_public'] : (bool)$song['is_public'];
            ?>
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" <?= $isPublicChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_public">Public (others can see this)</label>
        </div>

        <button type="submit" name="submit" class="btn btn-primary">
            <?= $editing ? "Save Changes" : "Add Song" ?>
        </button>
    </form>
</div>

<?php include_once '../footer.php'; ?>
