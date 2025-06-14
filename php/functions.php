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

function add_user($mysqli, $name, $username, $password, $group_id, $profile_picture_path = null) {
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
        return "duplicate"; // Return a custom error indicator
    }
    $stmt->close();

    // Insert new user
    $hashed_password = hash("sha256", $password);
    $stmt = $mysqli->prepare(
        "INSERT INTO users (name, username, password, group_id, date_created, admin, profile_picture) 
         VALUES (?, ?, ?, ?, CURDATE(), 0, ?)"
    );

    if (!$stmt) {
        echo "Prepare failed: " . $mysqli->error;
        return false;
    }

    $stmt->bind_param("sssis", $name, $username, $hashed_password, $group_id, $profile_picture_path);

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
        $stmt = $mysqli->prepare("INSERT INTO songs (category, title, artist, capo, capo_alt, owner, is_public, create_date, modified_date, tags, instruments, content) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)");
        if (!$stmt) {
            return "Prepare failed: " . $mysqli->error;
        }

        $stmt->bind_param(
            "issiisisss",
            $data["category"],
            $data["title"],
            $data["artist"],
            $data["capo"],
            $data["capo_alt"],
            $data["owner"],
            $data["is_public"],
            $data["tags"],
            $data["instruments"],
            $data["content"]
        );

        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;  // get the insert id here
            $stmt->close();
            return $insertedId;  // return the inserted ID instead of true
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
    if (!$mysqli->connect_errno) {
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
                content = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            return "Prepare failed: " . $mysqli->error;
        }

        $stmt->bind_param(
            "issiiisssi", 
            $data["category"],     // int
            $data["title"],        // string (varchar 30)
            $data["artist"],       // string (varchar 30)
            $data["capo"],         // int (4)
			$data["capo_alt"],
            $data["is_public"],    // int (tinyint 1)
            $data["tags"],         // string (varchar 50)
            $data["instruments"],  // string (varchar 50)
            $data["content"],      // string (text)
            $id                    // int (primary key)
        );

        if ($stmt->execute()) {
            return true;
        } else {
            return "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        return "Error connecting";
    }
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



function get_new_songs_this_week($mysqli) {
    $sql = "
        SELECT COUNT(*) AS total 
        FROM songs 
        WHERE YEARWEEK(create_date, 1) = YEARWEEK(CURDATE(), 1)
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

function get_all_songs($mysqli, $perPage, $page, $searchQuery, $category, $instruments) {
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
        $params[] = $_SESSION["user"] ?? ''; // fallback empty string if user not set
        $types .= "s";
    }

    // Search query - not case sensitive, match title OR artist
    if (!empty($searchQuery)) {
        $conditions[] = "(title LIKE ? OR artist LIKE ?)";
        $params[] = "%" . $searchQuery . "%";
        $params[] = "%" . $searchQuery . "%";
        $types .= "ss";
    }
    // Category filter (category is int, so use 'i')
    if (!empty($category) && $category !== 'all') {
        $conditions[] = "category = ?";
        $params[] = (int)$category;
        $types .= "i";
    }

    // Instruments filter: array of strings
    if (!empty($instruments) && is_array($instruments)) {
        foreach ($instruments as $instrument) {
            if (strlen(trim($instrument)) > 0) {
                $conditions[] = "instruments LIKE ?";
                $params[] = "%" . trim($instrument) . "%";
                $types .= "s";
            }
        }
    }

    // Combine WHERE clause
    if (!empty($conditions)) {
        $sql .= "WHERE " . implode(" AND ", $conditions) . " ";
    }

    // Ordering and pagination
    $sql .= "ORDER BY category ASC, id ASC LIMIT ?, ?";
    $offset = ($page - 1) * $perPage;
    $params[] = $offset;
    $params[] = $perPage;
    $types .= "ii";

    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        // Prepare failed
        return [
            'songs' => [],
            'total' => 0,
            'totalPages' => 0
        ];
    }

    // Bind params only if $types is non-empty
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

    // Count total for pagination - reuse conditions and params except LIMIT
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

    // Remove last two params (LIMIT offset and limit) for count query
    $countParams = $params;
    $countTypes = $types;

    // Remove last two 'i's for limit
    $countTypes = substr($countTypes, 0, -2);
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





?>