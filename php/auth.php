<?php
session_start();
include("db.php");

if (!isset($_SESSION["id"]) && isset($_COOKIE["rememberme"])) {
    $token = $_COOKIE["rememberme"];
    $hashedToken = hash("sha256", $token);

    $stmt = $mysqli->prepare("SELECT user_id FROM login_tokens WHERE token = ? AND expiry > NOW()");
    $stmt->bind_param("s", $hashedToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $userId = $row["user_id"];

        // Fetch user info
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
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
