<?php
session_start();
// Universal include path (works no matter where the project root is placed)
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';

$website = "none";

include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
?>

<div class="card mx-auto my-3 p-3" style="max-width: 600px;">
    <h3>Ďakujeme za vyplnenie ankety!</h3>
    <p>Výsledky sa dozvieš čoskoro:D</p>
    <p>Za vyplnenie ankety si získal 250xp, čo je ekvivalentné pridaním 5 pesničiek.</p>
    <a href= "../" class="btn btn-secondary">Späť na stránku</a>
</div>

<?php include $phpBase . 'footer.php'; ?>
