<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once('../db.php');
include_once('../categories.php');

if (empty($_SESSION["admin_view"]) || $_SESSION["admin_view"] !== true) {
    echo '<p class="text-danger">You do not have permission to view this.</p>';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo '<p>Invalid song.</p>';
    exit;
}

$stmt = $mysqli->prepare("SELECT * FROM songs_history WHERE song_id = ? ORDER BY changed_at DESC, history_id DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$rows) {
    echo '<p>No history recorded for this song yet.</p>';
    exit;
}

$actionClass = [
    'initial' => 'bg-secondary',
    'add'     => 'bg-success',
    'edit'    => 'bg-primary',
    'delete'  => 'bg-danger',
];
?>
<div class="accordion" id="songHistoryAccordion">
    <?php foreach ($rows as $i => $row): ?>
        <?php $collapseId = "songHistoryEntry" . (int)$row['history_id']; ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                        data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                    <span class="badge <?= $actionClass[$row['action']] ?? 'bg-secondary' ?> me-2">
                        <?= htmlspecialchars(ucfirst($row['action'])) ?>
                    </span>
                    <?= htmlspecialchars($row['changed_at']) ?>
                    &nbsp;&mdash;&nbsp;<?= htmlspecialchars($row['changed_by'] ?? 'unknown') ?>
                </button>
            </h2>
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                 data-bs-parent="#songHistoryAccordion">
                <div class="accordion-body">
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <div><strong>Title:</strong> <?= htmlspecialchars($row['title']) ?></div>
                            <div><strong>Artist:</strong> <?= htmlspecialchars($row['artist'] ?? '') ?></div>
                            <div><strong>Category:</strong> <?= htmlspecialchars($categories[$row['category']] ?? $row['category']) ?></div>
                            <div><strong>Capo:</strong> <?= htmlspecialchars($row['capo'] ?? 0) ?><?= $row['capo_alt'] !== null ? ' / ' . htmlspecialchars($row['capo_alt']) : '' ?></div>
                        </div>
                        <div class="col-md-6">
                            <div><strong>Owner:</strong> <?= htmlspecialchars($row['owner'] ?? '') ?></div>
                            <div><strong>Public:</strong> <?= $row['is_public'] ? 'Yes' : 'No' ?></div>
                            <div><strong>Tags:</strong> <?= htmlspecialchars($row['tags'] ?? '') ?></div>
                            <div><strong>Instruments:</strong> <?= htmlspecialchars($row['instruments'] ?? '') ?></div>
                        </div>
                    </div>
                    <strong>Content:</strong>
                    <pre style="white-space: pre-wrap; font-family: monospace;"><?= htmlspecialchars($row['content']) ?></pre>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
