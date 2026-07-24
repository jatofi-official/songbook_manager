<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

$songbookId = intval($_GET["songbook_id"] ?? 0);

if ($songbookId === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT sl.id, sl.name, sl.locked_at, sl.unlocked_at, u.name AS locked_by_name
    FROM songbook_locks sl
    LEFT JOIN users u ON u.id = sl.locked_by
    WHERE sl.songbook_id = ?
    ORDER BY sl.locked_at ASC
");
$stmt->bind_param("i", $songbookId);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
$activeLock = null;

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
    if ($row["unlocked_at"] === null) {
        $activeLock = $row; // last one encountered in chronological order wins
    }
}

echo json_encode([
    "success" => true,
    "locked" => $activeLock !== null,
    "active_lock" => $activeLock,
    "history" => $history
]);
