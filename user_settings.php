<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('php/auth.php'); 
require_once('php/db.php');
include_once('php/data.php');
$website = "user";
include_once('php/functions.php');
include("php/header.php");
include('php/navigation.php');

// Handle logout
if (isset($_POST["logout"])) {
    header("Location: php/logout.php");
    exit();
}

// Handle login/register
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
			add_activity($mysqli,$_SESSION["user"],"manual_login",'User "'.$_SESSION["user"].'" manually logged in.',0);



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
if (isset($_SESSION["user"])): 

$public_profile = get_public_profile_settings($mysqli,$_SESSION["id"]);

?>

<div class="container py-5">
	<div class="row">
		<!-- Sidebar Navigation -->
		<div class="col-lg-3">
			<div class="card settings-card">
				<div class="profile-header text-center">
					<?php 
					$profilePicPath = "profile_pics/" . $_SESSION["id"] . ".png";
					if (file_exists($profilePicPath)): 
						// Add last-modified timestamp to bust cache
						$mtime = filemtime($profilePicPath);
					?>
						<img src="<?= htmlspecialchars($profilePicPath . '?v=' . $mtime) ?>" 
							style="max-width: 100px; border-radius: 50%;" 
							class="rounded-circle mb-3" 
							id="profilePreview"  
							alt="Profile Picture">
					<?php else: ?>
						<img src="default_avatar.png" 
							alt="Profile picture" 
							style="max-width: 100px; border-radius: 50%;">
					<?php endif; ?>


					<h5 id="namePreview"><?php echo $_SESSION["name"];  ?></h5>
					<p class="text-light">@<?php echo $_SESSION["user"]; ?></p>
				</div>
				<div class="card-body">
					<div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
						<a class="nav-link active" id="v-pills-profile-tab" data-bs-toggle="pill" href="#v-pills-profile" role="tab">
							<i class="bi bi-person-circle me-2"></i>Profile
						</a>
						<a class="nav-link" id="v-pills-account-tab" data-bs-toggle="pill" href="#v-pills-account" role="tab">
							<i class="bi bi-shield-lock me-2"></i>Account Security
						</a>
						<a class="nav-link" id="v-pills-songbook-tab" data-bs-toggle="pill" href="#v-pills-songbook" role="tab">
							<i class="bi bi-music-note-list me-2"></i>Songbook Preferences
						</a>
						<?php if($_SESSION["admin"]==true){ ?>
						<a class="nav-link" id="v-pills-admin-tab" data-bs-toggle="pill" href="#v-pills-admin" role="tab">
							<i class="bi bi-music-note-list me-2"></i>Admin Settings
						</a>
						<?php }?>
						<!-- Logout -->
						<form method="post" class="nav flex-column"> 
						<input class="btn btn-danger" name="logout" type="submit" value="Log out"> 
						</form>
					</div>
				</div>
			</div>
		</div>

		<!-- Content Area -->
		<div class="col-lg-9">
			<div class="card settings-card">
				<div class="card-body">
					<div class="tab-content" id="v-pills-tabContent">
						<!-- Profile Tab -->
						<div class="tab-pane fade show active" id="v-pills-profile" role="tabpanel">
							<h4 class="section-title">Profile Settings</h4>
							<form id="profileForm">
								<div class="row mb-3">
									<div class="col-md">
										<label for="name" class="form-label">Full Name</label>
										<input type="text" class="form-control" id="name" name="name" value="<?php echo $_SESSION["name"]; ?>">
									</div>
								</div>

								<div class="mb-3">
									<label for="bio" class="form-label">Bio</label>
									<textarea class="form-control" id="bio" name="bio" rows="3"><?php echo $public_profile["bio"]; ?></textarea>
								</div>

								<div class="mb-3">
									<label for="profilePicture" class="form-label">Profile Picture</label>
									<input class="form-control" type="file" id="profilePicture" name="profilePicture">
								</div>

								<button type="submit" class="btn btn-primary">Update Profile</button>
							</form>

							<div id="updateMsg" class="mt-2"></div>

						</div>

						<!-- Account Security Tab -->
						<div class="tab-pane fade" id="v-pills-account" role="tabpanel">
							<h4 class="section-title">Account Security</h4>
							<div class="mb-4">
								<h5>Change Password</h5>
								<form id="passwordForm">
									<div class="mb-3">
										<label for="currentPassword" class="form-label">Current Password</label>
										<input type="password" class="form-control" id="currentPassword" name="currentPassword" autocomplete="current-password">
									</div>
									<div class="mb-3">
										<label for="newPassword" class="form-label">New Password</label>
										<input type="password" class="form-control" id="newPassword" name="newPassword" autocomplete="new-password">
									</div>
									<div class="mb-3">
										<label for="confirmPassword" class="form-label">Confirm New Password</label>
										<input type="password" class="form-control" id="confirmPassword" name="confirmPassword" autocomplete="new-password">
									</div>
									<button type="submit" class="btn btn-primary">Update Password</button>
								</form>

								<div id="passwordMessage" class="mt-3"></div>

							</div>

						</div>

						<!-- Songbook Preferences Tab -->
						<div class="tab-pane fade" id="v-pills-songbook" role="tabpanel">
							<h4 class="section-title">Songbook Preferences</h4>
							<div class="form-check form-switch mb-3">
								<input class="form-check-input" type="checkbox" id="advancedSwitch" checked>
								<label class="form-check-label" for="advancedSwitch">Advanced View</label>
							</div>
							<button type="submit" class="btn btn-primary">Save Preferences</button>
						</div>

						<?php if($_SESSION["admin"]==true){ ?>
						<div class="tab-pane fade" id="v-pills-admin" role="tabpanel">
							<h4 class="section-title">Admin Settings</h4>
							<div class="form-check form-switch mb-3">
								<input class="form-check-input" type="checkbox" id="adminViewSwitch" checked>
								<label class="form-check-label" for="adminViewSwitch">Admin View</label>
							</div>
						</div>
						<?php }?>

					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Scripts -->
