<?php
session_start();
include('php/db.php');
include('php/auth.php');
include('php/data.php');
$website = "songbooks";
include_once('php/functions.php');
include("php/header.php");
include('php/navigation.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);




if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $songbook = get_songbook($mysqli, $id);

    if (!$songbook) {
        exit("Songbook not found.");
    }

    // --- Visibility check ---
    $userId = $_SESSION['id'] ?? null;
    $userGroupId = $_SESSION['group_id'] ?? null;

    if (empty($_SESSION['admin_view'])) { // admin override off
        if (
            // Guest user: only see is_public = 2
            (!$userId && $songbook['is_public'] != 2)
            ||
            // Logged-in user
            ($userId && (
                // Group-access songbooks (is_public = 1)
                ($songbook['is_public'] == 1 &&
                    // either not in same group OR not admin
                    (!is_null($userGroupId) && $userGroupId != $songbook['group_id'] && !in_array($userId, $songbook['admins']))
                )
                ||
                // Private songbooks (is_public = 0)
                ($songbook['is_public'] == 0 && !in_array($userId, $songbook['admins']))
            ))
        ) {
            exit("You do not have permission to view this songbook.");
        }
    }


    $name = $songbook["name"] ?? '';
    $description = $songbook["description"] ?? '';

    $groupName = null;
    if (!empty($songbook["group_id"])) {
        $stmt = $mysqli->prepare("SELECT name FROM group_list WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $songbook["group_id"]);
            $stmt->execute();
            $stmt->bind_result($groupName);
            $stmt->fetch();
            $stmt->close();
        }
    }

    $groupHtml = $groupName !== '' ? "<span class=\"org-badge\">".htmlspecialchars($groupName)."</span>" : '';

    $jsCategories = [];
    if (!empty($songbook["songs_categories"])) {
        foreach ($songbook["songs_categories"] as $category) {
            $cat = [
                "id" => $category["id"] ?? null,
                "name" => $category["name"] ?? '',
                "songs" => []
            ];
            if (!empty($category["songs"])) {
                foreach ($category["songs"] as $songId) {
                    $song = get_song_title($mysqli, $songId);
                    $user = get_user_by_username($mysqli, $song['owner'] ?? '');
                    $cat["songs"][] = [
                        "id" => $song["id"] ?? null,
                        "title" => $song["title"] ?? '',
                        "artist" => $song["artist"] ?? '',
                        "is_fixed" => $song["is_fixed"] ?? 0,
                        "owner" => [
                            "id" => $user ? (int)$user["id"] : null,
                            "name" => $user["name"] ?? null,
                            "username" => $song['owner'] ?? ''
                        ]
                    ];
                }
            }
            $jsCategories[] = $cat;
        }
    }

    $jsCategorySelect = $songbook['categories'] ?? [];

} else {
    exit("Missing or invalid songbook ID.");
}

?>

