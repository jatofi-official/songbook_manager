<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "users";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){
?>

<!-- Page Header -->
<div class="user-management-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-7 mb-3">Users</h1>
            </div>
            <!-- <div class="col-md-4 text-end">
                <button class="btn btn-light me-2">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <button class="btn btn-outline-light">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </button>
            </div> -->
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
                    placeholder="Search users by name, username or organization...">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>

        <div class="col-md-4 mt-3 mt-md-0">
            <select class="form-select" name="group">
                <option value="">All Organizations</option>
                <?php foreach ($groupList as $group): 
                    $gName = htmlspecialchars($group['name']);
                    $selected = ($gName === $selectedGroup) ? 'selected' : '';
                ?>
                    <option value="<?= $gName ?>" <?= $selected ?>><?= $gName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>


</div>
    <!-- Users List -->
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
    $userData = get_all_users($mysqli, $perPage, $page, $searchQuery, $group);
    $users = $userData['users'];
    $totalPages = $userData['totalPages'];

    if (!empty($users)) {
        foreach ($users as $user) {
            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=random";
            $groupName = htmlspecialchars($user['group_name'] ?? '—');
            $userId = (int)$user['id'];
            $name = htmlspecialchars($user['name']);
            $username = htmlspecialchars($user['username']);
            $admin = $user['admin'] ? "<span class='badge bg-warning text-dark'>Admin</span>" : "";

            // XP info
            $xpInfo = get_xp($mysqli, $userId);
            $totalXP = $xpInfo['total_xp'] ?? '—';
            $level = $xpInfo['level'] ?? '—';

            // Determine if this user card is "selected"
            $selectedUserId = $_GET['selectedUser'] ?? null;
            $selectedClass = ($selectedUserId && $selectedUserId == $userId) ? "user-card--selected" : "";

            echo <<<HTML
            <a href="user.php?id=$userId" class="user-card $selectedClass text-decoration-none text-reset d-block">
                <div class="row align-items-center">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <div class="d-flex align-items-center">
                            <img src="$avatarUrl" class="user-avatar me-3" alt="$name">
                            <div>
                                <div class="user-name">$name</div>
                                <div class="username">@$username</div>
                                $admin
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <div>
                            <div class="xp-value">$totalXP</div>
                            <div class="xp-label">Total XP</div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3 mb-md-0">
                        <div class="level-badge">$level</div>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <span class="org-badge">$groupName</span>
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
    } else {
        echo "<p>No users found.</p>";
    }
    ?>
</div>



</div>


<?php 
}
else{
    echo "<p>Oops... Nothing to see here</p>";
}
include("php/footer.php"); ?>
