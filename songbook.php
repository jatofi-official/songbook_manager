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
<!-- Modal -->
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
          <option value="">Select Category</option>
          <?php foreach ($songbook['categories'] as $catId => $catName): ?>
            <option value="<?= (int)$catId ?>"><?= htmlspecialchars($catName) ?></option>
          <?php endforeach; ?>
        </select>

        <div class="ms-auto">
          <button id="addSongToCategoryBtn" class="btn btn-success me-2" disabled>Add to Category</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('searchBtn').addEventListener('click', () => {
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

      // Create a list of results with radio buttons for selection
      const ul = document.createElement('ul');
      ul.classList.add('list-group');

        songs.forEach(song => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center';

        const radioId = `songRadio_${song.id}`;

        // Create radio input
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'selectedSong';
        radio.value = song.id;
        radio.id = radioId;
        radio.classList.add('me-2');

        // Create label wrapping the radio and song text
        const label = document.createElement('label');
        label.setAttribute('for', radioId);
        label.classList.add('mb-0', 'd-flex', 'align-items-center', 'w-100', 'cursor-pointer'); // full width + pointer cursor

        label.appendChild(radio);

        const text = document.createElement('span');
        text.textContent = `${song.title} ${song.artist ? '– ' + song.artist : ''}`;

        label.appendChild(text);
        li.appendChild(label);

        ul.appendChild(li);
        });


      container.appendChild(ul);

      // Enable Add to Category only when a song is selected
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
});

document.getElementById('addSongToCategoryBtn').addEventListener('click', () => {
  const selectedSongRadio = document.querySelector('input[name="selectedSong"]:checked');
  if (!selectedSongRadio) return alert('Please select a song.');

  const songId = selectedSongRadio.value;
  const category = document.getElementById('categorySelect').value;

  if (!category) return alert('Please select a category.');

  console.log('Selected Song ID:', songId, 'Category ID:', category);

  // TODO: Replace console.log with AJAX to add song to songbook category in future

  // Optionally, close modal or reset selections
  // bootstrap.Modal.getInstance(document.getElementById('addSongModal')).hide();
});

</script>


<?php 
include("php/footer.php");
?>
