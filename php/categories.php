<?php
include 'php/db.php';  // Adjust path if needed

// Prepare and execute query to fetch all categories
$sql = "SELECT id, category_index, name FROM categories ORDER BY category_index ASC";
$result = $mysqli->query($sql);

if (!$result) {
    die("Database query failed: " . $mysqli->error);
}

$categories = [];

while ($row = $result->fetch_assoc()) {
    $id = (int)$row['id'];
    // Concatenate category_index and name with space
    $categories[$id] = $row['category_index'] . ' ' . $row['name'];
}

// For demonstration: print the resulting array
// echo "<pre>";
// print_r($categories);
// echo "</pre>";
?>
