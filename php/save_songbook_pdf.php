<?php
session_start();
require_once "db.php";
require_once "functions.php";

header("Content-Type: application/json");

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

$songbookId = intval($_POST["songbook_id"] ?? 0);
$title = trim($_POST["title"] ?? "");

if ($songbookId === 0 || $title === "") {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

if (!isset($_FILES["pdf"]) || $_FILES["pdf"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No PDF file received."]);
    exit;
}

$songbook = get_songbook($mysqli, $songbookId);
if (!$songbook) {
    echo json_encode(["success" => false, "message" => "Songbook not found."]);
    exit;
}

// Only a songbook admin (or global admin view) can save a PDF to the server.
$userId = $_SESSION["id"];
$isAdminView = isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true;
if (!in_array((string)$userId, $songbook["admins"], true) && !$isAdminView) {
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

$uploadDir = __DIR__ . "/../songbook_pdfs/";
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    // The extra is_dir() re-check handles a race where another request
    // created it between our check and mkdir() - only a genuine failure
    // (e.g. the web server user can't write to the project root) gets here.
    echo json_encode(["success" => false, "message" => "Server can't create the PDF storage directory (songbook_pdfs/) - check its permissions."]);
    exit;
}

if (!is_writable($uploadDir)) {
    echo json_encode(["success" => false, "message" => "PDF storage directory (songbook_pdfs/) exists but isn't writable by the server."]);
    exit;
}

// Stored filename is just an opaque, collision-proof handle - the
// human-readable "songbook name_title" name is generated on download from
// the current songbook name + the title column, so a later songbook rename
// doesn't leave old downloads with a stale name.
$storedFilename = $songbookId . "_" . uniqid("", true) . ".pdf";

if (!move_uploaded_file($_FILES["pdf"]["tmp_name"], $uploadDir . $storedFilename)) {
    echo json_encode(["success" => false, "message" => "Failed to save PDF file."]);
    exit;
}

$stmt = $mysqli->prepare("INSERT INTO songbook_pdfs (songbook_id, title, filename, created_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $songbookId, $title, $storedFilename, $userId);

if (!$stmt->execute()) {
    unlink($uploadDir . $storedFilename);
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

echo json_encode(["success" => true, "id" => $stmt->insert_id]);
