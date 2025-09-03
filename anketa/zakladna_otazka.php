<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<div class="container mt-5 text-center">
<?php 
if (!isset($_SESSION['user'])): ?>
    <p class="mb-3">Musíš byť prihlásený</p>
    <a href="<?= $projectUrl ?>/user_settings.php" class="btn btn-primary me-2">Log in</a>
    <a href="<?= $projectUrl ?>/register_user.php" class="btn btn-success">Register</a>
<?php else: ?>
    <h3>Základné otázky</h3>

    <div class="card mx-auto my-3 p-3" style="max-width: 500px;">
        <label class="form-label"><strong>Ako dlho si bol na tábore?</strong></label>
        <select class="form-select" id="otazka1">
            <option value="">-- Vyber --</option>
            <option value="oba_týždne_predcata">Oba týždne + predčata</option>
            <option value="oba_týždne">Oba týždne</option>
            <option value="prvy_tyzd">Prvý týždeň</option>
            <option value="druhy_tyzd">Druhý týždeň</option>
            <option value="menej_ako_tyzd">Menej ako týždeň</option>
        </select>
    </div>

    <div class="card mx-auto my-3 p-3" style="max-width: 500px;">
        <label class="form-label"><strong>Ako často si chodieval na táboráčiky?</strong></label>
        <select class="form-select" id="otazka2">
            <option value="">-- Vyber --</option>
            <option value="kazdy_krat">Každý krát</option>
            <option value="velmi_casto">Veľmi často</option>
            <option value="casto">Často</option>
            <option value="obcas">Občas</option>
        </select>
    </div>

    <div class="d-grid gap-2 mt-3" style="max-width: 500px; margin: auto;">
        <button id="next-btn" class="btn btn-success">Ďalej</button>
    </div>

    <script>
    const username = "<?= $_SESSION['user'] ?>";
    const categoryId = -1; // use -1 for basic questions

    document.getElementById('next-btn').addEventListener('click', () => {
        const otazka1 = document.getElementById('otazka1').value;
        const otazka2 = document.getElementById('otazka2').value;

        if (!otazka1 || !otazka2) {
            alert("Prosím, vyber všetky odpovede.");
            return;
        }

        const answers = `${otazka1}&${otazka2}`;

        fetch("../php/subpage/save_category_answers.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                username: username,
                category_id: categoryId,
                answers: answers
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = "<?= $projectUrl ?>/anketa?category_id=1";
            } else {
                alert("Chyba pri ukladaní výsledkov!");
                console.error(data.error);
            }
        })
        .catch(err => {
            alert("Chyba pri ukladaní výsledkov!");
            console.error(err);
        });
    });
    </script>

    <style>
    .card {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    </style>

<?php endif; ?>
</div>

<?php include $phpBase . 'footer.php'; ?>
