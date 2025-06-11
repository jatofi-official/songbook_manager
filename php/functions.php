<?php
date_default_timezone_set('Europe/Bratislava');

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

function add_user($mysqli, $name, $username, $password, $zbor) {
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli->connect_error;
        return false;
    }
    // Hash the password before storing it
    $hashed_password = hash("sha256",$password);

    // Prepare the SQL statement
    $stmt = $mysqli->prepare("INSERT INTO users (name, username, password, zbor, admin) VALUES (?, ?, ?, ?, 0)");

    if (!$stmt) {
        echo "Prepare failed: " . $mysqli->error;
        return false;
    }

    // Bind parameters
    $stmt->bind_param("sssi",$name, $username, $hashed_password, $zbor);

    // Execute the statement
    if ($stmt->execute()) {
        echo "User added successfully.";
        return true;
    } else {
        echo "Error: " . $stmt->error;
        return false;
    }

    // Close the statement
    $stmt->close();
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

function get_number_of_songs($mysqli) {
    $sql = "SELECT COUNT(*) AS total FROM songs";
    $result = $mysqli->query($sql);
    if (!$result) {
        error_log("MySQL Error: " . $mysqli->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['total'];
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

function add_activity($mysqli,$username, $type,$description,$public){
	if(!$mysqli->connect_errno){
    	$stmt = $mysqli->prepare("INSERT INTO activity_log (username, type, time, description, is_public) VALUES (?, ?, NOW(), ?, ?)");
		if (!$stmt) {
        	return "Prepare failed: " . $mysqli->error;
		}

		$stmt -> bind_param("sssi",$username, $type, $description,$public);
		


		// Execute the statement
		if ($stmt->execute()) {
			return true;
		} 
		else {
			return  "Error: " . $stmt->error;
		}

		// Close the statement
		$stmt->close();


	}
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

    // Search query - not case sensitive
    if (!empty($searchQuery)) {
        $conditions[] = "title LIKE ? ";
        $params[] = "%" . $searchQuery . "%";
        $types .= "s";
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




?>