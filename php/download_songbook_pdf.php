<?php
session_start();
require_once "db.php";
require_once "functions.php";

$id = intval($_GET["id"] ?? 0);
if ($id === 0) {
    http_response_code(400);
    exit("Missing parameters.");
}

$stmt = $mysqli->prepare("SELECT songbook_id, title, filename FROM songbook_pdfs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    http_response_code(404);
    exit("PDF not found.");
}

$songbook = get_songbook($mysqli, (int)$row["songbook_id"]);
if (!$songbook || !can_view_songbook($mysqli, $songbook)) {
    http_response_code(403);
    exit("You do not have permission to download this PDF.");
}

$filePath = __DIR__ . "/../songbook_pdfs/" . $row["filename"];
if (!is_file($filePath)) {
    http_response_code(404);
    exit("PDF file is missing.");
}

$displayName = $songbook["name"] . "_" . $row["title"] . ".pdf";
$safeName = preg_replace('/[^A-Za-z0-9_\- .]+/', '-', $displayName);

header("Content-Type: application/pdf");
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header("Content-Length: " . filesize($filePath));
readfile($filePath);
