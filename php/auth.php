<?php
require_once("db.php");
require_once("functions.php");

if (!isset($_SESSION["id"]) && isset($_COOKIE["rememberme"])) {
    $token = $_COOKIE["rememberme"];
    $hashedToken = hash("sha256", $token);

    $stmt = $mysqli->prepare("SELECT user_id FROM login_tokens WHERE token_hash = ? AND expires_at > NOW()");
    if (!$stmt) {
        die("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("s", $hashedToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $userId = $row["user_id"];

        // Fetch user info
        $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
        if (!$stmt) {
            die("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userResult = $stmt->get_result();
        $user = $userResult->fetch_assoc();

        if ($user) {
            $_SESSION["id"] = $user["id"];
            $_SESSION["user"] = $user["username"];
            $_SESSION["admin"] = $user["admin"];
            $_SESSION["name"] = $user["name"];

            
			$_SESSION["advanced_view"] = false;
            $_SESSION["admin_view"] = false;
            
            // Adding a log in log
            add_activity($mysqli,$user["username"],"auto_login",'User "'.$user["username"].'" automatically logged in.',0);

        }
    }
}
else if(!isset($_SESSION["user"])){
    // Logging ip address
    add_activity($mysqli,"anonymous","guest",get_user_ip(),0);

}
?>
