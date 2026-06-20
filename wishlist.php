<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('php/auth.php');

include_once('php/db.php');
include_once('php/data.php');

$website = "wishlist";

include_once('php/functions.php');
include("php/header.php");
include('php/navigation.php');

$userId = $_SESSION["id"] ?? null;
$username = $_SESSION["user"] ?? null;
$isAdmin = $_SESSION["admin_view"] ?? false;
$userGroupId = $userId ? get_user_group($mysqli, $userId) : null;

$errors = [];
$successMessage = "";

$activeTab = ($_GET['tab'] ?? 'general') === 'songbook' ? 'songbook' : 'general';
$selectedSongbookId = isset($_GET['songbook_id']) ? (int)$_GET['songbook_id'] : null;
$filterUser = trim($_GET['user'] ?? '');
$filterArtist = trim($_GET['artist'] ?? '');
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$sbPage = max(1, (int)($_GET['sb_page'] ?? 1));
$wishlistFilters = ['user' => $filterUser, 'artist' => $filterArtist, 'search' => $search];
$hasActiveFilters = $filterUser !== '' || $filterArtist !== '' || $search !== '';

// Builds a wishlist.php query string from the current GET params, overriding
// the given keys - used for pagination/"clear filters" links.
function wishlist_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return 'wishlist.php?' . http_build_query($params);
}

$accessibleSongbooks = get_songbooks_for_wishlist($mysqli);

// Default the picker to the usual songbook, if it's in the accessible list.
if (!$selectedSongbookId && !isset($_GET['songbook_id'])) {
    foreach ($accessibleSongbooks as $sb) {
        if ($sb['name'] === WISHLIST_DEFAULT_SONGBOOK_NAME) {
            $selectedSongbookId = (int)$sb['id'];
            break;
        }
    }
}

$selectedSongbook = null;
if ($selectedSongbookId) {
    foreach ($accessibleSongbooks as $sb) {
        if ((int)$sb['id'] === $selectedSongbookId) {
            $selectedSongbook = get_songbook($mysqli, $selectedSongbookId);
            break;
        }
    }
}

