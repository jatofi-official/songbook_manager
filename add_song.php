<?php
session_start();
include 'php/db.php';
include 'php/categories.php';   // sets $categories array
include 'php/instruments.php';  // sets $instruments array

// User must be logged in
if (!isset($_SESSION["user"])) {
    echo "You need to be logged in!";
    exit;
}

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["submit"])) {
    $submitted = true;
}

include 'php/header.php';
include 'php/navigation.php';
?>

<div class="container mt-4">
    <h2>Add New Song</h2>

    <?php if ($submitted): ?>
        <div class="alert alert-success">Submitted!</div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" name="title" id="title" class="form-control" maxlength="30" required>
        </div>

        <div class="mb-3">
            <label for="author" class="form-label">Author</label>
            <input type="text" name="author" id="author" class="form-control" maxlength="30" required>
        </div>

        <div class="mb-3">
            <label for="capo" class="form-label">Capo</label>
            <select name="capo" id="capo" class="form-select" required>
                <option value="0">No capo</option>
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select name="category" id="category" class="form-select" required>
                <?php foreach ($categories as $id => $label): ?>
                    <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Instruments</label>
            <div>
                <?php foreach ($instruments as $instrument): ?>
                    <div class="form-check form-check-inline">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="inst_<?= htmlspecialchars($instrument['id']) ?>"
                            name="instruments[]"
                            value="<?= htmlspecialchars($instrument['name']) ?>"
                            <?= (strcasecmp($instrument['name'], 'guitar') === 0) ? 'checked' : '' ?>
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
            <textarea name="content" id="content" rows="6" class="form-control" required></textarea>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="0">
            <label class="form-check-label" for="is_public">Private</label>
        </div>

        <button type="submit" name="submit" class="btn btn-primary">Add Song</button>
    </form>
</div>

<?php include 'php/footer.php'; ?>
