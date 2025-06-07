<?php
$instruments = [];
$res = $mysqli->query("SELECT id, name FROM instruments");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $instruments[] = ['id' => $row['id'], 'name' => $row['name']];
    }
    $res->free();
}

// print_r($instruments);
?>
