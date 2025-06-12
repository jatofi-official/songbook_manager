<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "user";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

// Handle logout
if(isset($_POST["logout"])){
	session_unset();
	session_destroy();
	header("Location: " . $_SERVER['PHP_SELF']);
	exit();
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && isset($_SESSION["id"])) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed) && $_FILES['profile_picture']['size'] < 2000000) {
        $filename = "profile_" . $_SESSION["id"] . "." . $ext;
        $targetPath = "uploads/" . $filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            // Save to DB
            $stmt = $mysqli->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $targetPath, $_SESSION["id"]);
            $stmt->execute();
        }
    }
}

// Get user info from DB
$userData = null;
if (isset($_SESSION["id"])) {
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
}
?>

<?php 
if(isset($_POST["submit"]) && isset($_POST["username"]) && isset($_POST["password"])){
	if ($_POST["submit"]=="Register"){
		header("Location: register_user.php");
		exit();
	}
	else{
		$result = login_user($mysqli,$_POST["username"],$_POST["password"]);
		if ($result){
			$_SESSION["user"] = $result["username"];
			$_SESSION["admin"] = $result["admin"];
			$_SESSION["id"]=$result["id"];
			$_SESSION["admin_view"] = false;
			header("Location: " . $_SERVER['PHP_SELF']);
			exit();
		}
	}
}

// PAGE CONTENT
if (isset($_SESSION["user"])): ?>

<section>
	<p>Welcome, <strong><?= htmlspecialchars($_SESSION["name"]) ?></strong>!</p>

	<!-- Profile picture -->
	<?php if (!empty($userData["profile_picture"])): ?>
		<img src="<?= htmlspecialchars($userData["profile_picture"]) ?>" style="max-width: 150px; border-radius: 50%;">
	<?php else: ?>
		<img src="default_avatar.png" style="max-width: 150px; border-radius: 50%;">
	<?php endif; ?>

	<!-- Upload form -->
	<form method="post" enctype="multipart/form-data" class="mt-3">
		<label for="profile_picture">Update Profile Picture:</label>
		<input type="file" name="profile_picture" accept="image/*" required>
		<button type="submit" class="btn btn-primary btn-sm">Upload</button>
	</form>

	<!-- Admin toggle -->
	<?php
	if (isset($_POST['toggle_admin_view'])) {
		if ($_SESSION['admin'] == true) {
			$_SESSION['admin_view'] = !$_SESSION['admin_view'];
		}
	}

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

<?php include('php/footer.php'); ?>
