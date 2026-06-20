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

function check_is_fixed($data, $category = null) {
    // Make sure $data is an array
    if (!is_array($data)) {
        return 0;
    }

    // Special rule for "duchovne"
    if ($category === 8||$category===9) {
        return (count($data) >= 2) ? 1 : 0;
    }

    // Default rule
    return (count($data) >= FIXED_TAGS_COUNT) ? 1 : 0;
}





function add_song($mysqli, $data) {
    if ($mysqli->connect_errno) {
        return "Error connecting";
    }

    // Normalize and clean tags
    $tagsArray = array_filter(array_map('trim', explode(',', $data["tags"] ?? '')));
    $normalizedTags = (implode(",", $tagsArray) != "") ? implode(',', $tagsArray) : null;

    // Category
    $category = (int)$data["category"];

    // Determine is_fixed
    $is_fixed = check_is_fixed($tagsArray, $category);

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


    $is_fixed = check_is_fixed($newTagsArray, (int)$data["category"]);
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






function get_number_of_songs(mysqli $mysqli, ?string $username = null): array {
    if ($username === null) {
        // Count all songs, fixed and not fixed
        $sql = "
            SELECT 
                COUNT(*) AS total,
                SUM(is_fixed = 1) AS fixed,
                SUM(is_fixed = 0) AS not_fixed
            FROM songs
        ";
        $result = $mysqli->query($sql);
        if (!$result) {
            error_log("MySQL Error: " . $mysqli->error);
            return ['total' => 0, 'fixed' => 0, 'not_fixed' => 0];
        }
        $row = $result->fetch_assoc();
        return [
            'total' => (int)$row['total'],
            'fixed' => (int)$row['fixed'],
            'not_fixed' => (int)$row['not_fixed'],
        ];
    } else {
        // Count songs for this username in 'owner' column
        $stmt = $mysqli->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(is_fixed = 1) AS fixed,
                SUM(is_fixed = 0) AS not_fixed
            FROM songs
            WHERE owner = ?
        ");
        if (!$stmt) {
            error_log("MySQL prepare error: " . $mysqli->error);
            return ['total' => 0, 'fixed' => 0, 'not_fixed' => 0];
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            error_log("MySQL execute error: " . $stmt->error);
            $stmt->close();
            return ['total' => 0, 'fixed' => 0, 'not_fixed' => 0];
        }
        $row = $result->fetch_assoc();
        $stmt->close();

        return [
            'total' => (int)$row['total'],
            'fixed' => (int)$row['fixed'],
            'not_fixed' => (int)$row['not_fixed'],
        ];
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
    $sql = "SELECT username, type, time, description FROM activity_log ";
    $params = [];
    $types = "";

    if ($username !== null) {
        // Filter by specific username (only public)
        $sql .= "WHERE username = ? AND is_public = 1 ";
        $params[] = $username;
        $types .= "s";
    } else {
        // No username filter: show only public activities
        $sql .= "WHERE is_public = 1 ";
    }

    // Ordering and limit
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

    $stmt->close();
    return $activities;
}

function get_recent_activity_number($mysqli, $type = "") {
    if ($type !== "") {
        // Count only a specific activity type in the last 7 days
        $stmt = $mysqli->prepare("
            SELECT COUNT(*) AS count
            FROM activity_log
            WHERE type = ?
              AND time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->bind_param("s", $type);
    } else {
        // Count all non-login activity in the last 7 days
        $stmt = $mysqli->prepare("
            SELECT COUNT(*) AS count
            FROM activity_log
            WHERE type NOT IN ('auto_login', 'manual_login','guest')
              AND time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }

    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['count'];
    } else {
        error_log("MySQL Error in get_recent_activity_number: " . $mysqli->error);
        return 0;
    }
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
    $stmt = $mysqli->prepare("SELECT * FROM user_xp WHERE user_id = ?");
    if (!$stmt) return null;

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // $xp_add = (int)$row["xp_add_song"];
    // $xp_edit = (int)$row["xp_edit_song"];
    $xp_special = (int)$row["special_xp"];


    // Instead calculate this manually
    $stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $username = $stmt->get_result()->fetch_assoc()['username'] ?? null;
    $stmt->close();
    
    if($username ===null){
        return null;
    }

    $xp_add = get_number_of_songs($mysqli,$username)["total"]*ADD_XP_AMOUNT;
    $xp_edit = get_activities_user($mysqli, "edit",$username)*EDIT_XP_AMOUNT;
    $xp_special += get_activities_user($mysqli,"Anketa",$username)*250;



    // continue normally
    $xp_total = $xp_add + $xp_edit + $xp_special;

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

    $sql = "SELECT id, name FROM categories ORDER BY category_index ASC";
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



function display_tag_selector($mysqli, $selectedTags = '', $allow_new = True) {
    $all_tags = get_all_tags($mysqli);


    $selectedTagsArray = array_filter(array_map('trim', explode(',', $selectedTags)));

    $display_tags = $selectedTagsArray;

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
                    <?php if($allow_new ) {?>
                    <button class="btn btn-outline-success add-tag-btn" type="button" disabled title="Add tag">
                        +
                    </button>
                    <?php }?>
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
            min-width: 200px;
            max-width: 300px;
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            background-color: white;
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
        if (!addTagBtn) return;  // <-- skip if button doesn't exist
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

    function updateSearchResults(term = '') {
        searchResults.innerHTML = '';
        updateAddTagButton();

        let matches = allTags.filter(tag => {
            const lowerTag = tag.toLowerCase();
            return (
                (term === '' || lowerTag.startsWith(term)) &&
                !Array.from(selectedTags).some(t => t.toLowerCase() === lowerTag) &&
                !Array.from(displayTags).some(t => t.toLowerCase() === lowerTag)
            );
        });

        matches = filterOutDifficulty(matches);

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
    }

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        updateSearchResults(term);
    });

    searchInput.addEventListener('focus', function() {
        updateSearchResults(this.value.toLowerCase());
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

    <?php if ($allow_new) : ?>
        addTagBtn.addEventListener('click', function() {
            const newTag = searchInput.value.trim();
            if (newTag.length > 0) {
                addNewTag(newTag);
            }
        });
    <?php endif; ?>



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

function get_song_title($mysqli, $id) {
    $stmt = $mysqli->prepare("
        SELECT id, title, artist, capo, capo_alt, owner, is_fixed
        FROM songs
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($id, $title, $artist, $capo, $capo_alt, $owner, $is_fixed);
    $stmt->fetch();
    $stmt->close();

    return [
        "id" => $id,
        "title" => $title ?? "Untitled",
        "artist" => $artist ?? "",
        "capo" => $capo,
        "capo_alt" => $capo_alt,
        "owner" => $owner,
        "is_fixed" => $is_fixed
    ];
}



function get_user_by_username($mysqli, $username) {
    $stmt = $mysqli->prepare("SELECT id, name FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}







// SONGBOOK FUNCTIONS


function create_songbook($mysqli, $name, $description, $owner, $admins, $groupId = null, $isPublic = false) {
    $categories = "";
    $songs = "";
    $numberOfSongs = 0;

    $stmt = $mysqli->prepare("INSERT INTO songbooks 
        (name, description, owner, admins, categories, songs, number_of_songs, group_id, is_public) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssisssiii",
        $name, $description, $owner, $admins,
        $categories, $songs, $numberOfSongs,
        $groupId, $isPublic
    );

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId; // return the new songbook ID
    } else {
        $stmt->close();
        return false;
    }
}




function update_songbook($mysqli, $id, $name, $description, $admins, $categories, $songs, $groupId = null, $isPublic = false) {
    $groupId = $groupId ?? 0;
    $isPublic = $isPublic ? 1 : 0;

    $sql = "UPDATE songbooks SET 
        name = ?, 
        description = ?, 
        admins = ?, 
        categories = ?, 
        songs = ?, 
        group_id = ?, 
        is_public = ?,
        modified_date = NOW()
        WHERE id = ?";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("sssssiii", $name, $description, $admins, $categories, $songs, $groupId, $isPublic, $id);
    $stmt->execute();

    if ($stmt->error) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    return $stmt->affected_rows > 0;
}






function delete_songbook($mysqli, $id) {
    $userId = $_SESSION["id"];

    $stmt = $mysqli->prepare("SELECT owner FROM songbooks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($ownerId);
    $stmt->fetch();
    $stmt->close();

    if ($ownerId != $userId) {
        return false; // Not authorized
    }

    $stmt = $mysqli->prepare("DELETE FROM songbooks WHERE id = ?");
    $stmt->bind_param("i", $id);

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}



function get_all_songbooks($mysqli, $limit = 20, $offset = 0, $search = null) {
    $params = [];
    $types = "";
    $conditions = [];

    // --- Admin override: show all songbooks ---
    if (!empty($_SESSION["admin_view"])) {
        // Skip all access rules; only apply search if present
        if (!empty($search)) {
            $conditions[] = "(s.name LIKE ? OR s.description LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }
    } elseif (!isset($_SESSION["id"])) {
        // Not logged in → only fully public songbooks
        $conditions[] = "s.is_public = 2";
    } else {
        // Logged in → normal rules
        $userId = $_SESSION["id"];
        $user = get_user($mysqli, $userId);
        if (!$user) {
            return ["songbooks" => [], "total" => 0, "totalPages" => 0];
        }
        $userGroupId = $user["group_id"];

        $conditions[] = "(
            s.is_public = 2
            OR (s.is_public = 1 AND (s.group_id = ? OR FIND_IN_SET(?, s.admins)))
            OR (s.is_public = 0 AND FIND_IN_SET(?, s.admins))
        )";

        $params[] = $userGroupId;
        $params[] = $userId;
        $params[] = $userId;
        $types .= "iii";

        // Optional search filter
        if (!empty($search)) {
            $conditions[] = "(s.name LIKE ? OR s.description LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = " WHERE " . implode(" AND ", $conditions);
    }

    // --- COUNT QUERY ---
    $countSql = "SELECT COUNT(*) FROM songbooks s" . $whereClause;
    $countStmt = $mysqli->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countStmt->bind_result($totalCount);
    $countStmt->fetch();
    $countStmt->close();

    $totalPages = ($limit > 0) ? ceil($totalCount / $limit) : 1;

    // --- DATA QUERY ---
    $dataSql = "
        SELECT 
            s.id,
            s.name,
            s.description,
            s.owner,
            s.number_of_songs,
            s.admins,
            g.name AS group_name
        FROM songbooks s
        LEFT JOIN group_list g ON s.group_id = g.id
        $whereClause
        ORDER BY s.name ASC
        LIMIT ? OFFSET ?
    ";

    $dataStmt = $mysqli->prepare($dataSql);

    $finalParams = $params;
    $finalTypes = $types . "ii";
    $finalParams[] = $limit;
    $finalParams[] = $offset;

    $dataStmt->bind_param($finalTypes, ...$finalParams);
    $dataStmt->execute();
    $result = $dataStmt->get_result();

    $songbooks = [];
    while ($row = $result->fetch_assoc()) {
        $songbooks[] = $row;
    }

    $dataStmt->close();

    return [
        "songbooks" => $songbooks,
        "total" => $totalCount,
        "totalPages" => $totalPages
    ];
}


function get_songbook($mysqli, $id) {
    $stmt = $mysqli->prepare("SELECT * FROM songbooks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $songbook = $result->fetch_assoc();
    $stmt->close();

    if (!$songbook) {
        return null;
    }

    // Parse admins as array
    $songbook["admins"] = $songbook["admins"] ? explode(",", $songbook["admins"]) : [];

    // Parse categories into id-name pairs
    $categories_raw = $songbook["categories"] ? explode(",", $songbook["categories"]) : [];
    $categories = [];
    foreach ($categories_raw as $cat) {
        [$catId, $catName] = explode("-", $cat, 2);
        $categories[$catId] = $catName;
    }

    // Every songbook has an implicit "Uncategorized" bucket (id 0) for songs
    // added without picking a category (e.g. fulfilled wishlist requests).
    if (!array_key_exists("0", $categories)) {
        $categories["0"] = "Uncategorized";
    }

    // Parse songs and group by category ID
    $songs_raw = $songbook["songs"] ? explode(",", $songbook["songs"]) : [];
    $songs_by_category = [];

    foreach ($songs_raw as $pair) {
        [$catId, $songId] = explode("-", $pair, 2);
        if (!isset($songs_by_category[$catId])) {
            $songs_by_category[$catId] = [];
        }
        $songs_by_category[$catId][] = $songId;
    }

    // Build structured categories with songs
    $songs_categories = [];
    foreach ($categories as $catId => $catName) {
        $songs_categories[] = [
            "id" => (int)$catId,
            "name" => $catName,
            "songs" => isset($songs_by_category[$catId]) ? $songs_by_category[$catId] : []
        ];
    }

    // Include both raw parsed categories and grouped songs
    $songbook["categories"] = $categories; // flat array: [id => name]
    $songbook["songs_categories"] = $songs_categories;

    return $songbook;
}

function get_number_of_songbooks($mysqli, $only_public = 1) {
    if ($only_public) {
        // Count only public songbooks
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM songbooks WHERE is_public = ?");
        $stmt->bind_param("i", $only_public);
    } else {
        // Count all songbooks
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM songbooks");
    }

    if (!$stmt->execute()) {
        error_log("MySQL Error in get_number_of_songbooks: " . $mysqli->error);
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)($row["total"] ?? 0);
}


// Get user group
function get_user_group($mysqli, $userId) {
    // Prepare statement to avoid SQL injection
    $stmt = $mysqli->prepare("SELECT `group_id` FROM `users` WHERE `id` = ?");
    if (!$stmt) {
        // Optional: log or handle error
        return null;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($groupId);
    $stmt->fetch();
    $stmt->close();

    return $groupId; // returns null if no user found
}


function get_user_ip(): ?string {
    $ipSources = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR', // may contain comma-separated list
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ipSources as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $ip = $_SERVER[$key];

        // If header contains a list (e.g. X-Forwarded-For), take the first one
        if (strpos($ip, ',') !== false) {
            $parts = array_map('trim', explode(',', $ip));
            $ip = $parts[0];
        }

        // Validate IP (both IPv4 and IPv6)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $ip;
        }

        // If not a public IP, still accept a valid IP (without private/reserved filter)
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }
    }

    return null;
}

function get_public_profile_settings($mysqli, $userId) {
    $stmt = $mysqli->prepare("
        SELECT bio, customisation, instruments
        FROM user_settings
        WHERE user_id = ?
    ");
    if (!$stmt) return null;

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        // Instruments: comma-separated
        if (!empty($row['instruments'])) {
            $row['instruments'] = array_map('trim', explode(',', $row['instruments']));
            $row['instruments'] = array_map('htmlspecialchars', $row['instruments']);
        } else {
            $row['instruments'] = [];
        }

        // Bio: sanitize
        $row['bio'] = htmlspecialchars($row['bio'] ?? '');

        return $row;
    }

    return null;
}



function load_user_settings_into_session($mysqli) {
    if (!isset($_SESSION["id"])) {
        return false; // not logged in
    }

    $userId = $_SESSION["id"];

    $stmt = $mysqli->prepare("SELECT advanced_view, admin_view FROM user_settings WHERE user_id = ?");
    if (!$stmt) {
        error_log("DB error: " . $mysqli->error);
        return false;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($advancedView, $adminView);

    if ($stmt->fetch()) {
        $_SESSION["advanced_view"] = (bool)$advancedView;
        $_SESSION["admin_view"] = (bool)$adminView;
        $stmt->close();
        return true;
    }

    $stmt->close();
    return false; // no row found
}

function create_default_user_settings($mysqli, $userId) {
    $stmt = $mysqli->prepare("
        INSERT INTO user_settings (user_id, advanced_view, admin_view)
        VALUES (?, 0, 0)
    ");
    if (!$stmt) {
        error_log("DB error in create_default_user_settings: " . $mysqli->error);
        return false;
    }
    $stmt->bind_param("i", $userId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}




// WISHLIST FUNCTIONS


// Whether a song with this title/artist (case-insensitive) already exists in
// the main songs table. Returns the matching row (id, title, artist) or null.
function find_song_in_database($mysqli, $name, $artist) {
    $name = trim($name);
    $artist = trim($artist ?? '');

    $stmt = $mysqli->prepare("SELECT id, title, artist FROM songs WHERE LOWER(title) = LOWER(?) AND LOWER(COALESCE(artist, '')) = LOWER(?)");
    $stmt->bind_param("ss", $name, $artist);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function add_wishlist_song($mysqli, $name, $artist, $youtubeLink, $username) {
    $name = trim($name);
    $artist = trim($artist ?? '');
    $youtubeLink = trim($youtubeLink ?? '');

    if ($name === '') {
        return ['status' => 'error', 'message' => 'Name is required.'];
    }

    $existingSong = find_song_in_database($mysqli, $name, $artist);
    if ($existingSong) {
        return ['status' => 'in_database', 'song_id' => (int)$existingSong['id']];
    }

    $stmt = $mysqli->prepare("SELECT id FROM wishlist_songs WHERE LOWER(name) = LOWER(?) AND LOWER(COALESCE(artist, '')) = LOWER(?)");
    $stmt->bind_param("ss", $name, $artist);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        return ['status' => 'duplicate', 'id' => (int)$existing['id']];
    }

    $stmt = $mysqli->prepare("INSERT INTO wishlist_songs (name, artist, youtube_link, username) VALUES (?, ?, ?, ?)");
    $artistParam = $artist !== '' ? $artist : null;
    $linkParam = $youtubeLink !== '' ? $youtubeLink : null;
    $stmt->bind_param("ssss", $name, $artistParam, $linkParam, $username);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    if ($username) {
        $likeStmt = $mysqli->prepare("INSERT INTO wishlist_song_likes (wishlist_song_id, username) VALUES (?, ?)");
        $likeStmt->bind_param("is", $newId, $username);
        $likeStmt->execute();
        $likeStmt->close();
    }

    return ['status' => 'ok', 'id' => $newId];
}

function get_wishlist_songs($mysqli, $username = null) {
    $sql = "SELECT ws.*,
            EXISTS(SELECT 1 FROM wishlist_song_likes l WHERE l.wishlist_song_id = ws.id AND l.username = ?) AS liked_by_me
            FROM wishlist_songs ws
            ORDER BY ws.likes DESC, ws.create_date DESC";
    $stmt = $mysqli->prepare($sql);
    $usernameParam = $username ?? '';
    $stmt->bind_param("s", $usernameParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['liked_by_me'] = (bool)$row['liked_by_me'];
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// Songbooks a guest/user is allowed to target with a wishlist request:
// fully-public ones, or group-restricted ones the user belongs to/administers.
// Private (is_public = 0) songbooks are never eligible. Admins see every songbook.
// WISHLIST_DEFAULT_SONGBOOK_NAME is always included regardless of visibility,
// since it's meant to be the default target for everyone, logged in or not.
function get_songbooks_for_wishlist($mysqli) {
    $conditions = ["s.is_public IN (1, 2)"];
    $params = [];
    $types = "";
    $isAdmin = !empty($_SESSION["admin_view"]);

    if (!$isAdmin) {
        if (!isset($_SESSION["id"])) {
            $conditions[] = "s.is_public = 2";
        } else {
            $userId = $_SESSION["id"];
            $userGroupId = get_user_group($mysqli, $userId);
            $conditions[] = "(s.is_public = 2 OR (s.is_public = 1 AND (s.group_id = ? OR FIND_IN_SET(?, s.admins))))";
            $params[] = $userGroupId;
            $params[] = $userId;
            $types .= "ii";
        }
    } else {
        $conditions = []; // admins may target any songbook, including private ones
    }

    $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
    $sql = "SELECT s.id, s.name, s.group_id, s.admins, s.is_public FROM songbooks s" . $whereClause . " ORDER BY s.name ASC";
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    $hasDefault = false;
    while ($row = $result->fetch_assoc()) {
        if ($row['name'] === WISHLIST_DEFAULT_SONGBOOK_NAME) {
            $hasDefault = true;
        }
        $rows[] = $row;
    }
    $stmt->close();

    if (!$isAdmin && !$hasDefault) {
        $stmt = $mysqli->prepare("SELECT id, name, group_id, admins, is_public FROM songbooks WHERE name = ?");
        $defaultName = WISHLIST_DEFAULT_SONGBOOK_NAME;
        $stmt->bind_param("s", $defaultName);
        $stmt->execute();
        $defaultRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($defaultRow) {
            array_unshift($rows, $defaultRow);
        }
    }

    return $rows;
}

function add_wishlist_songbook_entry($mysqli, $songbookId, $name, $artist, $youtubeLink, $existingSongId, $username) {
    $songbookId = (int)$songbookId;

    // Even if the caller didn't explicitly pick a database match, treat a
    // name/artist match against the real songs table as "existing song" too.
    if (!$existingSongId) {
        $matchedSong = find_song_in_database($mysqli, $name, $artist);
        if ($matchedSong) {
            $existingSongId = (int)$matchedSong['id'];
        }
    }

    if ($existingSongId) {
        $existingSongId = (int)$existingSongId;

        if (songbook_contains_song($mysqli, $songbookId, $existingSongId)) {
            return ['status' => 'in_songbook'];
        }

        $stmt = $mysqli->prepare("SELECT id FROM wishlist_songbooks WHERE songbook_id = ? AND song_id = ?");
        $stmt->bind_param("ii", $songbookId, $existingSongId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            return ['status' => 'duplicate', 'id' => (int)$existing['id']];
        }

        $stmt = $mysqli->prepare("INSERT INTO wishlist_songbooks (songbook_id, song_id, username) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $songbookId, $existingSongId, $username);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        if ($username) {
            $likeStmt = $mysqli->prepare("INSERT INTO wishlist_songbook_likes (wishlist_songbook_id, username) VALUES (?, ?)");
            $likeStmt->bind_param("is", $newId, $username);
            $likeStmt->execute();
            $likeStmt->close();
        }

        return ['status' => 'ok', 'id' => $newId];
    }

    $name = trim($name);
    $artist = trim($artist ?? '');
    $youtubeLink = trim($youtubeLink ?? '');

    if ($name === '') {
        return ['status' => 'error', 'message' => 'Name is required.'];
    }

    // Reuse a matching general wishlist entry instead of creating a duplicate.
    $stmt = $mysqli->prepare("SELECT id FROM wishlist_songs WHERE LOWER(name) = LOWER(?) AND LOWER(COALESCE(artist, '')) = LOWER(?)");
    $stmt->bind_param("ss", $name, $artist);
    $stmt->execute();
    $matchingWish = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($matchingWish) {
        $wishlistSongId = (int)$matchingWish['id'];

        $stmt = $mysqli->prepare("SELECT id FROM wishlist_songbooks WHERE songbook_id = ? AND wishlist_song_id = ?");
        $stmt->bind_param("ii", $songbookId, $wishlistSongId);
        $stmt->execute();
        $existingLink = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingLink) {
            return ['status' => 'duplicate', 'id' => (int)$existingLink['id']];
        }
    } else {
        $artistParam = $artist !== '' ? $artist : null;
        $linkParam = $youtubeLink !== '' ? $youtubeLink : null;
        $stmt = $mysqli->prepare("INSERT INTO wishlist_songs (name, artist, youtube_link, username) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $artistParam, $linkParam, $username);
        $stmt->execute();
        $wishlistSongId = $stmt->insert_id;
        $stmt->close();

        if ($username) {
            $likeStmt = $mysqli->prepare("INSERT INTO wishlist_song_likes (wishlist_song_id, username) VALUES (?, ?)");
            $likeStmt->bind_param("is", $wishlistSongId, $username);
            $likeStmt->execute();
            $likeStmt->close();
        }
    }

    $stmt = $mysqli->prepare("INSERT INTO wishlist_songbooks (songbook_id, wishlist_song_id, username) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $songbookId, $wishlistSongId, $username);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    if ($username) {
        $likeStmt = $mysqli->prepare("INSERT INTO wishlist_songbook_likes (wishlist_songbook_id, username) VALUES (?, ?)");
        $likeStmt->bind_param("is", $newId, $username);
        $likeStmt->execute();
        $likeStmt->close();
    }

    return ['status' => 'ok', 'id' => $newId];
}

function get_wishlist_songbook_entries($mysqli, $songbookId, $username = null) {
    $sql = "SELECT
                wsb.id, wsb.songbook_id, wsb.username, wsb.likes, wsb.create_date,
                wsb.wishlist_song_id, wsb.song_id,
                COALESCE(s.title, ws.name) AS name,
                COALESCE(s.artist, ws.artist) AS artist,
                ws.youtube_link AS youtube_link,
                (wsb.song_id IS NOT NULL) AS is_existing_song,
                EXISTS(SELECT 1 FROM wishlist_songbook_likes l WHERE l.wishlist_songbook_id = wsb.id AND l.username = ?) AS liked_by_me
            FROM wishlist_songbooks wsb
            LEFT JOIN wishlist_songs ws ON ws.id = wsb.wishlist_song_id
            LEFT JOIN songs s ON s.id = wsb.song_id
            WHERE wsb.songbook_id = ?
            ORDER BY wsb.likes DESC, wsb.create_date DESC";
    $stmt = $mysqli->prepare($sql);
    $usernameParam = $username ?? '';
    $stmt->bind_param("si", $usernameParam, $songbookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['liked_by_me'] = (bool)$row['liked_by_me'];
        $row['is_existing_song'] = (bool)$row['is_existing_song'];
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// $type is "song" (wishlist_songs/wishlist_song_likes) or "songbook" (wishlist_songbooks/wishlist_songbook_likes).
function toggle_wishlist_like($mysqli, $type, $id, $username) {
    $id = (int)$id;
    if ($type === 'song') {
        $entityTable = 'wishlist_songs';
        $likeTable = 'wishlist_song_likes';
        $likeColumn = 'wishlist_song_id';
    } elseif ($type === 'songbook') {
        $entityTable = 'wishlist_songbooks';
        $likeTable = 'wishlist_songbook_likes';
        $likeColumn = 'wishlist_songbook_id';
    } else {
        return ['status' => 'error', 'message' => 'Invalid type.'];
    }

    $stmt = $mysqli->prepare("SELECT id FROM $likeTable WHERE $likeColumn = ? AND username = ?");
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $stmt = $mysqli->prepare("DELETE FROM $likeTable WHERE id = ?");
        $stmt->bind_param("i", $existing['id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE $entityTable SET likes = likes - 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $liked = false;
    } else {
        $stmt = $mysqli->prepare("INSERT INTO $likeTable ($likeColumn, username) VALUES (?, ?)");
        $stmt->bind_param("is", $id, $username);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE $entityTable SET likes = likes + 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $liked = true;
    }

    $stmt = $mysqli->prepare("SELECT likes FROM $entityTable WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $likes = (int)($stmt->get_result()->fetch_assoc()['likes'] ?? 0);
    $stmt->close();

    return ['status' => 'ok', 'liked' => $liked, 'likes' => $likes];
}

// Voting on a songbook-targeted wishlist entry is restricted to that songbook's
// group (or its admins/site admins), even if the songbook itself is fully public,
// per product decision. If the songbook has no group, any logged-in user may vote.
function can_vote_on_wishlist_songbook($songbookRow, $userId, $userGroupId, $isAdmin) {
    if (!$userId) {
        return false;
    }
    if ($isAdmin) {
        return true;
    }
    if (in_array((string)$userId, $songbookRow['admins'] ?? [], true)) {
        return true;
    }
    if (empty($songbookRow['group_id'])) {
        return true;
    }
    return $userGroupId !== null && (int)$userGroupId === (int)$songbookRow['group_id'];
}

// A wishlist entry (general or songbook-targeted) may be removed by a site
// admin, or by the logged-in user who originally requested it.
function can_delete_wishlist_entry($entryRow, $username, $isAdmin) {
    if ($isAdmin) {
        return true;
    }
    return $username !== null && !empty($entryRow['username']) && $entryRow['username'] === $username;
}

// Whether a song already sits in a songbook, in any category. The `songs`
// column is a flat "catId-songId,catId-songId,..." string, so this checks
// the songId half of every pair rather than relying on a specific category.
function songbook_contains_song($mysqli, $songbookId, $songId) {
    $stmt = $mysqli->prepare("SELECT songs FROM songbooks WHERE id = ?");
    $stmt->bind_param("i", $songbookId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !$row['songs']) {
        return false;
    }

    $songId = (string)(int)$songId;
    foreach (explode(',', $row['songs']) as $pair) {
        $parts = explode('-', $pair, 2);
        if (isset($parts[1]) && $parts[1] === $songId) {
            return true;
        }
    }
    return false;
}

// Attaches an existing song to a songbook category, mirroring the client-side
// logic in songbook.php's "Add Song" modal so wishlist fulfillment can do the
// same thing from the server.
function attach_song_to_songbook($mysqli, $songbookId, $categoryId, $songId) {
    $songbook = get_songbook($mysqli, $songbookId);
    if (!$songbook) {
        return false;
    }

    $categoryId = (int)$categoryId;
    $songId = (int)$songId;

    if (songbook_contains_song($mysqli, $songbookId, $songId)) {
        return true;
    }

    $pairs = [];
    $found = false;
    foreach ($songbook['songs_categories'] as $cat) {
        $songsInCat = $cat['songs'];
        if ((int)$cat['id'] === $categoryId) {
            $songsInCat[] = (string)$songId;
            $found = true;
        }
        foreach ($songsInCat as $sid) {
            $pairs[] = $cat['id'] . '-' . $sid;
        }
    }

    if (!$found) {
        // Category not part of this songbook (shouldn't happen via the UI).
        return false;
    }

    $categoriesStr = implode(',', array_map(
        fn($id, $name) => $id . '-' . $name,
        array_keys($songbook['categories']),
        array_values($songbook['categories'])
    ));
    $adminsStr = implode(',', $songbook['admins']);
    $songsStr = implode(',', $pairs);

    return update_songbook(
        $mysqli,
        $songbookId,
        $songbook['name'],
        $songbook['description'],
        $adminsStr,
        $categoriesStr,
        $songsStr,
        $songbook['group_id'],
        $songbook['is_public']
    );
}

function delete_wishlist_song($mysqli, $id) {
    $stmt = $mysqli->prepare("DELETE FROM wishlist_songs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function delete_wishlist_songbook_entry($mysqli, $id) {
    $stmt = $mysqli->prepare("DELETE FROM wishlist_songbooks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}


?>