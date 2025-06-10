<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$categories = [];

// Everyone: get all categories
$sql = "SELECT id, category_index, name FROM categories ORDER BY category_index ASC";
$result = $mysqli->query($sql);
if (!$result) {
    die("Database query failed: " . $mysqli->error);
}
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['id'];
    // $categories[$id] = $row['category_index'] . ' ' . $row['name'];
    $categories[$id] = $row['name'];

}

// If admin, append usernames who have songs with category=0
// if (isset($_SESSION["admin"]) && $_SESSION["admin"] == 1) {
//     // Get distinct owners who have songs with category=0
//     $sql_users = "SELECT DISTINCT owner FROM songs WHERE category = 0 ORDER BY owner ASC";
//     $result_users = $mysqli->query($sql_users);
//     if (!$result_users) {
//         die("Database query failed: " . $mysqli->error);
//     }
//     while ($row_user = $result_users->fetch_assoc()) {
//         // Use a special key to avoid collisions, e.g. prefix 'user_'
//         $categories['user_' . $row_user['owner']] = $row_user['owner'];
//     }
// }

// Append logged-in user's name as last option if logged in
if (isset($_SESSION["name"]) && !empty($_SESSION["name"])) {
    $categories['user_me'] = $_SESSION["name"];
}
?>