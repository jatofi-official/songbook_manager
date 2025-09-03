<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Universal include path
$phpBase = dirname(__DIR__) . '/';
include $phpBase . 'db.php';  // <- now correctly points to php/db.php

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "Missing song ID";
    exit;
}

$id = intval($_GET['id']);

// Check $mysqli exists
if (!isset($mysqli)) {
    http_response_code(500);
    echo "Database connection not found!";
    exit;
}

$stmt = $mysqli->prepare("SELECT content FROM songs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$song = $result->fetch_assoc();

if (!$song) {
    http_response_code(404);
    echo "Song not found";
    exit;
}

echo htmlspecialchars($song['content']);
