<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once('../php/data.php');
$website = "admin-stats";
$title = "Detailed statistics";
include_once('../php/auth.php');
include_once('../php/db.php');
include_once('../php/functions.php');
include_once("../php/header.php");
include_once('../php/navigation.php');

// Admin-only access - same gate as admin/index.php and admin/log.php.
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    ?>
    <div class="container mt-5 text-center">
        <p class="text-danger">Táto stránka je prístupná iba administrátorom.</p>
    </div>
<?php
    include '../php/footer.php';
    exit;
}

// Totals
$songsCounts = get_number_of_songs($mysqli);
$totalUsers = get_total_users($mysqli);
$totalSongbooksAll = get_number_of_songbooks($mysqli, 0);
$totalGroups = get_total_groups($mysqli);
$totalUniqueTags = get_total_unique_tags($mysqli);
$totalWishlistSongs = get_total_wishlist_songs($mysqli);
$visibilityCounts = get_songbook_visibility_counts($mysqli);

// Trends (real timestamp data only - activity_log.time and
// xp_events.created_at are both genuine per-row timestamps, not fabricated).
$activityByDay = get_activity_by_day($mysqli, 30, 'activity');
$xpByDay = get_xp_by_day($mysqli, 30);

// Leaderboards
$xpLeaderboardTotal = get_xp_leaderboard($mysqli, 'total', 10);
$xpLeaderboardMonth = get_xp_leaderboard($mysqli, 'month', 10);
$topOwners = get_top_song_owners($mysqli, 10);
$topEditors = get_top_editors($mysqli, 10);
$recentActivity = get_recent_activity($mysqli, 15);

// Content breakdowns
$topTags = get_top_song_tags($mysqli, 20);
$difficultyCounts = get_app_wide_difficulty_counts($mysqli);
$topInstruments = get_app_wide_instrument_counts($mysqli, 15);
$topWishlistSongs = get_top_wishlist_songs($mysqli, 10);

