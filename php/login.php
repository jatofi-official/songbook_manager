<?php
session_start();
require_once("db.php");

if (!isset($_GET["remember"])) {
    header("Location: user_settings.php");
    exit();
}

$userId = intval($_GET["remember"]);

// Generate a secure token
$token = bin2hex(random_bytes(32));
$hashedToken = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60 * 60 * 24 * 30); // 30 days

// Prepare the insert statement with correct column names
$stmt = $mysqli->prepare("INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $mysqli->error);
}

$stmt->bind_param("iss", $userId, $hashedToken, $expiry);

if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

// Set cookie (same token, unhashed)
setcookie("rememberme", $token, time() + 60 * 60 * 24 * 30, "/", "", false, true);

header("Location: ../user_settings.php");
exit();
