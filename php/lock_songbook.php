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
$name = trim($input["name"] ?? "");

if ($songbookId === 0 || $name === "") {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$userId = $_SESSION["id"];

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

$stmt = $mysqli->prepare("INSERT INTO songbook_locks (songbook_id, name, locked_by) VALUES (?, ?, ?)");
$stmt->bind_param("isi", $songbookId, $name, $userId);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

$lockId = $stmt->insert_id;

// Record exactly which layout row is "current" for each category right now,
// by primary key - not by timestamp. songbook_category_layouts.id is a strictly
// monotonic AUTO_INCREMENT, so this can't be ambiguous the way comparing
// second-precision timestamps across two different tables can be (e.g. a song
// added and saved in the same second as the lock could otherwise get mistaken
// for having existed at lock time).
$stmt = $mysqli->prepare("
    SELECT t1.category_id, t1.id
    FROM songbook_category_layouts t1
    INNER JOIN (
        SELECT category_id, MAX(id) AS max_id
        FROM songbook_category_layouts
        WHERE songbook_id = ?
        GROUP BY category_id
    ) t2 ON t1.category_id = t2.category_id AND t1.id = t2.max_id
    WHERE t1.songbook_id = ?
");
$stmt->bind_param("ii", $songbookId, $songbookId);
$stmt->execute();
$latestLayouts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$snapStmt = $mysqli->prepare("INSERT INTO songbook_lock_snapshots (lock_id, category_id, layout_id) VALUES (?, ?, ?)");
foreach ($latestLayouts as $layout) {
    $categoryId = (int)$layout["category_id"];
    if ($categoryId === 0) {
        continue; // Uncategorized is never locked
    }
    $layoutId = (int)$layout["id"];
    $snapStmt->bind_param("iii", $lockId, $categoryId, $layoutId);
    $snapStmt->execute();
}

echo json_encode(["success" => true, "id" => $lockId]);
