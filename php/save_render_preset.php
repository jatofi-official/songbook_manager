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

$songbookId = intval($input["songbook_id"] ?? 0);
$presetId = intval($input["preset_id"] ?? 0);
$name = trim($input["name"] ?? "");
$settings = $input["settings"] ?? null;

if ($songbookId === 0 || $name === "" || !is_array($settings)) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$userId = $_SESSION["id"];

// Only a songbook admin (or global admin view) can save render presets for it.
$stmt = $mysqli->prepare("SELECT admins FROM songbooks WHERE id = ?");
$stmt->bind_param("i", $songbookId);
$stmt->execute();
$songbookRow = $stmt->get_result()->fetch_assoc();

if (!$songbookRow) {
    echo json_encode(["success" => false, "message" => "Songbook not found."]);
    exit;
}

$adminsArr = $songbookRow["admins"] ? explode(",", $songbookRow["admins"]) : [];
$isAdminView = isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true;

if (!in_array((string)$userId, $adminsArr, true) && !$isAdminView) {
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

$settingsJson = json_encode($settings);

if ($presetId === 0) {
    // No explicit target id - fall back to matching by name within this
    // songbook, so re-saving under the same name updates it instead of
    // creating a duplicate.
    $stmt = $mysqli->prepare("SELECT id FROM songbook_render_presets WHERE songbook_id = ? AND name = ?");
    $stmt->bind_param("is", $songbookId, $name);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        $presetId = (int)$existing["id"];
    }
}

if ($presetId > 0) {
    // Updating an existing preset must stay scoped to this songbook.
    $stmt = $mysqli->prepare("UPDATE songbook_render_presets SET name = ?, settings = ? WHERE id = ? AND songbook_id = ?");
    $stmt->bind_param("ssii", $name, $settingsJson, $presetId, $songbookId);
    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Database error."]);
        exit;
    }
    echo json_encode(["success" => true, "id" => $presetId]);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO songbook_render_presets (songbook_id, name, settings, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $songbookId, $name, $settingsJson, $userId);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

echo json_encode(["success" => true, "id" => $stmt->insert_id]);
