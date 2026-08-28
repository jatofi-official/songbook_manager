<?php
session_start();
require_once '../php/db.php';
require_once '../php/functions.php';
require_once '../php/auth.php';

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    exit('Admin access required.');
}

$formId = intval($_GET['form_id'] ?? 0);
$form = $formId ? get_anketa_form($mysqli, $formId) : null;
if (!$form) {
    http_response_code(404);
    exit('Form not found.');
}

$totalItems = 1 + count($form['category_ids']) + ($form['include_basic_questions'] ? 1 : 0);

// Respondents + their progress
$stmt = $mysqli->prepare("
    SELECT MAX(respondent) AS respondent, respondent_key, MAX(is_authenticated) AS is_authenticated, COUNT(DISTINCT category_id) AS answered, MAX(timestamp) AS last_entry
    FROM anketa_responses
    WHERE form_id = ?
    GROUP BY respondent_key
    ORDER BY last_entry DESC
");
$stmt->bind_param("i", $formId);
$stmt->execute();
$respondents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Aggregated per-category stats for the summary view
$stmt = $mysqli->prepare("SELECT category_id, answers FROM anketa_responses WHERE form_id = ? AND category_id >= 0");
$stmt->bind_param("i", $formId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stats = [];
$songIds = [];
foreach ($rows as $row) {
    $categoryId = (int)$row['category_id'];
    if (trim($row['answers']) === '') continue;
    foreach (explode(",", $row['answers']) as $entry) {
        if (trim($entry) === '' || !preg_match('/^(\d+)-(\d*)&(\d*)$/', trim($entry), $m)) continue;
        $songId = (int)$m[1];
        $freq = $m[2];
        $rating = $m[3];
        if ($freq === '' && $rating === '') continue;

        $songIds[$songId] = true;
        $stats[$categoryId][$songId] ??= ['freq_sum' => 0, 'freq_count' => 0, 'rating_sum' => 0, 'rating_count' => 0, 'responses' => 0];
        $s = &$stats[$categoryId][$songId];
        if ($freq !== '') { $s['freq_sum'] += (int)$freq; $s['freq_count']++; }
        if ($rating !== '') { $s['rating_sum'] += (int)$rating; $s['rating_count']++; }
        $s['responses']++;
        unset($s);
    }
}

$songs = [];
if (!empty($songIds)) {
    $ids = implode(",", array_keys($songIds));
    $songRes = $mysqli->query("SELECT id, title, artist FROM songs WHERE id IN ($ids)");
    while ($sRow = $songRes->fetch_assoc()) {
        $songs[(int)$sRow['id']] = $sRow;
    }
}
$freqLabels = [0 => "Ani raz", 1 => "Jeden krát", 2 => "Niekoľko krát", 3 => "Často"];

$website = 'admin';
$title = 'Results: ' . $form['title'];
include '../php/header.php';
include '../php/navigation.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Results — <?= htmlspecialchars($form['title']) ?></h1>
        <a href="anketa.php" class="btn btn-outline-secondary btn-sm">Back to forms</a>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-tab="summary" type="button">Summary</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="respondents" type="button">Respondents</button></li>
    </ul>

    <div id="tab-summary">
        <?php if (empty($stats)): ?>
            <p class="text-muted">No responses yet.</p>
        <?php endif; ?>
        <?php foreach ($stats as $categoryId => $songStats): ?>
            <div class="card mb-4">
                <div class="card-header fw-bold"><?= htmlspecialchars($form['category_names'][$categoryId] ?? "Kategória $categoryId") ?></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Pieseň</th><th class="text-center">Priem. hodnotenie</th><th class="text-center">Priem. hrateľnosť</th><th class="text-center">Počet odpovedí</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($songStats as $songId => $s): $song = $songs[$songId] ?? null; if (!$song) continue; ?>
                            <tr>
                                <td><?= htmlspecialchars($song['title']) ?> <small class="text-muted"><?= htmlspecialchars($song['artist'] ?? '') ?></small></td>
                                <td class="text-center"><?= $s['rating_count'] ? round($s['rating_sum'] / $s['rating_count'], 2) : '—' ?></td>
                                <td class="text-center"><?= $s['freq_count'] ? round($s['freq_sum'] / $s['freq_count'], 2) : '—' ?></td>
                                <td class="text-center"><?= $s['responses'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="tab-respondents" class="d-none">
        <div class="row">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">Respondenti</div>
                    <div class="list-group list-group-flush" id="respondentList">
                        <?php foreach ($respondents as $r): $percent = round(($r['answered'] / $totalItems) * 100); ?>
                        <button class="list-group-item list-group-item-action respondent-item" data-key="<?= htmlspecialchars($r['respondent_key']) ?>">
                            <div class="fw-bold"><?= htmlspecialchars($r['respondent']) ?> <?= $r['is_authenticated'] ? '' : '<span class="badge bg-info text-dark">heslo</span>' ?></div>
                            <div class="progress mt-2" style="height: 15px;">
                                <div class="progress-bar <?= $percent < 50 ? 'bg-danger' : ($percent < 100 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= $percent ?>%;">
                                    <?= (int)$r['answered'] ?>/<?= $totalItems ?>
                                </div>
                            </div>
                            <div class="small text-muted mt-1">Posledný záznam: <?= htmlspecialchars($r['last_entry']) ?></div>
                        </button>
                        <?php endforeach; ?>
                        <?php if (empty($respondents)): ?><div class="p-3 text-muted">Zatiaľ žiadni respondenti.</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div id="detailPanel" class="card h-100" style="min-height: 60vh;">
                    <div class="card-body text-muted d-flex align-items-center justify-content-center">
                        Vyberte respondenta z ľavej strany…
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.nav-link[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.nav-link[data-tab]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-summary').classList.toggle('d-none', btn.dataset.tab !== 'summary');
        document.getElementById('tab-respondents').classList.toggle('d-none', btn.dataset.tab !== 'respondents');
    });
});

