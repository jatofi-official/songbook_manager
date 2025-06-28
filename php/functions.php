<?php
date_default_timezone_set('Europe/Bratislava');
include_once("data.php");
//NEMAŽ TIETO AJ KEĎ CHCEŠ
function osetri($co) {
	return trim(strip_tags($co));
}
function nazov_ok ($nazov) {
	return strlen($nazov) >= 3 && strlen($nazov) <= 20;
}

// vrati udaje pouzivatela ako asociativne pole, ak existuje pouzivatel $username s heslom $pass, inak vrati FALSE
function login_user($mysqli, $username, $pass) {
	if (!$mysqli->connect_errno) {
		$sql2 = "SELECT * FROM users WHERE username='$username' AND password='".hash("sha256",$pass)."' ";
		// echo "sql2 = $sql2 <br>";
		if (($result = $mysqli->query($sql2))&& ($result->num_rows > 0)) {  // vykonaj dopyt
			$row = $result->fetch_assoc();
			

			$result->free();
			return $row;
		} else {
			// dopyt sa NEpodarilo vykonať, resp. používateľ neexistuje!
			return false;
		}
	} else {
		// NEpodarilo sa spojiť s databázovým serverom!
		echo "Error connecting to database!";
		return false;
	}
}

// zmeni password $pass pouzivatelovi s id cislom $id
function zmen_heslo($mysqli, $id, $pass) {
	if (!$mysqli->connect_errno) {
	  	$sql="UPDATE kvety_pouzivatelia SET password='".md5($pass)."'"." WHERE uid='$id' ;"; // definuj dopyt   
		echo "sql = $sql <br>";
		if ($result = $mysqli->query($sql)) {  // vykonaj dopyt
		// dopyt sa podarilo vykonať
      	echo '<p>Heslo bolo zmenené.</p>'. "\n"; 
		$_SESSION["password"]= md5($pass);
		} 
		else {
			// NEpodarilo sa vykonať dopyt!
			echo '<p class="chyba">Nastala chyba pri zmene hesla.</p>'. "\n"; 
		}
	} 
	else {
		// NEpodarilo sa spojiť s databázovým serverom alebo vybrať databázu!
		echo '<p class="chyba">NEpodarilo sa spojiť s databázovým serverom!</p>';
	}
}	// koniec funkcie

function add_user($mysqli, $name, $username, $password, $group_id) {
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli->connect_error;
        return false;
    }

    // Check for duplicate username
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        echo "Prepare failed: " . $mysqli->error;
        return false;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return "duplicate";
    }
    $stmt->close();

    // Insert new user
    $hashed_password = hash("sha256", $password);
    $stmt = $mysqli->prepare(
        "INSERT INTO users (name, username, password, group_id, date_created, admin) 
         VALUES (?, ?, ?, ?, CURDATE(), 0)"
    );

    if (!$stmt) {
        echo "Prepare failed: " . $mysqli->error;
        return false;
    }

    $stmt->bind_param("sssi", $name, $username, $hashed_password, $group_id);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $mysqli->query("INSERT INTO user_xp (user_id, xp_add_song, xp_edit_song) VALUES ($user_id, 0, 0)");
        $stmt->close();
        return $user_id;
    } else {
        echo "Error: " . $stmt->error;
        $stmt->close();
        return false;
    }
}



function get_song($mysqli, $id){
	if(!$mysqli->connect_errno){
		$stmt = $mysqli->prepare("SELECT * FROM songs WHERE id = ?");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows === 0) {
			echo "<p>Song not found.</p>";
			echo "<p><a href='?'>Back to list</a></p>";
			exit;
		}

		return $result->fetch_assoc();
	}
}

