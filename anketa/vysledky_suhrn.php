<?php
session_start();
$title = "Súhrn výsledkov";
// Universal include path
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include_once $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';
?>

<?php
if (isset($_SESSION['admin']) && $_SESSION["admin"] == true) {

    // Load category names
    $catRes = $mysqli->query("SELECT categories FROM songbooks LIMIT 1");
    $categoriesString = $catRes->fetch_assoc()['categories'];
    $categories = [];
    foreach (explode(",", $categoriesString) as $pair) {
        list($id, $name) = explode("-", $pair, 2);
        $categories[(int)$id] = trim($name);
    }

    // Load all per-category answers (category_id 1..13, -1 is "Základ" and has no song data)
    $result = $mysqli->query("SELECT category_id, answers FROM vysledky_ankety_25 WHERE category_id >= 1");

    // Aggregate: $stats[category_id][song_id] = ['freq_sum'=>, 'freq_count'=>, 'rating_sum'=>, 'rating_count'=>, 'responses'=>]
    $stats = [];
    $songIds = [];

    while ($row = $result->fetch_assoc()) {
        $categoryId = (int)$row['category_id'];
        if ($row['answers'] === null || trim($row['answers']) === '') continue;

        foreach (explode(",", $row['answers']) as $entry) {
            $entry = trim($entry);
            if ($entry === "") continue;
            if (!preg_match('/^(\d+)-(\d*)&(\d*)$/', $entry, $m)) continue;

            $songId = (int)$m[1];
            $freq = $m[2];
            $rating = $m[3];

            if ($freq === '' && $rating === '') continue; // no answer given for this song

            $songIds[$songId] = true;

            if (!isset($stats[$categoryId][$songId])) {
                $stats[$categoryId][$songId] = [
                    'freq_sum' => 0, 'freq_count' => 0,
                    'rating_sum' => 0, 'rating_count' => 0,
                    'responses' => 0,
                ];
            }
            $s = &$stats[$categoryId][$songId];

            if ($freq !== '') {
                $s['freq_sum'] += (int)$freq;
                $s['freq_count']++;
            }
            if ($rating !== '') {
                $s['rating_sum'] += (int)$rating;
                $s['rating_count']++;
            }
            $s['responses']++;
            unset($s);
        }
    }

    // Fetch song titles/artists
    $songs = [];
    if (!empty($songIds)) {
        $ids = implode(",", array_keys($songIds));
        $songRes = $mysqli->query("SELECT id, title, artist FROM songs WHERE id IN ($ids)");
        while ($sRow = $songRes->fetch_assoc()) {
            $songs[(int)$sRow['id']] = $sRow;
        }
    }

    $freqLabels = [0 => "Ani raz", 1 => "Jeden krát", 2 => "Niekoľko krát", 3 => "Často"];

    ksort($stats);
    ?>

    <div class="container mt-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Súhrn výsledkov podľa kategórií</h3>
            <a href="vysledky.php" class="btn btn-outline-secondary btn-sm">Detail podľa používateľa</a>
        </div>

        <?php if (empty($stats)): ?>
            <p class="text-muted">Zatiaľ nie sú k dispozícii žiadne odpovede.</p>
        <?php endif; ?>

        <?php foreach ($stats as $categoryId => $songStats): ?>
            <div class="card mb-4">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span><?= htmlspecialchars($categories[$categoryId] ?? ("Kategória " . $categoryId)) ?></span>
                    <button type="button" class="btn btn-sm btn-outline-primary export-csv-btn"
                            data-category-name="<?= htmlspecialchars($categories[$categoryId] ?? ("Kategoria_" . $categoryId)) ?>">
                        Export CSV
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 summary-table">
                        <thead>
                            <tr>
                                <th>Pieseň</th>
                                <th class="text-center" style="width:160px">
                                    <button type="button" class="sortable btn btn-outline-secondary btn-sm" data-sort="rating">Priem. hodnotenie</button>
                                </th>
                                <th class="text-center" style="width:180px">
                                    <button type="button" class="sortable btn btn-outline-secondary btn-sm" data-sort="freq">Priem. hrateľnosť</button>
                                </th>
                                <th class="text-center" style="width:120px">Počet odpovedí</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Default order: rating desc (unrated last)
                            uasort($songStats, function ($a, $b) {
                                $avgA = $a['rating_count'] > 0 ? $a['rating_sum'] / $a['rating_count'] : -1;
                                $avgB = $b['rating_count'] > 0 ? $b['rating_sum'] / $b['rating_count'] : -1;
                                return $avgB <=> $avgA;
                            });

                            $idx = 0;
                            foreach ($songStats as $songId => $s):
                                $song = $songs[$songId] ?? ['title' => "Pieseň #$songId", 'artist' => ''];
                                $avgRating = $s['rating_count'] > 0 ? $s['rating_sum'] / $s['rating_count'] : null;
                                $avgFreq = $s['freq_count'] > 0 ? $s['freq_sum'] / $s['freq_count'] : null;
                                $freqIndex = $avgFreq !== null ? (int)round($avgFreq) : null;
                                // sortable values scaled x100 to keep two decimals as integers; empty -> ""
                                $ratingSortVal = $avgRating !== null ? (int)round($avgRating * 100) : '';
                                $freqSortVal = $avgFreq !== null ? (int)round($avgFreq * 100) : '';
                                $ratingRaw = $avgRating !== null ? number_format($avgRating, 2) : '';
                                $freqRaw = $avgFreq !== null ? number_format($avgFreq, 2) : '';
                            ?>
                                <tr class="answer-row song-row" role="button"
                                    data-index="<?= $idx ?>"
                                    data-rating="<?= $ratingSortVal ?>"
                                    data-freq="<?= $freqSortVal ?>"
                                    data-title="<?= htmlspecialchars($song['title']) ?>"
                                    data-artist="<?= htmlspecialchars($song['artist'] ?? '') ?>"
                                    data-rating-raw="<?= $ratingRaw ?>"
                                    data-freq-raw="<?= $freqRaw ?>"
                                    data-responses="<?= (int)$s['responses'] ?>"
                                    data-song-id="<?= (int)$songId ?>"
                                    data-category-id="<?= (int)$categoryId ?>">
                                    <td>
                                        <span class="fw-bold"><?= htmlspecialchars($song['title']) ?></span>
                                        <?php if (!empty($song['artist'])): ?>
                                            <span class="small text-muted ms-1"><?= htmlspecialchars($song['artist']) ?></span>
                                        <?php endif; ?>
                                        <a href="../song.php?id=<?= (int)$songId ?>" target="_blank" rel="noopener"
                                           class="btn btn-outline-secondary btn-sm ms-2 open-song-btn">Otvoriť pieseň</a>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($avgRating !== null): ?>
                                            <div class="rating-circle mx-auto" data-value="<?= (int)round($avgRating) ?>">
                                                <?= number_format($avgRating, 2) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($avgFreq !== null): ?>
                                            <?= number_format($avgFreq, 2) ?>
                                            <span class="small text-muted">(<?= htmlspecialchars($freqLabels[$freqIndex] ?? '') ?>)</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int)$s['responses'] ?></td>
                                </tr>
                            <?php $idx++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
    document.querySelectorAll('.summary-table').forEach(function (table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var originalOrder = Array.from(tbody.querySelectorAll('.answer-row'));

        table.querySelectorAll('button.sortable').forEach(function (btn) {
            if (!btn.dataset.label) btn.dataset.label = btn.textContent.trim();
            btn.dataset.order = 'default';
        });

        table.querySelectorAll('thead').forEach(function (thead) {
            thead.addEventListener('click', function (e) {
                var btn = e.target.closest('button.sortable');
                if (!btn) return;

                var sortField = btn.dataset.sort; // 'rating' | 'freq'
                var current = btn.dataset.order || 'default';
                var next = (current === 'default') ? 'asc' : (current === 'asc' ? 'desc' : 'default');

                // reset labels/arrows for all sortable buttons in this table
                table.querySelectorAll('button.sortable').forEach(function (b) {
                    b.dataset.order = 'default';
                    b.textContent = b.dataset.label;
                });

                btn.dataset.order = next;
                if (next === 'asc') btn.textContent = btn.dataset.label + ' ▲';
                else if (next === 'desc') btn.textContent = btn.dataset.label + ' ▼';
                else btn.textContent = btn.dataset.label;

                if (next === 'default') {
                    originalOrder.forEach(function (r) { tbody.appendChild(r); });
                    return;
                }

                var val = function (row) {
                    var raw = row.dataset[sortField];
                    if (raw === '' || raw === undefined) return -999;
                    var n = parseInt(raw, 10);
                    return Number.isNaN(n) ? -999 : n;
                };

                var rows = Array.from(tbody.querySelectorAll('.answer-row'));
                rows.sort(function (a, b) {
                    var va = val(a), vb = val(b);
                    if (va === vb) {
                        return parseInt(a.dataset.index, 10) - parseInt(b.dataset.index, 10);
                    }
                    return next === 'asc' ? (va - vb) : (vb - va);
                });

                rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    });

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    document.querySelectorAll('.summary-table tbody').forEach(function (tbody) {
        tbody.addEventListener('click', function (e) {
            if (e.target.closest('.open-song-btn')) return;
            var row = e.target.closest('.song-row');
            if (!row || !tbody.contains(row)) return;

            var next = row.nextElementSibling;
            if (next && next.classList.contains('song-detail-row')) {
                next.remove();
                return;
            }

            // collapse any other open detail row in this tbody
            var openDetail = tbody.querySelector('.song-detail-row');
            if (openDetail) openDetail.remove();

            var detailRow = document.createElement('tr');
            detailRow.className = 'song-detail-row';
            var td = document.createElement('td');
            td.colSpan = 4;
            td.innerHTML = '<div class="text-muted small p-2">Načítavam odpovede…</div>';
            detailRow.appendChild(td);
            row.insertAdjacentElement('afterend', detailRow);

            var url = '../php/subpage/song_responses.php?category_id=' + row.dataset.categoryId + '&song_id=' + row.dataset.songId;
            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (responses) {
                    if (!responses.length) {
                        td.innerHTML = '<div class="text-muted small p-2">Žiadne odpovede.</div>';
                        return;
                    }
                    var html = '<div class="p-2"><table class="table table-sm mb-0">' +
                        '<thead><tr><th>Meno</th><th class="text-center">Hodnotenie</th><th class="text-center">Hrateľnosť</th></tr></thead><tbody>';
                    responses.forEach(function (r) {
                        var freqCell = r.freq_label ? r.freq_label : '<span class="text-muted">-</span>';
                        var ratingCell = (r.rating !== null && r.rating !== undefined)
                            ? '<div class="rating-circle mx-auto" data-value="' + r.rating + '">' + r.rating + '</div>'
                            : '<span class="text-muted">-</span>';
                        html += '<tr><td>' + escapeHtml(r.name || r.username) + '</td>' +
                            '<td class="text-center">' + ratingCell + '</td>' +
                            '<td class="text-center">' + freqCell + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    td.innerHTML = html;
                })
                .catch(function () {
                    td.innerHTML = '<div class="text-danger small p-2">Chyba pri načítaní odpovedí.</div>';
                });
        });
    });

    function csvEscape(value) {
        value = String(value ?? '');
        if (/[",\n]/.test(value)) {
            value = '"' + value.replace(/"/g, '""') + '"';
        }
        return value;
    }

    document.querySelectorAll('.export-csv-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.card');
            var table = card ? card.querySelector('.summary-table') : null;
            if (!table) return;

            var header = ['Pieseň', 'Interpret', 'Priemerné hodnotenie', 'Priemerná hrateľnosť', 'Počet odpovedí'];
            var lines = [header.map(csvEscape).join(',')];

            table.querySelectorAll('tbody .answer-row').forEach(function (row) {
                lines.push([
                    row.dataset.title || '',
                    row.dataset.artist || '',
                    row.dataset.ratingRaw || '',
                    row.dataset.freqRaw || '',
                    row.dataset.responses || '0'
                ].map(csvEscape).join(','));
            });

            var csvContent = '﻿' + lines.join('\r\n');
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);

            var categoryName = (btn.dataset.categoryName || 'kategoria').replace(/[^a-zA-Z0-9_-]+/g, '_');
            var a = document.createElement('a');
            a.href = url;
            a.download = 'vysledky_' + categoryName + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    });
    </script>

    <style>
    .rating-circle[data-value="1"] { background-color: #FF0000; color: #fff; }
    .rating-circle[data-value="2"] { background-color: #FF6666; }
    .rating-circle[data-value="3"] { background-color: #808080; color: #fff; }
    .rating-circle[data-value="4"] { background-color: #66CC66; }
    .rating-circle[data-value="5"] { background-color: #008000; color: #fff; }

    .rating-circle {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 56px;
        height: 32px;
        border-radius: 1rem;
        font-weight: bold;
        font-size: 0.8rem;
    }

    .song-row { cursor: pointer; }
    .song-detail-row td { background-color: #f8f9fa; }
    </style>

<?php
} else { ?>
    <div class="container mt-5 text-center">
        <p class="text-danger">Anketa je prístupná iba pre členov 24. zboru Mustangy</p>
    </div>
<?php }
?>

<?php include $phpBase . 'footer.php'; ?>
