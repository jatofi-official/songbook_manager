<?php
session_start();
require_once '../php/db.php';
require_once '../php/functions.php';
require_once '../php/auth.php';

if (empty($_SESSION['admin'])) {
    http_response_code(403);
    exit('Admin access required.');
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid request token.');
    }
    $formId = (int)($_POST['form_id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle_active' && $formId) {
        $stmt = $mysqli->prepare("UPDATE anketa_forms SET is_active = 1 - is_active WHERE id = ?");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
    } elseif (($_POST['action'] ?? '') === 'delete' && $formId) {
        $stmt = $mysqli->prepare("DELETE FROM anketa_forms WHERE id = ?");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
    }
    header("Location: anketa.php");
    exit;
}

$website = 'admin';
$title = 'Survey forms';
include '../php/header.php';
include '../php/navigation.php';

$forms = $mysqli->query("
    SELECT f.*, s.name AS songbook_name,
        (SELECT COUNT(*) FROM anketa_form_categories fc WHERE fc.form_id = f.id) AS category_count,
        (SELECT COUNT(DISTINCT respondent_key) FROM anketa_responses r WHERE r.form_id = f.id) AS respondent_count
    FROM anketa_forms f
    LEFT JOIN songbooks s ON s.id = f.songbook_id
    ORDER BY f.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Survey forms</h1>
        <a href="anketa_form.php" class="btn btn-success">New form</a>
    </div>

    <?php if (empty($forms)): ?>
        <p class="text-muted">No survey forms yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Title</th><th>Songbook</th><th>Categories</th><th>Password</th>
                    <th>Respondents</th><th>Status</th><th>Created</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($forms as $form): ?>
                <tr>
                    <td><?= htmlspecialchars($form['title']) ?></td>
                    <td><?= htmlspecialchars($form['songbook_name'] ?? '—') ?></td>
                    <td><?= (int)$form['category_count'] ?></td>
                    <td><?= $form['password_hash'] ? 'Yes' : 'No (login required)' ?></td>
                    <td><?= (int)$form['respondent_count'] ?></td>
                    <td><?= $form['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td><small><?= htmlspecialchars($form['created_at']) ?></small></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <a href="../anketa/form.php?form=<?= (int)$form['id'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Open</a>
                            <a href="anketa_results.php?form_id=<?= (int)$form['id'] ?>" class="btn btn-outline-primary btn-sm">Results</a>
                            <a href="anketa_form.php?id=<?= (int)$form['id'] ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <button type="submit" class="btn btn-outline-secondary btn-sm"><?= $form['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this form and all its responses?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php include '../php/footer.php'; ?>