<div class="container-fluid py-4">
  <div class="p-4 border rounded bg-white">
    <!-- Editable Header -->
    <div class="mb-4">
      <div class="row align-items-end">
        <div class="col-md-6">
          <div class="d-flex align-items-center" id="songbook-name-wrapper">
            <h3 class="me-2 mb-0" id="songbook-name-text"><?php echo htmlspecialchars($name); ?></h3>
          </div>
          <input type="text" class="form-control form-control-lg d-none" id="songbook-name-input" value="<?php echo htmlspecialchars($name); ?>">
        </div>
        <div class="col-md-4">
            <?php echo $groupHtml; ?>
        </div>
        <?php if (
            (isset($_SESSION["id"]) && in_array((string)$_SESSION["id"], $songbook["admins"])) ||
            (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true)
        ): ?>
        <div class="col-md-2 text-end mt-3 mt-md-0">
            <button id="saveSongbookBtn" class="btn btn-success">💾 Save</button>
        </div>
        <?php endif; ?>

      </div>
      <div class="mt-3">
        <div class="d-flex align-items-center" id="description-wrapper">
          <p class="me-2 mb-0" id="description-text"><?php echo htmlspecialchars($description); ?></p>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Categories & Songs - Main Section -->
      <div class="col-lg-9">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            🗂️ Categories & Songs
          </div>
          <div class="card-body" id="category-container">
            <!-- JS will render categories and songs here -->
          </div>
        </div>
      </div>

        <?php if (
            (isset($_SESSION["id"]) && in_array((string)$_SESSION["id"], $songbook["admins"])) ||
            (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true)
        ): ?>
      <!-- Actions Sidebar -->
      <div class="col-lg-3 mt-4 mt-lg-0">
        
        <!-- Actions Sidebar -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-light">
            ⚙️ Actions
          </div>
          <div class="card-body">
            <p class="text-muted small">Select categories to apply batch actions</p>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="select-all-categories-checkbox">
              <label class="form-check-label" for="select-all-categories-checkbox">
                Select All Categories
              </label>
            </div>

            <button id="render-categories-btn" class="btn btn-primary w-100 mt-2">
              🖨️ Render All Categories
            </button>
          </div>

        </div>

        <!-- Version Lock Sidebar -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-light">
            🔒 Version Locks
          </div>
          <div class="card-body">
            <button id="lock-songbook-btn" class="btn btn-outline-danger btn-sm w-100 mb-3 d-none">
              🔒 Lock Current State
            </button>
            <div id="lock-history-list">
              <p class="text-muted small mb-0">Checking lock status…</p>
            </div>
          </div>
        </div>

        <!-- Dev Tools Sidebar -->
        <div class="card shadow-sm">
          <div class="card-header bg-light">
            🧰 Dev Tools
          </div>
          <div class="card-body">
            <p class="text-muted small">Tools for development and layout debugging.</p>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="render-numbering-checkbox" checked>
              <label class="form-check-label" for="render-numbering-checkbox">
                Category Number
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="render-bw-checkbox">
              <label class="form-check-label" for="render-bw-checkbox">
                Black and White
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="allow-manual-ordering-checkbox">
              <label class="form-check-label" for="allow-manual-ordering-checkbox">
                Allow manual Ordering
              </label>
              <div class="form-text">Also shows debug lines and marks locked songs as non-movable.</div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="render-lock-name-checkbox" checked>
              <label class="form-check-label" for="render-lock-name-checkbox">
                Show Lock Name
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="transpose-capo-zero-checkbox">
              <label class="form-check-label" for="transpose-capo-zero-checkbox">
                Transpose to Capo 0
              </label>
            </div>

            <hr>
            <p class="text-muted small mb-2"><strong>Extra Display</strong></p>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="extra-difficulty-checkbox">
              <label class="form-check-label" for="extra-difficulty-checkbox">
                Difficulty
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="extra-tags-checkbox">
              <label class="form-check-label" for="extra-tags-checkbox">
                Tags
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="extra-instruments-checkbox">
              <label class="form-check-label" for="extra-instruments-checkbox">
                Instruments
              </label>
            </div>


            <div id="save-layout-wrapper" style="margin-top: 1rem;">
              <button id="save-layout-btn" class="btn btn-success" disabled>💾 Save Layout</button>
            </div>

              <button id="export-pdf-btn" class="btn btn-sm btn-primary mt-3" disabled>
                📄 Export to PDF
              </button>

            
          </div>
        </div>

        <!-- Reordering Info Sidebar -->
        <div class="card shadow-sm mt-4">
          <div class="card-header bg-light">
            ℹ️ Information
          </div>
          <div class="card-body" style="font-size: 0.9em;">
            <h6>🔁 Swap Two Songs</h6>
            <ul>
              <li>Click a song to select it.</li>
              <li>Click another song to swap their positions.</li>
            </ul>

            <h6>📌 Insert After</h6>
            <ul>
              <li>Click a song to select it.</li>
              <li><strong>Right-click</strong> another song to insert the first one after it.</li>
            </ul>

            <h6>⏪ Undo</h6>
            <ul>
              <li>Press <kbd>Ctrl</kbd> + <kbd>Z</kbd> to undo the last change.</li>
            </ul>

            <h6>💾 Saving + Loading</h6>
            <ul>
              <li>Click <strong>💾 Save Layout</strong> to persist the current order.</li>
              <li><strong>🖨️ Render Layout</strong> loads the saved order if one exists (any newly-added songs are appended after it), otherwise it auto-generates one.</li>
            </ul>

            <h6>⚙️ Auto Layout</h6>
            <ul>
              <li>Groups songs by artist.</li>
              <li>Keeps author groups on the same page.</li>
              <li><strong>Remember</strong> to check if multi page songs are ordered correctly</li>
            </ul>
          </div>
        </div>


        

        <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Song Modal -->
<div class="modal fade" id="addSongModal" tabindex="-1" aria-labelledby="addSongModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addSongModalLabel">Add Song to Songbook</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <!-- Search row: input + search button side by side -->
        <div class="mb-3 d-flex">
          <input type="text" id="searchInput" class="form-control me-2" placeholder="Search songs...">
          <button id="searchBtn" class="btn btn-primary">Search</button>
        </div>

        <!-- Results container -->
        <div id="searchResults" style="max-height: 300px; overflow-y: auto;"></div>

      </div>
      <div class="modal-footer d-flex justify-content-between align-items-center">
        <!-- Category select smaller width -->
        <select id="categorySelect" class="form-select w-auto me-2" style="min-width: 180px;" required>
          <!-- JS will populate -->
        </select>

        <div class="ms-auto">
          <button id="addSongToCategoryBtn" class="btn btn-success me-2" disabled>Add to Category</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="newCategoryName" class="form-control" placeholder="Category name" required>
      </div>
      <div class="modal-footer">
        <button id="createCategoryBtn" class="btn btn-success">Create</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Adding scripts -->

<script src="js/songbook_render_preview.js?v=<?= filemtime('js/songbook_render_preview.js') ?>"></script>
<script src="js/song_render.js?v=<?= filemtime('js/song_render.js') ?>"></script>
<script src="js/song_ordering.js?v=<?= filemtime('js/song_ordering.js') ?>"></script>

<!-- html2canvas for rendering DOM to image -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- jsPDF core library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>



<script>

  let currentCategory = null;
  let orderedIds = [];
  let selectedSongId = null;
  let undoStack = [];

  const selectedCategoryIds = [];



  const songbook = {
    songs_categories: <?= json_encode(array_map(function($cat) use ($mysqli) {
      return [
        "id" => $cat["id"],
        "name" => $cat["name"],
        "songs" => array_map(function($songId) use ($mysqli) {
          $song = get_song_title($mysqli, $songId);
          $user = get_user_by_username($mysqli, $song['owner']);
          return [
            "id" => $song["id"],
            "title" => $song["title"],
            "artist" => $song["artist"],
            "is_fixed" => isset($song["is_fixed"]) ? (bool)$song["is_fixed"] : false,
            "owner" => [
              "id" => $user ? $user["id"] : null,
              "username" => $song["owner"],
              "name" => $user ? $user["name"] : $song["owner"]
            ]
          ];

        }, $cat["songs"])
      ];
    }, $songbook["songs_categories"])) ?>,
    id: <?= (int)$songbook["id"] ?>,
    name: <?= json_encode($songbook["name"]) ?>,
    description: <?= json_encode($songbook["description"]) ?>,
    admins: <?= json_encode($songbook["admins"]) ?>,
    group_id: <?= $songbook["group_id"] ? (int)$songbook["group_id"] : 'null' ?>,
    is_public: <?= $songbook["is_public"] ? 'true' : 'false' ?>
  };

  const currentUserId = <?= isset($_SESSION["id"]) ? json_encode((string)$_SESSION["id"]) : 'null' ?>;
  const isAdminView = <?= isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true ? 'true' : 'false' ?>;

  function isCurrentUserAdmin() {
  return isAdminView || (currentUserId && songbook.admins.includes(currentUserId));
}

