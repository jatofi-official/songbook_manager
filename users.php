<?php
session_start();
include('php/db.php');
include('php/data.php');
include("php/auth.php");
$website = "users";
include_once('php/functions.php');
include("php/header.php");
include('php/navigation.php');

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

?>
<!-- Page Header -->
<div class="user-management-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-7 mb-3">Users</h1>
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
                    placeholder="Search users by name, username or organization...">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>

        <div class="col-md-2 mt-3 mt-md-0">
            <select class="form-select" name="group">
                <option value="">All Groups</option>
                <?php foreach ($groupList as $group): 
                    $gName = htmlspecialchars($group['name']);
                    $selected = ($gName === $selectedGroup) ? 'selected' : '';
                ?>
                    <option value="<?= $gName ?>" <?= $selected ?>><?= $gName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 mt-3 mt-md-0">
            <select class="form-select" name="sort" onchange="this.form.submit()">
                <option value="name">Name</option>
                <option value="xp" <?= ($_GET['sort'] ?? '') === 'xp' ? 'selected' : '' ?>>XP</option>
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
    <!-- Users List -->
<div class="users-list mb-4 container pt-4">
    <h5 class="section-title">All Users</h5>

    <?php
    $perPage = PER_PAGE;
    $page = (int)($_GET['page'] ?? 1);

    // Get filters from query params
    $searchQuery = $_GET['search'] ?? '';
    $group = $_GET['group'] ?? '';
    $sort = $_GET['sort'] ?? 'name';

    // Fetch user data
    $userData = get_all_users($mysqli, $perPage, $page, $searchQuery, $group, $sort);
    $users = $userData['users'];
    $totalPages = $userData['totalPages'];

echo <<<HTML
<!-- Header Row -->
<div class="user-list-header mb-2 d-none d-md-block">
    <div class="row gx-0 mx-0" style="padding: 12px 20px;">
        <!-- User Column -->
        <div class="col-md-4 d-flex align-items-center ps-3">
            <div style="width: 60px;"></div> <!-- Matches avatar width -->
            <span class="text-muted fw-bold ms-3 text-start">User</span> <!-- ms-3 matches avatar's me-3 -->
        </div>
        
        <!-- XP Column -->
        <div class="col-md-2 text-start ps-3">
            <span class="text-muted fw-bold">XP</span>
        </div>
        
        <!-- Level Column -->
        <div class="col-md-2 text-start ps-3">
            <span class="text-muted fw-bold">Level</span>
        </div>
        
        <!-- Group Column -->
        <div class="col-md-4 text-start ps-4">
            <span class="text-muted fw-bold">Group</span>
        </div>
    </div>
</div>
HTML;


    if (!empty($users)) {

        
    foreach ($users as $user) {
        $userId = (int)$user['id'];
        $name = htmlspecialchars($user['name']);
        $username = htmlspecialchars($user['username']);
        $groupName = htmlspecialchars($user['group_name'] ?? '');
        $adminFlag = $user['admin'] ?? false;

        // Profile picture logic
        $profilePicPath = __DIR__ . "/profile_pics/{$userId}.png";
        if ($userId && file_exists($profilePicPath)) {
            $mtime = filemtime($profilePicPath);
            $avatarUrl = "profile_pics/{$userId}.png?v={$mtime}";
        } else {
            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random";
        }

        // XP info
        $xpInfo = get_xp($mysqli, $userId);
        $totalXP = $user['ledger_xp'] ?? 0;
        $level = $xpInfo['level'] ?? '—';

        $selectedUserId = $_GET['selectedUser'] ?? null;
        $selectedClass = ($selectedUserId && $selectedUserId == $userId) ? "user-card--selected" : "";

        // Admin badge HTML (only if admin)
        $adminHtml = $adminFlag ? '<div class="admin-badge"><i class="bi bi-patch-check-fill me-1"></i>Admin</div>' : '';

        // Group badge (only show if groupName is not empty)
        $groupHtml = $groupName !== '' ? "<span class=\"org-badge\">$groupName</span>" : '';

        echo <<<HTML
        <a href="user.php?id=$userId" class="user-card $selectedClass text-decoration-none text-reset d-block">
        <div class="row align-items-center">
            <div class="col-md-4 mb-3 mb-md-0"> <!-- Changed from 3 to 4 -->
                <div class="d-flex align-items-center">
                    <img src="$avatarUrl" class="user-avatar me-3" alt="$name">
                    <div class="d-flex flex-column">
                        <div class="d-flex align-items-center gap-2"> <!-- Added flex container for name + badge -->
                            <div class="user-name">$name</div>
                            $adminHtml
                        </div>
                        <div class="username">@$username</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3 mb-md-0"> <!-- Reduced from 3 to 2 to compensate -->
                <div>
                    <div class="xp-value">$totalXP</div>
                    <div class="xp-label">Total XP</div>
                </div>
            </div>
            <div class="col-md-2 mb-3 mb-md-0">
                <div class="level-badge">$level</div>
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
