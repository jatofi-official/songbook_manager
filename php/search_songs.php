<?php
session_start();
include('../php/db.php');
include('../php/functions.php');

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all'; // optional, not needed here but you can extend

$page = 1;
$perPage = 20;

$result = get_all_songs($mysqli, $perPage, $page, $search, $category, [], [], null);

header('Content-Type: application/json');
echo json_encode($result['songs']);
