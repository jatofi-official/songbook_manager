<?php
include("db.php");
include("functions.php");

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    echo json_encode([]);
    exit;
}

$songId = (int)$_GET["id"];
$song = get_song_title($mysqli, $songId);

if ($song) {
    $user = get_user_by_username($mysqli, $song['owner']);

    // Replace 'owner' with a structured object
    $song['owner'] = [
        'id' => $user ? $user['id'] : null,
        'username' => $song['owner'],
        'name' => $user ? $user['name'] : $song['owner']
    ];

    echo json_encode($song);
} else {
    echo json_encode([]);
}
