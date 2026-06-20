<?php
session_start();
require_once '../db.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in to vote.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $input['type'] ?? '';
$id = isset($input['id']) ? (int)$input['id'] : 0;

if (!in_array($type, ['song', 'songbook'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$username = $_SESSION['user'];

if ($type === 'songbook') {
    $stmt = $mysqli->prepare("SELECT songbook_id FROM wishlist_songbooks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Wishlist entry not found.']);
        exit;
    }

    $songbook = get_songbook($mysqli, $row['songbook_id']);
    $isAdmin = !empty($_SESSION['admin_view']);
    $userGroupId = get_user_group($mysqli, $_SESSION['id']);

    if (!$songbook || !can_vote_on_wishlist_songbook($songbook, $_SESSION['id'], $userGroupId, $isAdmin)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not allowed to vote on this songbook\'s wishlist.']);
        exit;
    }
}

$result = toggle_wishlist_like($mysqli, $type, $id, $username);
echo json_encode($result);
