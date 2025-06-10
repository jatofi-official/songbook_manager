<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "user";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');
?>
<!-- Logging in -->
<?php 
if(isset($_POST["submit"]) && isset($_POST["username"]) && isset($_POST["password"]) ){
	// if ($_POST["subim"])
	if ($_POST["submit"]=="Register"){
		header("Location: register_user.php");
		exit();
	}
	else{
		$result = login_user($mysqli,$_POST["username"],$_POST["password"]);
		if ($result){
			$_SESSION["user"] = $result["username"];
			$_SESSION["admin"] = $result["admin"];
			$_SESSION["password"]=$result["password"];
			$_SESSION["name"]=$result["name"];
			$_SESSION["id"]=$result["id"];
			$_SESSION["admin_view"] = false;


			// Redirect after successful login
			header("Location: " . $_SERVER['PHP_SELF']);
			exit();
		}
	}
}
if(isset($_POST["logout"])){
	session_unset();
	session_destroy();

	// Redirect after logout to refresh the page and clear session data
	header("Location: " . $_SERVER['PHP_SELF']);
	exit();
}


// PAGE CONTENT
if (isset($_SESSION["user"])){

	
?>
<section>

<p>Welcome!<strong><?php echo ($_SESSION["meno"]); ?></strong>.</p>

<?php

// Check if admin view toggle button was pressed
if (isset($_POST['toggle_admin_view'])) {
    if (isset($_SESSION['admin']) && $_SESSION['admin'] == true) {
		if($_SESSION["admin_view"]==true){
	        $_SESSION['admin_view'] = false;
		}
		else{	
    	    $_SESSION['admin_view'] = true;
		}}
}

// Display the form if user is admin
if (isset($_SESSION["admin"]) && $_SESSION["admin"] == true) {
?>
    <form method="post">
        <button class="btn btn-primary" type="submit" name="toggle_admin_view"><?php if($_SESSION["admin_view"]==true){echo "Disable Admin View";}else{echo "Enable Admin View";} ?></button>
    </form>
<?php
}
?>



<!-- Log out -->
<form method="post"> 
  <p> 
    <input class="btn btn-danger" name="logout" type="submit" id="logout" value="Log out"> 
  </p> 
</form> 

<?php }

// LOGIN 
else{
	
	?>
	
	<div class="row">
		<div class="col 12 col-md-4 round_container">
		<form method="post">
			<h2 class="text-left">Login</h2>  <!-- Title for clarity -->

			<!-- Username Field -->
			<div class="mb-3">
				<label for="username" class="form-label">Username:</label>
				<input name="username" type="text" class="form-control" id="username" maxlength="30" 
					value="<?php if (isset($_POST["username"])) echo htmlspecialchars($_POST["username"]); ?>" required>
			</div>

			<!-- Password Field -->
			<div class="mb-3">
				<label for="password" class="form-label">Password:</label>
				<input name="password" type="password" class="form-control" id="password" maxlength="30" required>
			</div>

			<!-- Action Buttons -->
			<div class="d-flex justify-content-between">
				<button type="submit" name="submit" class="btn btn-primary">Login</button>
				<a href="register_user.php" class="btn btn-secondary">Register</a>
			</div>
		</form>

	</div>
</div>


</section>

<?php
}
include('php/footer.php');
?>
