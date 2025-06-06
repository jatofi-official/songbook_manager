<?php
session_start();
include('php/db.php');
include('php/data.php');
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');
?>

<section>
	
<div class="col-11 col-md-6 round_container">
<?php 

$chyby = array();
if (isset($_POST["submit"])) {
	$data = array();
	$data['username'] = osetri($_POST['username']);
	$data['password'] = $_POST['password'];
	$data['password2'] = $_POST['password2'];
	$data['name'] = osetri($_POST['name']);
	$data["zbor"] = $_POST["zbor"];

	if (isset($_POST['admin'])) $data['admin'] = osetri($_POST['admin']); else $data['admin'] = 0;	

	if (!nazov_ok($data['username'])) $chyby['username'] = 'The username is not in the correct format';
	if (empty($data['username'])) $chyby['username'] = 'You did not enter a username';
	if (!nazov_ok($data['password'])) $chyby['password'] = 'The password is not in the correct format';
	if (!nazov_ok($data['password2'])) $chyby['password2'] = 'The password (again) is not in the correct format';

	if (empty($data['password'])) $chyby['password'] = 'You did not enter a password';
	if (empty($data['password2'])) $chyby['password2'] = 'You did not repeat the password';
	if (!nazov_ok($data['name'])) $chyby['name'] = 'The name is not in the correct format';
	if (empty($data['name'])) $chyby['name'] = 'You did not enter a name';



}

if(empty($chyby) && isset($_POST["submit"])) {
	unset($data['password2']);
	add_user($mysqli,$data["name"], $data['username'], $data['password'], $data['zbor']);
	
	
} else {
	// ak bol odoslaný formulár, ale neboli zadané alebo boli zle zadané všetky povinné položky 
	if (!empty($chyby)) {
		// echo '<p class="chyba"> (Name, Username, Password)</p>';
		echo '<p class="chyba"><strong>Wrong entry</strong>:<br>';
		foreach($chyby as $ch) {
			echo "$ch<br>\n";
		}
		echo '</p>';
	}
?>

    <form method="post">
            <legend>Create Account</legend>

            <div class="mb-3">
                <label for="name" class="form-label">Name (3–20 characters):</label>
                <input type="text" name="name" id="name" class="form-control" maxlength="20"
                    value="<?php if (isset($data['name'])) echo $data['name']; ?>">
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Username:</label>
                <input type="text" name="username" id="username" class="form-control" maxlength="20"
                    value="<?php if (isset($data['username'])) echo $data['username']; ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password (3–30 characters):</label>
                <input type="password" name="password" id="password" class="form-control" maxlength="30">
            </div>

            <div class="mb-3">
                <label for="password2" class="form-label">Password (again):</label>
                <input type="password" name="password2" id="password2" class="form-control" maxlength="30">
            </div>

			<div class="mb-3">
				<label for="zbor" class="form-label">Zbor:</label>
				<select name="zbor" id="zbor" class="form-control">
					<?php
					for ($i = 1; $i <= 150; $i++) {
						$selected = (isset($data['zbor']) && $data['zbor'] == $i) ? 'selected' : '';
						echo "<option value=\"$i\" $selected>$i</option>";
					}
					?>
				</select>
			</div>

            <button type="submit" name="submit" class="btn btn-primary">Create User</button>
    </form>
</div>


<?php
}
?>	
</section>

<?php
include('php/footer.php');
?>