async function refreshLockStatus() {
  const lockBtn = document.getElementById('lock-songbook-btn');
  const historyList = document.getElementById('lock-history-list');
  if (!lockBtn || !historyList) return;

  const admin = isCurrentUserAdmin();
  lockBtn.classList.toggle('d-none', !admin);

  try {
    const res = await fetch(`php/get_songbook_lock_status.php?songbook_id=${songbook.id}`);
    const data = await res.json();

    if (!data.success) {
      historyList.innerHTML = '<p class="text-danger small mb-0">Unable to load lock status.</p>';
      return;
    }

    if (!data.history.length) {
      historyList.innerHTML = '<p class="text-muted small mb-0">Not locked yet. Anyone with access can reorder any song.</p>';
      return;
    }

    // Newest first
    const rows = [...data.history].reverse().map(lock => {
      const lockedAt = new Date(lock.locked_at.replace(' ', 'T')).toLocaleString();
      const isActive = lock.unlocked_at === null;

      let meta = `locked by ${lock.locked_by_name || 'unknown'} · ${lockedAt}`;
      if (!isActive) {
        const unlockedAt = new Date(lock.unlocked_at.replace(' ', 'T')).toLocaleString();
        meta += `<br>unlocked ${unlockedAt}`;
      }

      let actionBtnHtml = '';
      if (admin) {
        actionBtnHtml = isActive
          ? `<button class="btn btn-outline-secondary btn-sm mt-1 unlock-lock-btn" data-lock-id="${lock.id}">Unlock</button>`
          : `<button class="btn btn-outline-danger btn-sm mt-1 relock-lock-btn" data-lock-id="${lock.id}">Re-lock</button>`;
      }

      return `
        <div class="border rounded p-2 mb-2 ${isActive ? '' : 'bg-light text-muted'}">
          <div><strong>${lock.name}</strong></div>
          <div class="small">${meta}</div>
          ${actionBtnHtml}
          <button class="btn btn-outline-primary btn-sm mt-1 print-lock-btn" data-lock-id="${lock.id}">🖨️ Print This Lock</button>
        </div>
      `;
    });

    historyList.innerHTML = rows.join('');

    historyList.querySelectorAll('.unlock-lock-btn').forEach(btn => {
      btn.addEventListener('click', () => unlockSongbookLock(btn.dataset.lockId));
    });
    historyList.querySelectorAll('.relock-lock-btn').forEach(btn => {
      btn.addEventListener('click', () => relockSongbookLock(btn.dataset.lockId));
    });
    historyList.querySelectorAll('.print-lock-btn').forEach(btn => {
      btn.addEventListener('click', () => printSongbookLock(btn.dataset.lockId));
    });
  } catch (err) {
    console.error('Failed to load lock status', err);
    historyList.innerHTML = '<p class="text-danger small mb-0">Unable to load lock status.</p>';
  }
}

// Locking only ever freezes categories that already have a saved layout row
// (see functions.php get_song_lock_ownership) - a category you've only ever
// previewed with "Render Layout" but never explicitly saved would otherwise
// never get captured by any lock, ever, even retroactively. So before locking,
// make sure every category with songs actually has a saved layout, generating
// one with the same auto-arrange logic "Render Layout" uses if it doesn't.
async function ensureCategoryHasSavedLayout(category) {
  if (category.hasLoadOrderButton || category.songs.length === 0) return;

  const ids = category.songs.map(s => s.id);
  const songs = await fetchSongsByIds(ids);
  const ordered = orderSongs(songs, measurer);
  const generatedIds = getOrderedIds(ordered);

  const res = await fetch('php/save_layout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      songbook_id: songbook.id,
      category_id: category.id,
      ordered_ids: generatedIds
    })
  });
  const data = await res.json();
  if (data.success) {
    category.hasLoadOrderButton = true;
  } else {
    console.warn(`Failed to auto-save layout for category "${category.name}" before locking:`, data.message);
  }
}

document.getElementById('lock-songbook-btn')?.addEventListener('click', async () => {
  const name = prompt('Name this locked version (e.g. "Summer 25"):');
  if (!name || !name.trim()) return;

  try {
    // Commit song membership first (same reasoning as Save Layout - a layout
    // can only reference songs the songbook actually persists as members).
    const songbookSaveResult = await saveSongbookToServer();
    if (!songbookSaveResult.success) {
      alert('Failed to save songbook: ' + (songbookSaveResult.error || songbookSaveResult.message));
      return;
    }

    for (const category of songbook.songs_categories) {
      if (category.id === 0) continue; // Uncategorized is never locked
      await ensureCategoryHasSavedLayout(category);
    }

    const res = await fetch('php/lock_songbook.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ songbook_id: songbook.id, name: name.trim() })
    });
    const data = await res.json();
    if (data.success) {
      alert(`Locked as "${name.trim()}". Songs already in each category's layout can no longer be reordered or removed.`);
      refreshLockStatus();
    } else {
      alert('Failed to lock songbook: ' + data.message);
    }
  } catch (err) {
    console.error('Error locking songbook', err);
    alert('Error locking songbook.');
  }
});