// --- POST handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'general') {
        $name = trim($_POST['name'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $youtubeLink = trim($_POST['youtube_link'] ?? '');

        $result = add_wishlist_song($mysqli, $name, $artist, $youtubeLink, $username);
        if ($result['status'] === 'error') {
            $errors[] = $result['message'];
        } elseif ($result['status'] === 'duplicate') {
            $errors[] = "This song is already on the wishlist - vote for it instead.";
        } elseif ($result['status'] === 'in_database') {
            $errors[] = "This song is already in the database (song #{$result['song_id']}) - no need to wishlist it.";
        } else {
            $successMessage = "Added to the wishlist.";
            if ($username) {
                add_activity($mysqli, $username, "wishlist_add", "Wishlisted '{$name}' - {$artist}", 1);
            }
        }
        $activeTab = 'general';
    } elseif ($formType === 'songbook') {
        $songbookId = (int)($_POST['songbook_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $youtubeLink = trim($_POST['youtube_link'] ?? '');
        $existingSongId = isset($_POST['existing_song_id']) && $_POST['existing_song_id'] !== '' ? (int)$_POST['existing_song_id'] : null;

        $targetSongbook = null;
        foreach ($accessibleSongbooks as $sb) {
            if ((int)$sb['id'] === $songbookId) {
                $targetSongbook = $sb;
                break;
            }
        }

        if (!$targetSongbook) {
            $errors[] = "You may not add wishlist entries to that songbook.";
        } else {
            $result = add_wishlist_songbook_entry($mysqli, $songbookId, $name, $artist, $youtubeLink, $existingSongId, $username);
            if ($result['status'] === 'error') {
                $errors[] = $result['message'];
            } elseif ($result['status'] === 'duplicate') {
                $errors[] = "This song is already requested for this songbook - vote for it instead.";
            } elseif ($result['status'] === 'in_songbook') {
                $errors[] = "This song is already in this songbook.";
            } else {
                $successMessage = "Added to the songbook's wishlist.";
                if ($username) {
                    add_activity($mysqli, $username, "wishlist_add", "Wishlisted a song for songbook #{$songbookId}", 1);
                }
            }
        }
        $activeTab = 'songbook';
        $selectedSongbookId = $songbookId;
        if ($songbookId) {
            $selectedSongbook = get_songbook($mysqli, $songbookId);
        }
    } elseif ($formType === 'attach_existing') {
        if (!$userId) {
            $errors[] = "You must be logged in to do that.";
        } else {
            $entryId = (int)($_POST['entry_id'] ?? 0);
            $stmt = $mysqli->prepare("SELECT * FROM wishlist_songbooks WHERE id = ? AND song_id IS NOT NULL");
            $stmt->bind_param("i", $entryId);
            $stmt->execute();
            $entry = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$entry) {
                $errors[] = "Wishlist entry not found.";
            } else {
                $ok = attach_song_to_songbook($mysqli, $entry['songbook_id'], UNCATEGORIZED_CATEGORY_ID, $entry['song_id']);
                if ($ok) {
                    delete_wishlist_songbook_entry($mysqli, $entryId);
                    add_activity($mysqli, $username, "wishlist_fulfill", "Added a wishlisted song to songbook #{$entry['songbook_id']}", 1);
                    $successMessage = "Song added to the songbook.";
                } else {
                    $errors[] = "Could not add the song to the songbook.";
                }
            }
            $activeTab = 'songbook';
            $selectedSongbookId = (int)($entry['songbook_id'] ?? 0);
            if ($selectedSongbookId) {
                $selectedSongbook = get_songbook($mysqli, $selectedSongbookId);
            }
        }
    } elseif ($formType === 'delete_general') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT * FROM wishlist_songs WHERE id = ?");
        $stmt->bind_param("i", $entryId);
        $stmt->execute();
        $entry = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$entry) {
            $errors[] = "Wishlist entry not found.";
        } elseif (!can_delete_wishlist_entry($entry, $username, $isAdmin)) {
            $errors[] = "You may not remove this wishlist entry.";
        } else {
            delete_wishlist_song($mysqli, $entryId);
            $successMessage = "Removed from the wishlist.";
        }
        $activeTab = 'general';
    } elseif ($formType === 'delete_songbook') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT * FROM wishlist_songbooks WHERE id = ?");
        $stmt->bind_param("i", $entryId);
        $stmt->execute();
        $entry = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$entry) {
            $errors[] = "Wishlist entry not found.";
        } elseif (!can_delete_wishlist_entry($entry, $username, $isAdmin)) {
            $errors[] = "You may not remove this wishlist entry.";
        } else {
            delete_wishlist_songbook_entry($mysqli, $entryId);
            $successMessage = "Removed from the songbook's wishlist.";
        }
        $activeTab = 'songbook';
        $selectedSongbookId = (int)($entry['songbook_id'] ?? 0);
        if ($selectedSongbookId) {
            $selectedSongbook = get_songbook($mysqli, $selectedSongbookId);
        }
    } elseif ($formType === 'edit_general') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT * FROM wishlist_songs WHERE id = ?");
        $stmt->bind_param("i", $entryId);
        $stmt->execute();
        $entry = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$entry) {
            $errors[] = "Wishlist entry not found.";
        } elseif (!can_delete_wishlist_entry($entry, $username, $isAdmin)) {
            $errors[] = "You may not edit this wishlist entry.";
        } else {
            $result = update_wishlist_song($mysqli, $entryId, $_POST['name'] ?? '', $_POST['artist'] ?? '', $_POST['youtube_link'] ?? '');
            if ($result['status'] === 'error') {
                $errors[] = $result['message'];
            } else {
                $successMessage = "Wishlist entry updated.";
            }
        }
        $activeTab = 'general';
    } elseif ($formType === 'edit_songbook') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        $stmt = $mysqli->prepare("SELECT * FROM wishlist_songbooks WHERE id = ?");
        $stmt->bind_param("i", $entryId);
        $stmt->execute();
        $entry = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$entry) {
            $errors[] = "Wishlist entry not found.";
        } elseif (!can_delete_wishlist_entry($entry, $username, $isAdmin)) {
            $errors[] = "You may not edit this wishlist entry.";
        } elseif (!$entry['wishlist_song_id']) {
            $errors[] = "Songs already in the database can't be edited here.";
        } else {
            $result = update_wishlist_song($mysqli, $entry['wishlist_song_id'], $_POST['name'] ?? '', $_POST['artist'] ?? '', $_POST['youtube_link'] ?? '');
            if ($result['status'] === 'error') {
                $errors[] = $result['message'];
            } else {
                $successMessage = "Wishlist entry updated.";
            }
        }
        $activeTab = 'songbook';
        $selectedSongbookId = (int)($entry['songbook_id'] ?? 0);
        if ($selectedSongbookId) {
            $selectedSongbook = get_songbook($mysqli, $selectedSongbookId);
        }
    }
}

