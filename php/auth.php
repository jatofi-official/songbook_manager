<?php
session_start();
require_once("db.php");

if (!isset($_SESSION["id"]) && isset($_COOKIE["rememberme"])) {
    $token = $_COOKIE["rememberme"];
    $hashedToken = hash("sha256", $token);

    $stmt = $mysqli->prepare("SELECT user_id FROM login_tokens WHERE token_hash = ? AND expires_at > NOW()");
    if (!$stmt) {
        die("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("s", $hashedToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $userId = $row["user_id"];

        // Fetch user info
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
        if (!$stmt) {
            die("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userResult = $stmt->get_result();
        $user = $userResult->fetch_assoc();

        if ($user) {
            $_SESSION["id"] = $user["id"];
            $_SESSION["user"] = $user["username"];
            $_SESSION["admin"] = $user["admin"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["admin_view"] = false;
        }
    }
}
?>
