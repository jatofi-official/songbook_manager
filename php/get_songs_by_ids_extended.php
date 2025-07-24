<?php
include("db.php");
include("functions.php");

$data = json_decode(file_get_contents("php://input"), true);
$ids = $data["ids"] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode([]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$stmt = $mysqli->prepare("SELECT id, title, artist, content, capo, capo_alt, owner FROM songs WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$ids);
$stmt->execute();

$result = $stmt->get_result();
$songs = [];

while ($row = $result->fetch_assoc()) {
    $user = get_user_by_username($mysqli, $row['owner']);
    $row['owner'] = [
        'id' => $user ? $user['id'] : null,
        'username' => $row['owner'],
        'name' => $user ? $user['name'] : $row['owner']
    ];
    $songs[] = $row;
}

echo json_encode($songs);
