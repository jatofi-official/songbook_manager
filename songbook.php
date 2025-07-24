<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "songbooks";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

if (!isset($_SESSION["admin_view"]) || $_SESSION["admin_view"] !== true) {
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $songbook = get_songbook($mysqli, $id);

    $name = $songbook["name"];
    $description = $songbook["description"];

    $groupName = null;
    if (!empty($songbook["group_id"])) {
        $stmt = $mysqli->prepare("SELECT name FROM group_list WHERE id = ?");
        $stmt->bind_param("i", $songbook["group_id"]);
        $stmt->execute();
        $stmt->bind_result($groupName);
        $stmt->fetch();
        $stmt->close();
    }

    print_r($songbook);

    $groupHtml = $groupName !== '' ? "<span class=\"org-badge\">$groupName</span>" : '';
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
        <div class="col-md-2 text-end mt-3 mt-md-0">
          <button class="btn btn-success w-100">💾 Save</button>
        </div>
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
            <?php foreach ($songbook["songs_categories"] as $category): ?>
            <div class="mb-5 category-block">
                <div class="mb-2 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= htmlspecialchars($category["name"]) ?></h5>
                </div>
                <ul class="list-group">
                <?php foreach ($category["songs"] as $songId):
                    $song = get_song_title($mysqli, $songId);
                    $user = get_user_by_username($mysqli, $song['owner']); ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <?= htmlspecialchars($song["title"]) ?>
                        <?php if (!empty($song["artist"])): ?>
                        – <?= htmlspecialchars($song["artist"]) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($user): ?>
                        <a href="user.php?id=<?= (int)$user['id'] ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars($user['name']) ?></a>
                        <?php else: ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars($song['owner']) ?></span>
                        <?php endif; ?>
                    </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>

            <div class="text-end">
              <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#addSongModal">➕ Add Song</button>
              <button id="add-category-btn" class="btn btn-outline-primary">➕ Add Category</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions Sidebar -->
      <div class="col-lg-3 mt-4 mt-lg-0">
        <div class="card shadow-sm">
          <div class="card-header bg-light">
            ⚙️ Actions
          </div>
          <div class="card-body">
            <p class="text-muted small">Select songs to apply batch actions (e.g. delete or move).</p>
            <button class="btn btn-danger w-100 mb-2" disabled>🗑️ Delete Selected</button>
            <button class="btn btn-secondary w-100" disabled>📁 Move Selected</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Song Modal -->
<div class="modal fade" id="addSongModal" tabindex="-1" aria-labelledby="addSongModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addSongModalLabel">Add Song to Songbook Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Search Controls -->
        <div class="row g-2 mb-3">
          <div class="col-md-7">
            <input type="text" id="song-search-input" class="form-control" placeholder="Search songs by title or artist">
          </div>
          <div class="mb-3">
            <label for="categorySelect" class="form-label">Select Category</label>
            <select id="categorySelect" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($songbook['categories'] as $catId => $catName): ?>
                <option value="<?= (int)$catId ?>"><?= htmlspecialchars($catName) ?></option>
                <?php endforeach; ?>
            </select>
            </div>

          <div class="col-md-2">
            <button id="search-button" class="btn btn-primary w-100">Search</button>
          </div>
        </div>

        <!-- Search Results -->
        <div id="search-results" style="max-height: 350px; overflow-y: auto;">
          <!-- Results appear here -->
        </div>
      </div>

      <div class="modal-footer">
        <button id="add-to-category-btn" class="btn btn-success" disabled>Add to Category</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const searchInput = document.getElementById('song-search-input');
  const categorySelect = document.getElementById('category-select');
  const searchButton = document.getElementById('search-button');
  const resultsDiv = document.getElementById('search-results');
  const addBtn = document.getElementById('add-to-category-btn');

  let selectedSong = null;

  // Clear results and selection
  function clearResults() {
    resultsDiv.innerHTML = '';
    selectedSong = null;
    addBtn.disabled = true;
  }

  // Render search results with radio buttons for selection
  function renderResults(songs) {
    clearResults();
    if (songs.length === 0) {
      resultsDiv.innerHTML = '<p>No songs found.</p>';
      return;
    }
    const list = document.createElement('ul');
    list.className = 'list-group';

    songs.forEach(song => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center';

      // Unique ID for radio input
      const radioId = 'song-radio-' + song.id;

      li.innerHTML = `
        <div>
          <input type="radio" name="selectedSong" id="${radioId}" value="${song.id}" style="margin-right: 10px;">
          <label for="${radioId}">
            <strong>${song.title}</strong> – <em>${song.artist || ''}</em>
          </label>
        </div>
      `;

      list.appendChild(li);
    });

    resultsDiv.appendChild(list);

    // Listen for radio selection
    list.querySelectorAll('input[name="selectedSong"]').forEach(radio => {
      radio.addEventListener('change', e => {
        const songId = e.target.value;
        selectedSong = songs.find(s => s.id == songId) || null;
        addBtn.disabled = !selectedSong || !categorySelect.value;
      });
    });
  }

  // Handle Search button click
  searchButton.addEventListener('click', () => {
    const query = searchInput.value.trim();
    if (!query) {
      alert('Please enter a search term.');
      return;
    }
    fetch(`search_songs.php?searchQuery=${encodeURIComponent(query)}&category=all`)
      .then(res => res.json())
      .then(songs => renderResults(songs))
      .catch(err => {
        console.error(err);
        alert('Failed to fetch songs.');
      });
  });

  // Enable/disable Add button based on selection and category
  categorySelect.addEventListener('change', () => {
    addBtn.disabled = !selectedSong || !categorySelect.value;
  });

  // Add to Category button logs selected song & category
  addBtn.addEventListener('click', () => {
    if (!selectedSong) {
      alert('Select a song first.');
      return;
    }
    if (!categorySelect.value) {
      alert('Select a category first.');
      return;
    }
    console.log('Adding song:', selectedSong);
    console.log('To category ID:', categorySelect.value);
    // You can later send this data to your backend to update the songbook.
  });

  // Clear modal when hidden
  const addSongModal = document.getElementById('addSongModal');
  addSongModal.addEventListener('hidden.bs.modal', () => {
    searchInput.value = '';
    categorySelect.selectedIndex = 0;
    clearResults();
  });
})();
</script>



<?php 
include("php/footer.php");
?>
