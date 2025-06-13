<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "user";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

// Handle login
if (isset($_POST["submit"]) && isset($_POST["username"]) && isset($_POST["password"])) {
    if ($_POST["submit"] == "Register") {
        header("Location: register_user.php");
        exit();
    } else {
        $result = login_user($mysqli, $_POST["username"], $_POST["password"]);
        if ($result) {
            $_SESSION["user"] = $result["username"];
            $_SESSION["name"] = $result["name"];
            $_SESSION["admin"] = $result["admin"];
            $_SESSION["id"] = $result["id"];
            $_SESSION["admin_view"] = false;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Determine the userId to fetch:
$userId = null;
if (isset($_GET["id"])) {
    $userId = intval($_GET["id"]);
} elseif (isset($_SESSION["id"])) {
    $userId = intval($_SESSION["id"]);
}
?>

<?php if (isset($_SESSION["user"])||$userId != null): ?>
    <div id="user-details"></div>

    <?php if ($userId !== null): ?>
    <script>
        const userId = <?= json_encode($userId) ?>;
        fetch(`php/subpage/get_user.php?id=${userId}&embedded=1`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('user-details').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('user-details').innerHTML = '<p>Failed to load user info.</p>';
            });
    </script>
    <?php else: ?>
        <p>You need to be logged in or specify a user.</p>
    <?php endif; ?>

<?php else: ?>
    
    <!-- Show login form -->
    <section>
        <div class="row">
            <div class="col 12 col-md-4 round_container">
                <form method="post">
                    <h2 class="text-left">Login</h2>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username:</label>
                        <input name="username" type="text" class="form-control" id="username" maxlength="30" 
                            value="<?= isset($_POST["username"]) ? htmlspecialchars($_POST["username"]) : '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password:</label>
                        <input name="password" type="password" class="form-control" id="password" maxlength="30" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" name="submit" class="btn btn-primary">Login</button>
                        <a href="register_user.php" class="btn btn-secondary">Register</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php include("php/footer.php"); ?>
