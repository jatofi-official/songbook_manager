<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION["id"];
$name    = $_POST["name"] ?? '';
$bio     = $_POST["bio"] ?? '';
$error   = null;

// Enable PHP error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mysqli->begin_transaction();

try {
    // --- Update name in users ---
    $stmt = $mysqli->prepare("UPDATE users SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $user_id);
    $stmt->execute();
    $stmt->close();

    // Update session name
    $_SESSION["name"] = $name;

    // --- Update bio in user_settings ---
    $stmt = $mysqli->prepare("UPDATE user_settings SET bio = ? WHERE user_id = ?");
    $stmt->bind_param("si", $bio, $user_id);
    $stmt->execute();
    $stmt->close();

    // --- Handle profile picture upload ---
    if (!empty($_FILES["profilePicture"]["tmp_name"])) {

        $uploadDir = __DIR__ . "/../profile_pics/"; // sibling folder of php
        $filename = $uploadDir . $user_id . ".png";

        error_log("Debug: Uploading profile picture to: $filename");
        error_log("Debug: Upload folder exists: " . (is_dir($uploadDir) ? "yes" : "no"));
        error_log("Debug: Upload folder writable: " . (is_writable($uploadDir) ? "yes" : "no"));

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($_FILES["profilePicture"]["size"] > 2 * 1024 * 1024) {
            $error = "Profile picture must be less than 2MB.";
        } else {
            $image_data = getimagesize($_FILES["profilePicture"]["tmp_name"]);
            if ($image_data === false) {
                $error = "Invalid image file.";
            } else {
                $src_image = imagecreatefromstring(file_get_contents($_FILES["profilePicture"]["tmp_name"]));
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

                        if (!imagepng($resized, $filename)) {
                            $error = "Failed to save profile picture. Check folder permissions.";
                            error_log("Failed to save PNG to: $filename");
                        } else {
                            error_log("Profile picture saved successfully: $filename");
                        }

                        imagedestroy($resized);
                        imagedestroy($cropped);
                    }

                    imagedestroy($src_image);
                }
            }
        }
    }

    if ($error) {
        $mysqli->rollback();
        echo json_encode(["success" => false, "error" => $error]);
        exit;
    }

    $mysqli->commit();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
