<?php
session_start();
include "../db.php";

if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    http_response_code(403);
    exit("Access denied");
}

$categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$songId = isset($_GET['song_id']) ? intval($_GET['song_id']) : 0;

header('Content-Type: application/json; charset=utf-8');

if ($categoryId < 1 || $songId < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $mysqli->prepare("
    SELECT v.answers, v.username, u.name
    FROM vysledky_ankety_25 v
    JOIN users u ON v.username = u.username
    WHERE v.category_id = ?
");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$result = $stmt->get_result();

$freqLabels = [0 => "Ani raz", 1 => "Jeden krát", 2 => "Niekoľko krát", 3 => "Často"];

$responses = [];
while ($row = $result->fetch_assoc()) {
    if ($row['answers'] === null || trim($row['answers']) === '') continue;

    foreach (explode(",", $row['answers']) as $entry) {
        $entry = trim($entry);
        if ($entry === "") continue;
        if (!preg_match('/^(\d+)-(\d*)&(\d*)$/', $entry, $m)) continue;

        if ((int)$m[1] !== $songId) continue;

        $freq = $m[2];
        $rating = $m[3];
        if ($freq === '' && $rating === '') continue;

        $responses[] = [
            'username' => $row['username'],
            'name' => $row['name'],
            'freq' => $freq === '' ? null : (int)$freq,
            'freq_label' => $freq === '' ? null : ($freqLabels[(int)$freq] ?? null),
            'rating' => $rating === '' ? null : (int)$rating,
        ];
        break;
    }
}

echo json_encode($responses);
