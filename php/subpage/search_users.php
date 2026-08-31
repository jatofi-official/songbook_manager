<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('../db.php');

header('Content-Type: application/json');

if (empty($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$stmt = $mysqli->prepare(
    "SELECT id, username, name FROM users
     WHERE name COLLATE utf8mb4_general_ci LIKE ? OR username COLLATE utf8mb4_general_ci LIKE ?
     ORDER BY name LIMIT 8"
);
$like = '%' . $q . '%';
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(array_map(function ($u) {
    return [
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'name' => $u['name'] ?: $u['username'],
    ];
}, $rows));
