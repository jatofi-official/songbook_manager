<?php
session_start();
// Universal include path
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';

if (isset($_SESSION['user'])) {
    $username = $_SESSION['user'];

    // Count how many categories the user has answered
    $stmtCheck = $mysqli->prepare("
        SELECT COUNT(*) 
        FROM vysledky_ankety_25 
        WHERE username = ?
    ");
    $stmtCheck->bind_param("s", $username);
    $stmtCheck->execute();
    $stmtCheck->bind_result($answeredCount);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($answeredCount === 13) {
        // Full survey completed
        // Add activity if it doesn't exist at all
        $activityDescription =  $_SESSION["name"] ." vyplnil anketu!";
        $activityType = "Anketa";

        $stmt = $mysqli->prepare("
            SELECT id 
            FROM activity_log 
            WHERE username = ? AND description = ? 
            LIMIT 1
        ");
        $stmt->bind_param("ss", $username, $activityDescription);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmtInsert = $mysqli->prepare("
                INSERT INTO activity_log (username, type, time, description, is_public)
                VALUES (?, ?, NOW(), ?, 1)
            ");
            $stmtInsert->bind_param("sss", $username, $activityType, $activityDescription);
            $stmtInsert->execute();
            $stmtInsert->close();
        }

        $stmt->close();

        // Show full survey thank-you card
        echo '<div class="card mx-auto my-3 p-3" style="max-width: 600px;">
                <h3>Ďakujeme za vyplnenie ankety!</h3>
                <p>Výsledky sa dozvieš čoskoro:D</p>
                <p>Za vyplnenie ankety si získal 250xp, čo je ekvivalentné pridaním 5 pesničiek.</p>
                <a href="../" class="btn btn-secondary">Späť na stránku</a>
            </div>';
    } else {
        // Partial survey
        echo '<div class="card mx-auto my-3 p-3" style="max-width: 600px;">
                <h3>Ďakujeme za čiastočné vyplnenie ankety!</h3>
                <p>Výsledky sa dozvieš čoskoro. Zvyšné kategórie môžeš dokončiť neskôr.</p>
                <a href="' . $projectUrl . '/anketa/index.php" class="btn btn-primary">Späť na ankety</a>
            </div>';
    }
}

include $phpBase . 'footer.php';
?>