$generalResult = get_wishlist_songs($mysqli, $username, array_merge($wishlistFilters, ['page' => $page]));
$generalWishlist = $generalResult['items'];
$generalTotalPages = $generalResult['totalPages'];

$songbookResult = $selectedSongbookId
    ? get_wishlist_songbook_entries($mysqli, $selectedSongbookId, $username, array_merge($wishlistFilters, ['page' => $sbPage]))
    : ['items' => [], 'totalPages' => 1];
$songbookEntries = $songbookResult['items'];
$songbookTotalPages = $songbookResult['totalPages'];

$canVoteOnSelectedSongbook = $selectedSongbook ? can_vote_on_wishlist_songbook($selectedSongbook, $userId, $userGroupId, $isAdmin) : false;
?>

<div class="dashboard-header">
    <div class="container">
        <h1 class="h3 mb-0">Wishlist</h1>
        <p class="text-muted mb-0">Request songs and vote for the ones you want to see added</p>
    </div>
</div>

<div class="container mt-4">

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="wishlistTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab">General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === 'songbook' ? 'active' : '' ?>" id="songbook-tab" data-bs-toggle="tab" data-bs-target="#songbook-pane" type="button" role="tab">Songbook</button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- GENERAL TAB -->
        <div class="tab-pane fade <?= $activeTab === 'general' ? 'show active' : '' ?>" id="general-pane" role="tabpanel">

            <div class="card mb-4">
                <div class="card-header">
                    <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#generalAddForm">
                        <i class="bi bi-plus-circle me-1"></i> Add a song to the wishlist
                    </button>
                </div>
                <div class="collapse" id="generalAddForm">
                    <div class="card-body">
                        <form method="post" action="wishlist.php">
                            <input type="hidden" name="form_type" value="general">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" maxlength="60" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Artist</label>
                                    <input type="text" name="artist" class="form-control" maxlength="60">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">YouTube link</label>
                                    <input type="url" name="youtube_link" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Add to wishlist</button>
                        </form>
                    </div>
                </div>
            </div>

            <form method="get" action="wishlist.php" class="row g-2 mb-3 align-items-end">
                <input type="hidden" name="tab" value="general">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="song or artist">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by artist</label>
                    <input type="text" name="artist" class="form-control" value="<?= htmlspecialchars($filterArtist) ?>" placeholder="artist">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by user</label>
                    <input type="text" name="user" class="form-control" value="<?= htmlspecialchars($filterUser) ?>" placeholder="username">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="wishlist.php?tab=general" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (empty($generalWishlist)): ?>
                <p class="text-muted">No songs on the wishlist yet.</p>
            <?php else: ?>
                <?php foreach ($generalWishlist as $item): ?>
                    <div class="card wishlist-card mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong><?= htmlspecialchars($item['name']) ?></strong>
                                <?php if (!empty($item['artist'])): ?>
                                    <span class="text-muted">- <?= htmlspecialchars($item['artist']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['youtube_link'])): ?>
                                    <a href="<?= htmlspecialchars($item['youtube_link']) ?>" target="_blank" rel="noopener" class="ms-2">
                                        <i class="bi bi-youtube text-danger"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($item['username'])): ?>
                                    <div class="text-muted small">Requested by <?= htmlspecialchars($item['username']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button"
                                        class="btn btn-sm <?= $item['liked_by_me'] ? 'btn-primary' : 'btn-outline-primary' ?> wishlist-like-btn"
                                        data-type="song"
                                        data-id="<?= (int)$item['id'] ?>"
                                        <?= $username ? '' : 'disabled title="Log in to vote"' ?>>
                                    <i class="bi bi-hand-thumbs-up"></i>
                                    <span class="like-count"><?= (int)$item['likes'] ?></span>
                                </button>
                                <a class="btn btn-sm btn-success"
                                   href="song.php?new=1&title=<?= urlencode($item['name']) ?>&artist=<?= urlencode($item['artist'] ?? '') ?>&youtube_link=<?= urlencode($item['youtube_link'] ?? '') ?>&wishlist_song_id=<?= (int)$item['id'] ?>">
                                    Create this song
                                </a>
                                <?php if (can_delete_wishlist_entry($item, $username, $isAdmin)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editGeneral<?= (int)$item['id'] ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="wishlist.php" class="d-inline" onsubmit="return confirm('Remove this song from the wishlist?');">
                                        <input type="hidden" name="form_type" value="delete_general">
                                        <input type="hidden" name="entry_id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from wishlist">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (can_delete_wishlist_entry($item, $username, $isAdmin)): ?>
                            <div class="collapse" id="editGeneral<?= (int)$item['id'] ?>">
                                <div class="card-body border-top">
                                    <form method="post" action="wishlist.php">
                                        <input type="hidden" name="form_type" value="edit_general">
                                        <input type="hidden" name="entry_id" value="<?= (int)$item['id'] ?>">
                                        <div class="row g-3">
                                            <div class="col-md-5">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="name" class="form-control" maxlength="60" required value="<?= htmlspecialchars($item['name']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Artist</label>
                                                <input type="text" name="artist" class="form-control" maxlength="60" value="<?= htmlspecialchars($item['artist'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">YouTube link</label>
                                                <input type="url" name="youtube_link" class="form-control" value="<?= htmlspecialchars($item['youtube_link'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm mt-2">Save changes</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($generalTotalPages > 1): ?>
                    <nav class="d-flex justify-content-center mt-3">
                        <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= wishlist_url(['page' => $page - 1]) ?>">Previous</a>
                            </li>
                            <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $generalTotalPages ?></span></li>
                            <li class="page-item <?= $page >= $generalTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= wishlist_url(['page' => $page + 1]) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- SONGBOOK TAB -->
        <div class="tab-pane fade <?= $activeTab === 'songbook' ? 'show active' : '' ?>" id="songbook-pane" role="tabpanel">

            <form method="get" action="wishlist.php" class="row g-2 mb-4 align-items-end">
                <input type="hidden" name="tab" value="songbook">
                <div class="col-md-4">
                    <label class="form-label">Songbook</label>
                    <select name="songbook_id" class="form-select">
                        <option value="">Choose a songbook...</option>
                        <?php foreach ($accessibleSongbooks as $sb): ?>
                            <option value="<?= (int)$sb['id'] ?>" <?= $selectedSongbookId === (int)$sb['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sb['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="song or artist">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Artist</label>
                    <input type="text" name="artist" class="form-control" value="<?= htmlspecialchars($filterArtist) ?>" placeholder="artist">
                </div>
                <div class="col-md-2">
                    <label class="form-label">User</label>
                    <input type="text" name="user" class="form-control" value="<?= htmlspecialchars($filterUser) ?>" placeholder="username">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">View</button>
                    <?php if ($hasActiveFilters): ?>
                        <a href="wishlist.php?tab=songbook&songbook_id=<?= (int)$selectedSongbookId ?>" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!$selectedSongbook): ?>
                <p class="text-muted">Choose a songbook above to see and add wishlist requests for it.</p>
            <?php else: ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#songbookAddForm">
                            <i class="bi bi-plus-circle me-1"></i> Add a song to "<?= htmlspecialchars($selectedSongbook['name']) ?>"'s wishlist
                        </button>
                    </div>
                    <div class="collapse" id="songbookAddForm">
                        <div class="card-body">
                            <form method="post" action="wishlist.php?tab=songbook&songbook_id=<?= (int)$selectedSongbookId ?>" id="songbookAddFormEl">
                                <input type="hidden" name="form_type" value="songbook">
                                <input type="hidden" name="songbook_id" value="<?= (int)$selectedSongbookId ?>">
                                <input type="hidden" name="existing_song_id" id="existing_song_id" value="">

                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label">Name</label>
                                        <input type="text" name="name" id="songbook_name" class="form-control" maxlength="60" required autocomplete="off">
                                        <div id="existingSongResults" class="list-group mt-1"></div>
                                        <div id="songMatchStatus" class="form-text"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Artist</label>
                                        <input type="text" name="artist" id="songbook_artist" class="form-control" maxlength="60">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">YouTube link</label>
                                        <input type="url" name="youtube_link" id="songbook_youtube_link" class="form-control">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Add to wishlist</button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if (!$canVoteOnSelectedSongbook && $userId): ?>
                    <p class="text-muted small"><i class="bi bi-info-circle"></i> Only members of this songbook's group can vote here.</p>
                <?php endif; ?>

                <?php if (empty($songbookEntries)): ?>
                    <p class="text-muted">No wishlist requests for this songbook yet.</p>
                <?php else: ?>
                    <?php foreach ($songbookEntries as $item): ?>
                        <div class="card wishlist-card mb-2">
                            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                                    <?php if (!empty($item['artist'])): ?>
                                        <span class="text-muted">- <?= htmlspecialchars($item['artist']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['is_existing_song']): ?>
                                        <span class="badge bg-info text-dark ms-2">Already in database</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-2">New song</span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['youtube_link'])): ?>
                                        <a href="<?= htmlspecialchars($item['youtube_link']) ?>" target="_blank" rel="noopener" class="ms-2">
                                            <i class="bi bi-youtube text-danger"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($item['username'])): ?>
                                        <div class="text-muted small">Requested by <?= htmlspecialchars($item['username']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button"
                                            class="btn btn-sm <?= $item['liked_by_me'] ? 'btn-primary' : 'btn-outline-primary' ?> wishlist-like-btn"
                                            data-type="songbook"
                                            data-id="<?= (int)$item['id'] ?>"
                                            <?= $canVoteOnSelectedSongbook ? '' : 'disabled title="Only this songbook\'s group members can vote"' ?>>
                                        <i class="bi bi-hand-thumbs-up"></i>
                                        <span class="like-count"><?= (int)$item['likes'] ?></span>
                                    </button>

                                    <?php if ($item['is_existing_song']): ?>
                                        <form method="post" action="wishlist.php?tab=songbook&songbook_id=<?= (int)$selectedSongbookId ?>" class="d-inline">
                                            <input type="hidden" name="form_type" value="attach_existing">
                                            <input type="hidden" name="entry_id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" <?= $userId ? '' : 'disabled title="Log in to add"' ?>>Add to songbook</button>
                                        </form>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-success"
                                           href="song.php?new=1&title=<?= urlencode($item['name']) ?>&artist=<?= urlencode($item['artist'] ?? '') ?>&youtube_link=<?= urlencode($item['youtube_link'] ?? '') ?>&wishlist_song_id=<?= (int)$item['wishlist_song_id'] ?>&songbook_id=<?= (int)$selectedSongbookId ?>">
                                            Create this song
                                        </a>
                                    <?php endif; ?>
                                    <?php if (can_delete_wishlist_entry($item, $username, $isAdmin)): ?>
                                        <?php if (!$item['is_existing_song']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editSongbook<?= (int)$item['id'] ?>" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?>
                                        <form method="post" action="wishlist.php?tab=songbook&songbook_id=<?= (int)$selectedSongbookId ?>" class="d-inline" onsubmit="return confirm('Remove this song from the songbook\'s wishlist?');">
                                            <input type="hidden" name="form_type" value="delete_songbook">
                                            <input type="hidden" name="entry_id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from wishlist">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (can_delete_wishlist_entry($item, $username, $isAdmin) && !$item['is_existing_song']): ?>
                                <div class="collapse" id="editSongbook<?= (int)$item['id'] ?>">
                                    <div class="card-body border-top">
                                        <form method="post" action="wishlist.php?tab=songbook&songbook_id=<?= (int)$selectedSongbookId ?>">
                                            <input type="hidden" name="form_type" value="edit_songbook">
                                            <input type="hidden" name="entry_id" value="<?= (int)$item['id'] ?>">
                                            <div class="row g-3">
                                                <div class="col-md-5">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" name="name" class="form-control" maxlength="60" required value="<?= htmlspecialchars($item['name']) ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Artist</label>
                                                    <input type="text" name="artist" class="form-control" maxlength="60" value="<?= htmlspecialchars($item['artist'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">YouTube link</label>
                                                    <input type="url" name="youtube_link" class="form-control" value="<?= htmlspecialchars($item['youtube_link'] ?? '') ?>">
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm mt-2">Save changes</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($songbookTotalPages > 1): ?>
                        <nav class="d-flex justify-content-center mt-3">
                            <ul class="pagination">
                                <li class="page-item <?= $sbPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= wishlist_url(['sb_page' => $sbPage - 1]) ?>">Previous</a>
                                </li>
                                <li class="page-item disabled"><span class="page-link">Page <?= $sbPage ?> of <?= $songbookTotalPages ?></span></li>
                                <li class="page-item <?= $sbPage >= $songbookTotalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= wishlist_url(['sb_page' => $sbPage + 1]) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.wishlist-like-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (btn.disabled) return;
        fetch('php/subpage/wishlist_like.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: btn.dataset.type, id: parseInt(btn.dataset.id, 10) })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.error) {
                alert(data.error);
                return;
            }
            btn.querySelector('.like-count').textContent = data.likes;
            btn.classList.toggle('btn-primary', data.liked);
            btn.classList.toggle('btn-outline-primary', !data.liked);
        })
        .catch(function () {
            alert('Could not register your vote.');
        });
    });
});

