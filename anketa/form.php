<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'auth.php';
include_once $phpBase . 'functions.php';

$formId = intval($_GET['form'] ?? 0);
$form = $formId ? get_anketa_form($mysqli, $formId) : null;

$title = $form ? htmlspecialchars($form['title']) : "Anketa";
$website = "none";

include $phpBase . 'header.php';
include $phpBase . 'navigation.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == true;
?>

<div class="container mt-5 text-center">
<?php
if (!$form): ?>
    <p class="text-danger">Anketa nebola nájdená.</p>
<?php elseif (!$form['is_active'] && !$isAdmin): ?>
    <p class="text-danger">Táto anketa už nie je aktívna.</p>
<?php else:

    $hasPassword = !empty($form['password_hash']);
    $access = $_SESSION['anketa_access'][$formId] ?? null;
    $gateError = '';

    // Handle the password/name gate submission
    if (!$access && $hasPassword && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anketa_unlock'])) {
        if (!password_verify($_POST['password'] ?? '', $form['password_hash'])) {
            $gateError = 'Nesprávne heslo.';
        } elseif (isset($_SESSION['user'])) {
            $access = ['respondent' => $_SESSION['user'], 'respondent_key' => $_SESSION['user'], 'authenticated' => true];
        } else {
            $name = trim($_POST['name'] ?? '');
            if (mb_strlen($name) < 2) {
                $gateError = 'Zadaj svoje meno (aspoň 2 znaky).';
            } else {
                $access = ['respondent' => $name, 'respondent_key' => mb_strtolower($name), 'authenticated' => false];
            }
        }
        if ($access) {
            $_SESSION['anketa_access'][$formId] = $access;
        }
    }

    if (!$access && !$hasPassword && isset($_SESSION['user'])) {
        $access = ['respondent' => $_SESSION['user'], 'respondent_key' => $_SESSION['user'], 'authenticated' => true];
        $_SESSION['anketa_access'][$formId] = $access;
    }

    if (!$access):
        if ($hasPassword): ?>
            <div class="card mx-auto my-3 p-3" style="max-width: 400px;">
                <h4>Táto anketa je chránená heslom</h4>
                <?php if ($gateError): ?><p class="text-danger"><?= htmlspecialchars($gateError) ?></p><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="anketa_unlock" value="1">
                    <?php if (!isset($_SESSION['user'])): ?>
                        <div class="mb-3">
                            <label class="form-label">Tvoje meno</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Heslo</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Pokračovať</button>
                </form>
            </div>
        <?php else: ?>
            <p class="mb-3">Musíš byť prihlásený</p>
            <a href="../user_settings.php" class="btn btn-primary me-2">Log in</a>
            <a href="../register_user.php" class="btn btn-success">Register</a>
        <?php endif;

    else:
        // Access granted - resolve the item order: optional basic questions (-2) first,
        // then the form's categories, then the general comment (-1) last.
        $itemOrder = array_merge(
            $form['include_basic_questions'] ? [-2] : [],
            $form['category_ids'],
            [-1]
        );
        $categoryIdParam = $_GET['category_id'] ?? null;
        $categoryId = $categoryIdParam !== null ? intval($categoryIdParam) : null;

        if ($categoryId === null): ?>
            <!-- Introduction page -->
            <div class="card mx-auto my-3 p-3" style="max-width: 600px;">
                <h3>Vitaj v ankete, <?= htmlspecialchars($access['respondent']) ?>!</h3>
                <p>Tento formulár ti umožní ohodnotiť piesne v jednotlivých kategóriách spevníku.</p>
                <p>Hodnotí sa ako často sa pesnička hrala a ako veľmi sa ti páči. Na konci je aj priestor na všeobecné poznámky.</p>
                <a href="?form=<?= $formId ?>&category_id=<?= $itemOrder[0] ?>" class="btn btn-primary btn-lg">Začať</a>
            </div>
        <?php elseif (!in_array($categoryId, $itemOrder, true)): ?>
            <p class="text-danger">Táto položka nie je súčasťou tejto ankety.</p>
        <?php else:
            $currentIndex = array_search($categoryId, $itemOrder, true);
            $isLast = $currentIndex === count($itemOrder) - 1;

            // Existing answer for this respondent+item, if any
            $stmt = $mysqli->prepare("SELECT answers FROM anketa_responses WHERE form_id = ? AND respondent_key = ? AND category_id = ?");
            $stmt->bind_param("isi", $formId, $access['respondent_key'], $categoryId);
            $stmt->execute();
            $existingRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($categoryId === -2):
                $basicAnswers = $existingRow ? explode('&', $existingRow['answers']) : ['', ''];
                $otazka1 = $basicAnswers[0] ?? '';
                $otazka2 = $basicAnswers[1] ?? '';
                ?>
                <h2>Základné otázky</h2>
                <div class="card mx-auto my-3 p-3" style="max-width: 500px;">
                    <label class="form-label"><strong>Ako dlho si bol na tábore?</strong></label>
                    <select class="form-select" id="otazka1">
                        <option value="">-- Vyber --</option>
                        <option value="oba_týždne_predcata" <?= $otazka1 === 'oba_týždne_predcata' ? 'selected' : '' ?>>Oba týždne + predčata</option>
                        <option value="oba_týždne" <?= $otazka1 === 'oba_týždne' ? 'selected' : '' ?>>Oba týždne</option>
                        <option value="prvy_tyzd" <?= $otazka1 === 'prvy_tyzd' ? 'selected' : '' ?>>Prvý týždeň</option>
                        <option value="druhy_tyzd" <?= $otazka1 === 'druhy_tyzd' ? 'selected' : '' ?>>Druhý týždeň</option>
                        <option value="menej_ako_tyzd" <?= $otazka1 === 'menej_ako_tyzd' ? 'selected' : '' ?>>Menej ako týždeň</option>
                    </select>
                </div>
                <div class="card mx-auto my-3 p-3" style="max-width: 500px;">
                    <label class="form-label"><strong>Ako často si chodieval na táboráčiky?</strong></label>
                    <select class="form-select" id="otazka2">
                        <option value="">-- Vyber --</option>
                        <option value="kazdy_krat" <?= $otazka2 === 'kazdy_krat' ? 'selected' : '' ?>>Každý krát</option>
                        <option value="velmi_casto" <?= $otazka2 === 'velmi_casto' ? 'selected' : '' ?>>Veľmi často</option>
                        <option value="casto" <?= $otazka2 === 'casto' ? 'selected' : '' ?>>Často</option>
                        <option value="obcas" <?= $otazka2 === 'obcas' ? 'selected' : '' ?>>Občas</option>
                    </select>
                </div>
                <div class="d-grid gap-2 mt-3" style="max-width: 500px; margin: auto;">
                    <button id="next-btn" class="btn btn-success"><?= $isLast ? 'Ukončiť' : 'Ďalej' ?></button>
                </div>
                <script>
                document.getElementById('next-btn').addEventListener('click', () => {
                    const otazka1 = document.getElementById('otazka1').value;
                    const otazka2 = document.getElementById('otazka2').value;
                    if (!otazka1 || !otazka2) {
                        alert("Prosím, vyber všetky odpovede.");
                        return;
                    }
                    saveAndAdvance(`${otazka1}&${otazka2}`);
                });
                </script>
            <?php elseif ($categoryId === -1): ?>
                <h2>Všeobecné poznámky</h2>
                <div class="card mx-auto my-3 p-3" style="max-width: 600px;">
                    <label class="form-label"><strong>Máš nejaké všeobecné poznámky k spevníku?</strong></label>
                    <textarea id="general-comment" class="form-control" rows="5" placeholder="Nepovinné"><?= htmlspecialchars($existingRow['answers'] ?? '') ?></textarea>
                </div>
                <div class="d-grid gap-2 mt-3" style="max-width: 500px; margin: auto;">
                    <button id="next-btn" class="btn btn-success"><?= $isLast ? 'Ukončiť' : 'Ďalej' ?></button>
                </div>
                <script>
                document.getElementById('next-btn').addEventListener('click', () => {
                    const answers = document.getElementById('general-comment').value;
                    saveAndAdvance(answers);
                });
                </script>
            <?php else:
                $categoryName = $form['category_names'][$categoryId] ?? 'Kategória';

                $stmtLayout = $mysqli->prepare("SELECT ordered_ids FROM songbook_category_layouts WHERE songbook_id = ? AND category_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmtLayout->bind_param("ii", $form['songbook_id'], $categoryId);
                $stmtLayout->execute();
                $layout = $stmtLayout->get_result()->fetch_assoc();
                $stmtLayout->close();

                $songsOrdered = [];
                if ($layout && $layout['ordered_ids'] !== '') {
                    $orderedIds = explode(',', $layout['ordered_ids']);
                    $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
                    $types = str_repeat('i', count($orderedIds));
                    $stmtSongs = $mysqli->prepare("SELECT id, title, artist, youtube_link FROM songs WHERE id IN ($placeholders)");
                    $stmtSongs->bind_param($types, ...$orderedIds);
                    $stmtSongs->execute();
                    $songs = $stmtSongs->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtSongs->close();

                    $songsById = [];
                    foreach ($songs as $song) {
                        $songsById[$song['id']] = $song;
                    }
                    foreach ($orderedIds as $id) {
                        if (isset($songsById[$id])) {
                            $songsOrdered[] = $songsById[$id];
                        }
                    }
                }

                $existingAnswers = [];
                if ($existingRow) {
                    foreach (explode(',', $existingRow['answers']) as $pair) {
                        if (preg_match('/^(\d+)-(\d*)&(\d*)$/', $pair, $matches)) {
                            $existingAnswers[$matches[1]] = ['freq' => $matches[2], 'rating' => $matches[3]];
                        }
                    }
                }
                ?>
                <h2><?= htmlspecialchars($categoryName) ?></h2>

                <?php if (empty($songsOrdered)): ?>
                    <p class="text-muted">Táto kategória zatiaľ neobsahuje žiadne piesne.</p>
                <?php endif; ?>

                <?php foreach ($songsOrdered as $song): ?>
                    <div class="card mx-auto my-3 p-3" style="max-width: 500px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                                <h4 class="mb-0"><?= htmlspecialchars($song['title']) ?></h4>
                                <?php if (!empty($song['artist'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($song['artist']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm btn-text" data-song-id="<?= $song['id'] ?>">Text</button>
                                <?php if (!empty($song['youtube_link'])): ?>
                                    <a href="<?= htmlspecialchars($song['youtube_link']) ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-youtube"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="song-text-<?= $song['id'] ?>" class="mb-3 song-text d-none"
                            style="white-space: pre-wrap; padding: 10px; border-radius: 5px; font-family: 'Fira Mono', monospace; text-align: left;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>Ako často sa hrala?</strong></label>
                            <select class="form-select">
                                <option value="">-- Vyber --</option>
                                <option value="0" <?= (isset($existingAnswers[$song['id']]) && $existingAnswers[$song['id']]['freq'] === '0') ? 'selected' : '' ?>>Ani raz</option>
                                <option value="1" <?= (isset($existingAnswers[$song['id']]) && $existingAnswers[$song['id']]['freq'] === '1') ? 'selected' : '' ?>>Jeden krát</option>
                                <option value="2" <?= (isset($existingAnswers[$song['id']]) && $existingAnswers[$song['id']]['freq'] === '2') ? 'selected' : '' ?>>Niekoľko krát</option>
                                <option value="3" <?= (isset($existingAnswers[$song['id']]) && $existingAnswers[$song['id']]['freq'] === '3') ? 'selected' : '' ?>>Často</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Ako veľmi sa ti páči?</strong></label>
                            <?php $userRating = $existingAnswers[$song['id']]['rating'] ?? ''; ?>
                            <div class="d-flex justify-content-between mt-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="rating-circle <?= $userRating == $i ? 'selected' : '' ?>" data-value="<?= $i ?>"><?= $i ?></div>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" class="rating-value" name="rating-<?= $song['id'] ?>" value="<?= $userRating ?>">
                            <div class="d-flex justify-content-between mt-1 text-muted" style="font-size: 0.85rem;">
                                <span>vôbec</span><span></span><span>neutrálne/<br>neviem</span><span></span><span>veľmi</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-grid gap-2 mt-3" style="max-width: 500px; margin: auto;">
                    <button id="next-btn" class="btn btn-success"><?= $isLast ? 'Ukončiť' : 'Ďalej' ?></button>
                </div>

                <script>
                document.querySelectorAll('.rating-circle').forEach(circle => {
                    circle.addEventListener('click', () => {
                        const parent = circle.parentElement;
                        parent.querySelectorAll('.rating-circle').forEach(c => c.classList.remove('selected'));
                        circle.classList.add('selected');
                        parent.nextElementSibling.value = circle.dataset.value;
                    });
                });
                </script>

                <script src="../js/song_render.js?v=<?= filemtime(dirname(__DIR__) . '/js/song_render.js') ?>"></script>
                <script>
                document.addEventListener("DOMContentLoaded", function () {
                    document.querySelectorAll('.btn-text').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const songId = btn.dataset.songId;
                            const div = document.getElementById(`song-text-${songId}`);
                            document.querySelectorAll('.song-text').forEach(d => {
                                if (d !== div) { d.classList.add('d-none'); d.innerHTML = ''; }
                            });
                            if (div.classList.contains('d-none')) {
                                fetch(`../php/subpage/get_song_content.php?id=${songId}`)
                                    .then(res => res.text())
                                    .then(content => {
                                        div.innerHTML = content;
                                        div.classList.remove('d-none');
                                        enhanceSongDiv(div);
                                        adjustFontSizeToFitWidth(div);
                                    })
                                    .catch(err => {
                                        div.innerHTML = "<p>Error loading song text.</p>";
                                        div.classList.remove('d-none');
                                        console.error(err);
                                    });
                            } else {
                                div.classList.add('d-none');
                                div.innerHTML = '';
                            }
                        });
                    });
                });

                document.getElementById('next-btn').addEventListener('click', () => {
                    const results = [];
                    document.querySelectorAll('.card').forEach(card => {
                        const songTextDiv = card.querySelector('.song-text');
                        if (!songTextDiv) return;
                        const match = songTextDiv.id.match(/song-text-(\d+)/);
                        if (!match) return;
                        const songId = match[1];
                        const freqSelect = card.querySelector('select');
                        const freqValue = freqSelect ? freqSelect.value : '';
                        const ratingInput = card.querySelector('.rating-value');
                        const ratingValue = ratingInput ? ratingInput.value : '';
                        results.push(`${songId}-${freqValue}&${ratingValue}`);
                    });
                    saveAndAdvance(results.join(','));
                });
                </script>
            <?php endif; ?>

            <style>
            .card { background-color: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
            .rating-circle {
                width: 35px; height: 35px; border-radius: 50%; display: flex; justify-content: center;
                align-items: center; cursor: pointer; font-weight: bold; transition: all 0.2s; user-select: none; color: white;
            }
            .rating-circle[data-value="1"] { background-color: #FF0000; }
            .rating-circle[data-value="2"] { background-color: #FF6666; }
            .rating-circle[data-value="3"] { background-color: #808080; color: #fff; }
            .rating-circle[data-value="4"] { background-color: #66CC66; }
            .rating-circle[data-value="5"] { background-color: #008000; }
            .rating-circle.selected { box-shadow: 0 0 0 3px #00000033; transform: scale(1.2); }
            </style>

            <script>
            const anketaFormId = <?= $formId ?>;
            const anketaCategoryId = <?= $categoryId ?>;
            const anketaItemOrder = <?= json_encode($itemOrder) ?>;
            const anketaIsLast = <?= $isLast ? 'true' : 'false' ?>;

            function saveAndAdvance(answers) {
                fetch("../php/subpage/save_anketa_response.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ form_id: anketaFormId, category_id: anketaCategoryId, answers: answers })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (anketaIsLast) {
                            window.location.href = `form_thanks.php?form=${anketaFormId}`;
                        } else {
                            const currentIndex = anketaItemOrder.indexOf(anketaCategoryId);
                            const nextId = anketaItemOrder[currentIndex + 1];
                            window.location.href = `?form=${anketaFormId}&category_id=${nextId}`;
                        }
                    } else {
                        alert("Chyba pri ukladaní výsledkov!");
                        console.error(data.error);
                    }
                })
                .catch(err => {
                    alert("Chyba pri ukladaní výsledkov!");
                    console.error(err);
                });
            }
            </script>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php include $phpBase . 'footer.php'; ?>
