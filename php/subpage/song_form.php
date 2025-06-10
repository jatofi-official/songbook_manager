<?php
session_start();
require_once '../db.php';
include_once '../categories.php';
include_once '../instruments.php';
include_once '../functions.php';
include_once "../data.php";
$embedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';

if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){  
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

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
    "tags" => "",
    "content" => "",
    "is_public" => 1,
];

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $user = $_SESSION["user"];
    $isAdmin = $_SESSION["admin"] ?? false;

    $stmt = $mysqli->prepare("SELECT * FROM songs WHERE id = ? AND (owner = ? OR ? = 1)");
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
    $capo_alt = $_POST["capo_alt"] === "" ? 0 : (int)$_POST["capo_alt"];
    $category = $_POST["category"];
    $tags = trim($_POST["tags"] ?? "");
    $instruments = isset($_POST["instruments"]) ? $_POST["instruments"] : [];
    $content = osetri($_POST["content"]);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

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
            "tags" => $tags,
            "instruments" => implode(",", $instruments),
            "content" => $content,
            "is_public" => $is_public,
            "owner" => $_SESSION["user"],
        ];

        if ($editing) {
            $ok = edit_song($mysqli, $id, $data);
            if ($ok === true) {
                add_activity($mysqli, $data["owner"], "Edited song", "Edited '{$data["title"]}' by {$data["artist"]}", $data["is_public"]);
                echo '<div class="alert alert-success">Song updated successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">'.htmlspecialchars($ok).'</div>';
            }
        } else {
            $ok = add_song($mysqli, $data);
            if ($ok === true) {
                add_activity($mysqli, $data["owner"], "Added song", "Added '{$data["title"]}' by {$data["artist"]}", $data["is_public"]);
                echo '<div class="alert alert-success">Song added successfully.</div>';
            } else {
                echo '<div class="alert alert-danger">'.htmlspecialchars($ok).'</div>';
            }
        }
    } else {
        foreach ($errors as $error) {
            echo '<div class="alert alert-danger">'.htmlspecialchars($error).'</div>';
        }
    }
}
?>

<div class="container mt-4">
    <h2><?= $editing ? "Edit Song" : "Add New Song" ?></h2>

    <form method="post" action="?<?= $editing ? "id=" . (int)$id : "" ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" name="title" id="title" class="form-control" maxlength="60" required value="<?= htmlspecialchars($_POST["title"] ?? $song["title"]) ?>">
        </div>

        <div class="mb-3">
            <label for="artist" class="form-label">Artist</label>
            <input type="text" name="artist" id="artist" class="form-control" maxlength="60" required value="<?= htmlspecialchars($_POST["artist"] ?? $song["artist"]) ?>">
        </div>

        <div class="mb-3">
            <label for="capo" class="form-label">Capo</label>
            <select name="capo" id="capo" class="form-select" required>
                <?php for ($i = 0; $i <= 7; $i++): ?>
                    <option value="<?= $i ?>" <?= ((isset($_POST['capo']) ? $_POST['capo'] : $song['capo']) == $i) ? "selected" : "" ?>><?= $i === 0 ? "No capo" : $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="capo_alt" class="form-label">Capo alt</label>
            <select name="capo_alt" id="capo_alt" class="form-select">
                <option value="0">No alt</option>
                <?php for ($i = 1; $i <= 7; $i++): ?>
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
                    <option value="<?= htmlspecialchars($id) ?>" <?= ($catSelected == $id) ? "selected" : "" ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Instruments</label>
            <div>
                <?php
                $selectedInstruments = $_POST["instruments"] ?? $song["instruments"];
                if (!is_array($selectedInstruments)) $selectedInstruments = [];
                foreach ($instruments as $instrument): 
                    $checked = in_array($instrument['name'], $selectedInstruments, true) ? 'checked' : '';
                ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="inst_<?= htmlspecialchars($instrument['id']) ?>" name="instruments[]" value="<?= htmlspecialchars($instrument['name']) ?>" <?= $checked ?>>
                        <label class="form-check-label" for="inst_<?= htmlspecialchars($instrument['id']) ?>"><?= htmlspecialchars($instrument['name']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="tags" class="form-label">Tags</label>
            <input type="text" name="tags" id="tags" class="form-control" maxlength="50" value="<?= htmlspecialchars($_POST["tags"] ?? $song["tags"] ?? "") ?>">
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

<?php if (!$embedded): ?>
    <?php include_once '../footer.php'; ?>
<?php endif; ?>
