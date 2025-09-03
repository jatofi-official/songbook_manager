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
                // Build category buttons
                detailPanel.innerHTML = `
                    <div class="card-header">
                        <div class="btn-group mb-3" role="group" id="categoryButtons"></div>
                    </div>
                    <div class="card-body" id="answersContent">${html}</div>
                `;

                // Now fetch which categories user has answered
                fetch("../php/subpage/user_categories.php?username=" + username)
                    .then(r => r.json())
                    .then(cats => {
                        let btnGroup = document.getElementById("categoryButtons");
                        // always include základ
                        btnGroup.innerHTML = `<button class="btn btn-outline-primary category-btn active" data-cat="-1">Základ</button>`;
                        for (let i = 1; i <= 13; i++) {
                            let has = cats.includes(i);
                            btnGroup.innerHTML += `<button class="btn btn-outline-primary category-btn ${has?'':'disabled'}" data-cat="${i}">${i}</button>`;
                        }

                        // attach handlers
                        btnGroup.querySelectorAll(".category-btn").forEach(catBtn => {
                            catBtn.addEventListener("click", function() {
                                if (this.classList.contains("disabled")) return;
                                btnGroup.querySelectorAll(".category-btn").forEach(b => b.classList.remove("active"));
                                this.classList.add("active");
                                let cat = this.dataset.cat;
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
});
</script>

<!-- Sorting -->

<script>
document.getElementById("detailPanel").addEventListener("click", function(e) {
    const btn = e.target.closest("button.sortable");
    if (!btn) return;

    const rowsContainer = document.getElementById("answerRows");
    if (!rowsContainer) return;

    const sortField = btn.dataset.sort; // 'frekvencia' | 'hodnotenie'
    const rows = Array.from(rowsContainer.querySelectorAll(".answer-row"));

    // cycle order: default -> asc -> desc -> default
    const current = btn.dataset.order || "default";
    let next = (current === "default") ? "asc" : (current === "asc" ? "desc" : "default");

    // reset labels/arrows for all sortable buttons in THIS header
    const header = btn.closest(".table-header");
    if (header) {
        header.querySelectorAll("button.sortable").forEach(b => {
            b.dataset.order = "default";
            b.textContent = b.dataset.label || b.textContent.replace(/[▲▼]/g, "").trim();
        });
    }

    // update this button
    if (!btn.dataset.label) btn.dataset.label = btn.textContent.replace(/[▲▼]/g, "").trim();
    btn.dataset.order = next;
    if (next === "asc")       btn.textContent = btn.dataset.label + " ▲";
    else if (next === "desc") btn.textContent = btn.dataset.label + " ▼";
    else                      btn.textContent = btn.dataset.label;

    if (next === "default") {
        // restore original order by reloading current category
        const activeCat = document.querySelector(".category-btn.active");
        if (activeCat) activeCat.click();
        return;
    }

    // helper to coerce value: empty -> -999 so empties sink to bottom
    const val = (el) => {
        const raw = el.dataset[sortField];
        if (raw === "" || raw === undefined) return -999;
        const n = parseInt(raw, 10);
        return Number.isNaN(n) ? -999 : n;
    };

    // stable sort: tie-break by original data-index
    rows.sort((a, b) => {
        const va = val(a);
        const vb = val(b);
        if (va === vb) {
            return parseInt(a.dataset.index, 10) - parseInt(b.dataset.index, 10);
        }
        return next === "asc" ? (va - vb) : (vb - va);
    });

    // re-attach in sorted order
    rows.forEach(r => rowsContainer.appendChild(r));
});
</script>






<style>
.rating-circle[data-value="1"] { background-color: #FF0000; color: #fff; }
.rating-circle[data-value="2"] { background-color: #FF6666; }
.rating-circle[data-value="3"] { background-color: #808080; color: #fff; }
.rating-circle[data-value="4"] { background-color: #66CC66; }
.rating-circle[data-value="5"] { background-color: #008000; color: #fff; }

.rating-circle {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-weight: bold;
}


/* Table header background lighter and rounded */
.table-header {
    background-color: #f0f0f0; /* very light gray */
    color: #333;
    padding: 0.5rem 0.75rem;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
}

/* Sortable buttons smaller and rounded */
.sortable.btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.85rem;
    border-radius: 0.375rem;
    line-height: 1.2;
    min-width: auto;
}

/* Equal height rows */
.answer-row > .d-flex {
    min-height: 60px; /* adjust as needed */
}

/* Rounded, separated category buttons */
#categoryButtons .category-btn {
    border-radius: 0.375rem;
    margin-right: 0.25rem;
}
#categoryButtons .category-btn:last-child {
    margin-right: 0;
}

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
