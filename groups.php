<?php
session_start();
require_once 'php/db.php';
require_once 'php/functions.php';
require_once 'php/auth.php';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $currentGroup = get_user_group($mysqli, (int)$_SESSION['id']);
        if ($name === '') {
            $error = 'Group name is required.';
        } elseif ($currentGroup !== null && empty($_POST['confirm_move'])) {
            $error = 'Confirm leaving your current group first.';
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM group_list WHERE name = ?');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) {
                $error = 'A group with this name already exists.';
            } else {
                $groupId = add_group($mysqli, $name, $password, (int)$_SESSION['id']);
                if ($groupId) {
                    header("Location: group.php?id=$groupId");
                    exit;
                }
                $error = 'Could not create the group.';
            }
        }
    }
}

$groups = $mysqli->query("SELECT g.id, g.name, COUNT(DISTINCT u.id) AS members, COUNT(DISTINCT s.id) AS songbooks
    FROM group_list g LEFT JOIN users u ON u.group_id = g.id LEFT JOIN songbooks s ON s.group_id = g.id
    GROUP BY g.id, g.name ORDER BY g.name")->fetch_all(MYSQLI_ASSOC);
$website = 'groups';
$title = 'Groups';
include 'php/header.php';
include 'php/navigation.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
        <div class="flex-grow-1">
            <h1 class="h3">Groups</h1>
            <div class="row g-3">
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="group.php?id=<?= (int)$group['id'] ?>" class="card card-body h-100 text-decoration-none text-reset">
                            <h2 class="h5"><?= htmlspecialchars($group['name']) ?></h2>
                            <span class="text-muted"><?= (int)$group['members'] ?> members · <?= (int)$group['songbooks'] ?> songbooks</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$groups): ?><p class="text-muted">No groups yet.</p><?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['id'])): ?>
            <form method="post" class="card card-body" style="min-width:280px;max-width:360px">
                <h2 class="h5">Create group</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <label class="form-label" for="group-name">Name</label>
                <input class="form-control mb-3" id="group-name" name="name" maxlength="100" required>
                <label class="form-label" for="group-password">Join password (optional)</label>
                <input class="form-control mb-3" type="password" id="group-password" name="password" maxlength="100">
                <?php if (get_user_group($mysqli, (int)$_SESSION['id']) !== null): ?>
                    <label class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm_move" value="1" required>
                        <span class="form-check-label">Leave my current group</span>
                    </label>
                <?php endif; ?>
                <button class="btn btn-success" type="submit">Create group</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php include 'php/footer.php'; ?>
