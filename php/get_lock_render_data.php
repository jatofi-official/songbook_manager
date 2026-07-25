<?php
session_start();
require_once "db.php";
require_once "functions.php";

header("Content-Type: application/json");

$songbookId = intval($_GET["songbook_id"] ?? 0);
$lockId = intval($_GET["lock_id"] ?? 0);

if ($songbookId === 0 || $lockId === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$stmt = $mysqli->prepare("SELECT name FROM songbook_locks WHERE id = ? AND songbook_id = ?");
$stmt->bind_param("ii", $lockId, $songbookId);
$stmt->execute();
$lockRow = $stmt->get_result()->fetch_assoc();

if (!$lockRow) {
    echo json_encode(["success" => false, "message" => "Lock not found."]);
    exit;
}

$songbook = get_songbook($mysqli, $songbookId);
if (!$songbook) {
    echo json_encode(["success" => false, "message" => "Songbook not found."]);
    exit;
}

// Song ids owned by this lock, per category - membership sets only. The
// client renders the whole songbook itself (so page numbers reflect the
// songs' real position in the full book) and uses these sets purely to
// decide which already-rendered pages to keep.
$matches = [];
foreach ($songbook["songs_categories"] as $cat) {
    if ((int)$cat["id"] === 0) {
        continue; // Uncategorized is never locked
    }

    $ids = get_song_ids_for_lock($mysqli, $songbookId, $cat["id"], $lockId);
    if (empty($ids)) {
        continue;
    }

    $matches[$cat["id"]] = $ids;
}

echo json_encode([
    "success" => true,
    "lock_name" => $lockRow["name"],
    "matches" => $matches
]);
