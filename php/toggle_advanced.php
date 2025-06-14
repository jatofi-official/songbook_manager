<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Toggle the session variable
if (!isset($_SESSION['advanced_search'])) {
    $_SESSION['advanced_search'] = false;
}

$_SESSION['advanced_search'] = !$_SESSION['advanced_search'];

// Return the new value as JSON
echo json_encode(['advanced_search' => $_SESSION['advanced_search']]);
