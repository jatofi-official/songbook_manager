<?php
session_start();
require_once '../php/db.php';
require_once '../php/functions.php';
require_once '../php/auth.php';

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    exit('Admin access required.');
}

$result = $mysqli->query("SELECT id, title, artist, owner, category, capo, capo_alt, is_public, tags, instruments,
    content, is_fixed, is_nsfw, youtube_link, chord_source, strumming_pattern, original_artist, translator,
    views, create_date, modified_date
    FROM songs ORDER BY title, artist");
$songs = $result->fetch_all(MYSQLI_ASSOC);
$categories = get_all_categories($mysqli);

if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="songs-catalog.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Title', 'Artist', 'Owner', 'Category', 'Capo', 'Alternative capo', 'Visibility', 'Tags', 'Difficulty', 'Instruments', 'Chord source', 'Strumming', 'Original artist', 'Translator', 'Fixed', 'NSFW', 'YouTube', 'Views', 'Created', 'Modified', 'Content']);
    foreach ($songs as $song) {
        $filtered = filter_difficulty_tags($song['tags']);
        fputcsv($out, [
            $song['id'], $song['title'], $song['artist'], $song['owner'], $categories[$song['category']] ?? $song['category'],
            $song['capo'], $song['capo_alt'], $song['is_public'] ? 'Public' : 'Private', $filtered['tags'], $filtered['difficulty'], $song['instruments'],
            $song['chord_source'], $song['strumming_pattern'], $song['original_artist'], $song['translator'], $song['is_fixed'], $song['is_nsfw'],
            $song['youtube_link'], $song['views'], $song['create_date'], $song['modified_date'], $song['content']
        ]);
    }
    fclose($out);
    exit;
}

$website = 'admin';
$title = 'Song catalog';
include '../php/header.php';
include '../php/navigation.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Song catalog</h1>
        <a href="?format=csv" class="btn btn-success">Export CSV</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead><tr><th>ID</th><th>Song</th><th>Owner</th><th>Category</th><th>Metadata</th><th>Visibility</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($songs as $song): $difficulty = filter_difficulty_tags($song['tags'])['difficulty']; ?>
                <tr>
                    <td><?= (int)$song['id'] ?></td>
                    <td><a href="../song.php?id=<?= (int)$song['id'] ?>"><?= htmlspecialchars($song['title']) ?></a><br><small><?= htmlspecialchars($song['artist'] ?? '') ?></small></td>
                    <td><?= htmlspecialchars($song['owner']) ?></td>
                    <td><?= htmlspecialchars($categories[$song['category']] ?? $song['category']) ?></td>
                    <td><small><?= htmlspecialchars(implode(' · ', array_filter(['Capo ' . ($song['capo'] ?? 0), $difficulty, $song['tags'], $song['instruments'], $song['chord_source'], $song['strumming_pattern']]))) ?></small></td>
                    <td><?= $song['is_public'] ? 'Public' : 'Private' ?></td>
                    <td><?= (int)$song['views'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../php/footer.php'; ?>
