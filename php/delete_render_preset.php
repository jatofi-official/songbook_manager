<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid input."]);
    exit;
}

$presetId = intval($input["preset_id"] ?? 0);

if ($presetId === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$userId = $_SESSION["id"];

$stmt = $mysqli->prepare("
    SELECT p.songbook_id, s.admins
    FROM songbook_render_presets p
    INNER JOIN songbooks s ON s.id = p.songbook_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $presetId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(["success" => false, "message" => "Preset not found."]);
    exit;
}

$adminsArr = $row["admins"] ? explode(",", $row["admins"]) : [];
$isAdminView = isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true;

if (!in_array((string)$userId, $adminsArr, true) && !$isAdminView) {
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM songbook_render_presets WHERE id = ?");
$stmt->bind_param("i", $presetId);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

echo json_encode(["success" => true]);
