<?php
session_start();
require_once "db.php";
require_once "functions.php";

header("Content-Type: application/json");

$songbookId = intval($_GET["songbook_id"] ?? 0);
$categoryId = intval($_GET["category_id"] ?? 0);

if ($songbookId === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

echo json_encode([
    "success" => true,
    "lock_names" => get_song_lock_names($mysqli, $songbookId, $categoryId)
]);
