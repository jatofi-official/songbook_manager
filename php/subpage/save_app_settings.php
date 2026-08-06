<?php
// Uloženie globálnych app nastavení (OpenRouter). Len admin.
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json');

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$ok = true;
$model = trim($_POST['openrouter_model'] ?? '');
if ($model !== '') $ok = set_app_setting($mysqli, 'openrouter_model', $model) && $ok;

// kľúč aktualizuj len ak bol reálne zadaný (prázdne = ponechať existujúci)
$key = $_POST['openrouter_api_key'] ?? '';
if (trim($key) !== '') $ok = set_app_setting($mysqli, 'openrouter_api_key', trim($key)) && $ok;

if (!$ok) http_response_code(500);
echo json_encode([
    'success' => $ok,
    'error' => $ok ? null : 'Nastavenia sa nepodarilo uložiť.',
]);
