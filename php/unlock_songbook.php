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
$lockId = intval($input["lock_id"] ?? 0);

if ($songbookId === 0 || $lockId === 0) {
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

// Any individual active lock belonging to this songbook may be unlocked, not just the latest.
$stmt = $mysqli->prepare("
    SELECT id FROM songbook_locks
    WHERE id = ? AND songbook_id = ? AND unlocked_at IS NULL
");
$stmt->bind_param("ii", $lockId, $songbookId);
$stmt->execute();
$lockRow = $stmt->get_result()->fetch_assoc();

if (!$lockRow) {
    echo json_encode(["success" => false, "message" => "Lock not found or already unlocked."]);
    exit;
}

$stmt = $mysqli->prepare("UPDATE songbook_locks SET unlocked_by = ?, unlocked_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->bind_param("ii", $userId, $lockRow["id"]);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database error."]);
}
