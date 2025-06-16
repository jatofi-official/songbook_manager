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

function add_song($mysqli, $data){
    if (!$mysqli->connect_errno) {
        // Count tags and decide is_fixed
        $tagsArray = array_filter(array_map('trim', explode(',', $data["tags"])));
        $is_fixed = (count($tagsArray) >= 3) ? 1 : 0;

        $stmt = $mysqli->prepare("INSERT INTO songs (category, title, artist, capo, capo_alt, owner, is_public, create_date, modified_date, tags, instruments, content, is_fixed) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)");
        if (!$stmt) {
            return "Prepare failed: " . $mysqli->error;
        }

        $stmt->bind_param(
            "issiisisssi",
            $data["category"],
            $data["title"],
            $data["artist"],
            $data["capo"],
            $data["capo_alt"],
            $data["owner"],
            $data["is_public"],
            $data["tags"],
            $data["instruments"],
            $data["content"],
            $is_fixed
        );

        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;  // get the insert id here
            $stmt->close();

            // Add each tag with add_tag helper (updates usage_count etc.)
            foreach ($tagsArray as $tag) {
                add_tag($mysqli, $tag);
            }

            return $insertedId;
        } else {
            $error = "Error: " . $stmt->error;
            $stmt->close();
            return $error;
        }
    } else {
        return "Error connecting";
    }
}


function edit_song($mysqli, $id, $data) {
    if ($mysqli->connect_errno) {
        return "Error connecting";
    }

    // Step 1: Get original tags for this song
    $stmt = $mysqli->prepare("SELECT tags FROM songs WHERE id = ?");
    if (!$stmt) {
        return "Prepare failed: " . $mysqli->error;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $originalTagsStr = '';
    if ($row = $result->fetch_assoc()) {
        $originalTagsStr = $row['tags'];
    }
    $stmt->close();

    // Step 2: Prepare tag arrays
    $originalTags = array_filter(array_map('trim', explode(',', $originalTagsStr)));
    $newTags = array_filter(array_map('trim', explode(',', $data["tags"])));

    // Step 3: Calculate tags to add and remove
    $tagsToAdd = array_diff($newTags, $originalTags);
    $tagsToRemove = array_diff($originalTags, $newTags);

    // Step 4: Update is_fixed based on new tags count
    $is_fixed = (count($newTags) >= 3) ? 1 : 0;

    // Step 5: Update the song row (including new tags and is_fixed)
    $stmt = $mysqli->prepare("
        UPDATE songs 
        SET 
            category = ?, 
            title = ?, 
            artist = ?, 
            capo = ?,
            capo_alt = ?, 
            is_public = ?, 
            modified_date = NOW(), 
            tags = ?, 
            instruments = ?, 
            content = ?,
            is_fixed = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        return "Prepare failed: " . $mysqli->error;
    }

    $stmt->bind_param(
        "issiiisssii",
        $data["category"],     // int
        $data["title"],        // string (varchar 30)
        $data["artist"],       // string (varchar 30)
        $data["capo"],         // int
        $data["capo_alt"],     // int
        $data["is_public"],    // int (tinyint 1)
        $data["tags"],         // string (varchar 50)
        $data["instruments"],  // string (varchar 50)
        $data["content"],      // string (text)
        $is_fixed,             // int
        $id                    // int (primary key)
    );

    if (!$stmt->execute()) {
        $stmt->close();
        return "Error: " . $stmt->error;
    }
    $stmt->close();

    // Step 6: Remove old tags
    foreach ($tagsToRemove as $tag) {
        remove_tag($mysqli, $tag);
    }

    // Step 7: Add new tags
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


function get_recent_activity($mysqli, $number) {
	if (!$mysqli->connect_errno){
		// Base query
		$sql = "SELECT username, type, time, description 
				FROM activity_log ";

		$params = [];
		$types = "";

		// Apply visibility rules
		if (!isset($_SESSION["admin_view"]) || $_SESSION["admin_view"] == false) {
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
	else{
		return [];
	}
}

function get_all_songs($mysqli, $perPage, $page, $searchQuery, $category, $instruments, $tags = [], $owner = null) {
    if ($mysqli->connect_errno) {
        return [
            'songs' => [],
            'total' => 0,
            'totalPages' => 0
        ];
    }

    $sql = "SELECT id, category, title, artist, owner, is_public, tags, instruments, create_date FROM songs";
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

    $sql = "SELECT id, category, title, artist, owner, is_public, tags, instruments, create_date FROM songs ";
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

    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

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
    $required = 150;
    $increment = 50;
    $cap = 1000;
    $current_total = 0;

    while ($xp >= $required) {
        $xp -= $required;
        $current_total += $required;
        $level++;
        $increment = min($increment + 50, $cap);
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








?>