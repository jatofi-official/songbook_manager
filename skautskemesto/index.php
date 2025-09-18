<?php
session_start();
// Universal include path
$phpBase = dirname(__DIR__) . '/php/';


include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';


echo $stylesFs;
echo "<br>". $stylesUrl;

include $phpBase . 'footer.php';
?>
