<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$website = "user";
include('php/db.php');      // Your existing DB connection
include('php/functions.php');

$data = $_POST;
$error = "";

// Fetch all groups
$groups = get_all_groups($mysqli);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $password2 = $_POST["password2"];
    // Accept group_id or null
    $group_id = isset($_POST["group_id"]) && $_POST["group_id"] !== '' ? intval($_POST["group_id"]) : null;
    $group_password = $_POST["group_password"] ?? '';
    $profile_picture_path = null;

    // Basic validation
    if ($password !== $password2) {
        $error = "Passwords do not match.";
    } elseif (strlen($name) < 3 || strlen($username) < 3 || strlen($password) < 3) {
        $error = "Name, username and password must be at least 3 characters.";
    } else {
        // Check group password if group selected and group has a password
        if ($group_id !== null) {
            $selectedGroup = null;
            foreach ($groups as $group) {
                if ($group['id'] == $group_id) {
                    $selectedGroup = $group;
                    break;
                }
            }


        if ($selectedGroup && !empty($selectedGroup['password'])) {
            if (!verify_group_password($group_password, $selectedGroup['password'])) {
                $error = "Incorrect password for selected group.";
            }
        }


        }

        if (!$error) {
            // Create user without picture first
            $user_id = add_user($mysqli, $name, $username, $password, $group_id);
            add_activity($mysqli,$username,"new_user","User ".$name." joined");

            if ($user_id === "duplicate") {
                $error = "Username already taken. Please choose another.";
            } elseif ($user_id) {
                // Handle profile picture
                if (!empty($_FILES["profile_picture"]["tmp_name"])) {
                    if ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
                        $error = "Profile picture must be less than 2MB.";
                    } else {
                        $image_data = getimagesize($_FILES["profile_picture"]["tmp_name"]);
                        if ($image_data === false) {
                            $error = "Invalid image file.";
                        } else {
                            $src_image = imagecreatefromstring(file_get_contents($_FILES["profile_picture"]["tmp_name"]));
                            if (!$src_image) {
                                $error = "Failed to process image.";
                            } else {
                                $src_width = imagesx($src_image);
                                $src_height = imagesy($src_image);

                                // Crop to center square
                                $side = min($src_width, $src_height);
                                $src_x = ($src_width - $side) / 2;
                                $src_y = ($src_height - $side) / 2;

                                $cropped = imagecrop($src_image, [
                                    'x' => $src_x,
                                    'y' => $src_y,
                                    'width' => $side,
                                    'height' => $side
                                ]);

                                if (!$cropped) {
                                    $error = "Failed to crop image.";
                                } else {
                                    $resized = imagecreatetruecolor(512, 512);
                                    imagecopyresampled($resized, $cropped, 0, 0, 0, 0, 512, 512, $side, $side);

                                    if (!file_exists('profile_pics')) {
                                        mkdir('profile_pics', 0755, true);
                                    }

                                    $filename = "profile_pics/" . $user_id . ".png";
                                    if (!imagepng($resized, $filename)) {
                                        $error = "Failed to save profile picture.";
                                    } else {
                                        // Save path in DB
                                        // $stmt = $mysqli->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                                        // $stmt->bind_param("si", $filename, $user_id);
                                        // $stmt->execute();
                                        // $stmt->close();
                                    }

                                    imagedestroy($resized);
                                }

                                imagedestroy($cropped);
                            }

                            imagedestroy($src_image);
                        }
                    }
                }
                // Create default user_settings row
                create_default_user_settings($mysqli, $user_id);

                if (!empty($_POST["remember_me"])) {
                    header("Location: php/login.php?remember=$user_id");
                } else {
                    // Set session manually and redirect
                    $_SESSION["id"] = $user_id;
                    $_SESSION["name"] = $name;
                    $_SESSION["user"] = $username;
                    $_SESSION["admin"] = false;
                    $_SESSION["admin_view"] = false;

                    header("Location: user_settings.php");
                }
                exit;

                
            } else {
                $error = "Failed to create user.";
            }
        }
    }
}
?>

<?php include("php/header.php"); ?>
<?php include("php/navigation.php"); ?>

<section>
    <div class="container-wide">
        <form method="post" enctype="multipart/form-data" id="registerForm">
            <legend>Create Account</legend>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="name" class="form-label required-label">Name</label>
                <input type="text" name="name" id="name" class="form-control" required maxlength="20"
                       value="<?php echo htmlspecialchars($data["name"] ?? "") ?>">
            </div>

            <div class="mb-3">
                <label for="username" class="form-label required-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required maxlength="20"
                       value="<?php echo htmlspecialchars($data["username"] ?? "") ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label required-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required maxlength="30">
            </div>

            <div class="mb-3">
                <label for="password2" class="form-label required-label">Repeat Password</label>
                <input type="password" name="password2" id="password2" class="form-control" required maxlength="30">
            </div>

            <div class="mb-3">
                <label for="group_id" class="form-label">Group</label>
                <select name="group_id" id="group_id" class="form-select">
                    <option value="" <?= (!isset($data["group_id"]) || $data["group_id"] === "") ? "selected" : "" ?>>No group</option>
                    <?php foreach ($groups as $group): ?>
                        <option 
                            value="<?= htmlspecialchars($group['id']) ?>" 
                            data-has-password="<?= !empty($group['password']) ? '1' : '0' ?>"
                            <?= (isset($data["group_id"]) && $data["group_id"] == $group['id']) ? "selected" : "" ?>>
                            <?= htmlspecialchars($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3" id="groupPasswordDiv" style="display:none;">
                <label for="group_password" class="form-label required-label">Group Password</label>
                <input type="password" name="group_password" id="group_password" class="form-control" maxlength="30">
            </div>

            <div class="mb-3">
                <label for="profile_picture" class="form-label">Profile Picture (optional)</label>
                <input type="file" name="profile_picture" id="profile_picture" class="form-control" accept="image/*">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                <label class="form-check-label" for="remember_me">
                    Remember me
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">Register</button>
        </form>
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

    .required-label::after {
        content: " *";
        color: red;
    }
</style>

<script>
    // Show/hide password input if group has password
    function updateGroupPasswordField() {
        const select = document.getElementById('group_id');
        const passwordDiv = document.getElementById('groupPasswordDiv');
        const selectedOption = select.options[select.selectedIndex];
        if (selectedOption && selectedOption.dataset.hasPassword === "1") {
            passwordDiv.style.display = "block";
            document.getElementById('group_password').required = true;
        } else {
            passwordDiv.style.display = "none";
            document.getElementById('group_password').required = false;
        }
    }

    document.getElementById('group_id').addEventListener('change', updateGroupPasswordField);

    // Run on page load
    updateGroupPasswordField();
</script>

<?php include("php/footer.php"); ?>
