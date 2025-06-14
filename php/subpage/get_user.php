<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once('../db.php');
include_once('../functions.php');  // Make sure path is correct for your setup
include_once("../data.php");

// Get ID from GET and sanitize
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo "<p>Invalid user ID.</p>";
    exit;
}

$user = get_user($mysqli, $id);

if (!$user) {
    echo "<p>User not found.</p>";
    exit;
}

// XP 
$xp = get_xp($mysqli, $id);

$xpTotal = $xp['total_xp'];
$level = $xp['level'];
$progress = $xp['progress'] * 100; // For progress bar width %

$nextLevelAt = $xp['next_level_xp']; // XP needed for next level absolute
$xpToNextLevel = $nextLevelAt - $xpTotal; // How much XP left to next level



// print_r($user);

// Example user info from DB:
$displayName = htmlspecialchars($user['name'] ?? $user['username']);
$username = htmlspecialchars($user['username']);

$profilePicPath = __DIR__ . "/../../profile_pics/{$id}.png";  // filesystem path
$profilePicUrl = "";                 // URL path relative to site root

if ($id && file_exists($profilePicPath)) {
    $profilePicUrl = "profile_pics/".$id.".png";                  // URL path relative to site root

} else {
    $profilePicUrl = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=random";
}

// echo $profilePicUrl;

$zborId = htmlspecialchars($user['zbor_id'] ?? '12345');
$isAdmin = $user['admin'];

$accountCreated = date("M j, Y", strtotime($user['created_at'] ?? '2018-01-15'));
$lastLogin = date("M j, Y, h:i A", strtotime($user['last_login'] ?? 'now'));
$lastUpdated = date("M j, Y", strtotime($user['updated_at'] ?? '2023-06-05'));

// Activity stats example data
$songsAdded = get_number_of_songs($mysqli, $user["username"]);
$songsEdited = get_activities_user($mysqli, "edit",$user["username"]);
?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <!-- <img src="<?= $profilePicUrl ?>" class="profile-pic rounded-circle" alt="Profile Picture"> -->

                <img src="<?= htmlspecialchars($profilePicUrl) ?>" class="profile-pic rounded-circle" alt="Profile Picture">

            </div>
            <div class="col-md-7">
                <h1 class="display-5 mb-1"><?= $displayName ?></h1>
                <p class="lead mb-2">@<?= $username ?></p>
                <span class="xp-badge me-2">
                    <i class="bi bi-star-fill me-1"></i> Level <?= $level ?> Contributor
                </span>
                <?php if ($isAdmin): ?>
                <span class="xp-badge">
                    <i class="bi bi-patch-check-fill me-1"></i> Admin
                </span>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <!-- Left Column: XP and Stats -->
        <div class="col-md-5">
            <div class="mb-4">
                <h3 class="section-title">Experience Points</h3>
                <!-- XP Milestone -->
                <div class="xp-card bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="xp-category text-white">Total XP</div>
                            <div class="xp-value text-white"><?= number_format($xpTotal) ?></div>
                            <div class="text-white-50">XP points</div>
                        </div>
                        <div class="text-end">
                            <i class="bi bi-trophy" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                    <div class="progress xp-progress mt-3">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="mt-2 text-white-50">
                        Next level at <?= number_format($nextLevelAt) ?> XP
                    </div>
                </div>



            </div>
            
            <h3 class="section-title">Activity Stats</h3>
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stat-number"><?= $songsAdded ?></div>
                        <div class="stat-label">Songs</div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="stats-card">
                        <div class="stat-number"><?= $songsEdited ?></div>
                        <div class="stat-label">Edits</div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

