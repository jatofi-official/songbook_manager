<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user"])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

$songbookId = intval($_GET["songbook_id"] ?? $_POST["songbook_id"] ?? 0);
// -1 default (not 0) so a missing category_id can be told apart from the
// real "Uncategorized" category, whose id is 0.
$categoryId = intval($_GET["category_id"] ?? $_POST["category_id"] ?? -1);

if ($songbookId === 0 || $categoryId < 0) {
    echo json_encode(["success" => false, "message" => "Missing parameters."]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT ordered_ids 
    FROM songbook_category_layouts 
    WHERE songbook_id = ? AND category_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");

$stmt->bind_param("ii", $songbookId, $categoryId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $orderedIds = array_map('intval', explode(",", $row["ordered_ids"]));
    echo json_encode(["success" => true, "ordered_ids" => $orderedIds]);
} else {
    echo json_encode(["success" => false, "message" => "Layout not found."]);
}
