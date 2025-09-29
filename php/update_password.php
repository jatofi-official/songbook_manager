<?php
session_start();
require_once "db.php"; // your DB connection

header("Content-Type: application/json");

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$userId = $_SESSION["id"];
$currentPassword = $_POST["currentPassword"] ?? "";
$newPassword = $_POST["newPassword"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(["success" => false, "error" => "All fields are required"]);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(["success" => false, "error" => "New passwords do not match"]);
    exit;
}

// fetch current password hash
$stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($dbPassword);
$stmt->fetch();
$stmt->close();

// verify current password
if (hash("sha256", $currentPassword) !== $dbPassword) {
    echo json_encode(["success" => false, "error" => "Current password is incorrect"]);
    exit;
}

// update with new hash
$newHash = hash("sha256", $newPassword);

$stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $newHash, $userId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Password updated successfully"]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update password"]);
}
$stmt->close();