function add_song($mysqli, $data) {
    if ($mysqli->connect_errno) {
        return "Error connecting";
    }

    // Normalize and clean tags
    $tagsArray = array_filter(array_map('trim', explode(',', $data["tags"] ?? '')));
    $normalizedTags = (implode(",",$tagsArray)!="") ? implode(',', $tagsArray) : null;
    $is_fixed = (count($tagsArray) >= FIXED_TAGS_COUNT) ? 1 : 0;

    // Sanitize and normalize fields
    $category = (int)$data["category"];
    $title = $data["title"] ?? '';
    $artist = (isset($data["artist"]) && trim($data["artist"]) !== '') ? $data["artist"] : null;

    $capo = (isset($data["capo"]) && $data["capo"] !== '') ? (int)$data["capo"] : null;
    $capo_alt = (isset($data["capo_alt"]) && trim($data["capo_alt"]) !== '') ? (int)$data["capo_alt"] : null;

    $owner = $_SESSION["user"];
    $is_public = isset($data["is_public"]) ? (int)$data["is_public"] : 0;
    $instruments = $data["instruments"] ?? '';
    $content = $data["content"] ?? null;
    $is_nsfw = isset($data["is_nsfw"]) ? (int)$data["is_nsfw"] : 0;
    $youtube_link = (isset($data["youtube_link"]) && trim($data["youtube_link"]) !== '') ? $data["youtube_link"] : null;


    $stmt = $mysqli->prepare("INSERT INTO songs (
        category, title, artist, capo, capo_alt, owner, is_public, create_date, modified_date,
        tags, instruments, content, is_fixed, is_nsfw, youtube_link, views
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, 0)");

    if (!$stmt) {
        return "Prepare failed: " . $mysqli->error;
    }

    // Dynamic bind_param workaround for NULL support
    $types = 'issiisssssiis';
    $params = [
        &$category,
        &$title,
        &$artist,
        &$capo,
        &$capo_alt,
        &$owner,
        &$is_public,
        &$normalizedTags,
        &$instruments,
        &$content,
        &$is_fixed,
        &$is_nsfw,
        &$youtube_link
    ];

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $insertedId = $stmt->insert_id ?: $mysqli->insert_id;
        $stmt->close();

        foreach ($tagsArray as $tag) {
            add_tag($mysqli, $tag);
        }

        return $insertedId;
    } else {
        $error = "Error: " . $stmt->error;
        $stmt->close();
        return $error;
    }
}

function increment_song_view(mysqli $mysqli, int $songId): void {
    if (!isset($_SESSION['viewed_songs'])) {
        $_SESSION['viewed_songs'] = [];
    }

    $now = time();
    $lastViewed = $_SESSION['viewed_songs'][$songId] ?? 0;

    if ($now - $lastViewed >= 3600) {
        // Not viewed in the last hour, increment view count
        $stmt = $mysqli->prepare("UPDATE songs SET views = views + 1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $songId);
            $stmt->execute();
            $stmt->close();
        }

        // Update session timestamp
        $_SESSION['viewed_songs'][$songId] = $now;
    }
    clean_old_views();

}

function clean_old_views(){
    foreach ($_SESSION['viewed_songs'] as $id => $timestamp) {
        if (time() - $timestamp > 86400) { // older than 1 day
            unset($_SESSION['viewed_songs'][$id]);
        }
    }
}


function filter_difficulty_tags($string) {
    if (!is_string($string)) {
        $string = '';
    }

    $tagsArray = array_filter(array_map('trim', explode(',', strtolower($string))));
    $validDifficulties = ['easy', 'normal', 'hard'];
    $difficulty = '';
    $filteredTags = [];

    foreach ($tagsArray as $tag) {
        if (in_array($tag, $validDifficulties, true)) {
            $difficulty = $tag;
        } else {
            $filteredTags[] = $tag;
        }
    }

    return [
        'tags' => implode(',', $filteredTags),
        'difficulty' => $difficulty
    ];
}


function display_difficulty_span($diff,$displayNotSet = True){
    $difficulty = ($diff != "") ? strtolower($diff) : '';
    $difficultyClass = match ($difficulty) {
        'easy' => 'tag-success',
        'normal' => 'tag-secondary',
        'hard' => 'tag-danger',
        default => 'tag-secondary'
    };
    
    if ($difficulty){
        echo ' <span class="tag ' . $difficultyClass .' rounded-pill">';
        echo     ucfirst(htmlspecialchars($difficulty));
        echo '</span>';
    }
    else if ($displayNotSet){
        echo "Not set";
    }
}