async function unlockSongbookLock(lockId) {
  if (!confirm('Unlock this version? Songs it froze will become editable again (unless another active lock still covers them).')) return;

  try {
    const res = await fetch('php/unlock_songbook.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ songbook_id: songbook.id, lock_id: lockId })
    });
    const data = await res.json();
    if (data.success) {
      refreshLockStatus();
    } else {
      alert('Failed to unlock: ' + data.message);
    }
  } catch (err) {
    console.error('Error unlocking songbook', err);
    alert('Error unlocking songbook.');
  }
}

async function relockSongbookLock(lockId) {
  if (!confirm('Re-lock this version? Its original songs will be frozen again exactly as they were - anything added since it was unlocked stays untouched.')) return;

  try {
    const res = await fetch('php/relock_songbook.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ songbook_id: songbook.id, lock_id: lockId })
    });
    const data = await res.json();
    if (data.success) {
      refreshLockStatus();
    } else {
      alert('Failed to re-lock: ' + data.message);
    }
  } catch (err) {
    console.error('Error re-locking songbook', err);
    alert('Error re-locking songbook.');
  }
}

refreshLockStatus();

</script>



<script>
// Inject PHP data safely
const categorySelectOptions = <?php echo json_encode($jsCategorySelect, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;


function createSongListItem(song, categoryId) {
  const li = document.createElement('li');
  li.className = 'list-group-item d-flex justify-content-between align-items-center';
  // Apply orange background if is_fixed is not true
  if (!song.is_fixed) {
    li.style.backgroundColor = 'orange';
  }

  // Left: Title + artist
  const leftDiv = document.createElement('div');
  leftDiv.textContent = song.title + (song.artist ? " – " + song.artist : "");
  li.appendChild(leftDiv);

  // Right: Owner badge and optional remove button
  const rightDiv = document.createElement('div');
  rightDiv.className = 'd-flex align-items-center gap-2';

  if (song.owner && song.owner.id && song.owner.name) {
    const a = document.createElement('a');
    a.href = `user.php?id=${song.owner.id}`;
    a.className = 'badge bg-primary text-decoration-none';
    a.textContent = song.owner.name;
    rightDiv.appendChild(a);
  } else if (song.owner && song.owner.name) {
    const span = document.createElement('span');
    span.className = 'badge bg-secondary';
    span.textContent = song.owner.name;
    rightDiv.appendChild(span);
  }

  if (isCurrentUserAdmin()) {
    const removeBtn = document.createElement('button');
    removeBtn.className = 'btn btn-sm btn-outline-danger';
    removeBtn.textContent = '❌';
    removeBtn.title = 'Remove this song';
    removeBtn.onclick = () => {
      const category = songbook.songs_categories.find(c => c.id === categoryId);
      if (!category) return;

      category.songs = category.songs.filter(s => s.id !== song.id);
      li.remove(); // <- This removes the item from the visible list
    };

    rightDiv.appendChild(removeBtn);
  }

  li.appendChild(rightDiv);
  return li;
}



const categoryExpandedState = {};


function renderCategories() {
    const container = document.getElementById('category-container');
    container.innerHTML = '';

    songbook.songs_categories.forEach(category => {
        const catDiv = document.createElement('div');
        catDiv.className = 'mb-5 category-block';
        catDiv.dataset.categoryId = category.id;
        if (selectedCategoryIds.includes(category.id)) {
            catDiv.classList.add('selected-category');
        }


        const headerDiv = document.createElement('div');
        headerDiv.className = 'mb-2 d-flex justify-content-between align-items-center';

        // Toggle button (arrow) for collapsing
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn btn-sm btn-link p-0 me-2';
        toggleBtn.style.userSelect = 'none';

        const isExpanded = categoryExpandedState[category.id] === true;

        toggleBtn.textContent = isExpanded ? '▼' : '▶';
        toggleBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');

        // Category title container
        const h5 = document.createElement('h5');
        h5.className = 'mb-0 d-flex align-items-center';

        h5.style.cursor = 'pointer'; // Show pointer on hover

        h5.addEventListener('click', (e) => {
          // Avoid triggering toggle button clicks (▶/▼)
          if (e.target === toggleBtn) return;

          const catId = category.id;
          const index = selectedCategoryIds.indexOf(catId);

          if (index === -1) {
            selectedCategoryIds.push(catId);
            catDiv.classList.add('selected-category');
          } else {
            selectedCategoryIds.splice(index, 1);
            catDiv.classList.remove('selected-category');
          }

          updateRenderCategoriesUI();
        });

        const songCountSpan = document.createElement('span');
        songCountSpan.className = 'text-muted ms-2';
        songCountSpan.textContent = `(${category.songs.length} songs)`;
        songCountSpan.style.fontSize = '0.75em';
        songCountSpan.style.color = '#666';
        songCountSpan.style.display = isExpanded ? 'none' : 'inline';

        h5.appendChild(toggleBtn);
        h5.appendChild(document.createTextNode(category.name));
        h5.appendChild(songCountSpan);

        headerDiv.appendChild(h5);

        // Ensure headerDiv uses flex layout
        headerDiv.style.display = 'flex';
        headerDiv.style.alignItems = 'center';

        if (isCurrentUserAdmin()) {
            const btnContainer = document.createElement('div');
            btnContainer.className = 'd-flex align-items-center';

            // Single render button: loads the saved layout if one exists
            // (appending any newly-added songs after it), otherwise generates one.
            const renderBtn = document.createElement('button');
            renderBtn.className = 'btn btn-sm btn-outline-success';
            renderBtn.textContent = '🖨️ Render Layout';
            renderBtn.addEventListener('click', () => {
                handleRenderForCategory(category);
            });

            btnContainer.appendChild(renderBtn);

            // Check if a saved layout exists for this category, so the render
            // button knows whether to load or generate when clicked.
            fetch(`php/check_layout_exists.php?songbook_id=${songbook.id}&category_id=${category.id}`, {
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                category.hasLoadOrderButton = !!data.exists;
            })
            .catch(err => {
                console.error('Failed to check layout existence:', err);
                category.hasLoadOrderButton = false; // safe fallback
            });

            // Uncategorized (id 0) is an implicit bucket and can't be removed.
            if (category.id !== 0) {
                const removeBtn = document.createElement('button');
                removeBtn.className = 'btn btn-sm btn-outline-danger ms-2';
                removeBtn.textContent = '🗑️ Remove';
                removeBtn.addEventListener('click', () => {
                    removeCategory(category);
                });
                btnContainer.appendChild(removeBtn);
            }

            headerDiv.appendChild(btnContainer);
        }


        catDiv.appendChild(headerDiv);

        const ul = document.createElement('ul');
        ul.className = 'list-group';
        ul.style.display = isExpanded ? 'block' : 'none';

        category.songs.forEach(song => {
            const li = createSongListItem(song, category.id);
            ul.appendChild(li);
        });

        catDiv.appendChild(ul);
        container.appendChild(catDiv);

        // Collapse/expand logic
        toggleBtn.addEventListener('click', () => {
            const currentlyExpanded = categoryExpandedState[category.id] === true;
            categoryExpandedState[category.id] = !currentlyExpanded;

            if (currentlyExpanded) {
                ul.style.display = 'none';
                toggleBtn.textContent = '▶';
                toggleBtn.setAttribute('aria-expanded', 'false');
                songCountSpan.style.display = 'inline';
            } else {
                ul.style.display = 'block';
                toggleBtn.textContent = '▼';
                toggleBtn.setAttribute('aria-expanded', 'true');
                songCountSpan.style.display = 'none';
            }
        });
    });

    if (isCurrentUserAdmin()) {
        const buttonsDiv = document.createElement('div');
        buttonsDiv.className = 'text-end';

        const addSongBtn = document.createElement('button');
        addSongBtn.className = 'btn btn-outline-secondary me-2';
        addSongBtn.setAttribute('data-bs-toggle', 'modal');
        addSongBtn.setAttribute('data-bs-target', '#addSongModal');
        addSongBtn.textContent = '➕ Add Song';

        const addCategoryBtn = document.createElement('button');
        addCategoryBtn.id = 'addCategoryBtn';
        addCategoryBtn.className = 'btn btn-outline-primary';
        addCategoryBtn.textContent = '➕ Add Category';

        buttonsDiv.appendChild(addSongBtn);
        buttonsDiv.appendChild(addCategoryBtn);

        container.appendChild(buttonsDiv);
    }
}

// Removes a category from the songbook. Its songs are moved to Uncategorized
// rather than deleted, and any saved layouts for this category id are left
// untouched in the database - not deleted - in case the category is wanted
// again later. Purely in-memory until "💾 Save" is clicked, same as Add Category.
function removeCategory(category) {
    if (!confirm(`Remove category "${category.name}"? Its songs will be moved to Uncategorized. This isn't persisted until you click "💾 Save".`)) {
        return;
    }

    let uncategorized = songbook.songs_categories.find(cat => cat.id === 0);
    if (!uncategorized) {
        uncategorized = { id: 0, name: 'Uncategorized', songs: [] };
        songbook.songs_categories.push(uncategorized);
    }
    uncategorized.songs.push(...category.songs);

    songbook.songs_categories = songbook.songs_categories.filter(cat => cat.id !== category.id);

    // Drop the removed category's stale id from the selection too, otherwise
    // it lingers in selectedCategoryIds and throws off "Select All" (its count
    // no longer matches the now-shorter category list) and any future render.
    const selIndex = selectedCategoryIds.indexOf(category.id);
    if (selIndex !== -1) {
        selectedCategoryIds.splice(selIndex, 1);
    }

    const option = document.querySelector(`#categorySelect option[value="${category.id}"]`);
    if (option) option.remove();

    renderCategories();
    updateRenderCategoriesUI();
}



function populateCategorySelect() {
    const select = document.getElementById('categorySelect');
    select.innerHTML = '<option value="">Select Category</option>';
    for (const [id, name] of Object.entries(categorySelectOptions)) {
        const option = document.createElement('option');
        option.value = id;
        option.textContent = name;
        select.appendChild(option);
    }
}

// Initial render calls
renderCategories();
populateCategorySelect();
updateRenderCategoriesUI();

// Your existing event listeners for search and add song:

function performSearch() {
  const query = document.getElementById('searchInput').value.trim();
  if (!query) return alert('Please enter a search term');

  fetch(`php/search_songs.php?search=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(songs => {
      const container = document.getElementById('searchResults');
      container.innerHTML = '';

      if (songs.length === 0) {
        container.innerHTML = '<p>No songs found.</p>';
        return;
      }

      const ul = document.createElement('ul');
      ul.classList.add('list-group');

      songs.forEach(song => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center';

        const radioId = `songRadio_${song.id}`;

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'selectedSong';
        radio.value = song.id;
        radio.id = radioId;
        radio.classList.add('me-2');

        const label = document.createElement('label');
        label.setAttribute('for', radioId);
        label.classList.add('mb-0', 'd-flex', 'align-items-center', 'w-100', 'cursor-pointer');

        label.appendChild(radio);

        const text = document.createElement('span');
        text.textContent = `${song.title} ${song.artist ? '– ' + song.artist : ''}`;

        label.appendChild(text);
        li.appendChild(label);

        ul.appendChild(li);
      });

      container.appendChild(ul);

      const addBtn = document.getElementById('addSongToCategoryBtn');
      addBtn.disabled = true;

      ul.querySelectorAll('input[name="selectedSong"]').forEach(radio => {
        radio.addEventListener('change', () => {
          addBtn.disabled = false;
        });
      });
    })
    .catch(err => {
      console.error('Search error:', err);
      alert('Error while searching songs.');
    });
}

// Search button
document.getElementById('searchBtn').addEventListener('click', performSearch);

// Enter key
document.getElementById('searchInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault(); // prevents accidental form submit
    performSearch();
  }
});



// Adding song to category
document.getElementById('addSongToCategoryBtn').addEventListener('click', () => {
  const selectedSongRadio = document.querySelector('input[name="selectedSong"]:checked');
  const categoryId = document.getElementById('categorySelect').value;

  if (!selectedSongRadio || !categoryId) {
    alert('Please select both a song and category.');
    return;
  }

  const songId = parseInt(selectedSongRadio.value);

  // Fetch the full song details from backend
  fetch(`php/get_song_by_id.php?id=${songId}`)
    .then(res => res.json())
    .then(song => {
      if (!song || !song.title) {
        alert('Could not load song details.');
        return;
      }

      const cat = songbook.songs_categories.find(c => c.id == categoryId);
      if (!cat) {
        alert('Invalid category');
        return;
      }

      // Prevent duplicates
      if (cat.songs.some(s => s.id === song.id)) {
        alert('Song already exists in this category.');
        return;
      }

      // Add song to category
      cat.songs.push(song);

      renderCategories(); // Re-render the categories & songs

      // Close modal
      bootstrap.Modal.getInstance(document.getElementById('addSongModal')).hide();
    })
    .catch(err => {
      console.error('Failed to fetch song data:', err);
      alert('Error adding song.');
    });
});


document.getElementById("addCategoryBtn").addEventListener("click", () => {
  const modal = new bootstrap.Modal(document.getElementById("addCategoryModal"));
  modal.show();
});

document.getElementById("createCategoryBtn").addEventListener("click", () => {
  const name = document.getElementById("newCategoryName").value.trim();
  if (!name) {
    alert("Please enter a category name.");
    return;
  }

  // Compute next ID (max existing id + 1)
  let nextId = 1;
  if (songbook.songs_categories.length > 0) {
    const maxId = Math.max(...songbook.songs_categories.map(c => parseInt(c.id)));
    nextId = maxId + 1;
  }

  // Push new category into in-memory structure
  const newCat = {
    id: nextId,
    name: name,
    songs: []
  };
  songbook.songs_categories.push(newCat);

  // Re-render categories
  renderCategories();

  // Add new option to category select (for Add Song modal)
  const select = document.getElementById("categorySelect");
  const opt = document.createElement("option");
  opt.value = newCat.id;
  opt.textContent = newCat.name;
  select.appendChild(opt);

  // Close modal + clear input
  bootstrap.Modal.getInstance(document.getElementById("addCategoryModal")).hide();
  document.getElementById("newCategoryName").value = "";
});


// Persists the current in-memory songbook (categories + song membership) to
// the database. Shared by the "Save" button and "Save Layout" - a saved
// layout references song ids, so those ids must actually be committed as
// members of the songbook first, otherwise a layout can end up pointing at
// songs the database doesn't actually consider part of the songbook (they'd
// render today from the in-memory state, but vanish on next page load/render
// since the membership was never persisted).
async function saveSongbookToServer() {
  const categories = songbook.songs_categories.map(cat => ({
    id: cat.id,
    name: cat.name
  }));

  const songs = [];
  songbook.songs_categories.forEach(cat => {
    cat.songs.forEach(song => {
      songs.push({
        category_id: cat.id,
        song_id: song.id
      });
    });
  });

  const payload = {
    id: songbook.id,
    name: songbook.name,
    description: songbook.description || '',
    admins: songbook.admins, // ✅ Already an array
    group_id: songbook.group_id || null,
    is_public: songbook.is_public ? 1 : 0,
    categories: categories,
    songs: songs
  };

  const res = await fetch('php/update_songbook.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  return res.json();
}

const saveBtn = document.getElementById('saveSongbookBtn');
if (saveBtn) {
  document.getElementById('saveSongbookBtn').addEventListener('click', () => {
    saveSongbookToServer()
    .then(response => {
      if (response.success) {
        alert('✅ Songbook saved successfully!');
      } else {
        alert('❌ Failed to save songbook: ' + (response.error || response.message));
      }
    })
    .catch(err => {
      console.error('Save error:', err);
      alert('❌ Error saving songbook.');
    });
  });

  document.getElementById("save-layout-btn").addEventListener("click", async () => {
    if (!currentCategory || !orderedIds.length || !songbook?.id) {
      alert("Missing songbook, category, or layout info.");
      return;
    }

    try {
      // Commit the songbook's current song membership first, so every id in
      // this layout is guaranteed to actually be a persisted member.
      const songbookSaveResult = await saveSongbookToServer();
      if (!songbookSaveResult.success) {
        alert("Failed to save songbook: " + (songbookSaveResult.error || songbookSaveResult.message));
        return;
      }

      const res = await fetch("php/save_layout.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          songbook_id: songbook.id,
          category_id: currentCategory.id,
          ordered_ids: orderedIds
        })
      });

      const data = await res.json();
      if (data.success) {
        alert("Layout saved successfully!");

        // From now on, the category's render button should load this
        // saved layout instead of generating a fresh one.
        currentCategory.hasLoadOrderButton = true;

      } else {
        alert("Failed to save layout: " + data.message);
      }
    } catch (err) {
      console.error("Error saving layout", err);
      alert("Error saving layout.");
    }
  });

}


// Generate button click
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('export-pdf-btn'); // or whatever your button id is
  if (btn) {
    btn.addEventListener('click', exportToPDF);
  }
});

// Keyboard shortcuts

document.addEventListener('keydown', (e) => {
  // ESC to clear selection
  if (e.key === 'Escape' || e.key === 'Esc') {
    if (selectedSongId !== null) {
      selectedSongId = null;
      document.querySelectorAll('.song-block.selected').forEach(el =>
        el.classList.remove('selected')
      );
    }
  }

// Ctrl + Z to undo last change
if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
  if (undoStack.length > 0) {
    orderedIds = undoStack.pop();
    beginRender(orderedIds, currentCategory); // ✅ pass only IDs
  }
}

});


document.getElementById('render-categories-btn')?.addEventListener('click', () => {
  const idsToRender = selectedCategoryIds.length === 0
    ? songbook.songs_categories.map(cat => cat.id)
    : selectedCategoryIds;
  renderMultiple(idsToRender);
});

document.getElementById('select-all-categories-checkbox')?.addEventListener('change', (e) => {
  selectedCategoryIds.length = 0;
  if (e.target.checked) {
    selectedCategoryIds.push(...songbook.songs_categories.map(cat => cat.id));
  }
  renderCategories();
  updateRenderCategoriesUI();
});

// Keeps the render button's label/state and the manual-ordering checkbox in
// sync with the current category selection. Called whenever selection changes.
function updateRenderCategoriesUI() {
  const renderBtn = document.getElementById('render-categories-btn');
  const selectAllCheckbox = document.getElementById('select-all-categories-checkbox');
  // These live in the admin-only sidebar - simply absent for non-admins.
  if (!renderBtn || !selectAllCheckbox) return;

  renderBtn.textContent = selectedCategoryIds.length === 0
    ? '🖨️ Render All Categories'
    : '🖨️ Render Selected Categories';

  selectAllCheckbox.checked = songbook.songs_categories.length > 0
    && selectedCategoryIds.length === songbook.songs_categories.length;
}





// FUNCTIONS


async function fetchSongsByIds(ids) {
    const res = await fetch('php/get_songs_by_ids_extended.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids })
    });
    return res.json();
}

function toRoman(num) {
  const romans = [
    ["M", 1000], ["CM", 900], ["D", 500], ["CD", 400],
    ["C", 100], ["XC", 90], ["L", 50], ["XL", 40],
    ["X", 10], ["IX", 9], ["V", 5], ["IV", 4], ["I", 1]
  ];
  let result = "";
  for (const [letter, value] of romans) {
    while (num >= value) {
      result += letter;
      num -= value;
    }
  }
  return result;
}

async function handleGenerateForCategory(category, clearPages = true) {
  undoStack.length = 0;

  const ids = category.songs.map(s => s.id);
  const songs = await fetchSongsByIds(ids);

  const ordered = orderSongs(songs, measurer);
  orderedIds = getOrderedIds(ordered);
  currentCategory = category;

  // Pass clearPages argument correctly
  return await beginRender(orderedIds, category, clearPages);
}

async function handleLoadForCategory(category, clearPages = true) {
  try {
    const songbookId = songbook.id;
    const categoryId = category.id;

    const response = await fetch(`php/load_layout.php?songbook_id=${songbookId}&category_id=${categoryId}`, {
      method: 'GET',
      credentials: 'same-origin'
    });

    const data = await response.json();

    if (data.success) {
      // Songs added to the category since this layout was saved aren't in
      // it at all - render them after the saved order, in the order they
      // currently appear on the category (i.e. the order they were added).
      const currentSongIds = category.songs.map(s => s.id);
      const loadedIdSet = new Set(data.ordered_ids);
      const missingIds = currentSongIds.filter(id => !loadedIdSet.has(id));

      orderedIds = [...data.ordered_ids, ...missingIds];
      currentCategory = category;

      // Pass clearPages argument correctly
      const pageCount = await beginRender(orderedIds, category, clearPages);
      undoStack.length = 0;
      return pageCount;

    } else {
      console.warn("Load layout failed:", data.message);
      alert("No saved layout found for this category.");
      return 0;  // no pages rendered
    }
  } catch (error) {
    console.error("Error loading layout:", error);
    alert("Error loading layout.");
    return 0;  // no pages rendered
  }
}

// Single entry point for the per-category "Render Layout" button: loads the
// saved layout if one exists, otherwise auto-generates one.
async function handleRenderForCategory(category, clearPages = true) {
  if (category.hasLoadOrderButton) {
    return await handleLoadForCategory(category, clearPages);
  } else {
    return await handleGenerateForCategory(category, clearPages);
  }
}




async function beginRender(orderedIds, category, clearPages = true) {
  const container = document.getElementById('a4-preview-container');
  if (clearPages) {
    container.innerHTML = ''; // Clear previous content if requested
  }

  const includeRoman = document.getElementById('render-numbering-checkbox')?.checked;
  const blackAndWhite = document.getElementById('render-bw-checkbox')?.checked || false;
  const allowClicking = document.getElementById('allow-manual-ordering-checkbox')?.checked || false;
  const debugMode = allowClicking; // debug lines are just a side effect of manual ordering now
  const showLockName = document.getElementById('render-lock-name-checkbox')?.checked || false;
  const transposeToCapoZero = document.getElementById('transpose-capo-zero-checkbox')?.checked || false;
  const showDifficulty = document.getElementById('extra-difficulty-checkbox')?.checked || false;
  const showTags = document.getElementById('extra-tags-checkbox')?.checked || false;
  const showInstruments = document.getElementById('extra-instruments-checkbox')?.checked || false;

  const categoryTitle = includeRoman
    ? `${toRoman(category.id)}. ${category.name}`
    : category.name;

  const songs = await fetchSongsByIds(orderedIds);

  // Locked-song info is needed both to mark locked songs as non-movable
  // (manual ordering) and to label pages with their lock name.
  let lockNames = {};
  if (allowClicking || showLockName) {
    try {
      const res = await fetch(`php/get_song_lock_info.php?songbook_id=${songbook.id}&category_id=${category.id}`);
      const data = await res.json();
      if (data.success) lockNames = data.lock_names || {};
    } catch (err) {
      console.error('Failed to load lock info', err);
    }
  }

  // Wait for rendering and get the total page count
  const pageCount = await renderA4Preview(songs, orderedIds, {
    debug: debugMode,
    category: categoryTitle,
    blackAndWhite: blackAndWhite,
    allowClicking: allowClicking,
    transposeToCapoZero: transposeToCapoZero,
    showDifficulty: showDifficulty,
    showTags: showTags,
    showInstruments: showInstruments,
    showLockName: showLockName,
    lockNames: lockNames,
    categoryId: category.id
  });

  console.log(`✅ Rendered ${pageCount} pages.`);

  const exportBtn = document.getElementById('export-pdf-btn');
  const saveLayoutBtn = document.getElementById('save-layout-btn');

  setTimeout(() => {
    const hasPages = container.querySelectorAll('.a4-page').length > 0;
    exportBtn.disabled = !hasPages;
    saveLayoutBtn.disabled = !hasPages;
  }, 100);

  return pageCount;
}






function getCategoryById(id) {
  return songbook.songs_categories.find(cat => cat.id === id);
}



async function renderMultiple(categoryIds) {
  // Clear container once before rendering all categories
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  for (const id of categoryIds) {
    const category = getCategoryById(id);
    if (!category) {
      console.warn(`Category with id ${id} not found.`);
      continue;
    }

    // Each category restarts its own page numbering at 1 (clearPages=false
    // just avoids wiping previously-rendered categories from the container).
    const pages = await handleRenderForCategory(category, false);
    console.log(`Category "${category.name}" rendered with ${pages} pages.`);
  }
}

// Prints just one lock's songs - but by rendering the ENTIRE songbook first
// (every category, each with its own normal page numbering starting at 1 -
// see renderMultiple) and then keeping only the pages that contain a song
// owned by that lock (see get_song_lock_ownership in functions.php - each
// song "belongs" to whichever lock first introduced it). This way every kept
// page still shows the exact page number it would have in a normal full
// render of that category, rather than a fresh 1..N specific to this printout.
//
// Pages pair up into physical double-sided sheets (page 1&2, 3&4, ... within
// each category, since every category's own page count is always even - each
// pads itself, see renderA4Preview - so pairing by flat index across the
// whole rendered book never crosses a category boundary mid-sheet). A sheet
// is dropped entirely only if NEITHER of its two pages matches the lock; if
// just one side matches, BOTH sides are kept with their real, unmodified
// content - not blanked - so e.g. a lock that begins on an even (back) page
// still prints together with the real odd (front) page right before it,
// continuing straight into the lock's content instead of a forced blank gap.
async function printSongbookLock(lockId) {
  const container = document.getElementById('a4-preview-container');

  try {
    const res = await fetch(`php/get_lock_render_data.php?songbook_id=${songbook.id}&lock_id=${lockId}`);
    const data = await res.json();

    if (!data.success) {
      alert('Failed to load lock data: ' + (data.message || 'unknown error'));
      return;
    }

    const matchesByCategory = {};
    Object.entries(data.matches || {}).forEach(([catId, ids]) => {
      matchesByCategory[catId] = new Set(ids.map(String));
    });

    if (!Object.values(matchesByCategory).some(set => set.size > 0)) {
      alert(`"${data.lock_name}" doesn't own any songs currently in this songbook.`);
      return;
    }

    // Full render of every category, same as "Render All Categories".
    const allCategoryIds = songbook.songs_categories.map(cat => cat.id);
    await renderMultiple(allCategoryIds);

    const pages = Array.from(container.querySelectorAll('.a4-page'));

    const keepPage = pages.map(page => {
      const matchSet = matchesByCategory[page.dataset.categoryId];
      if (!matchSet || !matchSet.size) return false;
      return Array.from(page.querySelectorAll('.song-block[data-song-id]'))
        .some(el => matchSet.has(String(el.dataset.songId)));
    });

    // Each category always ends on an even page count (it pads itself -
    // see renderA4Preview), so pages always pair up cleanly here. Only drop
    // a sheet when neither side matches - otherwise keep both pages as-is,
    // real content included, so the printout stays continuous across the
    // sheet boundary (see comment above the function).
    for (let i = 0; i < pages.length; i += 2) {
      if (!keepPage[i] && !keepPage[i + 1]) {
        pages[i].remove();
        pages[i + 1].remove();
      }
    }

    // Drop now-empty page-pair row wrappers left behind by dropped sheets.
    container.querySelectorAll('.page-pair').forEach(row => {
      if (!row.querySelector('.a4-page')) row.remove();
    });

    // Save Layout has no meaning for this filtered, whole-book printout.
    document.getElementById('save-layout-btn').disabled = true;

    container.scrollIntoView({ behavior: 'smooth' });
  } catch (err) {
    console.error('Error printing lock', err);
    alert('Error printing lock.');
  }
}



</script>


<style>

.a4-page {
  font-family: 'Fira Mono', monospace;
  font-size: 14px;
  line-height: 1.5;
  color: #111;
  background: white;
  page-break-after: always;
}

.song-block {
  /* margin-bottom handled by JS */
}

.split-song {
  display: flex;
  justify-content: space-between;
}

.left-column, .right-column {
  width: 48%; /* just under half to fit nicely */
  white-space: pre-wrap;
  word-break: break-word;
}

pre {
  font-family: inherit;
  white-space: pre-wrap;
  word-break: break-word;
  margin: 0;
}



</style>
<div id="a4-preview-container" class="d-print-none mt-5"></div>




<?php 
include("php/footer.php");
?>
