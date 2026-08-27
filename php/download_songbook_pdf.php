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

// filename= alone is ASCII-only per the old RFC, so accented/diacritic
// characters (á, č, ň, ž, ...) would otherwise get mangled or stripped by
// the browser. filename*= (RFC 5987/6266) carries the real UTF-8 name for
// clients that support it; filename= stays as a transliterated ASCII
// fallback for the rare client that doesn't.
$asciiFallback = preg_replace('/[^A-Za-z0-9_\- .]+/', '_', $displayName);
if ($asciiFallback === '' || $asciiFallback === null) {
    $asciiFallback = 'songbook.pdf';
}

// "View" (opens in a new tab, rendered by the browser's own PDF viewer)
// uses the exact same file/permissions/filename logic as "Download" - just
// inline instead of attachment - so it's driven by a query param rather
// than a separate endpoint.
$disposition = (($_GET["mode"] ?? "") === "inline") ? "inline" : "attachment";

header("Content-Type: application/pdf");
header(
    "Content-Disposition: {$disposition}; filename=\"{$asciiFallback}\"; "
    . "filename*=UTF-8''" . rawurlencode($displayName)
);
header("Content-Length: " . filesize($filePath));
readfile($filePath);
