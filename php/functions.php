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

function add_song($mysqli,$data){
	if(!$mysqli->connect_errno){
    	$stmt = $mysqli->prepare("INSERT INTO songs (category, title, artist, capo, capo_alt, owner, is_public, create_date, modified_date, tags, instruments, content) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)");
		if (!$stmt) {
        	return "Prepare failed: " . $mysqli->error;
		}
		
		$stmt -> bind_param("issiisisss",$data["category"],$data["title"],$data["artist"],$data["capo"],$data["capo_alt"],$data["owner"],$data["is_public"],$data["tags"],$data["instruments"],$data["content"]);
		


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

	else{
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

function add_activity($mysqli,$username, $type,$description){
	if(!$mysqli->connect_errno){
    	$stmt = $mysqli->prepare("INSERT INTO activity_log (username, type, time, description) VALUES (?, ?, NOW(), ?)");
		if (!$stmt) {
        	return "Prepare failed: " . $mysqli->error;
		}

		$stmt -> bind_param("sss",$username, $type, $description);
		


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
		$sql = "SELECT username, type, time, description 
				FROM activity_log 
				ORDER BY time DESC 
				LIMIT ?";

		$stmt = $mysqli->prepare($sql);
		$stmt->bind_param("i", $number);
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