<?php
session_start();
include_once("db.php");

header('Content-Type: application/json');

// Parse JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$userId = intval($_SESSION["id"]);
$advanced = isset($input["advanced_view"]) ? (int)$input["advanced_view"] : 0;

try {
    // Update DB
    $stmt = $mysqli->prepare("UPDATE user_settings SET advanced_view = ? WHERE user_id = ?");
    $stmt->bind_param("ii", $advanced, $userId);
    $stmt->execute();
    $stmt->close();

    // Update session
    $_SESSION["advanced_view"] = $advanced;

    echo json_encode(["success" => true, "advanced_view" => $advanced]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
