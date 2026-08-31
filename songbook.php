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
    if (!can_view_songbook($mysqli, $songbook)) {
        exit("You do not have permission to view this songbook.");
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

    $adminProfiles = [];
    foreach ($songbook['admins'] as $adminId) {
        $admin = get_user($mysqli, (int)$adminId);
        if ($admin) $adminProfiles[] = $admin;
    }

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
        <?php
        $canEditSongbook =
            (isset($_SESSION["id"]) && in_array((string)$_SESSION["id"], $songbook["admins"])) ||
            (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true);
        $canEditAdmins =
            (isset($_SESSION["id"]) && (int)$_SESSION["id"] === (int)$songbook["owner"]) ||
            (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true);
        ?>
        <div class="col-md-6">
          <div class="d-flex align-items-center">
            <h3 class="me-2 mb-0" id="songbook-name-text"><?php echo htmlspecialchars($name); ?></h3>
          </div>
        </div>
        <div class="col-md-4">
            <?php echo $groupHtml; ?>
        </div>
        <?php if ($canEditSongbook): ?>
        <div class="col-md-2 text-end mt-3 mt-md-0 d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" id="editSongbookDetailsBtn"
                    title="Edit songbook details" data-bs-toggle="modal" data-bs-target="#editSongbookDetailsModal">Edit</button>
            <button id="saveSongbookBtn" class="btn btn-success">💾 Save</button>
        </div>
        <?php endif; ?>

      </div>
      <div class="mt-3">
        <p class="mb-0" id="description-text"><?php echo htmlspecialchars($description); ?></p>
      </div>
      <div class="mt-2 d-flex flex-wrap align-items-center gap-1" id="admin-badges-container">
        <?php if ($adminProfiles): ?>
          <span class="small text-muted me-1">Songbook admins:</span>
          <?php foreach ($adminProfiles as $admin): ?>
            <a href="user.php?id=<?= (int)$admin['id'] ?>" class="badge bg-primary text-decoration-none">
              <?= htmlspecialchars($admin['name'] ?? $admin['username']) ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
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

        <!-- Saved PDFs - visible to anyone who can see this songbook -->
        <div class="card shadow-sm mt-4">
          <div class="card-header bg-primary text-white">
            📄 Saved PDFs
          </div>
          <div class="card-body" id="saved-pdfs-container">
            <!-- JS will render the saved-PDF list (and, for admins, save controls) here -->
          </div>
        </div>
      </div>

        <?php
        $isSongbookAdmin = (isset($_SESSION["id"]) && in_array((string)$_SESSION["id"], $songbook["admins"]))
            || (isset($_SESSION["admin_view"]) && $_SESSION["admin_view"] === true);
        ?>
        <?php if ($isSongbookAdmin): ?>
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
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="select-all-numbering-checkbox">
              <label class="form-check-label" for="select-all-numbering-checkbox">
                Number All Categories
              </label>
            </div>
            <div class="form-text mb-2">Click a category name to include/exclude it from rendering. Use the 🔢 toggle next to a category to include/exclude it from numbering.</div>

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

            <div class="form-text mb-3">Which categories get numbered, and which are included when rendering, is set per-category in the Categories &amp; Songs panel (or via the Actions sidebar's "Select All" toggles).</div>
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
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="render-toc-checkbox" checked>
              <label class="form-check-label" for="render-toc-checkbox">Table of contents</label>
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
            <div class="mb-3">
              <label class="form-label" for="ignored-instrument-select">Ignore default instrument</label>
              <select id="ignored-instrument-select" class="form-select form-select-sm">
                <option value="">None</option>
                <?php foreach (get_all_instruments($mysqli) as $inst): ?>
                  <option value="<?= htmlspecialchars($inst['name']) ?>"><?= htmlspecialchars($inst['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Hides this instrument's badge when a song has it; shows it struck-through when a song doesn't.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="content-sort-select">Content order</label>
              <select id="content-sort-select" class="form-select form-select-sm">
                <option value="manual">Manual / saved order</option>
                <option value="artist">Artist</option>
                <option value="tag">First tag</option>
                <option value="instrument">First instrument</option>
                <option value="difficulty">Difficulty</option>
              </select>
            </div>

            <hr>
            <p class="text-muted small mb-2"><strong>Stats Page</strong></p>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="render-stats-checkbox">
              <label class="form-check-label" for="render-stats-checkbox">Generate stats page</label>
            </div>
            <!-- <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="render-stats-qr-checkbox">
              <label class="form-check-label" for="render-stats-qr-checkbox">Include QR code</label>
            </div> -->
            <!-- <div class="mb-3">
              <label class="form-label" for="stats-qr-url-input">QR code link (optional)</label>
              <input type="text" id="stats-qr-url-input" class="form-control form-control-sm" placeholder="Defaults to this songbook's page">
            </div> -->

            <hr>
            <p class="text-muted small mb-2"><strong>Presets</strong></p>
            <div class="mb-2">
              <select id="preset-select" class="form-select form-select-sm">
                <option value="">-- Select preset --</option>
              </select>
            </div>
            <div class="d-flex gap-2 mb-2">
              <button id="load-preset-btn" class="btn btn-sm btn-outline-primary" disabled>Load</button>
              <button id="delete-preset-btn" class="btn btn-sm btn-outline-danger" disabled>Delete</button>
            </div>
            <div class="input-group input-group-sm mb-3">
              <input type="text" id="preset-name-input" class="form-control" placeholder="Preset name">
              <button id="save-preset-btn" class="btn btn-outline-success">Save</button>
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




        <?php else: ?>
      <!-- Render Options Sidebar (view-only users) -->
      <div class="col-lg-3 mt-4 mt-lg-0">

        <div class="card shadow-sm">
          <div class="card-header bg-light">
            🖨️ Render Options
          </div>
          <div class="card-body">
            <p class="text-muted small">Select categories to render</p>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="select-all-categories-checkbox">
              <label class="form-check-label" for="select-all-categories-checkbox">
                Select All Categories
              </label>
            </div>

            <button id="render-categories-btn" class="btn btn-primary w-100 mb-3">
              🖨️ Render All Categories
            </button>

            <hr>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="render-bw-checkbox">
              <label class="form-check-label" for="render-bw-checkbox">
                Black and White
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="transpose-capo-zero-checkbox">
              <label class="form-check-label" for="transpose-capo-zero-checkbox">
                Transpose to Capo 0
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="render-toc-checkbox" checked>
              <label class="form-check-label" for="render-toc-checkbox">Table of contents</label>
            </div>

            <!-- Not user-editable, but still readable/writable by beginRender()
                 and applyRenderSettings() so a loaded preset can still set it. -->
            <input class="d-none" type="checkbox" id="render-lock-name-checkbox" checked>

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

            <hr>
            <p class="text-muted small mb-2"><strong>Stats Page</strong></p>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="render-stats-checkbox">
              <label class="form-check-label" for="render-stats-checkbox">Generate stats page</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="render-stats-qr-checkbox">
              <label class="form-check-label" for="render-stats-qr-checkbox">Include QR code</label>
            </div>
            <div class="mb-3">
              <label class="form-label" for="stats-qr-url-input">QR code link (optional)</label>
              <input type="text" id="stats-qr-url-input" class="form-control form-control-sm" placeholder="Defaults to this songbook's page">
            </div>

            <hr>
            <p class="text-muted small mb-2"><strong>Presets</strong></p>
            <div class="mb-2">
              <select id="preset-select" class="form-select form-select-sm">
                <option value="">-- Select preset --</option>
              </select>
            </div>
            <button id="load-preset-btn" class="btn btn-sm btn-outline-primary w-100 mb-3" disabled>Load</button>

            <button id="export-pdf-btn" class="btn btn-sm btn-primary" disabled>
              📄 Export to PDF
            </button>
          </div>
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

<?php if ($canEditSongbook): ?>
<div class="modal fade" id="editSongbookDetailsModal" tabindex="-1" aria-labelledby="editSongbookDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editSongbookDetailsModalLabel">Edit Songbook Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="editSongbookName" class="form-label">Name</label>
          <input type="text" class="form-control" id="editSongbookName">
        </div>
        <div class="mb-3">
          <label for="editSongbookDescription" class="form-label">Description</label>
          <textarea class="form-control" id="editSongbookDescription" rows="3"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Songbook Admins</label>
          <?php if ($canEditAdmins): ?>
          <div class="admin-picker" id="adminsPicker">
            <div class="admin-picker-chips d-flex flex-wrap gap-2 mb-2"></div>
            <div class="position-relative">
              <input type="text" class="form-control admin-picker-input" placeholder="Search users by name...">
              <div class="list-group admin-picker-results position-absolute w-100 shadow-sm"
                   style="z-index: 1060; display: none; max-height: 220px; overflow-y: auto;"></div>
            </div>
          </div>
          <?php else: ?>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($adminProfiles as $admin): ?>
              <span class="badge bg-secondary"><?= htmlspecialchars($admin['name'] ?? $admin['username']) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="form-text">Only the songbook owner or a site admin can change admins.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button id="saveSongbookDetailsBtn" class="btn btn-success">💾 Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Adding scripts -->

<script src="js/songbook_render_preview.js?v=<?= filemtime('js/songbook_render_preview.js') ?>"></script>
<script src="js/song_render.js?v=<?= filemtime('js/song_render.js') ?>"></script>
<script src="js/song_ordering.js?v=<?= filemtime('js/song_ordering.js') ?>"></script>
<script src="js/admin_picker.js?v=<?= filemtime('js/admin_picker.js') ?>"></script>

<!-- html2canvas for rendering DOM to image -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- jsPDF core library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- QR code generation for the songbook stats page's optional QR code card -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>



<script>

  let currentCategory = null;
  let orderedIds = [];
  let selectedSongId = null;
  let undoStack = [];

  const selectedCategoryIds = [];
  // Which categories currently show a category number when rendered.
  // Defaults to "all" so behavior matches the old always-numbered default.
  let numberedCategoryIds = [];



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

  // Snapshot of the currently-persisted admins (with display names), used to
  // reset the picker whenever the edit modal is (re)opened so a cancelled
  // edit never leaks into the next time it's opened.
  let adminsSnapshot = <?= json_encode(array_map(function ($admin) {
      return [
          'id' => (int)$admin['id'],
          'name' => $admin['name'] ?? $admin['username'],
          'username' => $admin['username'],
      ];
  }, $adminProfiles)) ?>;

  const editDetailsModalEl = document.getElementById('editSongbookDetailsModal');
  const adminsPickerEl = document.getElementById('adminsPicker');
  let adminPicker = null;
  if (adminsPickerEl) {
    adminPicker = createAdminPicker(adminsPickerEl, {
      initialAdmins: adminsSnapshot,
      protectedIds: [<?= (int)$songbook['owner'] ?>]
    });
  }

  if (editDetailsModalEl) {
    editDetailsModalEl.addEventListener('show.bs.modal', () => {
      document.getElementById('editSongbookName').value = songbook.name;
      document.getElementById('editSongbookDescription').value = songbook.description || '';
      if (adminPicker) adminPicker.setAdmins(adminsSnapshot);
    });

    document.getElementById('saveSongbookDetailsBtn').addEventListener('click', async () => {
      const newName = document.getElementById('editSongbookName').value.trim();
      if (!newName) {
        alert('Songbook name cannot be empty.');
        return;
      }
      const newDescription = document.getElementById('editSongbookDescription').value;
      const newAdmins = adminPicker ? adminPicker.getAdmins() : adminsSnapshot;

      songbook.name = newName;
      songbook.description = newDescription;
      songbook.admins = newAdmins.map(a => String(a.id));

      const result = await saveSongbookToServer();
      if (!result.success) {
        alert('❌ Failed to save songbook: ' + (result.error || result.message));
        return;
      }

      adminsSnapshot = newAdmins;
      document.getElementById('songbook-name-text').textContent = songbook.name;
      document.getElementById('description-text').textContent = songbook.description;

      const badgesContainer = document.getElementById('admin-badges-container');
      badgesContainer.innerHTML = '';
      if (newAdmins.length) {
        const label = document.createElement('span');
        label.className = 'small text-muted me-1';
        label.textContent = 'Songbook admins:';
        badgesContainer.appendChild(label);
        newAdmins.forEach(admin => {
          const link = document.createElement('a');
          link.href = 'user.php?id=' + admin.id;
          link.className = 'badge bg-primary text-decoration-none';
          link.textContent = admin.name || admin.username;
          badgesContainer.appendChild(link);
        });
      }

      bootstrap.Modal.getInstance(editDetailsModalEl).hide();
    });
  }

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

        {
            // Rendering a category (and selecting it via the header click above)
            // is available to every user who can see this songbook, not just
            // admins. Only the numbering toggle, category removal, and adding
            // songs/categories stay admin-only, gated below.
            const btnContainer = document.createElement('div');
            btnContainer.className = 'd-flex align-items-center';

            if (isCurrentUserAdmin()) {
                // Toggles whether this category shows a category number when rendered.
                const numberingCheck = document.createElement('div');
                numberingCheck.className = 'form-check form-check-inline me-2 mb-0';
                numberingCheck.title = 'Show category number when rendered';

                const numberingInput = document.createElement('input');
                numberingInput.className = 'form-check-input';
                numberingInput.type = 'checkbox';
                numberingInput.id = `numbering-check-${category.id}`;
                numberingInput.checked = numberedCategoryIds.includes(category.id);
                numberingInput.addEventListener('change', (e) => {
                    const index = numberedCategoryIds.indexOf(category.id);
                    if (e.target.checked && index === -1) {
                        numberedCategoryIds.push(category.id);
                    } else if (!e.target.checked && index !== -1) {
                        numberedCategoryIds.splice(index, 1);
                    }
                    updateRenderCategoriesUI();
                });

                const numberingLabel = document.createElement('label');
                numberingLabel.className = 'form-check-label small';
                numberingLabel.setAttribute('for', numberingInput.id);
                numberingLabel.textContent = '🔢';

                numberingCheck.appendChild(numberingInput);
                numberingCheck.appendChild(numberingLabel);
                btnContainer.appendChild(numberingCheck);
            }

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
            if (isCurrentUserAdmin() && category.id !== 0) {
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
numberedCategoryIds = songbook.songs_categories.map(cat => cat.id);
renderCategories();
populateCategorySelect();
updateRenderCategoriesUI();
loadPresetsList();
renderSavedPdfsList();

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


// addCategoryBtn is only created (inside renderCategories()) for admins - it
// doesn't exist in the DOM at all for view-only users.
document.getElementById("addCategoryBtn")?.addEventListener("click", () => {
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
// since the membership was never persisted). Also used by the "Edit Songbook
// Details" modal to persist name/description/admins changes.
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

  document.getElementById("save-layout-btn")?.addEventListener("click", async () => {
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

  const selectAllNumberingCheckbox = document.getElementById('select-all-numbering-checkbox');
  if (selectAllNumberingCheckbox) {
    selectAllNumberingCheckbox.checked = songbook.songs_categories.length > 0
      && numberedCategoryIds.length === songbook.songs_categories.length;
  }
}

document.getElementById('select-all-numbering-checkbox')?.addEventListener('change', (e) => {
  numberedCategoryIds.length = 0;
  if (e.target.checked) {
    numberedCategoryIds.push(...songbook.songs_categories.map(cat => cat.id));
  }
  renderCategories();
  updateRenderCategoriesUI();
});


// PRESETS
//
// A preset is a named, saved snapshot of every Dev Tools render option
// (including which categories are currently selected to render and which
// are numbered), scoped to this songbook. Loading one overwrites the current
// in-page settings; it isn't itself persisted until saved again.

let renderPresets = [];

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function getCurrentRenderSettings() {
  return {
    blackAndWhite: document.getElementById('render-bw-checkbox')?.checked || false,
    allowManualOrdering: document.getElementById('allow-manual-ordering-checkbox')?.checked || false,
    showLockName: document.getElementById('render-lock-name-checkbox')?.checked || false,
    transposeCapoZero: document.getElementById('transpose-capo-zero-checkbox')?.checked || false,
    showToc: document.getElementById('render-toc-checkbox')?.checked || false,
    showStats: document.getElementById('render-stats-checkbox')?.checked || false,
    showStatsQr: document.getElementById('render-stats-qr-checkbox')?.checked || false,
    statsQrUrl: document.getElementById('stats-qr-url-input')?.value || '',
    showDifficulty: document.getElementById('extra-difficulty-checkbox')?.checked || false,
    showTags: document.getElementById('extra-tags-checkbox')?.checked || false,
    showInstruments: document.getElementById('extra-instruments-checkbox')?.checked || false,
    ignoredInstrument: document.getElementById('ignored-instrument-select')?.value || '',
    sortMode: document.getElementById('content-sort-select')?.value || 'manual',
    renderedCategoryIds: [...selectedCategoryIds],
    numberedCategoryIds: [...numberedCategoryIds]
  };
}

function applyRenderSettings(settings) {
  if (!settings) return;

  const setChecked = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.checked = !!value;
  };

  setChecked('render-bw-checkbox', settings.blackAndWhite);
  setChecked('allow-manual-ordering-checkbox', settings.allowManualOrdering);
  setChecked('render-lock-name-checkbox', settings.showLockName);
  setChecked('transpose-capo-zero-checkbox', settings.transposeCapoZero);
  setChecked('render-toc-checkbox', settings.showToc);
  setChecked('render-stats-checkbox', settings.showStats);
  setChecked('render-stats-qr-checkbox', settings.showStatsQr);
  const qrUrlInput = document.getElementById('stats-qr-url-input');
  if (qrUrlInput) qrUrlInput.value = settings.statsQrUrl || '';
  setChecked('extra-difficulty-checkbox', settings.showDifficulty);
  setChecked('extra-tags-checkbox', settings.showTags);
  setChecked('extra-instruments-checkbox', settings.showInstruments);

  const ignoredInstrumentSelect = document.getElementById('ignored-instrument-select');
  if (ignoredInstrumentSelect) ignoredInstrumentSelect.value = settings.ignoredInstrument || '';

  const sortSelect = document.getElementById('content-sort-select');
  if (sortSelect) sortSelect.value = settings.sortMode || 'manual';

  const validCategoryIds = new Set(songbook.songs_categories.map(cat => cat.id));

  selectedCategoryIds.length = 0;
  if (Array.isArray(settings.renderedCategoryIds)) {
    selectedCategoryIds.push(...settings.renderedCategoryIds.filter(id => validCategoryIds.has(id)));
  }

  numberedCategoryIds.length = 0;
  if (Array.isArray(settings.numberedCategoryIds)) {
    numberedCategoryIds.push(...settings.numberedCategoryIds.filter(id => validCategoryIds.has(id)));
  } else {
    numberedCategoryIds.push(...songbook.songs_categories.map(cat => cat.id));
  }

  renderCategories();
  updateRenderCategoriesUI();
}

async function loadPresetsList() {
  const select = document.getElementById('preset-select');
  if (!select) return; // Absent only if the render sidebar itself didn't render.

  try {
    const res = await fetch(`php/get_render_presets.php?songbook_id=${songbook.id}`, {
      credentials: 'same-origin'
    });
    const data = await res.json();
    if (!data.success) return;

    renderPresets = data.presets || [];
    select.innerHTML = '<option value="">-- Select preset --</option>' +
      renderPresets.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
  } catch (err) {
    console.error('Failed to load presets', err);
  }
}

document.getElementById('preset-select')?.addEventListener('change', (e) => {
  const hasSelection = !!e.target.value;
  const loadBtn = document.getElementById('load-preset-btn');
  const deleteBtn = document.getElementById('delete-preset-btn');
  // delete-preset-btn / preset-name-input are admin-only - absent for view-only users.
  if (loadBtn) loadBtn.disabled = !hasSelection;
  if (deleteBtn) deleteBtn.disabled = !hasSelection;

  const preset = renderPresets.find(p => String(p.id) === e.target.value);
  const nameInput = document.getElementById('preset-name-input');
  if (nameInput) nameInput.value = preset ? preset.name : '';
});

document.getElementById('load-preset-btn')?.addEventListener('click', () => {
  const select = document.getElementById('preset-select');
  const preset = renderPresets.find(p => String(p.id) === select.value);
  if (preset) applyRenderSettings(preset.settings);
});

document.getElementById('save-preset-btn')?.addEventListener('click', async () => {
  const nameInput = document.getElementById('preset-name-input');
  const name = nameInput?.value.trim();
  if (!name) {
    alert('Enter a preset name.');
    return;
  }

  const select = document.getElementById('preset-select');
  const existing = renderPresets.find(p => p.name === name);

  try {
    const res = await fetch('php/save_render_preset.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        songbook_id: songbook.id,
        preset_id: existing ? existing.id : null,
        name,
        settings: getCurrentRenderSettings()
      })
    });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to save preset.');
      return;
    }

    await loadPresetsList();
    select.value = String(data.id);
    select.dispatchEvent(new Event('change'));
  } catch (err) {
    console.error('Failed to save preset', err);
    alert('Failed to save preset.');
  }
});

document.getElementById('delete-preset-btn')?.addEventListener('click', async () => {
  const select = document.getElementById('preset-select');
  const preset = renderPresets.find(p => String(p.id) === select.value);
  if (!preset) return;
  if (!confirm(`Delete preset "${preset.name}"?`)) return;

  try {
    const res = await fetch('php/delete_render_preset.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ preset_id: preset.id })
    });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to delete preset.');
      return;
    }

    document.getElementById('preset-name-input').value = '';
    await loadPresetsList();
  } catch (err) {
    console.error('Failed to delete preset', err);
    alert('Failed to delete preset.');
  }
});


// SAVED PDFs
//
// A saved PDF is a static snapshot of whatever's currently rendered below,
// stored on the server as "<songbook name>_<title>". Anyone who can view
// this songbook (including guests, on public ones) can see and download the
// list; only a songbook admin can generate a new one or delete an existing
// one - saveSongbookPdfToServer/deleteSongbookPdf below are only reachable
// through controls that renderSavedPdfsList only builds for admins.

async function renderSavedPdfsList() {
  const container = document.getElementById('saved-pdfs-container');
  if (!container) return;

  const admin = isCurrentUserAdmin();
  container.innerHTML = '';

  if (admin) {
    const saveRow = document.createElement('div');
    saveRow.className = 'input-group input-group-sm mb-2';

    const titleInput = document.createElement('input');
    titleInput.type = 'text';
    titleInput.id = 'pdf-title-input';
    titleInput.className = 'form-control';
    titleInput.placeholder = 'PDF title (e.g. "Summer 25")';

    const saveBtn = document.createElement('button');
    saveBtn.id = 'save-pdf-to-server-btn';
    saveBtn.className = 'btn btn-outline-success';
    saveBtn.textContent = '💾 Save current render as PDF';
    saveBtn.addEventListener('click', saveSongbookPdfToServer);

    saveRow.appendChild(titleInput);
    saveRow.appendChild(saveBtn);
    container.appendChild(saveRow);

    const hint = document.createElement('div');
    hint.className = 'form-text mb-3';
    hint.textContent = `Saves whatever is currently rendered below as "${songbook.name}_<title>". Render something first.`;
    container.appendChild(hint);
  }

  const listWrapper = document.createElement('div');
  listWrapper.textContent = 'Loading…';
  listWrapper.className = 'text-muted small';
  container.appendChild(listWrapper);

  try {
    const res = await fetch(`php/get_songbook_pdfs.php?songbook_id=${songbook.id}`, {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (!data.success) {
      listWrapper.textContent = data.message || 'Unable to load saved PDFs.';
      return;
    }

    if (!data.pdfs.length) {
      listWrapper.textContent = 'No PDFs saved yet.';
      return;
    }

    listWrapper.textContent = '';
    listWrapper.className = '';

    data.pdfs.forEach(pdf => {
      const row = document.createElement('div');
      row.className = 'd-flex justify-content-between align-items-center border rounded p-2 mb-2';

      const info = document.createElement('div');
      const nameLine = document.createElement('div');
      nameLine.className = 'fw-semibold';
      nameLine.textContent = pdf.display_name;
      info.appendChild(nameLine);

      const meta = document.createElement('div');
      meta.className = 'small text-muted';
      meta.textContent = new Date(pdf.created_at.replace(' ', 'T')).toLocaleString();
      info.appendChild(meta);

      row.appendChild(info);

      const actions = document.createElement('div');
      actions.className = 'd-flex align-items-center gap-2';

      const viewLink = document.createElement('a');
      viewLink.href = `php/download_songbook_pdf.php?id=${pdf.id}&mode=inline`;
      viewLink.target = '_blank';
      viewLink.rel = 'noopener';
      viewLink.className = 'btn btn-sm btn-outline-secondary';
      viewLink.textContent = '👁️ View';
      actions.appendChild(viewLink);

      const downloadLink = document.createElement('a');
      downloadLink.href = `php/download_songbook_pdf.php?id=${pdf.id}`;
      downloadLink.className = 'btn btn-sm btn-outline-primary';
      downloadLink.textContent = '⬇️ Download';
      actions.appendChild(downloadLink);

      if (admin) {
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn btn-sm btn-outline-danger';
        deleteBtn.textContent = '🗑️';
        deleteBtn.title = 'Delete this saved PDF';
        deleteBtn.addEventListener('click', () => deleteSongbookPdf(pdf.id, pdf.display_name));
        actions.appendChild(deleteBtn);
      }

      row.appendChild(actions);
      listWrapper.appendChild(row);
    });
  } catch (err) {
    console.error('Failed to load saved PDFs', err);
    listWrapper.textContent = 'Unable to load saved PDFs.';
  }
}

async function saveSongbookPdfToServer() {
  const titleInput = document.getElementById('pdf-title-input');
  const title = titleInput?.value.trim();
  if (!title) {
    alert('Enter a title for this PDF.');
    return;
  }

  const hasPages = document.querySelectorAll('#a4-preview-container .a4-page').length > 0;
  if (!hasPages) {
    alert("Render something first - there's nothing in the preview to save.");
    return;
  }

  const saveBtn = document.getElementById('save-pdf-to-server-btn');
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = '⏳ Generating...';
  }

  try {
    const blob = await generatePdfBlob();

    const formData = new FormData();
    formData.append('songbook_id', songbook.id);
    formData.append('title', title);
    formData.append('pdf', blob, 'songbook.pdf');

    const res = await fetch('php/save_songbook_pdf.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
    const data = await res.json();

    if (!data.success) {
      alert(data.message || 'Failed to save PDF.');
      return;
    }

    await renderSavedPdfsList();
  } catch (err) {
    console.error('Failed to save PDF to server', err);
    alert('Failed to save PDF.');
  } finally {
    // saveBtn no longer exists if renderSavedPdfsList() already rebuilt the
    // list (the success path) - only need to reset it on the error/early-out paths.
    const stillThere = document.getElementById('save-pdf-to-server-btn');
    if (stillThere) {
      stillThere.disabled = false;
      stillThere.textContent = '💾 Save current render as PDF';
    }
  }
}

async function deleteSongbookPdf(id, displayName) {
  if (!confirm(`Delete saved PDF "${displayName}"?`)) return;

  try {
    const res = await fetch('php/delete_songbook_pdf.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Failed to delete PDF.');
      return;
    }
    await renderSavedPdfsList();
  } catch (err) {
    console.error('Failed to delete PDF', err);
    alert('Failed to delete PDF.');
  }
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

  const includeRoman = numberedCategoryIds.includes(category.id);
  const blackAndWhite = document.getElementById('render-bw-checkbox')?.checked || false;
  const allowClicking = document.getElementById('allow-manual-ordering-checkbox')?.checked || false;
  const debugMode = allowClicking; // debug lines are just a side effect of manual ordering now
  const showLockName = document.getElementById('render-lock-name-checkbox')?.checked || false;
  const transposeToCapoZero = document.getElementById('transpose-capo-zero-checkbox')?.checked || false;
  const showDifficulty = document.getElementById('extra-difficulty-checkbox')?.checked || false;
  const showTags = document.getElementById('extra-tags-checkbox')?.checked || false;
  const showInstruments = document.getElementById('extra-instruments-checkbox')?.checked || false;
  const ignoredInstrument = document.getElementById('ignored-instrument-select')?.value || '';
  const sortMode = document.getElementById('content-sort-select')?.value || 'manual';

  const categoryTitle = includeRoman
    ? `${toRoman(category.id)}. ${category.name}`
    : category.name;

  const songs = await fetchSongsByIds(orderedIds);
  const sortValue = (song) => {
    if (sortMode === 'artist') return song.artist || '';
    if (sortMode === 'tag') return (song.tags || '').split(',')[0];
    if (sortMode === 'instrument') return (song.instruments || '').split(',')[0];
    if (sortMode === 'difficulty') return ({ easy: '1', normal: '2', hard: '3' })[song.difficulty] || '4';
    return '';
  };
  const renderIds = sortMode === 'manual' ? orderedIds : [...orderedIds].sort((leftId, rightId) => {
    const left = songs.find(song => song.id === leftId) || {};
    const right = songs.find(song => song.id === rightId) || {};
    return sortValue(left).localeCompare(sortValue(right), undefined, { sensitivity: 'base' })
      || String(left.title || '').localeCompare(String(right.title || ''), undefined, { sensitivity: 'base' });
  });

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
  const pageCount = await renderA4Preview(songs, renderIds, {
    debug: debugMode,
    category: categoryTitle,
    blackAndWhite: blackAndWhite,
    allowClicking: allowClicking && sortMode === 'manual',
    transposeToCapoZero: transposeToCapoZero,
    showDifficulty: showDifficulty,
    showTags: showTags,
    showInstruments: showInstruments,
    ignoredInstrument: ignoredInstrument,
    showLockName: showLockName,
    lockNames: lockNames,
    categoryId: category.id
  });

  console.log(`✅ Rendered ${pageCount} pages.`);

  const exportBtn = document.getElementById('export-pdf-btn');
  const saveLayoutBtn = document.getElementById('save-layout-btn');

  setTimeout(() => {
    const hasPages = container.querySelectorAll('.a4-page').length > 0;
    if (exportBtn) exportBtn.disabled = !hasPages;
    // save-layout-btn doesn't exist for view-only users - they can't save layouts.
    if (saveLayoutBtn) saveLayoutBtn.disabled = !hasPages;
  }, 100);

  return pageCount;
}






function getCategoryById(id) {
  return songbook.songs_categories.find(cat => cat.id === id);
}


// STATS PAGE
//
// A generated "Štatistiky" (stats) page summarizing only the categories
// currently being rendered (not necessarily the whole songbook) - song
// counts per category, song ownership (pie chart), lock/difficulty
// breakdowns, and the most-used tags. Always ends up as the very first page
// of the rendered book - see its call site in renderMultiple(), whose
// prepend runs after addTableOfContents's own prepend, so stats end up in
// front of the table of contents too. A single page (createPage(1, ...)),
// so it always renders as the book's lone standalone opening page - the
// same slot/convention real content's own page 1 uses.
//
// The pie chart is hand-drawn SVG (arc <path> elements), not a charting
// library - it needs to rasterize reliably through html2canvas for PDF
// export, and (per blackAndWhite below) switch from a color palette to
// hatch-pattern fills so slices stay distinguishable without color at all.

const STATS_DIFFICULTY_LABELS = { easy: 'Ľahká', normal: 'Stredná', hard: 'Náročná', none: 'Bez náročnosti' };

// Slovak needs a different noun/adjective form depending on the count: 1 ->
// singular, 2-4 -> "few" plural, everything else (0, 5+) -> genitive plural.
// Unlike Russian, this does NOT reset every decade - 21, 61, 101 etc. all
// still take the genitive-plural "many" form, only literal 1/2/3/4 are
// special-cased. forms = [singular, few, many], e.g.
// ['kategória', 'kategórie', 'kategórií'].
function slovakForm(n, forms) {
  if (n === 1) return forms[0];
  if (n >= 2 && n <= 4) return forms[1];
  return forms[2];
}
const STATS_COLOR_PALETTE = [
  '#4361ee', '#f72585', '#4cc9f0', '#f8961e', '#43aa8b',
  '#9b5de5', '#ffca3a', '#ef476f', '#06d6a0', '#118ab2'
];
// Cycled per pie slice in black-and-white mode - distinct hatch angles read
// apart reliably even in grayscale print, unlike color or gray-shade alone.
const STATS_BW_HATCH_ANGLES = [45, 135, 0, 90, 22, 112, 67, 157];

function statsSliceColor(i) {
  return STATS_COLOR_PALETTE[i % STATS_COLOR_PALETTE.length];
}

function statsSliceHatchAngle(i) {
  return STATS_BW_HATCH_ANGLES[i % STATS_BW_HATCH_ANGLES.length];
}

// CSS-only hatch swatch for legend rows (kept separate from the SVG chart's
// own <pattern> defs, which only resolve within their own <svg> root -
// repeating-linear-gradient reproduces the same angle without needing to
// share SVG pattern definitions across elements).
function statsSliceLegendStyle(i, blackAndWhite) {
  if (!blackAndWhite) {
    return `background:${statsSliceColor(i)};`;
  }
  const angle = statsSliceHatchAngle(i);
  return `background-image:repeating-linear-gradient(${angle}deg,#000,#000 1.5px,#fff 1.5px,#fff 5px);`
    + 'background-color:#fff;border:1px solid #000;';
}

const SVG_NS = 'http://www.w3.org/2000/svg';

function buildStatsPieChart(slices, size, blackAndWhite) {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('width', size);
  svg.setAttribute('height', size);
  svg.setAttribute('viewBox', `0 0 ${size} ${size}`);

  const radius = size / 2;
  const cx = radius;
  const cy = radius;
  const total = slices.reduce((sum, s) => sum + s.count, 0);

  if (total <= 0) {
    const circle = document.createElementNS(SVG_NS, 'circle');
    circle.setAttribute('cx', cx);
    circle.setAttribute('cy', cy);
    circle.setAttribute('r', radius - 1.5);
    circle.setAttribute('fill', 'none');
    circle.setAttribute('stroke', '#ccc');
    circle.setAttribute('stroke-width', 2);
    svg.appendChild(circle);
    return svg;
  }

  if (blackAndWhite) {
    const defs = document.createElementNS(SVG_NS, 'defs');
    slices.forEach((_, i) => {
      const pattern = document.createElementNS(SVG_NS, 'pattern');
      pattern.setAttribute('id', `stats-hatch-${i}`);
      pattern.setAttribute('patternUnits', 'userSpaceOnUse');
      pattern.setAttribute('width', 6);
      pattern.setAttribute('height', 6);
      pattern.setAttribute('patternTransform', `rotate(${statsSliceHatchAngle(i)})`);
      const bg = document.createElementNS(SVG_NS, 'rect');
      bg.setAttribute('width', 6);
      bg.setAttribute('height', 6);
      bg.setAttribute('fill', '#fff');
      const line = document.createElementNS(SVG_NS, 'line');
      line.setAttribute('x1', 0);
      line.setAttribute('y1', 0);
      line.setAttribute('x2', 0);
      line.setAttribute('y2', 6);
      line.setAttribute('stroke', '#000');
      line.setAttribute('stroke-width', 2);
      pattern.append(bg, line);
      defs.appendChild(pattern);
    });
    svg.appendChild(defs);
  }

  // -90deg (12 o'clock) start, sweeping clockwise - the standard SVG
  // pie-slice formula. A single 100%-share slice is drawn as a full circle
  // via two half-arcs, since one <path> arc command can't sweep a full 360deg.
  let angle = -Math.PI / 2;
  slices.forEach((slice, i) => {
    const fraction = slice.count / total;
    const nextAngle = angle + fraction * Math.PI * 2;

    const path = document.createElementNS(SVG_NS, 'path');
    if (fraction >= 0.9999) {
      path.setAttribute('d', `M ${cx} ${cy - radius} A ${radius} ${radius} 0 1 1 ${cx - 0.01} ${cy - radius} Z`);
    } else {
      const x1 = cx + radius * Math.cos(angle);
      const y1 = cy + radius * Math.sin(angle);
      const x2 = cx + radius * Math.cos(nextAngle);
      const y2 = cy + radius * Math.sin(nextAngle);
      const largeArc = fraction > 0.5 ? 1 : 0;
      path.setAttribute('d', `M ${cx} ${cy} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} Z`);
    }
    path.setAttribute('fill', blackAndWhite ? `url(#stats-hatch-${i})` : statsSliceColor(i));
    path.setAttribute('stroke', '#fff');
    path.setAttribute('stroke-width', 2);
    svg.appendChild(path);

    angle = nextAngle;
  });

  return svg;
}

async function computeSongbookStats(categoryIds) {
  const categories = categoryIds.map(id => getCategoryById(id)).filter(Boolean);

  const allSongs = [];
  categories.forEach(cat => cat.songs.forEach(song => allSongs.push(song)));
  const totalSongs = allSongs.length;

  const ownerCounts = new Map();
  allSongs.forEach(song => {
    const owner = song.owner || {};
    const key = owner.id != null ? `id:${owner.id}` : `name:${owner.username || owner.name || 'neznámy'}`;
    const label = owner.name || owner.username || 'Neznámy';
    if (!ownerCounts.has(key)) ownerCounts.set(key, { label, count: 0 });
    ownerCounts.get(key).count += 1;
  });

  const categoryCounts = categories
    .map(cat => ({ name: cat.name, count: cat.songs.length }))
    .filter(cat => cat.count > 0);

  const artistCounts = new Map();
  allSongs.forEach(song => {
    const name = (song.artist || '').trim();
    if (!name) return;
    artistCounts.set(name, (artistCounts.get(name) || 0) + 1);
  });
  const sortedArtists = [...artistCounts.entries()].sort((a, b) => b[1] - a[1]);
  const topArtists = sortedArtists.slice(0, 5);
  const restArtistsCount = sortedArtists.slice(5).reduce((sum, [, count]) => sum + count, 0);

  // Difficulty, tags, instruments and capo aren't in the seed
  // songbook.songs_categories data - same as beginRender(), they only come
  // back from get_songs_by_ids_extended.php.
  const allSongIds = allSongs.map(s => s.id);
  const extendedSongs = allSongIds.length ? await fetchSongsByIds(allSongIds) : [];

  const difficultyCounts = new Map();
  extendedSongs.forEach(song => {
    const key = song.difficulty && STATS_DIFFICULTY_LABELS[song.difficulty] ? song.difficulty : 'none';
    difficultyCounts.set(key, (difficultyCounts.get(key) || 0) + 1);
  });

  const tagCounts = new Map();
  extendedSongs.forEach(song => {
    (song.tags || '').split(',').map(t => t.trim()).filter(Boolean).forEach(tag => {
      tagCounts.set(tag, (tagCounts.get(tag) || 0) + 1);
    });
  });
  const topTags = [...tagCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 10);

  const capoCounts = new Map();
  extendedSongs.forEach(song => {
    const capo = parseInt(song.capo, 10);
    // Negative capo values are real data (not "no capo"), so they get their
    // own "Capo -N" bucket same as any positive value - only a genuinely
    // missing/non-numeric capo falls back to 0.
    const key = Number.isFinite(capo) ? String(capo) : '0';
    capoCounts.set(key, (capoCounts.get(key) || 0) + 1);
  });
  const capoStats = [...capoCounts.entries()]
    .map(([key, count]) => ({ label: `Capo ${key}`, count, sortKey: Number(key) }))
    .sort((a, b) => a.sortKey - b.sortKey);

  const instrumentCounts = new Map();
  extendedSongs.forEach(song => {
    (song.instruments || '').split(',').map(i => i.trim()).filter(Boolean).forEach(instrument => {
      instrumentCounts.set(instrument, (instrumentCounts.get(instrument) || 0) + 1);
    });
  });
  // Capo and Nástroje are shown side by side (see addStatsPages), so a
  // short Nástroje list under a much longer Capo list looks lopsided -
  // Nástroje's cutoff grows to match Capo's row count when there are more
  // than 5 distinct instruments to show, capped at whatever actually exists.
  const instrumentLimit = Math.max(5, capoStats.length);
  const topInstruments = [...instrumentCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, instrumentLimit);

  // Lock info is only exposed per-category (get_song_lock_info.php), and
  // Uncategorized (id 0) is never locked by design - see get_song_lock_names.
  const lockCounts = new Map();
  let lockedSongs = 0;
  for (const cat of categories) {
    if (cat.id === 0) continue;
    try {
      const res = await fetch(`php/get_song_lock_info.php?songbook_id=${songbook.id}&category_id=${cat.id}`);
      const data = await res.json();
      if (data.success) {
        Object.values(data.lock_names || {}).forEach(info => {
          const name = info.name || 'Originálne';
          lockCounts.set(name, (lockCounts.get(name) || 0) + 1);
          lockedSongs += 1;
        });
      }
    } catch (err) {
      console.error('Failed to load lock info for stats page', err);
    }
  }

  return {
    totalSongs,
    owners: [...ownerCounts.values()].sort((a, b) => b.count - a.count),
    categoryCounts,
    topArtists,
    restArtistsCount,
    difficultyCounts: [...difficultyCounts.entries()]
      .map(([key, count]) => ({ key, label: STATS_DIFFICULTY_LABELS[key], count }))
      .sort((a, b) => b.count - a.count),
    topTags,
    uniqueTagCount: tagCounts.size,
    topInstruments,
    capoStats,
    locks: [...lockCounts.entries()].map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count),
    unlockedSongs: totalSongs - lockedSongs
  };
}

function statPercent(count, total) {
  return total > 0 ? Math.round((count / total) * 1000) / 10 : 0;
}

// A bordered, rounded "dashboard tile" - the site's own card convention
// (.card / .card-header, e.g. the sidebar's 🗂️ Categories & Songs card),
// reproduced with inline styles since this renders inside an .a4-page meant
// for html2canvas, not the live page's own stylesheet-driven layout.
// `compact` is used for Capo/Nástroje when they need to squeeze into two
// half-width mini-cards inside a single masonry slot instead of each having
// a full slot to itself - see addStatsPages' space-based decision.
function buildStatsCard(titleText, blackAndWhite, compact) {
  const card = document.createElement('div');
  card.style.cssText = 'border:1px solid ' + (blackAndWhite ? '#000' : '#e2e5ea') + ';'
    + `border-radius:${compact ? 8 : 10}px;background:` + (blackAndWhite ? '#fff' : '#fafbfc') + ';'
    + `padding:${compact ? '8px 10px 9px' : '10px 14px 12px'};` + (compact ? '' : 'margin-bottom:12px;');

  const header = document.createElement('div');
  header.style.cssText = 'display:flex;align-items:center;gap:6px;font-family:system-ui,sans-serif;'
    + `font-weight:700;font-size:${compact ? 11 : 13}px;margin-bottom:${compact ? 6 : 8}px;padding-bottom:${compact ? 4 : 6}px;`
    + 'border-bottom:2px solid ' + (blackAndWhite ? '#000' : '#4361ee') + ';color:#212529;';
  header.textContent = titleText;
  card.appendChild(header);

  const body = document.createElement('div');
  card.appendChild(body);

  return { card, body };
}

// total === null means "no percentage" (e.g. the "Spolu" totals row, or
// tags/instruments - which aren't a share of a whole the way
// owners/categories/locks/difficulty/capo/artists are, since one song can
// have several of each). barMax draws the bar relative to a given max
// instead of `total` - used for tags, ranked by count with no natural
// "whole" to be a share of. options.labelElement, if given, replaces the
// plain-text label with a DOM element (e.g. a difficulty/tag badge) - see
// buildDifficultyBadge/buildTagBadge in songbook_render_preview.js.
//
// The label column is a FIXED width (not 1fr) specifically so every row's
// bar starts at the same X position - a flexible label column would resize
// per-row based on that row's own trailing count text width, throwing off
// alignment between rows even within the same card. options.compact drops
// the bar column and percentage text entirely and shrinks everything else,
// for when a row needs to fit in a half-width squeezed mini-card (see
// buildStatsCard) - with no bar there's nothing for a flexible label column
// to misalign, so it's safe to go back to 1fr there.
const STATS_ROW_LABEL_WIDTH = 150;

// Count column is also a FIXED width, same reasoning as the label column -
// it now sits BEFORE the bar, so if it were content-sized ("auto") the bar
// would drift per-row again, just like the original label-column bug.
const STATS_ROW_COUNT_WIDTH = 70;

function buildStatsRow(label, count, total, options) {
  options = options || {};
  const compact = !!options.compact;
  const showBar = options.showBar && !compact;

  const row = document.createElement('div');
  const gridTemplate = compact
    ? 'minmax(0,1fr) 32px'
    : `${STATS_ROW_LABEL_WIDTH}px ${STATS_ROW_COUNT_WIDTH}px 60px`;
  row.style.cssText = `display:grid;grid-template-columns:${gridTemplate};align-items:center;`
    + `gap:${compact ? 6 : 8}px;padding:${compact ? 1.5 : 2.5}px 0;font-family:system-ui,sans-serif;`
    + `font-size:${compact ? 10 : 11.5}px;`;

  const labelCell = document.createElement('div');
  labelCell.style.cssText = 'overflow:hidden;white-space:nowrap;';
  if (options.labelElement) {
    labelCell.appendChild(options.labelElement);
  } else {
    const labelSpan = document.createElement('span');
    labelSpan.textContent = label;
    labelSpan.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'
      + (options.bold ? 'font-weight:700;' : '');
    labelCell.appendChild(labelSpan);
  }

  const countSpan = document.createElement('span');
  countSpan.style.cssText = 'white-space:nowrap;text-align:right;overflow:hidden;' + (options.bold ? 'font-weight:700;' : '');
  countSpan.textContent = (total !== null && !compact) ? `${count} (${statPercent(count, total)}%)` : String(count);

  row.append(labelCell, countSpan);

  if (!compact) {
    const barTrack = document.createElement('div');
    if (showBar) {
      barTrack.style.cssText = 'width:60px;height:6px;border-radius:3px;background:#eceef1;overflow:hidden;';
      const ratio = options.barMax
        ? Math.max(0, Math.min(1, count / options.barMax))
        : (total ? Math.max(0, Math.min(1, count / total)) : 0);
      const bar = document.createElement('div');
      bar.style.cssText = `width:${Math.round(ratio * 100)}%;height:100%;background:${options.blackAndWhite ? '#000' : '#4361ee'};`;
      barTrack.appendChild(bar);
    }
    row.appendChild(barTrack);
  }

  return row;
}

// Owner rows get a color/hatch swatch (matching the pie slice it corresponds
// to) instead of a bar - the requested "show which user is which part of the chart".
function buildStatsOwnerRow(index, label, count, total, blackAndWhite) {
  const row = document.createElement('div');
  row.style.cssText = 'display:grid;grid-template-columns:13px minmax(0,1fr) auto;align-items:center;gap:8px;'
    + 'padding:2.5px 0;font-family:system-ui,sans-serif;font-size:11.5px;';

  const swatch = document.createElement('span');
  swatch.style.cssText = 'width:12px;height:12px;border-radius:3px;display:inline-block;'
    + statsSliceLegendStyle(index, blackAndWhite);

  const labelSpan = document.createElement('span');
  labelSpan.textContent = label;
  labelSpan.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';

  const countSpan = document.createElement('span');
  countSpan.style.cssText = 'white-space:nowrap;text-align:right;';
  countSpan.textContent = `${count} (${statPercent(count, total)}%)`;

  row.append(swatch, labelSpan, countSpan);
  return row;
}

function buildStatsFootnote(text) {
  const p = document.createElement('p');
  p.style.cssText = 'font-family:system-ui,sans-serif;font-size:10.5px;color:#888;margin:6px 0 0;';
  p.textContent = text;
  return p;
}

async function addStatsPages(container, categoryIds, blackAndWhite, qrUrl) {
  const stats = await computeSongbookStats(categoryIds);

  const page = createPage(1, 'Štatistiky');
  page.dataset.categoryLabel = 'Štatistiky';
  page.style.fontFamily = 'system-ui, sans-serif';

  // Header banner - the same gradient-tile look as the site's own
  // .songbook-icon (linear-gradient(135deg, var(--primary), var(--accent))),
  // collapsed to solid black in blackAndWhite mode like every other
  // colored element in this renderer (see buildDifficultyBadge/buildTagBadge).
  const header = document.createElement('div');
  header.style.cssText = 'border-radius:10px;padding:14px 18px;margin-bottom:16px;color:#fff;'
    + (blackAndWhite ? 'background:#000;' : 'background:linear-gradient(135deg,#4361ee,#3f37c9);');
  const headerTitle = document.createElement('div');
  headerTitle.textContent = 'Štatistiky';
  headerTitle.style.cssText = 'font-size:22px;font-weight:700;font-family:system-ui,sans-serif;';
  const headerSubtitle = document.createElement('div');
  const songWord = slovakForm(stats.totalSongs, ['pieseň', 'piesne', 'piesní']);
  const categoryWord = slovakForm(stats.categoryCounts.length, ['kategória', 'kategórie', 'kategórií']);
  headerSubtitle.textContent = `${stats.totalSongs} ${songWord} · ${stats.categoryCounts.length} ${categoryWord}`;
  headerSubtitle.style.cssText = 'font-size:12px;opacity:0.85;margin-top:2px;font-family:system-ui,sans-serif;';
  header.append(headerTitle, headerSubtitle);
  page.appendChild(header);

  // Masonry-style layout: every card is a sibling in a 2-column CSS
  // multi-column flow (not a fixed chart-column/list-column grid) so cards
  // pack tightly next to each other and the browser balances the two
  // columns by total content height, instead of leaving one column mostly
  // empty under a short card while the other overflows.
  const columns = document.createElement('div');
  columns.style.cssText = 'column-count:2;column-gap:20px;column-fill:balance;';
  page.appendChild(columns);

  // Appends any masonry item (a real bordered card, or the title-less QR
  // block below) and keeps it from being split across the two columns.
  function addItem(el) {
    el.style.breakInside = 'avoid';
    el.style.webkitColumnBreakInside = 'avoid';
    columns.appendChild(el);
  }

  // Every card is entirely omitted (not just shown with an empty-state
  // note) when it has nothing to show - e.g. no version locks were ever
  // made ("Rok pridania"), or this render has no songs with any instruments
  // tagged at all.

  if (stats.owners.length) {
    // Keeps both the pie chart and the page itself bounded regardless of how
    // many different people have contributed songs: only the top 5 get
    // their own slice/row, everyone else is folded into one "Ostatní" slice
    // (so the chart still accounts for 100% of songs) - named right in that
    // same row's label, rather than costing an extra row of its own.
    const STATS_TOP_OWNERS = 5;
    const shownOwners = stats.owners.slice(0, STATS_TOP_OWNERS);
    const restOwners = stats.owners.slice(STATS_TOP_OWNERS);
    const restCount = restOwners.reduce((sum, o) => sum + o.count, 0);
    const restLabel = restOwners.length ? `Ostatní: ${restOwners.map(o => o.label).join(', ')}` : 'Ostatní';
    const chartOwners = restOwners.length
      ? [...shownOwners, { label: restLabel, count: restCount }]
      : shownOwners;

    const ownerCard = buildStatsCard('Kontribúcie', blackAndWhite);
    const chartRow = document.createElement('div');
    chartRow.style.cssText = 'display:flex;justify-content:center;margin-bottom:10px;';
    chartRow.appendChild(buildStatsPieChart(chartOwners, 140, blackAndWhite));
    ownerCard.body.appendChild(chartRow);
    chartOwners.forEach((owner, i) => {
      ownerCard.body.appendChild(buildStatsOwnerRow(i, owner.label, owner.count, stats.totalSongs, blackAndWhite));
    });
    addItem(ownerCard.card);
  }

  if (stats.categoryCounts.length) {
    const categoryCard = buildStatsCard('Kategórie', blackAndWhite);
    stats.categoryCounts.forEach(cat => {
      categoryCard.body.appendChild(buildStatsRow(cat.name, cat.count, stats.totalSongs, { showBar: true, blackAndWhite }));
    });
    categoryCard.body.appendChild(buildStatsRow('Spolu', stats.totalSongs, null, { bold: true }));
    addItem(categoryCard.card);
  }

  if (stats.locks.length) {
    const lockCard = buildStatsCard('Rok pridania', blackAndWhite);
    stats.locks.forEach(lock => {
      lockCard.body.appendChild(buildStatsRow(lock.name, lock.count, stats.totalSongs, { showBar: true, blackAndWhite }));
    });
    if (stats.unlockedSongs > 0) {
      lockCard.body.appendChild(buildStatsRow('Nezaradené', stats.unlockedSongs, stats.totalSongs, { showBar: true, blackAndWhite }));
    }
    addItem(lockCard.card);
  }

  if (stats.difficultyCounts.length) {
    const difficultyCard = buildStatsCard('Náročnosť', blackAndWhite);
    stats.difficultyCounts.forEach(d => {
      // buildDifficultyBadge (songbook_render_preview.js) is the same
      // colored Easy/Normal/Hard badge used on rendered song headers -
      // returns null for the "no difficulty set" bucket, which has no
      // established badge of its own, so that one row keeps a plain label.
      const badge = buildDifficultyBadge(d.key === 'none' ? '' : d.key, blackAndWhite);
      difficultyCard.body.appendChild(buildStatsRow(d.label, d.count, stats.totalSongs, {
        showBar: true, blackAndWhite, labelElement: badge || undefined
      }));
    });
    addItem(difficultyCard.card);
  }

  if (stats.topTags.length) {
    // Bar is purely a rough visual aid here (relative to the top tag's own
    // count, via barMax) - no percentage text, since a song can have
    // several tags at once so "share of total songs" wouldn't mean much.
    const maxTagCount = stats.topTags[0][1];
    const tagsCard = buildStatsCard('Tagy', blackAndWhite);
    stats.topTags.forEach(([tag, count]) => {
      // buildTagBadge (songbook_render_preview.js) is the same pill badge
      // used for a song's tags elsewhere in the renderer.
      tagsCard.body.appendChild(buildStatsRow(tag, count, null, {
        showBar: true, barMax: maxTagCount, blackAndWhite, labelElement: buildTagBadge(tag, blackAndWhite)
      }));
    });
    const tagPhrase = slovakForm(stats.uniqueTagCount, ['unikátny tag', 'unikátne tagy', 'unikátnych tagov']);
    tagsCard.body.appendChild(buildStatsFootnote(`${stats.uniqueTagCount} ${tagPhrase}`));
    addItem(tagsCard.card);
  }

  if (stats.topArtists.length) {
    // Unlike tags/instruments, each song has exactly one artist, so (like
    // owners/categories/locks/difficulty/capo) "share of total songs" is a
    // real percentage here - bar and % are both relative to stats.totalSongs,
    // not to the top artist's own count.
    const artistCard = buildStatsCard('Top interpreti', blackAndWhite);
    stats.topArtists.forEach(([artist, count]) => {
      artistCard.body.appendChild(buildStatsRow(artist, count, stats.totalSongs, { showBar: true, blackAndWhite }));
    });
    if (stats.restArtistsCount > 0) {
      artistCard.body.appendChild(buildStatsRow('Ostatní', stats.restArtistsCount, stats.totalSongs, { showBar: true, blackAndWhite }));
    }
    addItem(artistCard.card);
  }

  // Capo and Nástroje each start as their own full masonry item (spacious -
  // matches every other card). Only if that ends up not fitting one A4 page
  // do they get squeezed together into a single masonry slot as two
  // half-width mini-cards - "squeeze based on how much free space there is",
  // not unconditionally. Deciding that requires actually measuring the
  // rendered height, which needs `page` briefly attached to the document
  // (an unattached element has no layout) - see below.
  function buildCapoCard(compact) {
    const c = buildStatsCard('Capo', blackAndWhite, compact);
    stats.capoStats.forEach(cap => {
      c.body.appendChild(buildStatsRow(cap.label, cap.count, stats.totalSongs, { showBar: !compact, blackAndWhite, compact }));
    });
    return c;
  }
  function buildInstrumentCard(compact) {
    const c = buildStatsCard('Nástroje', blackAndWhite, compact);
    stats.topInstruments.forEach(([instrument, count]) => {
      // Badges are dropped in compact mode - a pill's own padding/border
      // doesn't leave enough of the ~150px half-slot for the instrument
      // name itself, so it falls back to plain (still ellipsis-safe) text.
      c.body.appendChild(buildStatsRow(instrument, count, null, {
        compact, labelElement: compact ? undefined : buildTagBadge(instrument, blackAndWhite)
      }));
    });
    return c;
  }

  let capoCard = stats.capoStats.length ? buildCapoCard(false) : null;
  let instrumentCard = stats.topInstruments.length ? buildInstrumentCard(false) : null;
  if (capoCard) addItem(capoCard.card);
  if (instrumentCard) addItem(instrumentCard.card);

  if (capoCard && instrumentCard) {
    const measureWrapper = document.createElement('div');
    measureWrapper.style.cssText = 'position:absolute;visibility:hidden;left:-9999px;top:0;';
    document.body.appendChild(measureWrapper);
    measureWrapper.appendChild(page);
    const headerHeight = header.getBoundingClientRect().height;
    const columnsHeight = columns.getBoundingClientRect().height;
    document.body.removeChild(measureWrapper); // detaches `page` again - reattached for real via container.prepend below

    // .a4-page's own content box: 1123px tall minus Bootstrap's p-4 (24px)
    // padding on top and bottom (see createPage).
    const PAGE_CONTENT_BUDGET = 1123 - 24 * 2;
    const usedHeight = headerHeight + 16 /* header's own margin-bottom */ + columnsHeight;

    if (usedHeight > PAGE_CONTENT_BUDGET) {
      columns.removeChild(capoCard.card);
      columns.removeChild(instrumentCard.card);

      const pairItem = document.createElement('div');
      pairItem.style.cssText = 'display:flex;gap:10px;';
      capoCard = buildCapoCard(true);
      instrumentCard = buildInstrumentCard(true);
      capoCard.card.style.cssText += 'flex:1 1 0;min-width:0;';
      instrumentCard.card.style.cssText += 'flex:1 1 0;min-width:0;';
      pairItem.append(capoCard.card, instrumentCard.card);
      addItem(pairItem);
    }
  }

  // A single standalone page, same convention as real content's own page 1
  // (createPage right-aligns an odd page's footer number) - a lone centered
  // row, not paired with anything.
  const fragment = document.createDocumentFragment();
  const row = document.createElement('div');
  row.className = 'page-pair d-flex justify-content-center gap-4 mb-5';
  row.appendChild(page);
  fragment.appendChild(row);

  container.prepend(fragment);

  if (qrUrl) {
    // No card border/header/title, deliberately - just the code itself
    // (bigger than a card content item would allow) and the link it
    // encodes, pinned to the page's bottom-right corner (createPage already
    // sets position:relative on .a4-page). Built only now, after the real
    // container.prepend() above - QRCode (js/qrcode.min.js) needs its
    // container actually attached to the document to size itself; the
    // capo/instrument measurement pass above attaches `page` only
    // temporarily (and detaches it again), which is NOT enough for this.
    const qrWrapper = document.createElement('div');
    qrWrapper.style.cssText = 'position:absolute;bottom:24px;right:24px;'
      + 'display:flex;flex-direction:column;align-items:center;gap:4px;';
    const qrBox = document.createElement('div');
    qrWrapper.appendChild(qrBox);
    const qrCaption = document.createElement('div');
    qrCaption.textContent = qrUrl;
    qrCaption.style.cssText = 'font-family:system-ui,sans-serif;font-size:9px;color:#666;'
      + 'word-break:break-all;text-align:center;max-width:190px;';
    qrWrapper.appendChild(qrCaption);
    page.appendChild(qrWrapper);

    if (window.QRCode) {
      new QRCode(qrBox, { text: qrUrl, width: 190, height: 190, correctLevel: QRCode.CorrectLevel.M });
    }
  }
}


async function renderMultiple(categoryIds) {
  // Clear container once before rendering all categories
  const container = document.getElementById('a4-preview-container');
  container.innerHTML = '';

  // Always render in the songbook's own category order, regardless of the
  // order categories were clicked/selected in (selectedCategoryIds is built
  // in click order, not display order) - selection order shouldn't control
  // render order.
  const categoryOrder = new Map(songbook.songs_categories.map((cat, i) => [cat.id, i]));
  const orderedCategoryIds = [...categoryIds].sort(
    (a, b) => (categoryOrder.get(a) ?? 0) - (categoryOrder.get(b) ?? 0)
  );

  for (const id of orderedCategoryIds) {
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

  if (document.getElementById('render-toc-checkbox')?.checked) {
    addTableOfContents(container);
  } else {
    renumberRenderedPages(container);
  }

  // Runs last so its own prepend() ends up in front of the table of
  // contents too - the stats page is meant to be the very first page of the
  // book. Scoped to just the categories in this render (categoryIds), not
  // the whole songbook - see computeSongbookStats.
  if (document.getElementById('render-stats-checkbox')?.checked) {
    try {
      const blackAndWhite = document.getElementById('render-bw-checkbox')?.checked || false;
      let qrUrl = null;
      if (document.getElementById('render-stats-qr-checkbox')?.checked) {
        const customUrl = document.getElementById('stats-qr-url-input')?.value.trim();
        // Defaults to this songbook's own page - a clean canonical link
        // rather than whatever the current address bar happens to contain.
        qrUrl = customUrl || `${window.location.origin}${window.location.pathname}?id=${songbook.id}`;
      }
      await addStatsPages(container, orderedCategoryIds, blackAndWhite, qrUrl);
    } catch (err) {
      console.error('Failed to build stats page', err);
    }
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
    const printLockSaveLayoutBtn = document.getElementById('save-layout-btn');
    if (printLockSaveLayoutBtn) printLockSaveLayoutBtn.disabled = true;

    container.scrollIntoView({ behavior: 'smooth' });
  } catch (err) {
    console.error('Error printing lock', err);
    alert('Error printing lock.');
  }
}



</script>


<link href="<?= 'styles/a4_page.css?v=' . filemtime('styles/a4_page.css') ?>" rel="stylesheet">
<div id="a4-preview-container" class="d-print-none mt-5"></div>




<?php 
include("php/footer.php");
?>
