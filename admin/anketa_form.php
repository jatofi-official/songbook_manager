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

$formId = intval($_GET['id'] ?? 0);
$existing = $formId ? get_anketa_form($mysqli, $formId) : null;
if ($formId && !$existing) {
    http_response_code(404);
    exit('Form not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $songbookId = (int)($_POST['songbook_id'] ?? 0);
        $postTitle = trim($_POST['title'] ?? '');
        $usePassword = isset($_POST['use_password']);
        $password = trim($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $includeBasicQuestions = isset($_POST['include_basic_questions']) ? 1 : 0;
        // category_ids[] arrives in the order the admin dragged them into on the client,
        // so it's already the sort order - no separate order field needed.
        $selectedCategoryIds = array_map('intval', $_POST['category_ids'] ?? []);

        if (!$songbookId || $postTitle === '') {
            $error = 'Songbook and title are required.';
        } elseif ($usePassword && $password === '' && !($existing && $existing['password_hash'])) {
            $error = 'A password is required when password protection is enabled.';
        } elseif (empty($selectedCategoryIds)) {
            $error = 'Select at least one category.';
        } else {
            if ($usePassword) {
                $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $existing['password_hash'];
            } else {
                $passwordHash = null;
            }

            if ($existing) {
                $stmt = $mysqli->prepare("UPDATE anketa_forms SET songbook_id = ?, title = ?, password_hash = ?, is_active = ?, include_basic_questions = ? WHERE id = ?");
                $stmt->bind_param("issiii", $songbookId, $postTitle, $passwordHash, $isActive, $includeBasicQuestions, $formId);
                $stmt->execute();
            } else {
                $userId = $_SESSION['id'] ?? null;
                $stmt = $mysqli->prepare("INSERT INTO anketa_forms (songbook_id, title, password_hash, is_active, include_basic_questions, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issiii", $songbookId, $postTitle, $passwordHash, $isActive, $includeBasicQuestions, $userId);
                $stmt->execute();
                $formId = $stmt->insert_id;
            }

            $mysqli->query("DELETE FROM anketa_form_categories WHERE form_id = " . (int)$formId);
            $stmt = $mysqli->prepare("INSERT INTO anketa_form_categories (form_id, category_id, sort_order) VALUES (?, ?, ?)");
            foreach ($selectedCategoryIds as $sortOrder => $categoryId) {
                $stmt->bind_param("iii", $formId, $categoryId, $sortOrder);
                $stmt->execute();
            }

            header("Location: anketa.php");
            exit;
        }
    }
}

// Data for the songbook <select> and its category checklist, rendered client-side
$songbooksRes = $mysqli->query("SELECT id, name, categories FROM songbooks ORDER BY name ASC");
$songbooks = [];
$songbooksJson = [];
while ($row = $songbooksRes->fetch_assoc()) {
    $songbooks[] = $row;
    $songbooksJson[(int)$row['id']] = get_songbook_categories($mysqli, (int)$row['id']);
}

$selectedSongbookId = (int)($_POST['songbook_id'] ?? $existing['songbook_id'] ?? 0);
$selectedCategoryIds = $_POST['category_ids'] ?? ($existing['category_ids'] ?? []);
$selectedCategoryIds = array_map('intval', $selectedCategoryIds);

$isRepost = isset($_POST['title']);
$checkedUsePassword = $isRepost ? isset($_POST['use_password']) : ($existing && $existing['password_hash']);
$checkedIsActive = $isRepost ? isset($_POST['is_active']) : (!$existing || $existing['is_active']);
$checkedIncludeBasicQuestions = $isRepost ? isset($_POST['include_basic_questions']) : ($existing && $existing['include_basic_questions']);

$website = 'admin';
$title = $existing ? 'Edit survey form' : 'New survey form';
include '../php/header.php';
include '../php/navigation.php';
?>
<div class="container py-4">
    <h1 class="h3 mb-3"><?= $existing ? 'Edit survey form' : 'New survey form' ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" style="max-width: 600px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required
                   value="<?= htmlspecialchars($_POST['title'] ?? $existing['title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Songbook</label>
            <select name="songbook_id" id="songbook_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($songbooks as $sb): ?>
                    <option value="<?= (int)$sb['id'] ?>" <?= $selectedSongbookId === (int)$sb['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sb['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="include_basic_questions" name="include_basic_questions"
                   <?= $checkedIncludeBasicQuestions ? 'checked' : '' ?>>
            <label class="form-check-label" for="include_basic_questions">Include the two basic camp questions at the start (how long at camp, how often at campfires)</label>
        </div>

        <div class="mb-3">
            <label class="form-label">Categories to include, in order</label>
            <p class="form-text mt-0">Check the categories to include, and drag rows (<i class="bi bi-grip-vertical"></i>) to set their order in the form.</p>
            <div id="category-checklist" class="border rounded p-2"></div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="use_password" name="use_password"
                   <?= $checkedUsePassword ? 'checked' : '' ?>>
            <label class="form-check-label" for="use_password">Require a password to access this form (allows non-logged-in respondents)</label>
        </div>
        <div class="mb-3">
            <label class="form-label">Password<?= ($existing && $existing['password_hash']) ? ' (leave blank to keep current password)' : '' ?></label>
            <input type="text" name="password" class="form-control">
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                   <?= $checkedIsActive ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>

        <button type="submit" class="btn btn-success">Save</button>
        <a href="anketa.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div>

<style>
#category-checklist .cat-row { cursor: grab; background: var(--surface); }
#category-checklist .cat-row.dragging { opacity: 0.4; }
</style>
<script>
const songbookCategories = <?= json_encode($songbooksJson) ?>;
// Order to render in: previously-selected categories first (in their saved order),
// then the songbook's remaining categories in their natural order.
const initialSelectedOrder = <?= json_encode($selectedCategoryIds) ?>;

function renderCategories() {
    const songbookId = document.getElementById('songbook_id').value;
    const container = document.getElementById('category-checklist');
    container.innerHTML = '';

    const categories = songbookCategories[songbookId] || {};
    const allIds = Object.keys(categories).map(Number);
    if (allIds.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">Select a songbook to see its categories.</p>';
        return;
    }

    const selectedHere = initialSelectedOrder.filter(id => allIds.includes(id));
    const rest = allIds.filter(id => !selectedHere.includes(id));
    const orderedIds = selectedHere.concat(rest);

    orderedIds.forEach(catId => {
        const row = document.createElement('div');
        row.className = 'cat-row d-flex align-items-center gap-2 mb-1 p-1 border rounded';
        row.draggable = true;
        row.innerHTML = `
            <i class="bi bi-grip-vertical text-muted"></i>
            <div class="form-check flex-grow-1 mb-0">
                <input class="form-check-input" type="checkbox" name="category_ids[]" value="${catId}" id="cat-${catId}"
                    ${selectedHere.includes(catId) ? 'checked' : ''}>
                <label class="form-check-label" for="cat-${catId}"></label>
            </div>
        `;
        row.querySelector('label').textContent = categories[catId];
        addDragHandlers(row);
        container.appendChild(row);
    });
}

let dragSrcRow = null;

function addDragHandlers(row) {
    row.addEventListener('dragstart', (e) => {
        dragSrcRow = row;
        row.classList.add('dragging');
        // Firefox won't actually start the drag unless data is set.
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', 'category-row');
    });
    row.addEventListener('dragend', () => {
        row.classList.remove('dragging');
    });
    row.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (!dragSrcRow || dragSrcRow === row) return;
        const rect = row.getBoundingClientRect();
        const insertAfter = (e.clientY - rect.top) / rect.height > 0.5;
        row.parentElement.insertBefore(dragSrcRow, insertAfter ? row.nextSibling : row);
    });
}

document.getElementById('songbook_id').addEventListener('change', renderCategories);
renderCategories();
</script>

<?php include '../php/footer.php'; ?>
