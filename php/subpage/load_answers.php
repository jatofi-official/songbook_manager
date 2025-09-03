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
    // Category with songs
    $query = "SELECT answers FROM vysledky_ankety_25 
              WHERE username='$username' AND category_id=$category";
    $result = $mysqli->query($query);

    echo "<h5>" . htmlspecialchars($categories[$category] ?? '') . "</h5>";

    if ($row = $result->fetch_assoc()) {
        $entries = explode(",", $row['answers']);

        // header row
        echo "<div class='d-flex fw-bold mb-2'>
                <div class='flex-grow-1'>Pieseň</div>
                <div class='text-center' style='width:120px'>Frekvencia</div>
                <div class='text-center' style='width:120px'>Hodnotenie</div>
              </div>";

        echo "<div class='row row-cols-1 gy-3'>";
        foreach ($entries as $entry) {
            if (trim($entry) === "") continue;

            list($songId, $ansString) = explode("-", $entry, 2);
            list($often, $like) = explode("&", $ansString);

            $songId = intval($songId);
            $songRes = $mysqli->query("SELECT title, artist FROM songs WHERE id=$songId");
            $song = $songRes->fetch_assoc();

            // First rating (1–4) as simple colored badge
            $oftenBadge = $often !== "" ? "<span class='badge bg-" . (
                $often == 1 ? "danger" :
                ($often == 2 ? "warning" :
                ($often == 3 ? "info" : "success"))
            ) . "'>$often</span>" : "-";

            // Second rating (1–5) as custom circle
            $likeCircle = $like !== "" 
                ? "<div class='rating-circle' data-value='".intval($like)."'>$like</div>" 
                : "-";

            echo "<div class='col'>
                    <div class='d-flex align-items-center border rounded p-3 h-100'>
                        <div class='flex-grow-1'>
                            <div class='fw-bold'>" . htmlspecialchars($song['title']) . " — " . htmlspecialchars($song['artist']) . "</div>
                        </div>
                        <div class='text-center' style='width:120px'>$oftenBadge</div>
                        <div class='text-center' style='width:120px'>$likeCircle</div>
                    </div>
                  </div>";
        }
        echo "</div>";
    } else {
        echo "<p>Žiadne odpovede.</p>";
    }

} else {
    echo "<p>Neplatná kategória.</p>";
}