<script>
document.getElementById("profileForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    try {
        const response = await fetch("php/update_profile.php", {
            method: "POST",
            body: formData
        });

        // Read raw response for debugging
        const text = await response.text();
        console.log("Raw response from server:", text);

        let result;
        try {
            result = JSON.parse(text);
        } catch (err) {
            console.error("JSON parse error:", err);
            document.getElementById("updateMsg").innerHTML =
                '<div class="alert alert-danger">Invalid JSON response. Check console.</div>';
            return;
        }

        if (result.success) {
            document.getElementById("updateMsg").innerHTML =
                '<div class="alert alert-success">Profile updated successfully!</div>';

            // Update displayed name
            const namePreview = document.getElementById("namePreview");
            const nameInput = document.getElementById("name");
            if (namePreview && nameInput) {
                namePreview.textContent = nameInput.value;
            }

            // Update profile picture preview (with cache-busting)
            const profileImg = document.getElementById("profilePreview");
            if (profileImg) {
                profileImg.src = "profile_pics/<?php echo $_SESSION["id"]; ?>.png?" + new Date().getTime();
            }

        } else {
            document.getElementById("updateMsg").innerHTML =
                '<div class="alert alert-danger">Error: ' + (result.error || "Update failed") + '</div>';
        }

    } catch (err) {
        console.error("Fetch error:", err);
        document.getElementById("updateMsg").innerHTML =
            '<div class="alert alert-danger">Request failed: ' + err.message + '</div>';
    }
});


// Password changing
document.getElementById("passwordForm").addEventListener("submit", function(e) {
    e.preventDefault();

    let formData = new FormData(this);

    fetch("php/update_password.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log("Password change response:", data);
        let msg = document.getElementById("passwordMessage");

        if (data.success) {
            msg.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
            document.getElementById("passwordForm").reset();
        } else {
            msg.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
        }
    })
    .catch(err => {
        console.error("Request failed:", err);
        document.getElementById("passwordMessage").innerHTML = 
            `<div class="alert alert-danger">Request failed. Check console.</div>`;
    });
});


</script>


<style>
	.settings-card {
			border-radius: 10px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.1);
			margin-bottom: 20px;
			transition: transform 0.3s;
	}
	.profile-header {
		/* background: linear-gradient(135deg, #6c5ce7, #8e44ad); */
		color: white;
		border-radius: 10px 10px 0 0;
		padding: 20px;
	}
	.nav-pills .nav-link.active {
		background-color: #6c5ce7;
	}
	.nav-pills .nav-link {
		color: #495057;
	}
	.section-title {
		border-bottom: 2px solid #6c5ce7;
		padding-bottom: 10px;
		margin-bottom: 20px;
		color: #6c5ce7;
	}
	.btn-primary:hover {
		background-color: #5649c0;
		border-color: #5649c0;
	}
</style>


<?php 
// Default page
else: ?>
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