function edit_song($mysqli, $id, $data) {
    if ($mysqli->connect_errno) {
        return "Error connecting";
    }

    // Fetch original tags
    $originalTagsStr = '';
    $stmt = $mysqli->prepare("SELECT tags FROM songs WHERE id = ?");
    if (!$stmt) return "Prepare failed: " . $mysqli->error;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $originalTagsStr = $row['tags'];
    }
    $stmt->close();

    $originalTags = array_filter(array_map('trim', explode(',', $originalTagsStr ?? '')));
    $newTagsArray = array_filter(array_map('trim', explode(',', $data["tags"] ?? '')));
    $normalizedTags = (implode(",",$newTagsArray)!="") ? implode(',', $newTagsArray) : null;

    $tagsToAdd = array_diff($newTagsArray, $originalTags);
    $tagsToRemove = array_diff($originalTags, $newTagsArray);


    $is_fixed = (count($newTagsArray) >= FIXED_TAGS_COUNT) ? 1 : 0;
    $data["is_nsfw"] = isset($data["is_nsfw"]) && $data["is_nsfw"] !== '' ? (int)$data["is_nsfw"] : 0;

    // Base fields always updated:
    $fields = [
        "category" => $data["category"],
        "title" => $data["title"],
        "capo" => $data["capo"],
        "is_public" => $data["is_public"],
        "tags" => $normalizedTags,
        "instruments" => $data["instruments"],
        "content" => $data["content"],
        "is_fixed" => $is_fixed,
        "is_nsfw" => $data["is_nsfw"]
    ];

    // Nullable optional fields — always included, set to NULL if empty
    $fields["artist"] = (isset($data["artist"]) && trim($data["artist"]) !== '') ? $data["artist"] : null;
    $fields["capo_alt"] = (isset($data["capo_alt"]) && trim($data["capo_alt"]) !== '') ? $data["capo_alt"] : null;
    $fields["youtube_link"] = (isset($data["youtube_link"]) && trim($data["youtube_link"]) !== '') ? $data["youtube_link"] : null;

    // Build the SQL dynamically:
    $setParts = [];
    $types = '';
    $params = [];
    foreach ($fields as $field => $value) {
        $setParts[] = "$field = ?";
        if (is_int($value)) {
            $types .= 'i';
        } else {
            // Use 's' for string or null (null is accepted and becomes SQL NULL)
            $types .= 's';
        }
        $params[] = $value;
    }
    $setParts[] = "modified_date = NOW()"; // no bind param for NOW()
    $setClause = implode(", ", $setParts);

    $sql = "UPDATE songs SET $setClause WHERE id = ?";
    $types .= 'i'; // for id
    $params[] = $id;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return "Prepare failed: " . $mysqli->error;

    // Bind params dynamically, need references for call_user_func_array
    $bind_params = [];
    $bind_params[] = &$types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_params[] = &$params[$i];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_params);

    if (!$stmt->execute()) {
        $stmt->close();
        return "Error: " . $stmt->error;
    }
    $stmt->close();

    // Handle tags add/remove:
    foreach ($tagsToRemove as $tag) {
        remove_tag($mysqli, $tag);
    }
    foreach ($tagsToAdd as $tag) {
        add_tag($mysqli, $tag);
    }

    return true;
}






function get_number_of_songs(mysqli $mysqli, ?string $username = null): int {
    if ($username === null) {
        // Count all songs
        $sql = "SELECT COUNT(*) AS total FROM songs";
        $result = $mysqli->query($sql);
        if (!$result) {
            error_log("MySQL Error: " . $mysqli->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    } else {
        // Count songs for this username in 'owner' column
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM songs WHERE owner = ?");
        if (!$stmt) {
            error_log("MySQL prepare error: " . $mysqli->error);
            return 0;
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            error_log("MySQL execute error: " . $stmt->error);
            $stmt->close();
            return 0;
        }
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)$row['total'];
    }
}



function get_new_songs_count($mysqli) {
    $sql = "
        SELECT COUNT(*) AS total 
        FROM songs 
        WHERE create_date >= DATE_SUB(NOW(), INTERVAL ".INTERVAL." DAY)
        AND is_public = 1
    ";

    $result = $mysqli->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'];
    } else {
        error_log("MySQL Error: " . $mysqli->error);
        return 0;
    }
}


