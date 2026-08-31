<?php
session_start();
require_once 'db.php';
require_once "functions.php";

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

$existing = get_songbook($mysqli, (int)$id);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Songbook not found']);
    exit;
}

$isOwner = (int)$existing['owner'] === (int)$userId;
$isSiteAdmin = isset($_SESSION['admin_view']) && $_SESSION['admin_view'] === true;

// Permission check: current user must already be an admin (or owner/site admin)
if (!in_array((string)$userId, $existing['admins'], true) && !$isOwner && !$isSiteAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if (is_array($admins)) {
    $adminsStr = implode(',', array_map('intval', $admins));
} else {
    $adminsStr = $admins; // fallback if string
}
$adminsArr = array_filter(explode(',', $adminsStr), 'strlen');

// Only the owner or a site admin may change who the admins are. The owner
// must also always remain an admin, regardless of what was posted.
$existingAdminsSorted = $existing['admins'];
sort($existingAdminsSorted);
$postedAdminsSorted = $adminsArr;
sort($postedAdminsSorted);
if ($postedAdminsSorted !== $existingAdminsSorted) {
    if (!$isOwner && !$isSiteAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the songbook owner or a site admin can change admins']);
        exit;
    }
    if (!in_array((string)$existing['owner'], $adminsArr, true)) {
        $adminsArr[] = (string)$existing['owner'];
    }
}
$adminsStr = implode(',', $adminsArr);

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

$result = update_songbook($mysqli,$id,$name,$description,$adminsStr,$categoriesStr,$songsStr,$groupIdVal,$is_public);

if ($result) {
    // ✅ Log activity
    if (isset($_SESSION['user'])) {
        add_activity(
            $mysqli,
            $_SESSION['user'],
            "Edited songbook",
            "User " . $_SESSION['user'] . " edited songbook " . $name,
            0
        );
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed']);
}
