<?php
session_start();
require_once "../db.php";
require_once "../functions.php";

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    exit("Access denied");
}

$formId = intval($_GET['form_id'] ?? 0);
$respondentKey = $_GET['respondent'] ?? '';
$categoryId = intval($_GET['category'] ?? -1);

$form = $formId ? get_anketa_form($mysqli, $formId) : null;
if (!$form || $respondentKey === '') {
    exit("<p>Invalid request.</p>");
}

$stmt = $mysqli->prepare("SELECT answers FROM anketa_responses WHERE form_id = ? AND respondent_key = ? AND category_id = ?");
$stmt->bind_param("isi", $formId, $respondentKey, $categoryId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($categoryId === -1) {
    echo "<h5>Všeobecné poznámky</h5>";
    if ($row && trim($row['answers']) !== '') {
        echo "<div class='border rounded p-2 mb-2 bg-light'>" . nl2br(htmlspecialchars($row['answers'])) . "</div>";
    } else {
        echo "<p class='text-muted'>Žiadne poznámky.</p>";
    }
    exit;
}

if ($categoryId === -2) {
    echo "<h5>Základné otázky</h5>";
    if ($row && trim($row['answers']) !== '') {
        $labels1 = [
            'oba_týždne_predcata' => 'Oba týždne + predčata', 'oba_týždne' => 'Oba týždne',
            'prvy_tyzd' => 'Prvý týždeň', 'druhy_tyzd' => 'Druhý týždeň', 'menej_ako_tyzd' => 'Menej ako týždeň',
        ];
        $labels2 = ['kazdy_krat' => 'Každý krát', 'velmi_casto' => 'Veľmi často', 'casto' => 'Často', 'obcas' => 'Občas'];
        [$v1, $v2] = array_pad(explode('&', $row['answers']), 2, '');
        echo "<div class='border rounded p-2 mb-2 bg-light'><strong>Ako dlho si bol na tábore?</strong> " . htmlspecialchars($labels1[$v1] ?? $v1) . "</div>";
        echo "<div class='border rounded p-2 mb-2 bg-light'><strong>Ako často si chodieval na táboráčiky?</strong> " . htmlspecialchars($labels2[$v2] ?? $v2) . "</div>";
    } else {
        echo "<p class='text-muted'>Žiadne odpovede.</p>";
    }
    exit;
}

echo "<div class='p-2 mb-3 text-dark rounded'><h4 class='m-0'>" . htmlspecialchars($form['category_names'][$categoryId] ?? '') . "</h4></div>";

if (!$row || trim($row['answers']) === '') {
    echo "<p>Žiadne odpovede.</p>";
    exit;
}

$freqLabels = [0 => "Ani raz", 1 => "Jeden krát", 2 => "Niekoľko krát", 3 => "Často"];
$freqColors = [
    0 => "bg-danger-light text-dark",
    1 => "bg-warning-light text-dark",
    2 => "bg-success-light text-dark",
    3 => "bg-success-dark text-dark",
];

echo "<div class='d-flex fw-bold mb-2 table-header align-items-center'>
        <div class='flex-grow-1'>Pieseň</div>
        <div class='text-center' style='width:150px'>Frekvencia</div>
        <div class='text-center' style='width:120px'>Hodnotenie</div>
      </div>";

echo "<div class='row row-cols-1 gy-3'>";
foreach (explode(",", $row['answers']) as $entry) {
    if (trim($entry) === "" || !preg_match('/^(\d+)-(\d*)&(\d*)$/', trim($entry), $m)) continue;

    $songId = (int)$m[1];
    $often = $m[2];
    $like = $m[3];

    $songStmt = $mysqli->prepare("SELECT title, artist FROM songs WHERE id = ?");
    $songStmt->bind_param("i", $songId);
    $songStmt->execute();
    $song = $songStmt->get_result()->fetch_assoc();
    $songStmt->close();
    if (!$song) continue;

    if ($often === '') {
        $oftenBadge = "-";
    } else {
        $freqValue = $freqLabels[(int)$often] ?? "-";
        $freqClass = $freqColors[(int)$often] ?? "bg-secondary text-white";
        $oftenBadge = "<span class='badge $freqClass px-3 py-2'>$freqValue</span>";
    }
    $likeCircle = $like !== "" ? "<div class='rating-circle' data-value='" . intval($like) . "'>$like</div>" : "-";

    echo "<div class='col'>
            <div class='d-flex align-items-center border rounded p-3 h-100'>
                <div class='flex-grow-1 d-flex align-items-baseline'>
                    <div class='fw-bold text-dark me-2'>" . htmlspecialchars($song['title']) . "</div>
                    <div class='small text-muted'>" . htmlspecialchars($song['artist'] ?? '') . "</div>
                </div>
                <div class='text-center' style='width:150px'>$oftenBadge</div>
                <div class='text-center' style='width:120px'>$likeCircle</div>
            </div>
          </div>";
}
echo "</div>";
