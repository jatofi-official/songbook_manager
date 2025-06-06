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



function select_song_titles(){
    
}


?>