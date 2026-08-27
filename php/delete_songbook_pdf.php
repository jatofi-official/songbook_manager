<?php
session_start();
require_once "db.php";
require_once "functions.php";

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

$id = intval($input["id"] ?? 0);
if ($id === 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$stmt = $mysqli->prepare("SELECT songbook_id, filename FROM songbook_pdfs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(["success" => false, "message" => "PDF not found."]);
    exit;
}

$songbook = get_songbook($mysqli, (int)$row["songbook_id"]);
if (!$songbook) {
    echo json_encode(["success" => false, "message" => "Songbook not found."]);
    exit;
}

$userId = $_SESSION["id"];
$isAdminView = isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true;
if (!in_array((string)$userId, $songbook["admins"], true) && !$isAdminView) {
    echo json_encode(["success" => false, "message" => "Permission denied."]);
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM songbook_pdfs WHERE id = ?");
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database error."]);
    exit;
}

$filePath = __DIR__ . "/../songbook_pdfs/" . $row["filename"];
if (is_file($filePath)) {
    unlink($filePath);
}

echo json_encode(["success" => true]);
