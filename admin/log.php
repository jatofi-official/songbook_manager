<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';
?>

<?php
// Admin-only access
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    ?>
    <div class="container mt-5 text-center">
        <p class="text-danger">Táto stránka je prístupná iba administrátorom.</p>
    </div>
<?php
    include $phpBase . 'footer.php';
    exit;
}

// Fetch latest 500 activities for admins
$activities = get_recent_activity($mysqli, 500);

// Get all activity types dynamically
$typeRes = $mysqli->query("SELECT DISTINCT type FROM activity_log ORDER BY type ASC");
$types = [];
while ($row = $typeRes->fetch_assoc()) {
    $types[] = $row['type'];
}
?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4">Admin Activity Log</h1>

<div class="row mb-3">
    <div class="col-md-3">
        <input type="text" id="searchUser" class="form-control" placeholder="Filter by username">
    </div>
    <div class="col-md-3">
        <select id="filterType" class="form-control">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select id="filterPublic" class="form-control">
            <option value="">All</option>
            <option value="1">Public</option>
            <option value="0">Private</option>
        </select>

    </div>
    <div class="col-md-3">
        <button id="resetFilters" class="btn btn-secondary w-100">Reset Filters</button>
    </div>
</div>

<table class="table table-hover table-bordered" id="activityTable">
    <thead>
        <tr class="table-header">
            <th style="width:150px"><button class="sortable btn btn-outline-secondary btn-sm" data-column="username" data-order="none">Username</button></th>
            <th style="width:200px"><button class="sortable btn btn-outline-secondary btn-sm" data-column="type" data-order="none">Type</button></th>
            <th style="width:80px"><button class="sortable btn btn-outline-secondary btn-sm" data-column="is_public" data-order="none">Visibility</button></th>
            <th style="width:180px"><button class="sortable btn btn-outline-secondary btn-sm" data-column="time" data-order="none">Time</button></th>
            <th><button class="sortable btn btn-outline-secondary btn-sm" data-column="description" data-order="none">Description</button></th>
        </tr>
    </thead>
    <tbody id="activityBody">
        <?php foreach ($activities as $act): ?>
            <tr data-id="<?= intval($act['id']) ?>" data-is_public="<?= intval($act['is_public']) ?>">
                <td><?= htmlspecialchars($act['username']) ?></td>
                <td><?= htmlspecialchars($act['type']) ?></td>
                <td class="<?= $act['is_public'] ? 'text-success' : 'text-danger' ?>">
                    <?= $act['is_public'] ? 'Public' : 'Private' ?>
                </td>
                <td><?= htmlspecialchars($act['time']) ?></td>
                <td><?= htmlspecialchars($act['description']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>

</table>

<script>
const rowsPerPage = 25;
let currentPage = 1;
const tbody = document.getElementById("activityBody");
const allRowsMaster = Array.from(tbody.rows);

// Default sort: by data-id descending
function sortDefault() {
    const rows = [...allRowsMaster];
    rows.sort((a, b) => parseInt(b.dataset.id) - parseInt(a.dataset.id));
    rows.forEach(r => tbody.appendChild(r));
    renderPage(1);
}

// Filter function
function filterTable() {
    const userVal = document.getElementById("searchUser").value.toLowerCase();
    const typeVal = document.getElementById("filterType").value.toLowerCase();
    const pubVal = document.getElementById("filterPublic").value;

    allRowsMaster.forEach(row => {
        const username = row.cells[0].textContent.toLowerCase();
        const type = row.cells[1].textContent.toLowerCase();
        const isPublic = row.dataset.is_public; // 0 or 1

        row.style.display = ((userVal === "" || username.includes(userVal)) &&
                             (typeVal === "" || type.includes(typeVal)) &&
                             (pubVal === "" || isPublic === pubVal))
                             ? "" : "none";
    });

    renderPage(1);
}


// Pagination function
function renderPage(page = 1) {
    currentPage = page;
    const visibleRows = allRowsMaster.filter(row => row.style.display !== "none");
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    visibleRows.forEach((row, idx) => {
        row.style.display = (idx >= start && idx < end) ? "" : "none";
    });

    const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
    const pagination = document.getElementById("pagination");
    pagination.innerHTML = "";
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement("li");
        li.className = "page-item " + (i === page ? "active" : "");
        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
        li.addEventListener("click", e => { e.preventDefault(); renderPage(i); });
        pagination.appendChild(li);
    }
}

// Sorting function
document.querySelectorAll(".sortable").forEach(btn => {
    btn.addEventListener("click", () => {
        const colIndex = Array.from(btn.parentNode.parentNode.children).indexOf(btn.parentNode);
        const orderCycle = ["none", "asc", "desc"];
        const currentOrder = btn.dataset.order || "none";
        const nextOrder = orderCycle[(orderCycle.indexOf(currentOrder) + 1) % 3];
        btn.dataset.order = nextOrder;

        // Reset arrows for other buttons
        document.querySelectorAll(".sortable").forEach(b => {
            if (b !== btn) b.textContent = b.textContent.replace(/[▲▼]/g, "");
        });

        // Update arrow for current button
        btn.textContent = btn.textContent.replace(/[▲▼]/g, "");
        if (nextOrder === "asc") btn.textContent += " ▲";
        else if (nextOrder === "desc") btn.textContent += " ▼";

        const visibleRows = allRowsMaster.filter(row => row.style.display !== "none");

        if (nextOrder === "none") {
            sortDefault();
            return;
        }

        visibleRows.sort((a, b) => {
            let aVal = a.cells[colIndex].textContent.toLowerCase();
            let bVal = b.cells[colIndex].textContent.toLowerCase();

            if (btn.dataset.column === "time") {
                aVal = new Date(aVal);
                bVal = new Date(bVal);
            }
            if (btn.dataset.column === "is_public") {
                aVal = parseInt(a.dataset.is_public, 10);
                bVal = parseInt(b.dataset.is_public, 10);
            }


            if (aVal < bVal) return nextOrder === "asc" ? -1 : 1;
            if (aVal > bVal) return nextOrder === "asc" ? 1 : -1;
            return 0;
        });

        visibleRows.forEach(row => tbody.appendChild(row));
        renderPage(1);
    });
});

document.getElementById("searchUser").addEventListener("input", filterTable);
document.getElementById("filterType").addEventListener("change", filterTable);
document.getElementById("filterPublic").addEventListener("change", filterTable);
document.getElementById("resetFilters").addEventListener("click", () => {
    document.getElementById("searchUser").value = "";
    document.getElementById("filterType").value = "";
    document.getElementById("filterPublic").value = "";
    filterTable();
});

// Initial render
sortDefault();
filterTable();
</script>

<style>
.table-hover tbody tr:hover { background-color: #f0f8ff; }
.sortable { cursor: pointer; min-width: 100px; }
.table-header button.btn-sm { border-radius: 0.375rem; }
</style>


<?php include $phpBase . 'footer.php'; ?>
