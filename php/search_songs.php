<?php
session_start();
include('php/db.php');
include('php/functions.php');

$searchQuery = $_GET['searchQuery'] ?? '';
$category = $_GET['category'] ?? 'all';

// Pagination params (optional, you can customize)
$perPage = 20;
$page = 1;

$result = get_all_songs($mysqli, $perPage, $page, $searchQuery, $category, [], [], null);

header('Content-Type: application/json');
echo json_encode($result['songs']);
exit;