function get_total_users($mysqli) {
    $sql = "SELECT COUNT(*) AS total FROM users";
    $result = $mysqli->query($sql);
    if (!$result) {
        error_log("MySQL Error: " . $mysqli->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['total'];
}

function add_activity($mysqli, $username, $type, $description, $is_public = 1) {
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli->connect_error;
        return false;
    }

    // Check for duplicate in last 10 minutes
    $stmt = $mysqli->prepare("
        SELECT id FROM activity_log 
        WHERE username = ? 
        AND type = ? 
        AND description = ? 
        AND time >= NOW() - INTERVAL ".ACTIVITY_COOLDOWN_MINUTES." MINUTE
        LIMIT 1
    ");

    if (!$stmt) {
        echo "Prepare failed: " . $mysqli->error;
        return false;
    }

    $stmt->bind_param("sss", $username, $type, $description);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Found recent duplicate
        $stmt->close();
        return "duplicate";
    }
    $stmt->close();

    // Insert new activity
    $stmt = $mysqli->prepare("
        INSERT INTO activity_log (username, type, time, description, is_public)
        VALUES (?, ?, NOW(), ?, ?)
    ");
    if (!$stmt) {
        echo "Prepare failed: " . $mysqli->error;
        return false;
    }

    $stmt->bind_param("sssi", $username, $type, $description, $is_public);

    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        echo "Error: " . $stmt->error;
        $stmt->close();
        return false;
    }
}
function get_activities_user($mysqli, string $type, string $username): int {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM activity_log WHERE type = ? AND username = ?");
    if (!$stmt) {
        error_log("MySQL Prepare Error: " . $mysqli->error);
        return 0;
    }

    $stmt->bind_param("ss", $type, $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $count = 0;

    if ($row = $result->fetch_assoc()) {
        $count = (int)$row['total'];
    }

    $stmt->close();
    return $count;
}


function get_recent_activity($mysqli, int $number, ?string $username = null): array {
	if ($mysqli->connect_errno) return [];

	// Base query
	$sql = "SELECT username, type, time, description 
			FROM activity_log ";

	$params = [];
	$types = "";

	if ($username !== null) {
		// Show all activity of the specified user
		$sql .= "WHERE username = ? ";
		$params[] = $username;
		$types .= "s";
	} elseif (!isset($_SESSION["admin_view"]) || $_SESSION["admin_view"] == false) {
		// Not an admin: show public or own private entries
		$sql .= "WHERE is_public = 1 OR username = ? ";
		$params[] = $_SESSION["user"];
		$types .= "s";
	}
	// Append ordering and limit
	$sql .= "ORDER BY time DESC LIMIT ?";
	$params[] = $number;
	$types .= "i";

	$stmt = $mysqli->prepare($sql);
	if ($stmt === false) return [];

	// Bind parameters dynamically
	$stmt->bind_param($types, ...$params);
	$stmt->execute();
	$result = $stmt->get_result();

	$activities = [];
	while ($row = $result->fetch_assoc()) {
		$activities[] = $row;
	}

	return $activities;
}


function get_all_songs($mysqli, $perPage, $page, $searchQuery, $category, $instruments, $tags = [], $owner = null, $finished = null) {
    if ($mysqli->connect_errno) {
        return [
            'songs' => [],
            'total' => 0,
            'totalPages' => 0
        ];
    }

    $sql = "SELECT id, category, title, artist, owner, is_public, tags, instruments, create_date, is_nsfw FROM songs";
    $conditions = [];
    $params = [];
    $types = "";

    // Visibility rules
    if (!isset($_SESSION["admin_view"]) || $_SESSION["admin_view"] == false) {
        $conditions[] = "(is_public = 1 OR owner = ?)";
        $params[] = $_SESSION["user"] ?? '';
        $types .= "s";
    }

    // User filter (partial match on owner)
    if (!empty($owner)) {
        $conditions[] = "owner LIKE ?";
        $params[] = "%" . $owner . "%";
        $types .= "s";
    }

    // Finished filter
    if ($finished === "0" || $finished === "1") {
        $conditions[] = "is_fixed = ?";
        $params[] = (int)$finished;
        $types .= "i";
    }

    // Search query - not case sensitive, match title OR artist
    if (!empty($searchQuery)) {
        $conditions[] = "(title LIKE ? OR artist LIKE ?)";
        $params[] = "%" . $searchQuery . "%";
        $params[] = "%" . $searchQuery . "%";
        $types .= "ss";
    }

    // Category filter
    if (!empty($category) && $category !== 'all') {
        $conditions[] = "category = ?";
        $params[] = (int)$category;
        $types .= "i";
    }

    // Instruments filter
    if (!empty($instruments) && is_array($instruments)) {
        foreach ($instruments as $instrument) {
            if (strlen(trim($instrument)) > 0) {
                $conditions[] = "FIND_IN_SET(?, instruments) > 0";
                $params[] = trim($instrument);
                $types .= "s";
            }
        }
    }

    // Tags filter
    if (!empty($tags) && is_array($tags)) {
        foreach ($tags as $tag) {
            if (strlen(trim($tag)) > 0) {
                $conditions[] = "FIND_IN_SET(?, tags) > 0";
                $params[] = trim($tag);
                $types .= "s";
            }
        }
    }

    // WHERE clause
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions) . " ";
    }


    // Ordering and pagination - order by category, then id
    $sql .= " ORDER BY category ASC, id ASC LIMIT ?, ?";

    $offset = ($page - 1) * $perPage;
    $params[] = $offset;
    $params[] = $perPage;
    $types .= "ii";

    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        return [
            'songs' => [],
            'total' => 0,
            'totalPages' => 0
        ];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $songs = [];
    while ($row = $result->fetch_assoc()) {
        $songs[] = $row;
    }
    $stmt->close();

    // Count total
    $countSql = "SELECT COUNT(*) AS total FROM songs ";
    if (!empty($conditions)) {
        $countSql .= "WHERE " . implode(" AND ", $conditions);
    }

    $countStmt = $mysqli->prepare($countSql);
    if ($countStmt === false) {
        return [
            'songs' => $songs,
            'total' => 0,
            'totalPages' => 0
        ];
    }

    // Remove pagination params for count
    $countParams = $params;
    $countTypes = $types;
    $countTypes = substr($countTypes, 0, -2); // remove 'ii' for limit params
    array_splice($countParams, -2);

    if ($countTypes !== '') {
        $countStmt->bind_param($countTypes, ...$countParams);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalSongs = $countResult->fetch_assoc()['total'] ?? 0;

    $countStmt->close();

    return [
        'songs' => $songs,
        'total' => (int)$totalSongs,
        'totalPages' => $perPage > 0 ? (int)ceil($totalSongs / $perPage) : 1
    ];
}

function get_new_songs($mysqli, $perPage, $page) {
    if ($mysqli->connect_errno) {
        return [
            'songs' => [],
            'total' => 0,
            'totalPages' => 0
        ];
    }

    $sql = "SELECT id, category, title, artist, owner, is_public, tags, instruments, create_date, is_nsfw FROM songs ";
    $conditions = [];
    $params = [];
    $types = "";

    // Visibility rules
    if (!isset($_SESSION["admin_view"]) || $_SESSION["admin_view"] == false) {
        $conditions[] = "(is_public = 1 OR owner = ?)";
        $params[] = $_SESSION["user"] ?? '';
        $types .= "s";
    }

    // Only songs from the past 7 days
    $conditions[] = "create_date >= DATE_SUB(NOW(), INTERVAL ".INTERVAL." DAY)";

    if (!empty($conditions)) {
        $sql .= "WHERE " . implode(" AND ", $conditions) . " ";
    }

    // Order and pagination
    $sql .= " ORDER BY create_date DESC LIMIT ?, ?";
    $offset = ($page - 1) * $perPage;
    $params[] = $offset;
    $params[] = $perPage;
    $types .= "ii";

    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        return [
            'songs' => [],
            'total' => 0,
            'totalPages' => 0
        ];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $songs = [];
    while ($row = $result->fetch_assoc()) {
        $songs[] = $row;
    }
    $stmt->close();

    // Count total
    $countSql = "SELECT COUNT(*) AS total FROM songs ";
    if (!empty($conditions)) {
        $countSql .= "WHERE " . implode(" AND ", $conditions);
    }

    $countStmt = $mysqli->prepare($countSql);
    if ($countStmt === false) {
        return [
            'songs' => $songs,
            'total' => 0,
            'totalPages' => 0
        ];
    }

    // Remove pagination params for count
    $countParams = $params;
    $countTypes = substr($types, 0, -2); // remove 'ii' for LIMIT
    array_splice($countParams, -2);

    if ($countTypes !== '') {
        $countStmt->bind_param($countTypes, ...$countParams);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalSongs = $countResult->fetch_assoc()['total'] ?? 0;

    $countStmt->close();

    return [
        'songs' => $songs,
        'total' => (int)$totalSongs,
        'totalPages' => $perPage > 0 ? (int)ceil($totalSongs / $perPage) : 1
    ];
}



function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diffDays = $now->diff($ago)->format('%a');
    $weeks = floor($diffDays / 7);
    $days = $diffDays % 7;


    $string = [
        'y' => $diff->y ? $diff->y . ' year' . ($diff->y > 1 ? 's' : '') : null,
        'm' => $diff->m ? $diff->m . ' month' . ($diff->m > 1 ? 's' : '') : null,
        'w' => $weeks ? $weeks . ' week' . ($weeks > 1 ? 's' : '') : null,
        'd' => $days ? $days . ' day' . ($days > 1 ? 's' : '') : null,
        'h' => $diff->h ? $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') : null,
        'i' => $diff->i ? $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') : null,
        's' => $diff->s ? $diff->s . ' second' . ($diff->s > 1 ? 's' : '') : null,
    ];

    $string = array_filter($string);

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }

    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function get_user($mysqli, $id) {
    // Prepare the statement to prevent SQL injection
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
    if (!$stmt) {
        // Handle prepare error if needed
        return null;
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    } else {
        $stmt->close();
        return null; // User not found
    }
}

function calculate_level(int $xp): array {
    $level = 1;
    $required = 100;
    $increment = 50;
    $max_increment = 1000;
    $current_total = 0;
    $used_1000 = false;

    while ($xp >= $required) {
        $xp -= $required;
        $current_total += $required;
        $level++;

        // Determine next increment
        if ($level <= 5) {
            $increment += 50;
        } else {
            if (!$used_1000) {
                // Normal +100 increments, skipping 900
                $increment += 100;
                if ($increment === 900) {
                    $increment = 1000;
                }

                if ($increment >= 1000) {
                    $increment = 1000;
                    $used_1000 = true;

                    // First time using 1000, make sure cumulative XP ends in 000
                    if ($current_total % 1000 !== 0) {
                        $adjustment = 1000 - ($current_total % 1000);

                        if ($xp < $adjustment) {
                            // Not enough XP left — backtrack and stop
                            $level--;
                            $current_total -= $required;
                            $xp += $required;
                            break;
                        }

                        $xp -= $adjustment;
                        $current_total += $adjustment;
                    }
                }
            }
        }

        $required = $increment;
    }

    $progress = $xp / $required;
    $next_level_xp = $current_total + $required;

    return [
        'level' => $level,
        'progress' => $progress,
        'next_level_xp' => $next_level_xp
    ];
}


function get_xp(mysqli $mysqli, int $id): ?array {
    // $stmt = $mysqli->prepare("SELECT * FROM user_xp WHERE user_id = ?");
    // if (!$stmt) return null;

    // $stmt->bind_param("i", $id);
    // $stmt->execute();
    // $result = $stmt->get_result();

    // if ($result->num_rows === 0) {
    //     $stmt->close();
    //     return null;
    // }

    // $row = $result->fetch_assoc();
    // $stmt->close();

    // $xp_add = (int)$row["xp_add_song"];
    // $xp_edit = (int)$row["xp_edit_song"];


    // Instead calculate this manually
    $stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $username = $stmt->get_result()->fetch_assoc()['username'] ?? null;
    $stmt->close();
    
    if($username ===null){
        return null;
    }

    $xp_add = get_number_of_songs($mysqli,$username)*ADD_XP_AMOUNT;
    $xp_edit = get_activities_user($mysqli, "edit",$username)*EDIT_XP_AMOUNT;
    



    // continue normally
    $xp_total = $xp_add + $xp_edit;

    $levelInfo = calculate_level($xp_total);

    return [
        'total_xp' => $xp_total,
        'add_xp' => $xp_add,
        'xp_edit' => $xp_edit,
        'level' => $levelInfo['level'],
        'progress' => $levelInfo['progress'],
        'next_level_xp' => $levelInfo['next_level_xp']
    ];
}

function get_all_groups(mysqli $mysqli) {
    $sql = "SELECT id, name, password FROM group_list ORDER BY name ASC";
    $result = $mysqli->query($sql);

    $groups = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
    }

    return $groups;
}

