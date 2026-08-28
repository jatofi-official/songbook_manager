<?php
session_start();
include_once("db.php");

header('Content-Type: application/json');

// Parse JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$userId = intval($_SESSION["id"]);
$advanced = isset($input["advanced_view"]) ? (int)$input["advanced_view"] : 0;
$theme = $input['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark', 'system'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid theme.']);
    exit;
}

$songFont = $input['song_font'] ?? 'monospace';
if (!in_array($songFont, ['monospace', 'calibri', 'arial', 'verdana', 'georgia', 'times'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid song font.']);
    exit;
}

try {
    // Update DB
    $stmt = $mysqli->prepare("UPDATE user_settings SET advanced_view = ?, theme = ?, song_font = ? WHERE user_id = ?");
    $stmt->bind_param("issi", $advanced, $theme, $songFont, $userId);
    $stmt->execute();
    $stmt->close();

    // Update session
    $_SESSION["advanced_view"] = $advanced;
    $_SESSION['theme'] = $theme;
    $_SESSION['song_font'] = $songFont;

    echo json_encode(["success" => true, "advanced_view" => $advanced, 'theme' => $theme, 'song_font' => $songFont]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
