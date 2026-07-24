<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "db.php";
require_once "functions.php";

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

$orderedIds = array_map("intval", $orderedIds);
$commaList = implode(",", $orderedIds);
$userId = $_SESSION["user"];

// If the songbook has an active lock, whatever was already committed to this
// category's layout as of that lock must stay in the exact same relative
// order and can't be dropped - only ids that weren't part of it yet are free
// to be added/reordered/removed. Uncategorized (category 0) is never locked.
$lockedIds = get_locked_ids($mysqli, $songbookId, $categoryId);

if (!empty($lockedIds)) {
    $lockedIdSet = array_flip($lockedIds);

    $submittedLockedIds = array_values(array_filter($orderedIds, function ($id) use ($lockedIdSet) {
        return isset($lockedIdSet[$id]);
    }));

    if ($submittedLockedIds !== $lockedIds) {
        echo json_encode([
            "success" => false,
            "message" => "This songbook is locked - songs that were already committed can't be reordered or removed. Only newly added songs can be changed."
        ]);
        exit;
    }
}

$stmt = $mysqli->prepare("INSERT INTO songbook_category_layouts (songbook_id, category_id, ordered_ids, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iisi", $songbookId, $categoryId, $commaList, $userId);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database error."]);
}
