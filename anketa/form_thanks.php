<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'auth.php';
include_once $phpBase . 'functions.php';

$formId = intval($_GET['form'] ?? 0);
$form = $formId ? get_anketa_form($mysqli, $formId) : null;

$title = "Ďakujeme";
$website = "none";

include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
?>

<div class="container mt-5 text-center">
<?php
$access = $form ? ($_SESSION['anketa_access'][$formId] ?? null) : null;

if (!$form || !$access): ?>
    <p class="text-danger">Anketa nebola nájdená.</p>
<?php else:
    $respondentKey = $access['respondent_key'];
    $totalItems = 1 + count($form['category_ids']) + ($form['include_basic_questions'] ? 1 : 0);

    $stmt = $mysqli->prepare("SELECT COUNT(DISTINCT category_id) AS answered FROM anketa_responses WHERE form_id = ? AND respondent_key = ?");
    $stmt->bind_param("is", $formId, $respondentKey);
    $stmt->execute();
    $answered = (int)($stmt->get_result()->fetch_assoc()['answered'] ?? 0);
    $stmt->close();

    if ($answered >= $totalItems):
        if (!empty($access['authenticated']) && isset($_SESSION['id'])) {
            add_xp_event($mysqli, (int)$_SESSION['id'], 250, 'survey', "survey-form:$formId:" . (int)$_SESSION['id']);
        }
        ?>
        <div class="card mx-auto my-3 p-3" style="max-width: 600px;">
            <h3>Ďakujeme za vyplnenie ankety!</h3>
            <p>Tvoje odpovede boli uložené.</p>
            <a href="../" class="btn btn-secondary">Späť na stránku</a>
        </div>
    <?php else: ?>
        <div class="card mx-auto my-3 p-3" style="max-width: 600px;">
            <h3>Ďakujeme za čiastočné vyplnenie ankety!</h3>
            <p>Zvyšné kategórie môžeš dokončiť neskôr.</p>
            <a href="form.php?form=<?= $formId ?>" class="btn btn-primary">Späť na anketu</a>
        </div>
    <?php endif;
endif; ?>
</div>

<?php include $phpBase . 'footer.php'; ?>