function add_group(mysqli $mysqli, string $name, ?string $password, int $user_id): bool {
    $hashedPassword = hash('sha256', $password);
    $admin_ids = (string) $user_id;

    $stmt = $mysqli->prepare("INSERT INTO group_list (name, password, admin_ids, owner) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sssi", $name, $hashedPassword, $admin_ids, $user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function add_group_admin(mysqli $mysqli, int $group_id, array $user_ids): bool {
    // Fetch current admin_ids
    $stmt = $mysqli->prepare("SELECT admin_ids FROM group_list WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $stmt->bind_result($admin_ids_str);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    // Convert to array and merge new ids
    $current_admins = array_filter(explode(',', $admin_ids_str));
    $current_admins = array_map('intval', $current_admins);

    foreach ($user_ids as $id) {
        $id = (int)$id;
        if ($id > 0 && !in_array($id, $current_admins, true)) {
            $current_admins[] = $id;
        }
    }

    // Save updated list
    $updated_ids = implode(',', $current_admins);
    $update = $mysqli->prepare("UPDATE group_list SET admin_ids = ? WHERE id = ?");
    $update->bind_param("si", $updated_ids, $group_id);
    $success = $update->execute();
    $update->close();

    return $success;
}

function remove_group_admin(mysqli $mysqli, int $group_id, int $user_id): bool {
    $stmt = $mysqli->prepare("SELECT admin_ids FROM group_list WHERE id = ?");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $stmt->bind_result($admin_ids_str);
    if (!$stmt->fetch()) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    $admin_ids = array_filter(explode(',', $admin_ids_str), fn($id) => $id !== '' && (int)$id !== $user_id);
    $updated_ids = implode(',', $admin_ids);

    $update = $mysqli->prepare("UPDATE group_list SET admin_ids = ? WHERE id = ?");
    $update->bind_param("si", $updated_ids, $group_id);
    $success = $update->execute();
    $update->close();

    return $success;
}

function add_tag($mysqli, $tag) {
    // Check if tag exists
    $stmt = $mysqli->prepare("SELECT id, usage_count FROM song_tags WHERE name = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $tag);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Tag exists, increment usage_count
        $stmt->close();
        $stmt = $mysqli->prepare("UPDATE song_tags SET usage_count = usage_count + 1 WHERE name = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("s", $tag);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    } else {
        // Tag doesn't exist, insert with usage_count = 1
        $stmt->close();
        $stmt = $mysqli->prepare("INSERT INTO song_tags (name, usage_count) VALUES (?, 1)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("s", $tag);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}

function remove_tag($mysqli, $tag) {
    // Decrement usage_count by 1
    $stmt = $mysqli->prepare("UPDATE song_tags SET usage_count = usage_count - 1 WHERE name = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $tag);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows === 0) {
        // Tag not found or no update, return false
        return false;
    }

    // Delete tag if usage_count is now 0 or less
    $stmt = $mysqli->prepare("DELETE FROM song_tags WHERE name = ? AND usage_count <= 0");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $tag);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}


function get_all_tags($mysqli) {
    $result = $mysqli->query("SELECT name FROM song_tags ORDER BY usage_count DESC");
    if (!$result) {
        return [];
    }
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row['name'];
    }
    return $tags;
}

function get_all_instruments($mysqli) {
    $res = $mysqli->query("SELECT id, name FROM instruments");
    if (!$res) {
        return [];
    }

    return $res->fetch_all(MYSQLI_ASSOC);
}

function get_all_categories($mysqli) {
    $categories = [];

    $sql = "SELECT id, name FROM categories";
    $result = $mysqli->query($sql);

    if (!$result) {
        return []; 
    }

    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        // Option 1: Just the name
        $categories[$id] = $row['name'];

        // Option 2: Include index if you want (uncomment this line instead)
        // $categories[$id] = $row['category_index'] . ' ' . $row['name'];
    }

    $result->free();
    return $categories;
}
function get_all_users($mysqli, $limit, $page, $search = '', $group = '') {
    $where = [];
    $params = [];
    $types = '';

    // Search by name or username
    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR u.username LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'ss';
    }

    // Filter by group name
    if (!empty($group)) {
        $where[] = "g.name = ?";
        $params[] = $group;
        $types .= 's';
    }

    // Combine conditions
    $whereSQL = '';
    if (!empty($where)) {
        $whereSQL = 'WHERE ' . implode(' AND ', $where);
    }

    $offset = ($page - 1) * $limit;
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $sql = "
        SELECT SQL_CALC_FOUND_ROWS u.*, g.name AS group_name
        FROM users u
        LEFT JOIN group_list g ON u.group_id = g.id
        $whereSQL
        ORDER BY u.name
        LIMIT ? OFFSET ?
    ";

    // Prepare and bind
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        die("MySQL prepare failed: " . $mysqli->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    // Get total count
    $result = $mysqli->query("SELECT FOUND_ROWS() as total");
    $totalRows = $result->fetch_assoc()['total'];
    $totalPages = ceil($totalRows / $limit);

    return [
        'users' => $users,
        'totalPages' => $totalPages
    ];
}



function display_tag_selector($mysqli, $selectedTags = '') {
    $all_tags = get_all_tags($mysqli);

    $top8_tags = array_slice($all_tags, 0, 8);
    $joinedTagsString = implode(',', $top8_tags);
    $filtered = filter_difficulty_tags($joinedTagsString);

    $popular_tags = explode(',', $filtered['tags']);
    $popular_tags = array_filter(array_map('trim', $popular_tags));
    $popular_tags = array_slice($popular_tags, 0, 5);

    $selectedTagsArray = array_filter(array_map('trim', explode(',', $selectedTags)));

    // Merge selected and popular tags for left display
    $display_tags = array_unique(array_merge($popular_tags, $selectedTagsArray));

    $all_tags = (array)$all_tags;
    $display_tags = array_values($display_tags); // reindex
    $selectedTagsArray = array_map('strtolower', $selectedTagsArray); // normalize for JS
    $uniqid = uniqid();
    ?>
    <div class="tag-selector compact-inline" id="tag-selector-<?= $uniqid ?>">
        <div class="d-flex flex-column gap-2">
            <!-- Search input row (on top) -->
            <div class="position-relative" style="max-width: 250px;">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control tag-search" placeholder="Search..." style="min-width: 120px;">
                    <button class="btn btn-outline-success add-tag-btn" type="button" disabled title="Add tag">
                        +
                    </button>
                </div>
                <div class="search-results position-absolute d-none bg-white shadow-sm"></div>
            </div>

            <!-- Tag display row (popular + selected) -->
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <div class="popular-tags d-flex flex-wrap gap-1 flex-grow-0">
                    <?php foreach ($display_tags as $tag): ?>
                        <span class="tag badge rounded-pill bg-secondary" data-tag="<?= htmlspecialchars($tag) ?>">
                            <?= htmlspecialchars($tag) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <!-- Non-popular selected tags -->
                <div class="selected-tags d-flex flex-wrap gap-1 flex-grow-1"></div>
            </div>

        </div>
        <input type="hidden" name="selected_tags" class="selected-tags-input">
    </div>

    <style>
        .tag-selector.compact-inline .tag {
            cursor: pointer;
            padding: 0.3rem 0.6rem;
            margin-right: 0.2rem;
            display: inline-block;
        }
        .tag-selector.compact-inline .tag.selected {
            background-color: #212529 !important;
            color: white;
        }
        .tag-selector.compact-inline .search-results {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.3rem;
            z-index: 100;
            margin-top: 0.2rem;
            width: max-content;
            min-width: 150px;
        }
        .tag-selector.compact-inline .popular-tags {
            white-space: nowrap;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .tag-selector.compact-inline .selected-tags span {
            user-select: none;
            cursor: pointer;
        }
        .tag-selector.compact-inline .selected-tags span {
            user-select: none;
            cursor: pointer;
        }

    </style>

<script>
(function() {
    const container = document.getElementById('tag-selector-<?= $uniqid ?>');
    const allTags = <?= json_encode($all_tags) ?>;
    const displayTags = new Set(<?= json_encode($display_tags) ?>);
    const selectedTags = new Set(<?= json_encode($selectedTagsArray) ?>);

    const searchInput = container.querySelector('.tag-search');
    const searchResults = container.querySelector('.search-results');
    const addTagBtn = container.querySelector('.add-tag-btn');

    function filterOutDifficulty(tagsArray) {
        const difficultySet = new Set(['easy', 'normal', 'hard']);
        return tagsArray.filter(tag => !difficultySet.has(tag.toLowerCase()));
    }

    function toggleTagSelection(tag) {
        if (selectedTags.has(tag)) {
            selectedTags.delete(tag);
        } else {
            selectedTags.add(tag);
        }
        updateUI();
    }

    function addNewTag(tag) {
        tag = tag.trim();
        if (!tag || selectedTags.has(tag)) return;

        selectedTags.add(tag);
        updateUI();
        searchInput.value = '';
        searchResults.classList.add('d-none');
        updateAddTagButton();
    }

    function updateAddTagButton() {
        const input = searchInput.value.trim().toLowerCase();
        const isNew = input.length > 0 && !selectedTags.has(input);
        addTagBtn.disabled = !isNew;
    }

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('tag')) {
            const tag = e.target.dataset.tag;
            toggleTagSelection(tag);

            if (searchResults.contains(e.target)) {
                searchInput.value = '';
                searchResults.classList.add('d-none');
                updateAddTagButton();
            }
        }
    });

    container.querySelector('.selected-tags').addEventListener('click', function(e) {
        if (e.target.tagName === 'SPAN') {
            const tag = e.target.textContent.trim();
            if (selectedTags.has(tag)) {
                toggleTagSelection(tag);
                updateAddTagButton();
            }
        }
    });

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        searchResults.innerHTML = '';
        updateAddTagButton();

        if (term.length < 1) {
            searchResults.classList.add('d-none');
            return;
        }

        let matches = allTags.filter(tag => {
            const lowerTag = tag.toLowerCase();
            return (
                lowerTag.startsWith(term) &&
                !Array.from(selectedTags).some(t => t.toLowerCase() === lowerTag) &&
                !Array.from(displayTags).some(t => t.toLowerCase() === lowerTag)
            );
        });


        matches = filterOutDifficulty(matches).slice(0, 8);

        if (matches.length) {
            matches.forEach(tag => {
                const span = document.createElement('span');
                span.className = 'tag badge rounded-pill bg-secondary';
                span.dataset.tag = tag;
                span.textContent = tag;
                searchResults.appendChild(span);
            });
            searchResults.classList.remove('d-none');
        } else {
            searchResults.classList.add('d-none');
        }
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const newTag = this.value.trim();
            if (newTag.length > 0) {
                addNewTag(newTag);
            }
        }
    });

    addTagBtn.addEventListener('click', function() {
        const newTag = searchInput.value.trim();
        if (newTag.length > 0) {
            addNewTag(newTag);
        }
    });

    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            searchResults.classList.add('d-none');
        }
    });

    function updateUI() {
        const tagSpans = container.querySelectorAll('.popular-tags .tag');
        tagSpans.forEach(span => {
            const tag = span.dataset.tag;
            if (selectedTags.has(tag)) {
                span.classList.add('selected');
                span.classList.remove('bg-secondary');
                span.classList.add('bg-dark');
            } else {
                span.classList.remove('selected');
                span.classList.remove('bg-dark');
                span.classList.add('bg-secondary');
            }
        });

        const display = container.querySelector('.selected-tags');
        display.innerHTML = '';
        selectedTags.forEach(tag => {
            if (!displayTags.has(tag)) {
                const span = document.createElement('span');
                span.className = 'badge rounded-pill bg-dark';
                span.textContent = tag;
                display.appendChild(span);
            }
        });

        container.querySelector('.selected-tags-input').value = Array.from(selectedTags).join(',');
    }

    updateUI();
})();
</script>



    <?php
}







?>