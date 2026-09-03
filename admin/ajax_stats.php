<?php
session_start();
include_once('../php/data.php');
include_once('../php/auth.php');
include_once('../php/db.php');
include_once('../php/functions.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$categoryId = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;
$onlyPublic = isset($_GET['public']) && $_GET['public'] === '1' ? true : null;
$onlyFinished = isset($_GET['finished']) && $_GET['finished'] === '1' ? true : null;

$songsCounts = get_number_of_songs($mysqli, null, $categoryId, $onlyPublic, $onlyFinished);
$topOwners = get_top_song_owners($mysqli, 10, $categoryId, $onlyPublic, $onlyFinished);
$difficultyCounts = get_app_wide_difficulty_counts($mysqli, $categoryId, $onlyPublic, $onlyFinished);
$topInstruments = get_app_wide_instrument_counts($mysqli, 15, $categoryId, $onlyPublic, $onlyFinished);
$topTags = ($categoryId !== null || $onlyPublic || $onlyFinished)
    ? get_filtered_song_tag_counts($mysqli, $categoryId, $onlyPublic, $onlyFinished, 20)
    : get_top_song_tags($mysqli, 20);

// Top song contributors
ob_start();
if (!$topOwners) {
    ?><p class="text-muted small mb-0">No data yet.</p><?php
} else {
    ?>
    <div class="row row-cols-1 row-cols-md-2 g-4 align-items-center">
        <div class="col">
            <ol class="mb-0 ps-3">
                <?php foreach ($topOwners as $row): ?>
                    <li class="small mb-1"><?= htmlspecialchars($row['name']) ?> &mdash; <?= (int)$row['total'] ?> songs</li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="col d-flex justify-content-center">
            <?= render_admin_pie_with_legend(
                array_map(fn($row) => ['label' => $row['name'], 'count' => (int)$row['total']], $topOwners),
                130
            ) ?>
        </div>
    </div>
    <?php
}
$topOwnersHtml = ob_get_clean();

// Difficulty distribution pie
$difficultyHtml = render_admin_pie_with_legend([
    ['label' => 'Easy', 'count' => $difficultyCounts['easy']],
    ['label' => 'Normal', 'count' => $difficultyCounts['normal']],
    ['label' => 'Hard', 'count' => $difficultyCounts['hard']],
    ['label' => 'Not set', 'count' => $difficultyCounts['none']],
], 130, ['#198754', '#6c757d', '#dc3545', '#adb5bd']);

// Most used tags
ob_start();
if (!$topTags) {
    ?><p class="text-muted small mb-0">No tags used yet.</p><?php
} else {
    $tagMax = max(1, ...array_map(fn($t) => (int)$t['usage_count'], $topTags));
    foreach ($topTags as $tag) {
        echo render_stat_bar_row($tag['name'], (int)$tag['usage_count'], $tagMax);
    }
}
$topTagsHtml = ob_get_clean();

// Instrument usage
ob_start();
if (!$topInstruments) {
    ?><p class="text-muted small mb-0">No instruments tagged yet.</p><?php
} else {
    $instrumentMax = max(1, ...array_values($topInstruments));
    foreach ($topInstruments as $instrument => $count) {
        echo render_stat_bar_row($instrument, $count, $instrumentMax, 'bg-warning');
    }
}
$topInstrumentsHtml = ob_get_clean();

echo json_encode([
    'songsCount' => $songsCounts['total'],
    'topOwners' => $topOwnersHtml,
    'difficulty' => $difficultyHtml,
    'topTags' => $topTagsHtml,
    'topInstruments' => $topInstrumentsHtml,
]);