const anketaFormId = <?= (int)$formId ?>;
const anketaCategoryIds = <?= json_encode($form['category_ids']) ?>;
const anketaCategoryNames = <?= json_encode($form['category_names']) ?>;
const anketaIncludeBasicQuestions = <?= $form['include_basic_questions'] ? 'true' : 'false' ?>;
const detailPanel = document.getElementById('detailPanel');

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.querySelectorAll('.respondent-item').forEach(btn => {
    btn.addEventListener('click', function () {
        const key = this.dataset.key;
        document.querySelectorAll('.respondent-item').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const firstCat = anketaIncludeBasicQuestions ? -2 : -1;
        fetch(`../php/subpage/load_anketa_answers.php?form_id=${anketaFormId}&respondent=${encodeURIComponent(key)}&category=${firstCat}`)
            .then(res => res.text())
            .then(html => {
                const buttons = [];
                if (anketaIncludeBasicQuestions) {
                    buttons.push(`<button class="btn btn-outline-secondary btn-sm cat-btn active" data-cat="-2">Základné otázky</button>`);
                }
                buttons.push(...anketaCategoryIds.map(id => `<button class="btn btn-outline-secondary btn-sm cat-btn" data-cat="${id}">${escapeHtml(anketaCategoryNames[id] ?? id)}</button>`));
                buttons.push(`<button class="btn btn-outline-secondary btn-sm cat-btn${anketaIncludeBasicQuestions ? '' : ' active'}" data-cat="-1">Poznámky</button>`);
                detailPanel.innerHTML = `
                    <div class="card-header">
                        <div class="btn-group mb-3 flex-wrap" role="group" id="categoryButtons">${buttons.join('')}</div>
                    </div>
                    <div class="card-body" id="answersContent">${html}</div>
                `;
                detailPanel.querySelectorAll('.cat-btn').forEach(catBtn => {
                    catBtn.addEventListener('click', function () {
                        detailPanel.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        fetch(`../php/subpage/load_anketa_answers.php?form_id=${anketaFormId}&respondent=${encodeURIComponent(key)}&category=${this.dataset.cat}`)
                            .then(res => res.text())
                            .then(html => { document.getElementById('answersContent').innerHTML = html; });
                    });
                });
            });
    });
});
</script>

<style>
.rating-circle {
    width: 35px; height: 35px; border-radius: 50%; display: inline-flex; justify-content: center;
    align-items: center; font-weight: bold; color: white; background-color: #808080;
}
</style>

<?php include '../php/footer.php'; ?>
