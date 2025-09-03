<?php
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

<?php include $phpBase . 'footer.php'; ?>
