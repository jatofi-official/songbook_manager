<?php
session_start();
require_once 'db.php';

if (isset($_COOKIE['rememberme'])) {
    $hash = hash('sha256', $_COOKIE['rememberme']);
    $stmt = $mysqli->prepare("DELETE FROM login_tokens WHERE token_hash = ?");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    setcookie('rememberme', '', time() - 3600, "/", "", false, true);
}

session_unset();
session_destroy();

header("Location: ../user_settings.php");
exit();

?>