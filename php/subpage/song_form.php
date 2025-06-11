<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$embedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';
require_once __DIR__ . '/../db.php';
include_once __DIR__ . '/../categories.php';
include_once __DIR__ . '/../instruments.php';
include_once __DIR__ . '/../functions.php';
include_once __DIR__ . '/../data.php';

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
?>

<div class="container mt-4">
    <h2><?= $editing ? "Edit Song" : "Add New Song" ?></h2>

    <form id="songForm" method="post" >
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" name="title" id="title" class="form-control" maxlength="60" required value="<?= htmlspecialchars($song["title"]) ?>">
        </div>

        <div class="mb-3">
            <label for="artist" class="form-label">Artist</label>
            <input type="text" name="artist" id="artist" class="form-control" maxlength="60" required value="<?= htmlspecialchars($song["artist"]) ?>">
        </div>

        <div class="mb-3">
            <label for="capo" class="form-label">Capo</label>
            <select name="capo" id="capo" class="form-select" required>
                <?php for ($i = 0; $i <= 7; $i++): ?>
                    <option value="<?= $i ?>" <?= $song['capo'] == $i ? "selected" : "" ?>><?= $i === 0 ? "No capo" : $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="capo_alt" class="form-label">Capo alt</label>
            <select name="capo_alt" id="capo_alt" class="form-select">
                <option value="0">No alt</option>
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <option value="<?= $i ?>" <?= $song['capo_alt'] == $i ? "selected" : "" ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select name="category" id="category" class="form-select" required>
                <?php foreach ($categories as $catId => $label): ?>
                    <option value="<?= htmlspecialchars($catId) ?>" <?= $song["category"] == $catId ? "selected" : "" ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Instruments</label>
            <div>
                <?php foreach ($instruments as $instrument): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="inst_<?= $instrument['id'] ?>" name="instruments[]" value="<?= $instrument['name'] ?>" <?= in_array($instrument['name'], $song['instruments']) ? "checked" : "" ?>>
                        <label class="form-check-label" for="inst_<?= $instrument['id'] ?>"><?= $instrument['name'] ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="tags" class="form-label">Tags</label>
            <input type="text" name="tags" id="tags" class="form-control" maxlength="50" value="<?= htmlspecialchars($song["tags"]) ?>">
        </div>

        <div class="mb-3">
            <label for="content" class="form-label">Song Content</label>
            <textarea name="content" id="content" rows="6" class="form-control" required><?= htmlspecialchars($song["content"]) ?></textarea>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" <?= $song["is_public"] ? "checked" : "" ?>>
            <label class="form-check-label" for="is_public">Public (others can see this)</label>
        </div>

        <button type="submit" class="btn btn-primary"><?= $editing ? "Save Changes" : "Add Song" ?></button>
    </form>

    <div id="formFeedback" class="mt-3"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("songForm");
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            const formData = new FormData(form);

            fetch("php/save_song.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin", // <-- crucial line!
            })


            .then(res => res.json())
            .then(data => {
                const feedback = document.getElementById("formFeedback");
                if (data.success) {
                    feedback.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                    if (!document.querySelector('input[name="id"]')) {
                        form.reset();
                    }
                } else if (data.errors) {
                    feedback.innerHTML = data.errors.map(e => `<div class="alert alert-danger">${e}</div>`).join('');
                } else {
                    feedback.innerHTML = `<div class="alert alert-danger">${data.error || "Unknown error occurred."}</div>`;
                }
            })
            .catch(err => {
                document.getElementById("formFeedback").innerHTML = "<div class='alert alert-danger'>Submission failed.</div>";
            });
        });
    }
});
</script>

<?php if (!$embedded): ?>
    <?php include_once __DIR__ . '/../footer.php'; ?>
<?php endif; ?>
