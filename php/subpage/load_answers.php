<?php
include "../db.php";

if (!isset($_GET['username'])) {
    exit("No user provided");
}

$username = $mysqli->real_escape_string($_GET['username']);
$category = isset($_GET['category']) ? intval($_GET['category']) : -1;

// Load categories mapping
$catRes = $mysqli->query("SELECT categories FROM songbooks LIMIT 1");
$categoriesString = $catRes->fetch_assoc()['categories'];
$categories = [];
foreach (explode(",", $categoriesString) as $pair) {
    list($id, $name) = explode("-", $pair, 2);
    $categories[(int)$id] = trim($name);
}

if ($category == -1) {
    // Základ
    $query = "SELECT answers FROM vysledky_ankety_25 
              WHERE username='$username' AND category_id=-1";
    $result = $mysqli->query($query);

    echo "<h5>Základ</h5>";
    while ($row = $result->fetch_assoc()) {
        $parts = explode("&", $row['answers']);
        echo "<div class='border rounded p-2 mb-2 bg-light'>
                <strong>" . htmlspecialchars(trim($parts[0])) . "</strong> - " . htmlspecialchars(trim($parts[1])) . "
              </div>";
    }

} elseif ($category >= 1 && $category <= 13) {
    $query = "SELECT answers FROM vysledky_ankety_25 
              WHERE username='$username' AND category_id=$category";
    $result = $mysqli->query($query);

    // Category title row (lighter background)
    echo "<div class='p-2 mb-3 text-dark rounded'>
            <h4 class='m-0'>" . htmlspecialchars($categories[$category] ?? '') . "</h4>
        </div>";


    if ($row = $result->fetch_assoc()) {
        $entries = explode(",", $row['answers']);

    // header row with nicer, rounded, smaller buttons
    echo "<div class='d-flex fw-bold mb-2 table-header align-items-center'>
            <div class='flex-grow-1'>Pieseň</div>
            <div class='text-center' style='width:150px'>
                <button class='sortable btn btn-outline-secondary btn-sm' data-sort='frekvencia'>Frekvencia</button>
            </div>
            <div class='text-center' style='width:120px'>
                <button class='sortable btn btn-outline-secondary btn-sm' data-sort='hodnotenie'>Hodnotenie</button>
            </div>
        </div>";


    // ...above your foreach
    $idx = 0;

    echo "<div id='answerRows' class='row row-cols-1 gy-3'>";
    foreach ($entries as $entry) {
        if (trim($entry) === "") continue;

        list($songId, $ansString) = explode("-", $entry, 2);
        list($often, $like) = explode("&", $ansString);

        $songId = intval($songId);
        $songRes = $mysqli->query("SELECT title, artist FROM songs WHERE id=$songId");
        $song = $songRes->fetch_assoc();

        // map badges (unchanged)
        $freqLabels = [0=>"Ani raz",1=>"Jeden krát",2=>"Niekoľko krát",3=>"Často"];
        $freqColors = [
            0 => "bg-danger-light text-dark",   // Ani raz
            1 => "bg-warning-light text-dark",  // Jeden krát
            2 => "bg-success-light text-dark",  // Niekoľko krát
            3 => "bg-success-dark text-dark",    // Často
        ];

        if ($often === "" || $often === null) {
            $oftenBadge = "-";
        } else {
            $freqValue = $freqLabels[(int)$often] ?? "-";
            $freqClass = $freqColors[(int)$often] ?? "bg-secondary text-white";
            $oftenBadge = "<span class='badge $freqClass px-3 py-2'>$freqValue</span>";
        }

        $likeCircle = $like !== "" 
            ? "<div class='rating-circle' data-value='".intval($like)."'>$like</div>" 
            : "-";

        // numeric (or empty) values for data-*
        $oftenVal = ($often !== "" && is_numeric($often)) ? (int)$often : "";
        $likeVal  = ($like  !== "" && is_numeric($like))  ? (int)$like  : "";

        echo "<div class='col answer-row'
                    data-index='{$idx}'
                    data-frekvencia='{$oftenVal}'
                    data-hodnotenie='{$likeVal}'>
                <div class='d-flex align-items-center border rounded p-3 h-100'>
                    <div class='flex-grow-1 d-flex align-items-baseline'>
                        <div class='fw-bold text-dark me-2'>" . htmlspecialchars($song['title']) . "</div>
                        <div class='small text-muted'>" . htmlspecialchars($song['artist']) . "</div>
                    </div>
                    <div class='text-center' style='width:150px'>$oftenBadge</div>
                    <div class='text-center' style='width:120px'>$likeCircle</div>
                </div>
            </div>";
        $idx++;
    }
    echo "</div>";


    } else {
        echo "<p>Žiadne odpovede.</p>";
    }

} else {
    echo "<p>Neplatná kategória.</p>";
}
