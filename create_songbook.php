<?php
session_start();
include('php/db.php');
include('php/auth.php');
include('php/data.php');
$website = "songbooks";
include_once('php/functions.php');
include("php/header.php");
include('php/navigation.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['user'])):

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $admins = trim($_POST['admins'] ?? '');

    if (!$name) {
        $errors[] = "Name is required.";
    }

    if (empty($errors)) {
        // Ensure the current user is always in admins
        $adminsArray = array_filter(array_map('trim', explode(',', $admins)), 'strlen');
        if (!in_array($_SESSION['id'], $adminsArray)) {
            $adminsArray[] = $_SESSION['id'];
        }
        $admins = implode(',', $adminsArray);

        // Get current user info
        $currentUser = get_user($mysqli, $_SESSION['id']);
        $groupId = $currentUser['group_id'] ?? null; // assign null if no group

        // Now call create_songbook with the group_id
        $songbookId = create_songbook(
            $mysqli,
            $name,
            $description,
            $_SESSION['id'], // owner
            $admins,           // string of admin IDs
            $groupId           // group_id
        );


        if ($songbookId) {
            header('Location: songbook.php?id=' . $songbookId);
            exit;
        } else {
            $errors[] = "Failed to create songbook. Please try again.";
        }

    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-plus-lg"></i> Create New Songbook</h4>
                </div>
                <div class="card-body">
                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Songbook Name</label>
                            <input type="text" name="name" id="name" class="form-control"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"><?= 
                                htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="admins" class="form-label">Admin User IDs (comma-separated)</label>
                            <input type="text" name="admins" id="admins" class="form-control"
                                   value="<?= htmlspecialchars($_POST['admins'] ?? '') ?>">
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-plus-lg"></i> Create Songbook
                            </button>
                            <a href="songbooks.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
else:
    echo "<p>Oops... Nothing to see here</p>";
endif;

include("php/footer.php"); 
?>
