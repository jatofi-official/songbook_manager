<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

session_start();
$website = "user";
include('php/db.php');      // Your existing DB connection
include('php/functions.php');

$data = $_POST;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $password2 = $_POST["password2"];
    $zbor = intval($_POST["zbor"]);
    $profile_picture_path = null;

    if ($password !== $password2) {
        $error = "Passwords do not match.";
    } elseif (strlen($name) < 3 || strlen($username) < 3 || strlen($password) < 3) {
        $error = "Name, username and password must be at least 3 characters.";
    } else {
        // Create user without picture first
        $user_id = add_user($mysqli, $name, $username, $password, $zbor);

        if ($user_id === "duplicate") {
            $error = "Username already taken. Please choose another.";
        } 
        elseif ($user_id) {
            // Handle profile picture
            if (!empty($_FILES["profile_picture"]["tmp_name"])) {
            // Limit to 2MB (2 * 1024 * 1024 bytes)
                if ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
                    $error = "Profile picture must be less than 2MB.";
                } 
                else {
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
                                    $stmt = $mysqli->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                                    $stmt->bind_param("si", $filename, $user_id);
                                    $stmt->execute();
                                    $stmt->close();
                                }

                                imagedestroy($resized);
                            }

                            imagedestroy($cropped);
                        }

                        imagedestroy($src_image);
                }
            }
        }


            if (!$error) {
                $_SESSION["id"] = $user_id;
                $_SESSION["user"] = $username;
                $_SESSION["admin"] = false;
                $_SESSION["admin_view"] = false;

                header("Location: user_settings.php");
                exit;
            }
        } else {
            $error = "Failed to create user.";
        }
    }
}
?>

<?php include("php/header.php"); ?>
<?php include("php/navigation.php"); ?>

<section>
    <div class="container-wide">
        <form method="post" enctype="multipart/form-data">
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
                <label for="zbor" class="form-label required-label">Zbor</label>
                <select name="zbor" id="zbor" class="form-select" required>
                    <?php for ($i = 1; $i <= 150; $i++): ?>
                        <option value="<?= $i ?>" <?= (isset($data["zbor"]) && $data["zbor"] == $i) ? "selected" : "" ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="profile_picture" class="form-label">Profile Picture (optional)</label>
                <input type="file" name="profile_picture" id="profile_picture" class="form-control" accept="image/*">
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

<?php include("php/footer.php"); ?>
