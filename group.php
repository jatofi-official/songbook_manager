<?php
session_start();
require_once 'php/db.php';
require_once 'php/functions.php';
require_once 'php/auth.php';

$groupId = (int)($_GET['id'] ?? 0);
$stmt = $mysqli->prepare('SELECT * FROM group_list WHERE id = ?');
$stmt->bind_param('i', $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$group) { http_response_code(404); exit('Group not found.'); }

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$userId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;
$currentGroupId = $userId ? get_user_group($mysqli, $userId) : null;
$adminIds = array_values(array_filter(array_map('intval', explode(',', $group['admin_ids'] ?? ''))));
$isGroupAdmin = $userId && ((int)$group['owner'] === $userId || in_array($userId, $adminIds, true));
$canManage = $isGroupAdmin || !empty($_SESSION['admin']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } elseif (($_POST['action'] ?? '') === 'join') {
        if ($currentGroupId !== null && (int)$currentGroupId !== $groupId && empty($_POST['confirm_move'])) {
            $error = 'Confirm leaving your current group.';
        } elseif (!verify_group_password($_POST['password'] ?? '', $group['password'])) {
            $error = 'Incorrect group password.';
        } else {
            $stmt = $mysqli->prepare('UPDATE users SET group_id = ? WHERE id = ?');
            $stmt->bind_param('ii', $groupId, $userId);
            $stmt->execute();
            $stmt->close();
            header("Location: group.php?id=$groupId");
            exit;
        }
    } elseif ($canManage && ($_POST['action'] ?? '') === 'add_admin') {
        $memberId = (int)($_POST['user_id'] ?? 0);
        $stmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? AND group_id = ?');
        $stmt->bind_param('ii', $memberId, $groupId);
        $stmt->execute();
        $isMember = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($isMember) add_group_admin($mysqli, $groupId, [$memberId]);
        header("Location: group.php?id=$groupId");
        exit;
    } elseif ($canManage && ($_POST['action'] ?? '') === 'remove_admin') {
        $memberId = (int)($_POST['user_id'] ?? 0);
        if ($memberId !== (int)$group['owner']) remove_group_admin($mysqli, $groupId, $memberId);
        header("Location: group.php?id=$groupId");
        exit;
    }
}

$stmt = $mysqli->prepare('SELECT id, name, username, admin FROM users WHERE group_id = ? ORDER BY name');
$stmt->bind_param('i', $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$memberCanSee = $currentGroupId !== null && (int)$currentGroupId === $groupId;
$songbookSql = 'SELECT id, name, description, is_public FROM songbooks WHERE group_id = ?';
if (!$memberCanSee && !$canManage) $songbookSql .= ' AND is_public = 2';
$songbookSql .= ' ORDER BY name';
$stmt = $mysqli->prepare($songbookSql);
$stmt->bind_param('i', $groupId);
$stmt->execute();
$songbooks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$website = 'groups';
$title = $group['name'];
include 'php/header.php';
include 'php/navigation.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h1 class="h3 mb-1"><?= htmlspecialchars($group['name']) ?></h1><span class="text-muted"><?= count($members) ?> members</span></div>
        <?php if ($userId && !$memberCanSee): ?>
            <form method="post" class="card card-body p-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="join">
                <?php if (!empty($group['password'])): ?><input class="form-control form-control-sm mb-2" type="password" name="password" placeholder="Group password" required><?php endif; ?>
                <?php if ($currentGroupId !== null): ?><label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_move" value="1" required><span class="form-check-label">Leave current group</span></label><?php endif; ?>
                <button class="btn btn-success btn-sm">Join group</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <section class="col-lg-6">
            <h2 class="h4">Members</h2>
            <div class="list-group">
                <?php foreach ($members as $member): $memberIsAdmin = (int)$group['owner'] === (int)$member['id'] || in_array((int)$member['id'], $adminIds, true); ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <a href="user.php?id=<?= (int)$member['id'] ?>"><?= htmlspecialchars($member['name']) ?> <small class="text-muted">@<?= htmlspecialchars($member['username']) ?></small></a>
                        <div class="d-flex gap-1 align-items-center">
                            <?php if ($memberIsAdmin): ?><span class="badge bg-primary"><?= (int)$group['owner'] === (int)$member['id'] ? 'Owner' : 'Admin' ?></span><?php endif; ?>
                            <?php if ($canManage && (int)$group['owner'] !== (int)$member['id']): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="<?= $memberIsAdmin ? 'remove_admin' : 'add_admin' ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$member['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= $memberIsAdmin ? 'Remove admin' : 'Make admin' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="col-lg-6">
            <h2 class="h4">Group songbooks</h2>
            <div class="list-group">
                <?php foreach ($songbooks as $songbook): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <a class="text-decoration-none flex-grow-1" href="songbook.php?id=<?= (int)$songbook['id'] ?>">
                            <strong><?= htmlspecialchars($songbook['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($songbook['description'] ?? '') ?></small>
                        </a>
                        <?php if ($memberCanSee || $canManage): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="wishlist.php?tab=songbook&amp;songbook_id=<?= (int)$songbook['id'] ?>">Checklist</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$songbooks): ?><p class="text-muted">No visible songbooks.</p><?php endif; ?>
        </section>
    </div>
</div>
<?php include 'php/footer.php'; ?>
