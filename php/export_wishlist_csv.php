<?php
session_start();
require_once 'db.php';
require_once 'functions.php';
require_once 'auth.php';

if (empty($_SESSION['id'])) {
    http_response_code(401);
    exit('Log in to export a checklist.');
}

$songbookId = (int)($_GET['songbook_id'] ?? 0);
$allowed = false;
foreach (get_songbooks_for_wishlist($mysqli) as $songbook) {
    if ((int)$songbook['id'] === $songbookId) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    exit('You do not have access to this checklist.');
}

$songbook = get_songbook($mysqli, $songbookId);
$result = get_wishlist_songbook_entries($mysqli, $songbookId, $_SESSION['user'] ?? null, [
    'page' => 1,
    'perPage' => 100000,
]);
$safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $songbook['name'] ?? 'songbook');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . trim($safeName, '-') . '-checklist.csv"');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Status', 'Song', 'Artist', 'Existing song', 'Votes', 'Requested by', 'Created']);
foreach ($result['items'] as $item) {
    fputcsv($output, [
        'Open',
        $item['name'],
        $item['artist'] ?? '',
        $item['is_existing_song'] ? 'Yes' : 'No',
        (int)$item['likes'],
        $item['username'] ?? '',
        $item['create_date'] ?? '',
    ]);
}
fclose($output);
