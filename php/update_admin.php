<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

if (empty($_SESSION["admin"]) || $_SESSION["admin"] != 1) {
    echo json_encode(["success" => false, "error" => "Permission denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["admin_view"])) {
    echo json_encode(["success" => false, "error" => "Missing admin_view value"]);
    exit;
}

$userId = $_SESSION["id"];
$adminView = $data["admin_view"] ? 1 : 0;

// Update user_settings
$stmt = $mysqli->prepare("UPDATE user_settings SET admin_view = ? WHERE user_id = ?");
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database error: " . $mysqli->error]);
    exit;
}
$stmt->bind_param("ii", $adminView, $userId);

if ($stmt->execute()) {
    $_SESSION["admin_view"] = (bool)$adminView;
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update settings"]);
}
$stmt->close();
