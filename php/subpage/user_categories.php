<?php
include "../db.php";
if (!isset($_GET['username'])) { echo "[]"; exit; }

$username = $mysqli->real_escape_string($_GET['username']);
$res = $mysqli->query("SELECT DISTINCT category_id FROM vysledky_ankety_25 WHERE username='$username' AND category_id>0");
$cats = [];
while ($row = $res->fetch_assoc()) {
    $cats[] = (int)$row['category_id'];
}
header("Content-Type: application/json");
echo json_encode($cats);
