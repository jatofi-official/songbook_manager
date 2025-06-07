<?php
session_start();
include 'php/db.php';
include 'php/categories.php';   // sets $categories array
include 'php/instruments.php';  // sets $instruments array
include "php/functions.php";

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["submit"])) {
    $submitted = true;
}

include 'php/header.php';
include 'php/navigation.php';

// User must be logged in
if (!isset($_SESSION["user"])) {
    echo "You need to be logged in!";
}
else{


?>

<div class="container mt-4">
    <h2>Add New Song</h2>

    <?php if ($submitted): 
        $data = [];
        $data["title"] = osetri($_POST["title"]);
        $data["author"] = osetri($_POST["author"]);
        $data["capo"] = $_POST["capo"];
        $data["capo_alt"] = ($_POST["capo_alt"] == "") ? NULL : $_POST["capo_alt"];
        $data["category"] = $_POST["category"];
        $data["instruments"] = implode(",", $_POST["instruments"]);
        $data["is_public"] = isset($_POST['is_public']) ? 1 : 0;
        $data["owner"] = $_SESSION["user"];
        $data["tags"] = "";
        $data["content"] = osetri($_POST["content"]);
        
        
        // echo "post:'".$_POST["capo_alt"]."'<br>";
        // echo "data:'".$data["capo_alt"]."'";
        
        

        $result = add_song($mysqli,$data);

        if($result == true){
            echo ('<div class="alert alert-success">Submitted!</div>');
        }
        else{
            echo ('<div class="alert alert-danger">'.$result.'</div>');

        }


    endif; ?>

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
            <label for="capo_alt" class="form-label">Capo alt</label>
            <select name="capo_alt" id="capo_alt" class="form-select">
                <option value="">No alt</option>
                <?php for ($i = 0; $i <= 7; $i++): ?>
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
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1" checked>
            <label class="form-check-label" for="is_public">Public (others can see this)</label>
        </div>

        <button type="submit" name="submit" class="btn btn-primary">Add Song</button>
    </form>
</div>

<?php
}


include 'php/footer.php'; ?>
