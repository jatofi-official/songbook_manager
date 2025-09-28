<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include_once $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';

$projectRoot = dirname(__DIR__);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$projectFolder = basename($projectRoot);
$projectUrl = $protocol . "://" . $host . "/" . $projectFolder;


?>

<!-- Main -->

<?php
include $phpBase . 'footer.php';
?>
