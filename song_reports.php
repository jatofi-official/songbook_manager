<?php
session_start();
require_once 'php/db.php';
require_once 'php/functions.php';
require_once 'php/auth.php';

if (empty($_SESSION['id'])) {
    header('Location: user_settings.php');
    exit;
}

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$error = '';
$isSiteAdmin = !empty($_SESSION['admin']);
$username = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please reload the page.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $songId = (int)($_POST['song_id'] ?? 0);
        $reportText = trim($_POST['report_text'] ?? '');
        $selectedText = trim($_POST['selected_text'] ?? '');

        $stmt = $mysqli->prepare("SELECT id FROM songs WHERE id = ? AND (is_public = 1 OR owner = ? OR ? = 1)");
        $adminInt = $isSiteAdmin ? 1 : 0;
        $stmt->bind_param('isi', $songId, $username, $adminInt);
        $stmt->execute();
        $canReport = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$canReport || $reportText === '') {
            $error = 'Song not found or report text is empty.';
        } else {
            $stmt = $mysqli->prepare("INSERT INTO song_reports (song_id, reported_by, report_text, selected_text) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiss', $songId, $_SESSION['id'], $reportText, $selectedText);
            $stmt->execute();
            $stmt->close();
            header('Location: song_reports.php?sent=1');
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'resolve') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        $adminInt = $isSiteAdmin ? 1 : 0;
        $stmt = $mysqli->prepare("UPDATE song_reports r JOIN songs s ON s.id = r.song_id
            SET r.status = 'resolved', r.resolved_at = NOW(), r.resolved_by = ?
            WHERE r.id = ? AND (s.owner = ? OR ? = 1)");
        $stmt->bind_param('iisi', $_SESSION['id'], $reportId, $username, $adminInt);
        $stmt->execute();
        $stmt->close();
        header('Location: song_reports.php?resolved=1');
        exit;
    }
}

$status = ($_GET['status'] ?? 'pending') === 'resolved' ? 'resolved' : 'pending';
$sql = "SELECT r.*, s.title, s.artist, s.owner, u.username AS reporter
        FROM song_reports r
        JOIN songs s ON s.id = r.song_id
        JOIN users u ON u.id = r.reported_by
        WHERE r.status = ?";
if (!$isSiteAdmin) $sql .= " AND s.owner = ?";
$sql .= " ORDER BY r.reported_at DESC";
$stmt = $mysqli->prepare($sql);
if ($isSiteAdmin) $stmt->bind_param('s', $status);
else $stmt->bind_param('ss', $status, $username);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$website = 'reports';
$title = 'Song reports';
include 'php/header.php';
include 'php/navigation.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Song reports</h1>
        <div class="btn-group">
            <a class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?status=pending">Open</a>
            <a class="btn btn-sm <?= $status === 'resolved' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?status=resolved">Resolved</a>
        </div>
    </div>

    <?php if (isset($_GET['sent'])): ?><div class="alert alert-success">Report sent to the song owner.</div><?php endif; ?>
    <?php if (isset($_GET['resolved'])): ?><div class="alert alert-success">Report resolved.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!$reports): ?>
        <p class="text-muted">No <?= $status === 'pending' ? 'open' : 'resolved' ?> reports.</p>
    <?php endif; ?>

    <?php foreach ($reports as $report): ?>
        <article class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="h5"><a href="song.php?id=<?= (int)$report['song_id'] ?>"><?= htmlspecialchars($report['title']) ?></a></h2>
                    <span class="badge <?= $report['status'] === 'resolved' ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <?= $report['status'] === 'resolved' ? 'Resolved' : 'Open' ?>
                    </span>
                </div>
                <p class="mb-2"><?= nl2br(htmlspecialchars($report['report_text'])) ?></p>
                <?php if ($report['selected_text']): ?>
                    <pre class="border rounded p-2 bg-light" style="white-space:pre-wrap"><?= htmlspecialchars($report['selected_text']) ?></pre>
                <?php endif; ?>
                <small class="text-muted">Reported by <?= htmlspecialchars($report['reporter']) ?> · <?= htmlspecialchars($report['reported_at']) ?></small>
                <?php if ($report['status'] === 'pending'): ?>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                        <button class="btn btn-sm btn-success" type="submit">Mark resolved</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php include 'php/footer.php'; ?>
