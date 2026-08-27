<?php
session_start();
require_once "db.php";
require_once "functions.php";

header("Content-Type: application/json");

$songbookId = intval($_GET["songbook_id"] ?? 0);
if ($songbookId === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$songbook = get_songbook($mysqli, $songbookId);
if (!$songbook) {
    echo json_encode(["success" => false, "message" => "Songbook not found."]);
    exit;
}

// Anyone who can see the songbook can see (and download) its saved PDFs -
// same rule songbook.php itself enforces, including guests on public books.
if (!can_view_songbook($mysqli, $songbook)) {
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

$stmt = $mysqli->prepare("SELECT id, title, created_at FROM songbook_pdfs WHERE songbook_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $songbookId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pdfs = array_map(function ($row) use ($songbook) {
    return [
        "id" => (int)$row["id"],
        "title" => $row["title"],
        "display_name" => $songbook["name"] . "_" . $row["title"],
        "created_at" => $row["created_at"],
    ];
}, $rows);

echo json_encode(["success" => true, "pdfs" => $pdfs]);
