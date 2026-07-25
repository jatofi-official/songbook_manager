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
// -1 default (not 0) so a missing category_id can be told apart from the
// real "Uncategorized" category, whose id is 0.
$categoryId = intval($input["category_id"] ?? -1);
$orderedIds = $input["ordered_ids"] ?? [];

if ($songbookId === 0 || $categoryId < 0 || !is_array($orderedIds)) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$orderedIds = array_map("intval", $orderedIds);
$userId = $_SESSION["user"];

// A layout can only ever reference songs that are actually persisted members of
// this category right now - otherwise a layout could end up pointing at songs
// the songbook doesn't (yet, or anymore) actually contain, which would render
// today from stale client state but silently vanish (or worse, resurrect) on
// a later fresh load, since the layout table isn't the source of truth for
// membership - songbooks.songs is.
$stmt = $mysqli->prepare("SELECT songs FROM songbooks WHERE id = ?");
$stmt->bind_param("i", $songbookId);
$stmt->execute();
$songbookRow = $stmt->get_result()->fetch_assoc();

if (!$songbookRow) {
    echo json_encode(["success" => false, "message" => "Songbook not found."]);
    exit;
}

$memberIds = [];
foreach (explode(",", $songbookRow["songs"] ?? "") as $pair) {
    $parts = explode("-", $pair, 2);
    if (count($parts) === 2 && (int)$parts[0] === $categoryId) {
        $memberIds[(int)$parts[1]] = true;
    }
}

$orderedIds = array_values(array_filter($orderedIds, function ($id) use ($memberIds) {
    return isset($memberIds[$id]);
}));

$commaList = implode(",", $orderedIds);

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
