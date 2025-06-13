<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "user";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');



// Determine the userId to fetch:
$userId = null;
if (isset($_GET["id"])) {
    $userId = intval($_GET["id"]);
} elseif (isset($_SESSION["id"])) {
    $userId = intval($_SESSION["id"]);
}
?>

<?php if (isset($_SESSION["user"])||$userId != null): ?>
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
        <p>User not found.</p>
    <?php endif; ?>

<?php else: ?>
    <p>User not found.</p>


<?php endif; ?>

<?php include("php/footer.php"); ?>
