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
    $stmt = $mysqli->prepare("SELECT owner, title, artist, is_public, tags FROM songs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $deleteFailed = false;

    if ($song && ($isAdmin || $song['owner'] === $user)) {
        $ownerUser = get_user_by_username($mysqli, $song['owner']);
        // Remove tags usage counts
        $tagsArray = array_filter(array_map('trim', explode(',', $song['tags'])));
        foreach ($tagsArray as $tag) {
            remove_tag($mysqli, $tag);
        }

        // Snapshot before deleting so history retains the final state
        save_song_snapshot($mysqli, $id, 'delete', $user);

        // Delete the song
        $deleteStmt = $mysqli->prepare("DELETE FROM songs WHERE id = ?");
        if (!$deleteStmt) {
            error_log("delete_song: failed to prepare DELETE for id=$id: " . $mysqli->error);
            $deleteFailed = true;
        } else {
            $deleteStmt->bind_param("i", $id);
            if (!$deleteStmt->execute()) {
                error_log("delete_song: DELETE execute failed for id=$id: " . $deleteStmt->error);
                $deleteFailed = true;
            } elseif ($deleteStmt->affected_rows === 0) {
                error_log("delete_song: DELETE affected 0 rows for id=$id (song vanished or blocked)");
                $deleteFailed = true;
            }
            $deleteStmt->close();
        }

        if (!$deleteFailed) {
            if ($ownerUser) {
                revoke_song_add_xp($mysqli, (int)$ownerUser['id'], $id);
            }

            add_activity(
                $mysqli,
                $user,
                "delete",
                "Deleted '" . $song["title"] . "' by " . $song["artist"],
                $song["is_public"]
            );
        }
    } else {
        error_log("delete_song: permission denied for id=$id, session_user=" . var_export($user, true) . ", song_owner=" . var_export($song['owner'] ?? null, true) . ", isAdmin=" . var_export($isAdmin, true));
        $deleteFailed = true;
    }

    // Build redirect URL from preserved query params
    $redirectParams['delete_failed'] = $deleteFailed ? '1' : null;
    $redirectParams = array_filter($redirectParams, fn($v) => $v !== null);
    $query = http_build_query($redirectParams);
    $redirectUrl = '../../songs.php' . ($query ? "?$query" : '');

    header("Location: $redirectUrl");
    exit;
}

// Fallback if something goes wrong
header("Location: ../../songs.php");
exit;
