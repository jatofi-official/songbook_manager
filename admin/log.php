<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';
$title = "Activity log";
include $phpBase . 'db.php';
include $phpBase . 'data.php';
$website = "admin";
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
            <th style="width:180px"><button class="sortable btn btn-outline-secondary btn-sm" data-column="time" data-order="none">Time</button></th>
            <th><button class="sortable btn btn-outline-secondary btn-sm" data-column="description" data-order="none">Description</button></th>
            <th style="width:80px"><button class="sortable btn btn-outline-secondary btn-sm" data-column="is_public" data-order="none">Visibility</button></th>
        </tr>
    </thead>
    <tbody id="activityBody"></tbody>


</table>
<!-- Pagination -->
<nav>
    <ul class="pagination justify-content-center" id="pagination">
        <!-- Dynamically filled by JS -->
    </ul>
</nav>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const tableBody = document.getElementById("activityBody");
    const pagination = document.getElementById("pagination");
    const userInput = document.getElementById("searchUser");
    const typeSelect = document.getElementById("filterType");
    const publicSelect = document.getElementById("filterPublic");
    const rowsPerPage = 25;

    let currentPage = 1;
    let currentSortColumn = 'id';
    let currentSortOrder = 'desc';

    async function loadActivity() {
        const username = userInput.value.trim();
        const type = typeSelect.value;
        const isPublic = publicSelect.value;

        const params = new URLSearchParams({
            page: currentPage,
            limit: rowsPerPage,
            sort_column: currentSortColumn,
            sort_order: currentSortOrder,
            username: username,
            type: type,
            is_public: isPublic
        });

        const res = await fetch(`../php/subpage/fetch_activity.php?${params.toString()}`);
        if (!res.ok) return;
        const data = await res.json();

        // Clear table
        tableBody.innerHTML = '';

        data.activities.forEach(act => {
            const tr = document.createElement('tr');

            const usernameTd = document.createElement('td');
            usernameTd.textContent = act.username;
            tr.appendChild(usernameTd);

            const typeTd = document.createElement('td');
            typeTd.textContent = act.type;
            tr.appendChild(typeTd);

            const timeTd = document.createElement('td');
            timeTd.textContent = act.time;
            tr.appendChild(timeTd);

            const descTd = document.createElement('td');
            descTd.textContent = act.description;
            tr.appendChild(descTd);

            const pubTd = document.createElement('td');
            pubTd.textContent = act.is_public == 1 ? 'Public' : 'Private';
            pubTd.style.color = act.is_public == 1 ? 'green' : 'red';
            tr.appendChild(pubTd);

            tableBody.appendChild(tr);
        });

        renderPagination(data.total);
        updateSortArrows();
    }

    function renderPagination(totalItems) {
        const totalPages = Math.ceil(totalItems / rowsPerPage);
        pagination.innerHTML = '';

        if (totalPages <= 1) return;

        const addPage = (page, label = null, active = false) => {
            const li = document.createElement('li');
            li.className = 'page-item' + (active ? ' active' : '');
            li.innerHTML = `<a class="page-link" href="#">${label ?? page}</a>`;
            li.addEventListener('click', e => {
                e.preventDefault();
                if (page === currentPage) return;
                currentPage = page;
                loadActivity();
            });
            pagination.appendChild(li);
        };

        const start = Math.max(2, currentPage - 2);
        const end = Math.min(totalPages - 1, currentPage + 2);

        addPage(1, '1', currentPage === 1);

        if (start > 2) {
            const li = document.createElement('li');
            li.className = 'page-item disabled';
            li.innerHTML = `<span class="page-link">…</span>`;
            pagination.appendChild(li);
        }

        for (let i = start; i <= end; i++) {
            addPage(i, i, i === currentPage);
        }

        if (end < totalPages - 1) {
            const li = document.createElement('li');
            li.className = 'page-item disabled';
            li.innerHTML = `<span class="page-link">…</span>`;
            pagination.appendChild(li);
        }

        if (totalPages > 1) addPage(totalPages, totalPages, currentPage === totalPages);
    }

    function updateSortArrows() {
        document.querySelectorAll(".sortable").forEach(btn => {
            btn.textContent = btn.dataset.column.charAt(0).toUpperCase() + btn.dataset.column.slice(1); // reset
            if (btn.dataset.column === currentSortColumn) {
                btn.textContent += currentSortOrder === 'asc' ? ' ▲' : ' ▼';
            }
        });
    }

    // Filters
    [userInput, typeSelect, publicSelect].forEach(el => {
        el.addEventListener('input', () => {
            currentPage = 1;
            loadActivity();
        });
        el.addEventListener('change', () => {
            currentPage = 1;
            loadActivity();
        });
    });

    // Sorting
    document.querySelectorAll(".sortable").forEach(btn => {
        btn.addEventListener("click", () => {
            const col = btn.dataset.column;
            if (currentSortColumn === col) {
                if (currentSortOrder === 'desc') currentSortOrder = 'asc';
                else if (currentSortOrder === 'asc') {
                    currentSortColumn = 'id';
                    currentSortOrder = 'desc';
                } else currentSortOrder = 'desc';
            } else {
                currentSortColumn = col;
                currentSortOrder = 'desc';
            }
            currentPage = 1;
            loadActivity();
        });
    });

    // Reset button
    document.getElementById("resetFilters").addEventListener('click', () => {
        userInput.value = '';
        typeSelect.value = '';
        publicSelect.value = '';
        currentPage = 1;
        currentSortColumn = 'id';
        currentSortOrder = 'desc';
        loadActivity();
    });

    // Initial load
    loadActivity();
});
</script>



<style>
.table-hover tbody tr:hover { background-color: #f0f8ff; }
.sortable { cursor: pointer; min-width: 100px; }
.table-header button.btn-sm { border-radius: 0.375rem; }
</style>


<?php include $phpBase . 'footer.php'; ?>