// Song category filter (genre/type category, i.e. the `categories` table) -
// drives the dynamic, PowerBI-style re-filtering of the song-scoped sections
// below via admin/ajax_stats.php. Only sections whose data actually comes
// from `songs` rows are category-scoped: users/groups/wishlist/activity &
// login trends/XP leaderboards/top editors/songbook visibility aren't tied
// to a song category, so they stay unfiltered.
$allCategories = get_all_categories($mysqli);
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 mb-0">Detailed statistics</h1>
                <p class="text-muted mb-0">Site-wide totals, trends, leaderboards and content breakdowns</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container">

    <!-- ==================== Global statistics (not affected by the category filter) ==================== -->

    <!-- Totals -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-4">
        <div class="col">
            <a href="../users.php" class="stat-card-link h-100">
                <div class="stat-card p-3 h-100 my-0">
                    <div class="number"><?= $totalUsers ?></div>
                    <div class="label">Users</div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="../songbooks.php" class="stat-card-link h-100">
                <div class="stat-card p-3 h-100 my-0">
                    <div class="number"><?= $totalSongbooksAll ?></div>
                    <div class="label">Songbooks</div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="../groups.php" class="stat-card-link h-100">
                <div class="stat-card p-3 h-100 my-0">
                    <div class="number"><?= $totalGroups ?></div>
                    <div class="label">Groups</div>
                </div>
            </a>
        </div>
        <div class="col">
            <div class="stat-card p-3 h-100 my-0">
                <div class="number"><?= $totalUniqueTags ?></div>
                <div class="label">Unique tags</div>
            </div>
        </div>
        <div class="col">
            <a href="../wishlist.php" class="stat-card-link h-100">
                <div class="stat-card p-3 h-100 my-0">
                    <div class="number"><?= $totalWishlistSongs ?></div>
                    <div class="label">Wishlist requests</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Trends -->
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <div class="col">
            <div class="card h-100">
                <div class="card-header">Activity, last 30 days</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?= render_admin_bar_chart_svg($activityByDay, 'count', '#4361ee', 3) ?>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">XP earned, last 30 days</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?= render_admin_bar_chart_svg($xpByDay, 'amount', '#f72585', 3) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboards -->
    <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
        <div class="col">
            <div class="card h-100">
                <div class="card-header">XP leaderboard (all-time)</div>
                <div class="card-body">
                    <?php if (!$xpLeaderboardTotal): ?>
                        <p class="text-muted small mb-0">No data yet.</p>
                    <?php else: ?>
                        <ol class="mb-0 ps-3">
                            <?php foreach ($xpLeaderboardTotal as $row): ?>
                                <li class="small mb-1"><?= htmlspecialchars($row['name'] ?: $row['username']) ?> &mdash; <?= number_format((int)$row['xp']) ?> XP</li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">XP leaderboard (this month)</div>
                <div class="card-body">
                    <?php if (!$xpLeaderboardMonth): ?>
                        <p class="text-muted small mb-0">No data yet.</p>
                    <?php else: ?>
                        <ol class="mb-0 ps-3">
                            <?php foreach ($xpLeaderboardMonth as $row): ?>
                                <li class="small mb-1"><?= htmlspecialchars($row['name'] ?: $row['username']) ?> &mdash; <?= number_format((int)$row['xp']) ?> XP</li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">Top editors</div>
                <div class="card-body">
                    <?php if (!$topEditors): ?>
                        <p class="text-muted small mb-0">No data yet.</p>
                    <?php else: ?>
                        <ol class="mb-0 ps-3">
                            <?php foreach ($topEditors as $row): ?>
                                <li class="small mb-1"><?= htmlspecialchars($row['name']) ?> &mdash; <?= (int)$row['total'] ?> edits</li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Recent activity
                    <a href="log.php" class="small">Full log &rarr;</a>
                </div>
                <div class="card-body">
                    <?php if (!$recentActivity): ?>
                        <p class="text-muted small mb-0">No activity yet.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($recentActivity as $entry): ?>
                                <li class="small mb-1 d-flex justify-content-between gap-2 border-bottom pb-1">
                                    <span><strong><?= htmlspecialchars($entry['username']) ?></strong> &middot; <?= htmlspecialchars($entry['type']) ?> &middot; <?= htmlspecialchars($entry['description']) ?></span>
                                    <span class="text-muted text-nowrap"><?= htmlspecialchars($entry['time']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Content breakdowns (global) -->
    <div class="row row-cols-1 row-cols-lg-2 g-4 mb-5">
        <div class="col">
            <div class="card h-100">
                <div class="card-header">Songbook visibility</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?= render_admin_pie_with_legend([
                        ['label' => 'Public', 'count' => $visibilityCounts['public']],
                        ['label' => 'Group-only', 'count' => $visibilityCounts['group']],
                        ['label' => 'Private', 'count' => $visibilityCounts['private']],
                    ], 130, ['#198754', '#0dcaf0', '#6c757d']) ?>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">Most requested wishlist songs</div>
                <div class="card-body">
                    <?php if (!$topWishlistSongs): ?>
                        <p class="text-muted small mb-0">No wishlist requests yet.</p>
                    <?php else: ?>
                        <?php $wishlistMax = max(1, ...array_map(fn($s) => (int)$s['likes'], $topWishlistSongs)); ?>
                        <?php foreach ($topWishlistSongs as $song): ?>
                            <?php $label = $song['name'] . ($song['artist'] ? ' — ' . $song['artist'] : ''); ?>
                            <?= render_stat_bar_row($label, (int)$song['likes'], $wishlistMax, 'bg-danger') ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== Category statistics (song genre/type - dynamically filterable) ==================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 border-top pt-4">
        <h4 class="mb-0">Category breakdown</h4>
        <div class="d-flex flex-wrap align-items-end gap-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="filter-public">
                <label class="form-check-label small" for="filter-public">Public only</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="filter-finished">
                <label class="form-check-label small" for="filter-finished">Finished only</label>
            </div>
            <div>
                <label for="category-filter" class="form-label small text-muted mb-1">Filter by category</label>
                <select id="category-filter" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($allCategories as $catId => $catName): ?>
                        <option value="<?= (int)$catId ?>"><?= htmlspecialchars($catName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Category KPI -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 col-lg-2">
            <a href="../songs.php" class="stat-card-link h-100">
                <div class="stat-card p-3 h-100 my-0">
                    <div class="number" id="stat-songs-count"><?= $songsCounts['total'] ?></div>
                    <div class="label">Songs</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Top song contributors -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Top song contributors</div>
                <div class="card-body" id="stat-top-owners">
                    <?php if (!$topOwners): ?>
                        <p class="text-muted small mb-0">No data yet.</p>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4 align-items-center">
                            <div class="col">
                                <ol class="mb-0 ps-3">
                                    <?php foreach ($topOwners as $row): ?>
                                        <li class="small mb-1"><?= htmlspecialchars($row['name']) ?> &mdash; <?= (int)$row['total'] ?> songs</li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                            <div class="col d-flex justify-content-center">
                                <?= render_admin_pie_with_legend(
                                    array_map(fn($row) => ['label' => $row['name'], 'count' => (int)$row['total']], $topOwners),
                                    130
                                ) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Difficulty / tags -->
    <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
        <div class="col">
            <div class="card h-100">
                <div class="card-header">Difficulty distribution</div>
                <div class="card-body d-flex align-items-center justify-content-center" id="stat-difficulty">
                    <?= render_admin_pie_with_legend([
                        ['label' => 'Easy', 'count' => $difficultyCounts['easy']],
                        ['label' => 'Normal', 'count' => $difficultyCounts['normal']],
                        ['label' => 'Hard', 'count' => $difficultyCounts['hard']],
                        ['label' => 'Not set', 'count' => $difficultyCounts['none']],
                    // Matches buildDifficultyBadge's colors in songbook_render_preview.js (easy/normal/hard),
                    // plus a neutral gray for "not set", which that badge has no color for.
                    ], 130, ['#198754', '#6c757d', '#dc3545', '#adb5bd']) ?>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-header">Most used tags</div>
                <div class="card-body" id="stat-top-tags">
                    <?php if (!$topTags): ?>
                        <p class="text-muted small mb-0">No tags used yet.</p>
                    <?php else: ?>
                        <?php $tagMax = max(1, ...array_map(fn($t) => (int)$t['usage_count'], $topTags)); ?>
                        <?php foreach ($topTags as $tag): ?>
                            <?= render_stat_bar_row($tag['name'], (int)$tag['usage_count'], $tagMax) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Instruments -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Instrument usage</div>
                <div class="card-body" id="stat-instruments">
                    <?php if (!$topInstruments): ?>
                        <p class="text-muted small mb-0">No instruments tagged yet.</p>
                    <?php else: ?>
                        <?php $instrumentMax = max(1, ...array_values($topInstruments)); ?>
                        <?php foreach ($topInstruments as $instrument => $count): ?>
                            <?= render_stat_bar_row($instrument, $count, $instrumentMax, 'bg-warning') ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function refreshCategoryStats() {
    const categoryId = document.getElementById('category-filter').value;
    const onlyPublic = document.getElementById('filter-public').checked;
    const onlyFinished = document.getElementById('filter-finished').checked;

    const params = new URLSearchParams();
    if (categoryId) params.set('category', categoryId);
    if (onlyPublic) params.set('public', '1');
    if (onlyFinished) params.set('finished', '1');

    const query = params.toString();
    const url = 'ajax_stats.php' + (query ? ('?' + query) : '');

    fetch(url, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(data => {
            if (data.error) return;
            document.getElementById('stat-songs-count').textContent = data.songsCount;
            document.getElementById('stat-top-owners').innerHTML = data.topOwners;
            document.getElementById('stat-difficulty').innerHTML = data.difficulty;
            document.getElementById('stat-top-tags').innerHTML = data.topTags;
            document.getElementById('stat-instruments').innerHTML = data.topInstruments;
        })
        .catch(err => console.error('Failed to update stats:', err));
}

document.getElementById('category-filter').addEventListener('change', refreshCategoryStats);
document.getElementById('filter-public').addEventListener('change', refreshCategoryStats);
document.getElementById('filter-finished').addEventListener('change', refreshCategoryStats);
</script>

<?php
include('../php/footer.php');
?>
