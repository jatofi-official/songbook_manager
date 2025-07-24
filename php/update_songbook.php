<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$id = $input['id'] ?? null;
$name = $input['name'] ?? '';
$description = $input['description'] ?? '';
$admins = $input['admins'] ?? [];
$categories = $input['categories'] ?? [];
$songs = $input['songs'] ?? [];
$group_id = $input['group_id'] ?? null;
$is_public = isset($input['is_public']) ? (int)$input['is_public'] : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing songbook ID']);
    exit;
}

$userId = $_SESSION['id'];

// Permission check: admins must include current user
if (is_array($admins)) {
    $adminsStr = implode(',', array_map('intval', $admins));
} else {
    $adminsStr = $admins; // fallback if string
}
$adminsArr = explode(',', $adminsStr);
if (!in_array($userId, $adminsArr)) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

// Prepare categories string: id-name
$categoriesStrParts = [];
foreach ($categories as $cat) {
    if (isset($cat['id']) && isset($cat['name'])) {
        $categoriesStrParts[] = $cat['id'] . '-' . $cat['name'];
    }
}
$categoriesStr = implode(',', $categoriesStrParts);

// Prepare songs string: category_id-song_id
$songsStrParts = [];
foreach ($songs as $song) {
    if (isset($song['category_id']) && isset($song['song_id'])) {
        $songsStrParts[] = $song['category_id'] . '-' . $song['song_id'];
    }
}
$songsStr = implode(',', $songsStrParts);

$groupIdVal = ($group_id === '' || $group_id === null) ? 'NULL' : intval($group_id);

$sql = "UPDATE songbooks SET
    name = ?,
    description = ?,
    admins = ?,
    categories = ?,
    songs = ?,
    group_id = $groupIdVal,
    is_public = ?,
    modified_date = NOW()
WHERE id = ?";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param(
    "ssssiii",
    $name,
    $description,
    $adminsStr,
    $categoriesStr,
    $songsStr,
    $is_public,
    $id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
}

$stmt->close();
$mysqli->close();
