<?php
session_start();
include('php/auth.php'); 
if (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] == true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

include('php/db.php');
include('php/data.php');

$website = "songs";

include('php/functions.php');
include("php/header.php");
include('php/navigation.php');
include("php/categories.php");


// Call the function with $mysqli to output everything
display_tag_selector($mysqli);
?>

<!-- Submit button for testing outside the function -->
<button id="submit-tags-btn" class="btn btn-primary mt-3">Submit Tags</button>

<script>
(() => {
    const submitBtn = document.getElementById('submit-tags-btn');
    const hiddenInput = document.querySelector('.selected-tags-input');

    submitBtn.addEventListener('click', () => {
        alert('Selected tags: ' + (hiddenInput.value || '(none)'));
    });
})();
</script>

<?php include("php/footer.php"); ?>
