<?php
session_start();

// Admin-only access
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

require_once  '../db.php';

// Get & sanitize parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 25;

$validColumns = ['id','username','type','time','description','is_public'];
$sortColumn = isset($_GET['sort_column']) && in_array($_GET['sort_column'], $validColumns) ? $_GET['sort_column'] : 'id';

$sortOrder = (isset($_GET['sort_order']) && strtolower($_GET['sort_order']) === 'asc') ? 'ASC' : 'DESC';

$usernameFilter = isset($_GET['username']) ? trim($_GET['username']) : '';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : '';
$isPublic = isset($_GET['is_public']) ? $_GET['is_public'] : 'all';

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if ($usernameFilter !== '') {
    $where[] = "username LIKE ?";
    $params[] = "%$usernameFilter%";
    $types .= 's';
}

if ($typeFilter !== '') {
    $where[] = "type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

if ($isPublic === '1' || $isPublic === '0') {
    $where[] = "is_public = ?";
    $params[] = $isPublic;
    $types .= 'i';
}

// Construct final SQL
$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM activity_log $whereSql";
$stmt = $mysqli->prepare($countSql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$total = $res->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Fetch current page data
$offset = ($page - 1) * $limit;
$dataSql = "SELECT id, username, type, time, description, is_public 
            FROM activity_log 
            $whereSql 
            ORDER BY $sortColumn $sortOrder 
            LIMIT ?, ?";
$paramsPage = $params;
$typesPage = $types . 'ii';
$paramsPage[] = $offset;
$paramsPage[] = $limit;

$stmt = $mysqli->prepare($dataSql);
$stmt->bind_param($typesPage, ...$paramsPage);
$stmt->execute();
$res = $stmt->get_result();

$activities = [];
while ($row = $res->fetch_assoc()) {
    $activities[] = $row;
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode([
    'total' => intval($total),
    'activities' => $activities
]);
