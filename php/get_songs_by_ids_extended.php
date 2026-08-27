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

$stmt = $mysqli->prepare("SELECT id, title, artist, content, capo, capo_alt, owner, tags, instruments,
    category, chord_source, original_artist, translator
    FROM songs WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$ids);
$stmt->execute();

$result = $stmt->get_result();
$categories = get_all_categories($mysqli);
$songs = [];

// Tags render most-used-first (see songbook_render_preview.js), so the
// renderer's overflow/collapse logic always keeps a song's most common tags
// visible and folds its rarer ones away first.
$tagUsage = [];
$tagResult = $mysqli->query("SELECT name, usage_count FROM song_tags");
if ($tagResult) {
    while ($tagRow = $tagResult->fetch_assoc()) {
        $tagUsage[strtolower($tagRow['name'])] = (int)$tagRow['usage_count'];
    }
}

while ($row = $result->fetch_assoc()) {
    $user = get_user_by_username($mysqli, $row['owner']);
    $row['owner'] = [
        'id' => $user ? $user['id'] : null,
        'username' => $row['owner'],
        'name' => $user ? $user['name'] : $row['owner']
    ];

    $filtered = filter_difficulty_tags($row['tags']);
    $row['difficulty'] = $filtered['difficulty'];

    $tagList = array_values(array_filter(array_map('trim', explode(',', $filtered['tags'])), fn($t) => $t !== ''));
    usort($tagList, function ($a, $b) use ($tagUsage) {
        $countA = $tagUsage[strtolower($a)] ?? 0;
        $countB = $tagUsage[strtolower($b)] ?? 0;
        return $countB <=> $countA;
    });
    $row['tags'] = implode(',', $tagList);

    $row['category_name'] = $categories[$row['category']] ?? '';

    $songs[] = $row;
}

echo json_encode($songs);
