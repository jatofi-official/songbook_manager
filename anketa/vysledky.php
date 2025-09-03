<?php
session_start();
// Universal include path
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';
?>


<?php
if (isset($_SESSION['admin_view']) && $_SESSION["admin_view"] == true) {

    $query = "
        SELECT v.username, COUNT(*) as total_entries, MAX(v.timestamp) as last_entry, u.name
        FROM vysledky_ankety_25 v
        JOIN users u ON v.username = u.username
        GROUP BY v.username
        ORDER BY last_entry DESC
    ";
    $result = $mysqli->query($query);
    ?>

    <!-- Outer wrapper for nicer spacing -->
    <div class="container mt-4 mb-4">
        <div class="row">
            <!-- Left side: user list -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">Účastníci ankety</div>
                    <div class="list-group list-group-flush" id="userList">
                        <?php while ($row = $result->fetch_assoc()): 
                            $progress = min(14, (int)$row['total_entries']); // cap at 14
                            $percent = round(($progress / 14) * 100);
                        ?>
                        <button class="list-group-item list-group-item-action user-item" 
                                data-username="<?= htmlspecialchars($row['username']) ?>">
                            <div class="fw-bold"><?= htmlspecialchars($row['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($row['username']) ?></small>
                            <div class="progress mt-2" style="height: 15px;">
                                <div class="progress-bar 
                                    <?= $percent < 50 ? 'bg-danger' : ($percent < 100 ? 'bg-warning' : 'bg-success') ?>" 
                                    style="width: <?= $percent ?>%;">
                                    <?= $progress ?>/14
                                </div>
                            </div>
                            <div class="small text-muted mt-1">
                                Posledný záznam: <?= htmlspecialchars($row['last_entry']) ?>
                            </div>
                        </button>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Right side: details -->
            <div class="col-md-8">
                <div id="detailPanel" class="card h-100" style="min-height: 100vh;">
                    <div class="card-body text-muted d-flex align-items-center justify-content-center">
                        Vyberte používateľa z ľavej strany…
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const detailPanel = document.getElementById("detailPanel");

    // When clicking on user
    document.querySelectorAll(".user-item").forEach(btn => {
        btn.addEventListener("click", function() {
            let username = this.dataset.username;

            // Load "Základ" first
            fetch("../php/subpage/load_answers.php?username=" + username + "&category=-1")
                .then(res => res.text())
                .then(html => {
                    detailPanel.innerHTML = `
                        <div class="card-header">
                            <div class="row g-2">
                                <div class="col">
                                    <button class="btn btn-outline-primary w-100 cat-btn active" data-cat="-1">Základ</button>
                                </div>
                                ${Array.from({length: 13}, (_, i) => `
                                    <div class="col">
                                        <button class="btn btn-outline-primary w-100 cat-btn" data-cat="${i+1}">${i+1}</button>
                                    </div>`).join("")}
                            </div>
                        </div>
                        <div class="card-body" id="answersContent">${html}</div>
                    `;

                    // Attach events to category buttons
                    detailPanel.querySelectorAll(".cat-btn").forEach(catBtn => {
                        catBtn.addEventListener("click", function() {
                            let cat = this.dataset.cat;
                            detailPanel.querySelectorAll(".cat-btn").forEach(b => b.classList.remove("active"));
                            this.classList.add("active");

                            fetch("../php/subpage/load_answers.php?username=" + username + "&category=" + cat)
                                .then(res => res.text())
                                .then(data => {
                                    document.getElementById("answersContent").innerHTML = data;
                                });
                        });
                    });
                });
        });
    });
});
</script>


<style>
.rating-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  line-height: 32px;
  text-align: center;
  font-weight: bold;
  color: #fff;
  margin: auto;
}
.rating-circle[data-value="1"] { background-color: #FF0000; }
.rating-circle[data-value="2"] { background-color: #FF6666; }
.rating-circle[data-value="3"] { background-color: #808080; color: #fff; }
.rating-circle[data-value="4"] { background-color: #66CC66; }
.rating-circle[data-value="5"] { background-color: #008000; }

</style>

<!-- User not admin/logged in -->
<?php
} else { ?>
    <div class="container mt-5 text-center">
        <p class="text-danger">Anketa je prístupná iba pre členov 24. zboru Mustangy</p>
    </div>
<?php }
?>

<?php include $phpBase . 'footer.php';?>
