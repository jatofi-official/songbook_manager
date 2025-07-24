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

    $groupHtml = $groupName !== '' ? "<span class=\"org-badge\">".htmlspecialchars($groupName)."</span>" : '';

    // Prepare songbook data to pass to JS
    // We'll include categories and songs info, but need to fetch song details as well for JS
    $jsCategories = [];
    foreach ($songbook["songs_categories"] as $category) {
        $cat = [
            "id" => $category["id"] ?? null,
            "name" => $category["name"],
            "songs" => []
        ];
        foreach ($category["songs"] as $songId) {
            $song = get_song_title($mysqli, $songId);
            // fetch user for owner display
            $user = get_user_by_username($mysqli, $song['owner']);
            $cat["songs"][] = [
                "id" => $song["id"],
                "title" => $song["title"],
                "artist" => $song["artist"],
                "owner" => [
                    "id" => $user ? (int)$user["id"] : null,
                    "name" => $user ? $user["name"] : null,
                    "username" => $song['owner']
                ]
            ];
        }
        $jsCategories[] = $cat;
    }

    // Categories for select (id => name) from $songbook['categories']
    $jsCategorySelect = $songbook['categories'];

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
            <!-- JS will render categories and songs here -->
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

<script>
// Inject PHP data safely
const categoriesData = <?php echo json_encode($jsCategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const categorySelectOptions = <?php echo json_encode($jsCategorySelect, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

function createSongListItem(song) {
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';

    const leftDiv = document.createElement('div');
    leftDiv.textContent = song.title + (song.artist ? " – " + song.artist : "");
    li.appendChild(leftDiv);

    const rightDiv = document.createElement('div');
    if (song.owner && song.owner.id && song.owner.name) {
        const a = document.createElement('a');
        a.href = `user.php?id=${song.owner.id}`;
        a.className = 'badge bg-primary text-decoration-none';
        a.textContent = song.owner.name;
        rightDiv.appendChild(a);
    } else if (song.owner && song.owner.username) {
        const span = document.createElement('span');
        span.className = 'badge bg-secondary';
        span.textContent = song.owner.username;
        rightDiv.appendChild(span);
    }
    li.appendChild(rightDiv);

    return li;
}

function renderCategories() {
    const container = document.getElementById('category-container');
    container.innerHTML = '';

    categoriesData.forEach(category => {
        const catDiv = document.createElement('div');
        catDiv.className = 'mb-5 category-block';

        const headerDiv = document.createElement('div');
        headerDiv.className = 'mb-2 d-flex justify-content-between align-items-center';

        const h5 = document.createElement('h5');
        h5.className = 'mb-0';
        h5.textContent = category.name;

        headerDiv.appendChild(h5);
        catDiv.appendChild(headerDiv);

        const ul = document.createElement('ul');
        ul.className = 'list-group';

        category.songs.forEach(song => {
            const li = createSongListItem(song);
            ul.appendChild(li);
        });

        catDiv.appendChild(ul);
        container.appendChild(catDiv);
    });

    // Add the buttons after categories
    const buttonsDiv = document.createElement('div');
    buttonsDiv.className = 'text-end';

    const addSongBtn = document.createElement('button');
    addSongBtn.className = 'btn btn-outline-secondary me-2';
    addSongBtn.setAttribute('data-bs-toggle', 'modal');
    addSongBtn.setAttribute('data-bs-target', '#addSongModal');
    addSongBtn.textContent = '➕ Add Song';

    const addCategoryBtn = document.createElement('button');
    addCategoryBtn.id = 'add-category-btn';
    addCategoryBtn.className = 'btn btn-outline-primary';
    addCategoryBtn.textContent = '➕ Add Category';

    buttonsDiv.appendChild(addSongBtn);
    buttonsDiv.appendChild(addCategoryBtn);

    container.appendChild(buttonsDiv);
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

// Your existing event listeners for search and add song:

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
