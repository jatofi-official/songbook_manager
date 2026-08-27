<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

$songbookId = intval($_GET["songbook_id"] ?? 0);

if ($songbookId === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$stmt = $mysqli->prepare("SELECT id, name, settings FROM songbook_render_presets WHERE songbook_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $songbookId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$presets = array_map(function ($row) {
    return [
        "id" => (int)$row["id"],
        "name" => $row["name"],
        "settings" => json_decode($row["settings"], true),
    ];
}, $rows);

echo json_encode(["success" => true, "presets" => $presets]);
