<?php
session_start();

// Initialize diary storage in session
if (!isset($_SESSION['diaries'])) {
    $_SESSION['diaries'] = [];
}

// Handle new diary submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['content'])) {
    $entry = [
        'id' => uniqid(),
        'title' => htmlspecialchars($_POST['title']),
        'content' => nl2br(htmlspecialchars($_POST['content'])),
        'date' => date("Y-m-d H:i:s"),
    ];
    $_SESSION['diaries'][] = $entry;
    header("Location: " . $_SERVER['PHP_SELF']); // prevent resubmission
    exit;
}

// Handle diary view
$selectedDiary = null;
if (isset($_GET['view'])) {
    foreach ($_SESSION['diaries'] as $d) {
        if ($d['id'] === $_GET['view']) {
            $selectedDiary = $d;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Diary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h1 class="mb-4 text-center">📖 My Diary</h1>

    <div class="row">
        <!-- Diary Form -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header">New Diary Entry</div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save Entry</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Diary Table -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header">Diary Entries</div>
                <div class="card-body">
                    <?php if (empty($_SESSION['diaries'])): ?>
                        <p class="text-muted">No entries yet. Start writing!</p>
                    <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Title</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['diaries'] as $d): ?>
                                    <tr>
                                        <td><?= $d['date'] ?></td>
                                        <td><?= $d['title'] ?></td>
                                        <td>
                                            <a href="?view=<?= $d['id'] ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Selected Diary View -->
            <?php if ($selectedDiary): ?>
                <div class="card shadow-sm">
                    <div class="card-header">Viewing: <?= $selectedDiary['title'] ?></div>
                    <div class="card-body">
                        <p><strong>Date:</strong> <?= $selectedDiary['date'] ?></p>
                        <hr>
                        <p><?= $selectedDiary['content'] ?></p>
                        <a href="?" class="btn btn-secondary">Back</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
