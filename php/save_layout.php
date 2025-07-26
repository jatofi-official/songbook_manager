<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user"])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid input."]);
    exit;
}

$songbookId = intval($input["songbook_id"] ?? 0);
$categoryId = intval($input["category_id"] ?? 0);
$orderedIds = $input["ordered_ids"] ?? [];

if ($songbookId === 0 || $categoryId === 0 || !is_array($orderedIds)) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$commaList = implode(",", array_map("intval", $orderedIds));
$userId = $_SESSION["user"];

$stmt = $mysqli->prepare("INSERT INTO songbook_category_layouts (songbook_id, category_id, ordered_ids, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iisi", $songbookId, $categoryId, $commaList, $userId);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database error."]);
}
