<?php
session_start();
header('Content-Type: application/json');



if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Universal include path
$phpBase = dirname(__DIR__) . '/';
include $phpBase . 'db.php';  // <- now correctly points to php/db.php

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$username = $mysqli->real_escape_string($data['username'] ?? '');
$category_id = intval($data['category_id'] ?? 0);
$answers = $mysqli->real_escape_string($data['answers'] ?? '');

if (!$username || !$category_id || !$answers) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Check if entry already exists
$stmt = $mysqli->prepare("SELECT id FROM vysledky_ankety_25 WHERE username = ? AND category_id = ?");
$stmt->bind_param("si", $username, $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Update
    $stmtUpdate = $mysqli->prepare("UPDATE vysledky_ankety_25 SET answers = ?, timestamp = NOW() WHERE id = ?");
    $stmtUpdate->bind_param("si", $answers, $row['id']);
    $stmtUpdate->execute();
} else {
    // Insert
    $stmtInsert = $mysqli->prepare("INSERT INTO vysledky_ankety_25 (username, category_id, answers) VALUES (?, ?, ?)");
    $stmtInsert->bind_param("sis", $username, $category_id, $answers);
    $stmtInsert->execute();
}

echo json_encode(['success' => true]);