(function () {
    var nameInput = document.getElementById('songbook_name');
    if (!nameInput) return;

    var artistInput = document.getElementById('songbook_artist');
    var resultsBox = document.getElementById('existingSongResults');
    var statusBox = document.getElementById('songMatchStatus');
    var existingSongIdInput = document.getElementById('existing_song_id');

    function clearMatch() {
        existingSongIdInput.value = '';
        artistInput.readOnly = false;
        statusBox.innerHTML = '';
    }

    var searchTimer = null;
    nameInput.addEventListener('input', function () {
        clearMatch();
        resultsBox.innerHTML = '';
        var query = nameInput.value.trim();
        if (query.length < 2) {
            return;
        }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            fetch('php/subpage/wishlist_song_search.php?search=' + encodeURIComponent(query))
                .then(function (res) { return res.json(); })
                .then(function (songs) {
                    resultsBox.innerHTML = '';
                    if (songs.length === 0) {
                        statusBox.innerHTML = '<span class="text-muted">No matching song found - this will be added as a new song request.</span>';
                        return;
                    }
                    statusBox.innerHTML = '<span class="text-muted">Found in the database - click a match if it\'s the same song, otherwise keep typing to add it as new:</span>';
                    songs.forEach(function (song) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.textContent = song.title + (song.artist ? ' - ' + song.artist : '');
                        btn.addEventListener('click', function () {
                            existingSongIdInput.value = song.id;
                            nameInput.value = song.title;
                            artistInput.value = song.artist || '';
                            artistInput.readOnly = true;
                            resultsBox.innerHTML = '';
                            statusBox.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Linked to existing song in the database.</span> ' +
                                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="clearMatchBtn">Use as new song instead</button>';
                            document.getElementById('clearMatchBtn').addEventListener('click', clearMatch);
                        });
                        resultsBox.appendChild(btn);
                    });
                });
        }, 300);
    });
})();
</script>

<?php include("php/footer.php"); ?>
