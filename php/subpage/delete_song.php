<?php
session_start();
require_once('../db.php');
include_once("../functions.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    // redirect_params is passed as a query string, parse it into an array
    $redirectParamsRaw = $_POST['redirect_params'] ?? '';
    parse_str($redirectParamsRaw, $redirectParams);

    $user = $_SESSION["user"] ?? null;
    $isAdmin = $_SESSION["admin"] ?? false;

    // Check if the song exists and is owned by user or user is admin
    $stmt = $mysqli->prepare("SELECT owner, title, artist, is_public FROM songs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($song && ($isAdmin || $song['owner'] === $user)) {
        $deleteStmt = $mysqli->prepare("DELETE FROM songs WHERE id = ?");
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        $deleteStmt->close();

        add_activity(
            $mysqli,
            $user,
            "Deleted song",
            "Deleted '" . $song["title"] . "' by " . $song["artist"],
            $song["is_public"]
        );
    }

    // Build redirect URL from preserved query params
    $query = http_build_query($redirectParams);
    $redirectUrl = '../../songs.php' . ($query ? "?$query" : '');

    header("Location: $redirectUrl");
    exit;
}

// Fallback if something goes wrong
header("Location: ../../songs.php");
exit;
