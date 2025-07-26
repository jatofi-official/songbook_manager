<?php
session_start();
include('php/db.php');
include('php/auth.php');
include('php/data.php');
$website = "songbooks";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



?>
<!-- Page Header -->
<div class="user-management-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-7 mb-3">Songbooks</h1>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Search and Filter -->
    <?php
    $selectedGroup = $_GET['group'] ?? '';
    $groupList = get_all_groups($mysqli);
    ?>

    <form method="GET" class="row">
        <div class="col-md-8">
            <div class="input-group">
                <input type="text" name="search" class="form-control"
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                    placeholder="Search songbooks by title...">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>

        <div class="col-md-4 mt-3 mt-md-0">
            <select class="form-select" name="group">
                <option value="">All Groups</option>
                <?php foreach ($groupList as $group): 
                    $selected = ($group["id"] === $selectedGroup) ? 'selected' : '';
                ?>
                    <option value="<?= $group["id"] ?>" <?= $selected ?>><?= $group["name"] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <script>
    // Add this right after your form closing tag
    document.querySelector('select[name="group"]').addEventListener('change', function() {
        // Create hidden inputs for all current GET parameters except 'group'
        const form = this.form;
        const params = new URLSearchParams(window.location.search);
        
        params.forEach((value, key) => {
            if (key !== 'group' && !form.elements[key]) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        });
        
        form.submit();
    });
    </script>

    <div class="users-list mb-4 container pt-4">


</div>


    <!-- Users List -->
<div class="users-list mb-4 container pt-4">
    <h5 class="section-title">All Users</h5>

    <?php
    $perPage = PER_PAGE;
    $page = (int)($_GET['page'] ?? 1);

    // Get filters from query params
    $searchQuery = $_GET['search'] ?? '';
    $group = $_GET['group'] ?? '';

    // Fetch user data
    $songbooksData = get_all_songbooks($mysqli,$limit=$perPage,$offset=$page-1,$search=$searchQuery,$groupId=$group);
    // $songbooksData = get_all_songbooks_alt($mysqli);
    $songbooks = $songbooksData['songbooks'];
    $totalPages = $songbooksData['totalPages'];
    $total = $songbooksData["total"];


echo <<<HTML
<!-- Header Row -->
<div class="user-list-header mb-2 d-none d-md-block">
    <div class="row gx-0 mx-0" style="padding: 12px 20px;">
        <!-- Name Column -->
        <div class="col-md-4 d-flex align-items-center ps-3">
            <div style="width: 60px;"></div> <!-- Matches avatar width -->
            <span class="text-muted fw-bold ms-3 text-start">Name</span> <!-- ms-3 matches avatar's me-3 -->
        </div>
        
        <!-- Songs Column -->
        <div class="col-md-2 text-start ps-3">
            <span class="text-muted fw-bold">Songs</span>
        </div>
        
        
        <!-- Group Column -->
        <div class="col-md-4 text-start ps-4">
            <span class="text-muted fw-bold">Group</span>
        </div>
    </div>
</div>
HTML;


if (!empty($songbooks)) {

        
        foreach ($songbooks as $songbook) {

        $id = (int)$songbook["id"];
        $name = htmlspecialchars($songbook["name"]);
        $owner = htmlspecialchars($songbook["owner"]);
        $description = htmlspecialchars($songbook["description"]);
        $groupName = htmlspecialchars($songbook['group_name'] ?? '');


        // Group badge (only show if groupName is not empty)
        $groupHtml = $groupName !== '' ? "<span class=\"org-badge\">$groupName</span>" : '';

        echo <<<HTML
        <a href="songbook.php?id=$id" class="user-card text-decoration-none text-reset d-block">
        <div class="row align-items-center">
            <div class="col-md-4 mb-3 mb-md-0"> 
                <div class="d-flex align-items-center">
                    <div class="d-flex flex-column">
                        <div class="d-flex align-items-center gap-2"> <!-- Added flex container for name + badge -->
                            <div class="user-name">$name</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3 mb-md-0"> <!-- Reduced from 3 to 2 to compensate -->
                <div>
                    <div class="username">$description</div>
                </div>
            </div>

            <div class="col-md-4 mb-3 mb-md-0"> <!-- Increased from 3 to 4 -->
            $groupHtml
            </div>
        </div>
        </a>
        HTML;
    }




        // Pagination
        echo "<div class='pagination mt-4'>";

        function page_button($i, $page) {
            return $i == $page
                ? "<span class='btn btn-sm btn-primary active'>$i</span> "
                : "<a href='?" . http_build_query(array_merge($_GET, ['page' => $i])) . "' class='btn btn-sm btn-outline-primary'>$i</a> ";
        }

        if ($page > 1) {
            echo "<a href='?" . http_build_query(array_merge($_GET, ['page' => $page - 1])) . "' class='btn btn-sm btn-outline-primary'>&lt;</a> ";
        } else {
            echo "<span class='btn btn-sm btn-outline-secondary disabled'>&lt;</span> ";
        }

        $range = 2;
        $start = max(1, $page - $range);
        $end = min($totalPages, $page + $range);

        if ($start > 1) echo page_button(1, $page) . "<span class='btn btn-sm disabled'>...</span> ";
        for ($i = $start; $i <= $end; $i++) echo page_button($i, $page);
        if ($end < $totalPages) echo "<span class='btn btn-sm disabled'>...</span> " . page_button($totalPages, $page);

        if ($page < $totalPages) {
            echo "<a href='?" . http_build_query(array_merge($_GET, ['page' => $page + 1])) . "' class='btn btn-sm btn-outline-primary'>&gt;</a>";
        } else {
            echo "<span class='btn btn-sm btn-outline-secondary disabled'>&gt;</span>";
        }

        echo "</div>";
    ?>
</div>



</div>


<?php 
}
else{
    echo "<p>Oops... Nothing to see here</p>";
}
include("php/footer.php"); ?>
