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

try {
    $out = ["success" => true];

    if (array_key_exists("advanced_view", $input)) {
        $advanced = (int)$input["advanced_view"];
        $stmt = $mysqli->prepare("UPDATE user_settings SET advanced_view = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $advanced, $userId);
        $stmt->execute();
        $stmt->close();
        $_SESSION["advanced_view"] = $advanced;
        $out["advanced_view"] = $advanced;
    }

    if (array_key_exists("import_enabled", $input)) {
        $imp = (int)$input["import_enabled"];
        $stmt = $mysqli->prepare("UPDATE user_settings SET import_enabled = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $imp, $userId);
        $stmt->execute();
        $stmt->close();
        $_SESSION["import_enabled"] = $imp;
        $out["import_enabled"] = $imp;
    }

    echo json_encode($out);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
