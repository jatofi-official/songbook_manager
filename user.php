<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "user";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');


// Determine the userId to fetch:
// Priority: GET id if set, else session id if logged in, else null
$userId = null;
if (isset($_GET["id"])) {
    $userId = intval($_GET["id"]);
} elseif (isset($_SESSION["id"])) {
    $userId = intval($_SESSION["id"]);
}
?>

<div id="user-details"></div>

<?php if ($userId !== null): ?>
<script>
    const userId = <?= json_encode($userId) ?>;
    fetch(`php/subpage/get_user.php?id=${userId}&embedded=1`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('user-details').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('user-details').innerHTML = '<p>Failed to load user info.</p>';
        });
</script>
<?php else: ?>
<p>You need to be logged in or specify a user.</p>
<?php endif; ?>

<?php
include("php/footer.php");
?>
