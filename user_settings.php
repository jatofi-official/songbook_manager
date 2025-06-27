<?php
session_start();
include('php/auth.php'); 
require_once('php/db.php');
include('php/data.php');
$website = "user";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

// Handle logout
if (isset($_POST["logout"])) {
    header("Location: php/logout.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["username"]) && isset($_POST["password"])) {
	if ($_POST["submit"] == "Register") {
		header("Location: register_user.php");
		exit();
	} else {
		$result = login_user($mysqli, $_POST["username"], $_POST["password"]);
		if ($result) {
			$_SESSION["user"] = $result["username"];
			$_SESSION["admin"] = $result["admin"];
			$_SESSION["name"] = $result["name"];
			$_SESSION["id"] = $result["id"];
			$_SESSION["admin_view"] = false;
			$_SESSION["advanced_view"] = false;


			// Call login.php to set the cookie/token (via GET with ?id=...)
			if (isset($_POST['rememberme'])) {
				header("Location: php/login.php?remember=" . $result["id"]);
				exit();
			} else {
				header("Location: " . $_SERVER['PHP_SELF']);
				exit();
			}
		}
	}
}


// PAGE CONTENT
if (isset($_SESSION["user"])): ?>

<section>
	<p>Welcome, <strong><?= htmlspecialchars($_SESSION["name"]) ?></strong>!</p>

	<!-- Profile picture -->
	<?php if (!empty("profile_pics/".$_SESSION["id"].".png")): ?>
		<img src="<?= htmlspecialchars("profile_pics/".$_SESSION["id"].".png") ?>" style="max-width: 150px; border-radius: 50%;">
	<?php else: ?>
		<img src="default_avatar.png" style="max-width: 150px; border-radius: 50%;">
	<?php endif; ?>


	<!-- Admin toggle -->
	<?php
	if (isset($_POST['toggle_admin_view'])) {
		if ($_SESSION['admin'] == true) {
			$_SESSION['admin_view'] = !$_SESSION['admin_view'];
		}
	}
	if (isset($_POST['toggle_advanced_view'])) {
		$_SESSION['advanced_view'] = !$_SESSION['admin_view'];
	}
	?>
	<form method="post">
		<button class="btn btn-primary mt-3" type="submit" name="toggle_advanced_view">
			<?= $_SESSION["advanced_view"] ? "Disable Advanced View" : "Enable Advanced View" ?>
		</button>
	</form>

	<?php

	if ($_SESSION["admin"] == true): ?>
		<form method="post">
			<button class="btn btn-primary mt-3" type="submit" name="toggle_admin_view">
				<?= $_SESSION["admin_view"] ? "Disable Admin View" : "Enable Admin View" ?>
			</button>
		</form>
	<?php endif; ?>

	<!-- Logout -->
	<form method="post" class="mt-3"> 
		<input class="btn btn-danger" name="logout" type="submit" value="Log out"> 
	</form>
</section>

<?php else: ?>
<section>
	<div class="row">
		<div class="col 12 col-md-4 container-wide">
			<form method="post">
				<legend>Login</legend>

				<div class="mb-3">
					<label for="username" class="form-label">Username:</label>
					<input name="username" type="text" class="form-control" id="username" maxlength="30" 
						value="<?= isset($_POST["username"]) ? htmlspecialchars($_POST["username"]) : '' ?>" required>
				</div>

				<div class="mb-3">
					<label for="password" class="form-label">Password:</label>
					<input name="password" type="password" class="form-control" id="password" maxlength="30" required>
				</div>
				<div class="mb-3 form-check">
					<input type="checkbox" class="form-check-input" id="rememberme" name="rememberme" checked>
					<label class="form-check-label" for="rememberme">Remember me</label>
				</div>


				<div class="d-flex justify-content-between">
					<button type="submit" name="submit" class="btn btn-primary">Login</button>
					<a href="register_user.php" class="btn btn-secondary">Register</a>
				</div>
			</form>
		</div>
	</div>
</section>
<style>
    .container-wide {
        max-width: 900px;
        margin: 20px auto 60px;
        background: white;
        padding: 30px 40px;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }

    legend {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 25px;
    }
</style>
<?php endif; ?>

<?php include('php/footer.php'); ?>
