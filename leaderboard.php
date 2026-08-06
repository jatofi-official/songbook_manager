<?php
session_start();
require_once 'php/db.php';
require_once 'php/functions.php';
require_once 'php/auth.php';
$website = 'leaderboard';
$title = 'XP leaderboard';
$period = in_array($_GET['period'] ?? 'week', ['week', 'month', 'total'], true) ? $_GET['period'] : 'week';
$leaders = get_xp_leaderboard($mysqli, $period, 100);
include 'php/header.php';
include 'php/navigation.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">XP leaderboard</h1>
        <div class="btn-group">
            <?php foreach (['week' => 'Week', 'month' => 'Month', 'total' => 'All time'] as $value => $label): ?>
                <a href="?period=<?= $value ?>" class="btn btn-sm <?= $period === $value ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>#</th><th>User</th><th class="text-end">XP</th></tr></thead>
            <tbody>
            <?php foreach ($leaders as $index => $leader): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><a href="user.php?id=<?= (int)$leader['id'] ?>"><?= htmlspecialchars($leader['name']) ?></a><br><small class="text-muted">@<?= htmlspecialchars($leader['username']) ?></small></td>
                    <td class="text-end fw-bold"><?= number_format((int)$leader['xp']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'php/footer.php'; ?>
