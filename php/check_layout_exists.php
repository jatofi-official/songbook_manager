<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['songbook_id']) || !isset($_GET['category_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$songbookId = (int)$_GET['songbook_id'];
$categoryId = (int)$_GET['category_id'];

// Assume you have $mysqli connection already set up:
require_once 'db.php';

$stmt = $mysqli->prepare("SELECT 1 FROM songbook_category_layouts WHERE songbook_id = ? AND category_id = ? LIMIT 1");
$stmt->bind_param("ii", $songbookId, $categoryId);
$stmt->execute();
$stmt->store_result();

$exists = $stmt->num_rows > 0;

echo json_encode(['exists' => $exists]);
