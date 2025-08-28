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
function attachCardHoverListeners() {
    const cards = document.querySelectorAll(".card.position-relative");
    cards.forEach((card, index) => {
        const infoBox = card.querySelector(".info-box");
        if (!infoBox) return;
        if (card.dataset.hoverAttached) return;
        card.dataset.hoverAttached = "1";

        card.addEventListener("mouseenter", () => {
            infoBox.style.display = "block";
            // console.log(`Hover enter on card #${index + 1}`, card);
        });
        card.addEventListener("mouseleave", () => {
            infoBox.style.display = "none";
            // console.log(`Hover leave on card #${index + 1}`, card);
        });
    });
}
</script>



<script>
    const userId = <?= json_encode($userId ?? 0) ?>;

    if (userId > 0) {
        fetch(`php/subpage/get_user.php?id=${userId}&embedded=1`)
            .then(response => response.text())
            .then(html => {
                const container = document.getElementById('user-details');
                container.innerHTML = html;

                // Attach hover listeners to dynamically loaded cards
                if (typeof attachCardHoverListeners === "function") {
                    attachCardHoverListeners();
                }
            })
            .catch(err => {
                console.error("Failed to load user info via AJAX:", err);
                document.getElementById('user-details').innerHTML = '<p>Failed to load user info.</p>';
            });
    } else {
        document.getElementById('user-details').innerHTML = '<p>User not found.</p>';
    }
</script>




    <?php else: ?>
        <p>User not found.</p>
    <?php endif; ?>

<?php else: ?>
    <p>User not found.</p>


<?php endif; ?>

<?php include("php/footer.php"); ?>
