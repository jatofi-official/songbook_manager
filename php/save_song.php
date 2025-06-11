<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){  
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

require_once __DIR__ . '/db.php';
include_once __DIR__ . '/functions.php';
include_once __DIR__ . '/data.php';

header('Content-Type: application/json');

if (!isset($_SESSION["user"])) {
    echo json_encode(["success" => false, "error" => "You must be logged in."]);
    exit;
}

$userId = $_SESSION["user"];
$isAdmin = $_SESSION["admin"] ?? false;

// Collect and sanitize input
$title = osetri($_POST["title"] ?? "");
$artist = osetri($_POST["artist"] ?? "");
$capo = (int)($_POST["capo"] ?? 0);
$capo_alt = ($_POST["capo_alt"] === "") ? 0 : (int)$_POST["capo_alt"];
$category = $_POST["category"] ?? "";
$tags = trim($_POST["tags"] ?? "");
$instruments = $_POST["instruments"] ?? [];
$content = osetri($_POST["content"] ?? "");
$is_public = isset($_POST['is_public']) ? 1 : 0;

$errors = [];

if (!$title) $errors[] = "Title is required.";
if (!$artist) $errors[] = "Artist is required.";
if (!$category) $errors[] = "Category is required.";
if (!$content) $errors[] = "Content is required.";

if (!empty($errors)) {
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

$data = [
    "title" => $title,
    "artist" => $artist,
    "capo" => $capo,
    "capo_alt" => $capo_alt,
    "category" => $category,
    "tags" => $tags,
    "instruments" => implode(",", $instruments),
    "content" => $content,
    "is_public" => $is_public,
    "owner" => $userId
];

// Editing song
if (isset($_POST['id']) && $_POST['id'] !== "") {
    $id = (int)$_POST['id'];

    // Verify ownership/admin before editing
    $stmt = $mysqli->prepare("SELECT id FROM songs WHERE id = ? AND (owner = ? OR ? = 1)");
    $stmt->bind_param("isi", $id, $userId, $isAdmin);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["success" => false, "error" => "Song not found or no permission."]);
        exit;
    }

    $stmt->close();

    $ok = edit_song($mysqli, $id, $data);
    if ($ok === true) {
        add_activity($mysqli, $userId, "Edited song", "Edited '{$title}' by {$artist}", $is_public);
        echo json_encode(["success" => true, "message" => "Song updated."]);
    } else {
        echo json_encode(["success" => false, "error" => $ok]);
    }
} 
// Adding new song
else {
    $ok = add_song($mysqli, $data);
    if ($ok === true) {
        add_activity($mysqli, $userId, "Added song", "Added '{$title}' by {$artist}", $is_public);
        echo json_encode(["success" => true, "message" => "Song added."]);
    } else {
        echo json_encode(["success" => false, "error" => $ok]);
    }
}
