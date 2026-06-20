<?php
session_start();
require_once '../db.php';
require_once '../functions.php';

header('Content-Type: application/json');

$search = trim($_GET['search'] ?? '');
if ($search === '') {
    echo json_encode([]);
    exit;
}

$result = get_all_songs($mysqli, 20, 1, $search, 'all', [], [], null);

$songs = array_map(function ($song) {
    return [
        'id' => (int)$song['id'],
        'title' => $song['title'],
        'artist' => $song['artist'],
    ];
}, $result['songs']);

echo json_encode($songs);
