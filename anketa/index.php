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
    <?php else: 
        $userId = $_SESSION['id'];
        $group = get_user_group($mysqli, $userId);

        if ($group != 1): ?>
            <p class="text-danger">Anketa je prístupná iba pre členov 24. zboru Mustangy</p>
        <?php else: ?>
            <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</p>
            <!-- Here you can add the anketa content for authorized users -->
        <?php endif; 
    endif; ?>
</div>




<?php include $phpBase . 'footer.php'; ?>
