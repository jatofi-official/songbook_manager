<?php
session_start();
header('Content-Type: application/json');

$phpBase = dirname(__DIR__) . '/';
include $phpBase . 'db.php';
include_once $phpBase . 'functions.php';

$data = json_decode(file_get_contents('php://input'), true);
$formId = intval($data['form_id'] ?? 0);
$categoryId = isset($data['category_id']) ? intval($data['category_id']) : null;
$answers = $data['answers'] ?? '';

if (!$formId || $categoryId === null || !is_string($answers)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$access = $_SESSION['anketa_access'][$formId] ?? null;
if (!$access) {
    echo json_encode(['success' => false, 'error' => 'No access to this form']);
    exit;
}

$form = get_anketa_form($mysqli, $formId);
if (!$form || (!$form['is_active'] && empty($_SESSION['admin']))) {
    echo json_encode(['success' => false, 'error' => 'Form not available']);
    exit;
}

$validSentinels = $form['include_basic_questions'] ? [-2, -1] : [-1];
if (!in_array($categoryId, $validSentinels, true) && !in_array($categoryId, $form['category_ids'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid category for this form']);
    exit;
}

$respondent = $access['respondent'];
$respondentKey = $access['respondent_key'];
$isAuthenticated = !empty($access['authenticated']) ? 1 : 0;

$stmt = $mysqli->prepare("
    INSERT INTO anketa_responses (form_id, respondent, respondent_key, category_id, answers, is_authenticated)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE answers = VALUES(answers), respondent = VALUES(respondent), is_authenticated = VALUES(is_authenticated), timestamp = NOW()
");
$stmt->bind_param("issisi", $formId, $respondent, $respondentKey, $categoryId, $answers, $isAuthenticated);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
