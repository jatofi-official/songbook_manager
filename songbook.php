<?php
session_start();
include('php/db.php');
include('php/auth.php');
include('php/data.php');
$website = "songbooks";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);




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
            <p class="text-muted small">Select songs to apply batch actions (e.g. delete or move).</p>
            <button class="btn btn-danger w-100 mb-2" disabled>🗑️ Delete Selected</button>
            <button class="btn btn-secondary w-100" disabled>📁 Move Selected</button>
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
              <input class="form-check-input" type="checkbox" value="" id="render-debug-checkbox">
              <label class="form-check-label" for="render-debug-checkbox">
                🛠️ Render Debug Lines
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="" id="render-numbering-checkbox">
              <label class="form-check-label" for="render-numbering-checkbox">
                Render Category Number
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

        

        <?php endif; ?>
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



</script>



<script>
// Inject PHP data safely
const categorySelectOptions = <?php echo json_encode($jsCategorySelect, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;


function createSongListItem(song, categoryId) {
  const li = document.createElement('li');
  li.className = 'list-group-item d-flex justify-content-between align-items-center';

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

  // Only show ❌ if user is admin
    if (isCurrentUserAdmin()) {
    const removeBtn = document.createElement('button');
    removeBtn.className = 'btn btn-sm btn-outline-danger';
    removeBtn.textContent = '❌';
    removeBtn.title = 'Remove this song';
    removeBtn.onclick = () => {
      const category = songbook.songs_categories.find(c => c.id === categoryId);
      if (!category) return;

      category.songs = category.songs.filter(s => s.id !== song.id);
      renderCategories();
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
            const generateBtn = document.createElement('button');
            generateBtn.className = 'btn btn-sm btn-outline-success';
            generateBtn.textContent = '⚙️ Generate Layout';
            generateBtn.addEventListener('click', () => {
                handleGenerateForCategory(category);
            });

            const loadBtn = document.createElement('button');
            loadBtn.className = 'btn btn-sm btn-outline-primary me-2'; // margin-right for spacing
            loadBtn.textContent = '📥 Load order';
            loadBtn.addEventListener('click', () => {
                handleLoadForCategory(category);
            });

            headerDiv.appendChild(loadBtn);
            headerDiv.appendChild(generateBtn);
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
        addCategoryBtn.id = 'add-category-btn';
        addCategoryBtn.className = 'btn btn-outline-primary';
        addCategoryBtn.textContent = '➕ Add Category';

        buttonsDiv.appendChild(addSongBtn);
        buttonsDiv.appendChild(addCategoryBtn);

        container.appendChild(buttonsDiv);
    }
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
const saveBtn = document.getElementById('saveSongbookBtn');
if (saveBtn) {
  document.getElementById('saveSongbookBtn').addEventListener('click', () => {
    // Prepare categories array for payload
    const categories = songbook.songs_categories.map(cat => ({
      id: cat.id,
      name: cat.name
    }));

    // Prepare songs array for payload (flatten songs with their category ids)
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

    fetch('php/update_songbook.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(res => res.json())
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

async function handleGenerateForCategory(category) {
  const ids = category.songs.map(s => s.id);
  const songs = await fetchSongsByIds(ids);

  const ordered = orderSongs(songs, measurer);
  orderedIds = getOrderedIds(ordered);
  currentCategory = category;

  // ⬇️ Just call beginRender with IDs now
  beginRender(orderedIds, category);
}


async function beginRender(orderedIds, category) {
  const debugMode = document.getElementById('render-debug-checkbox').checked;
  const includeRoman = document.getElementById('render-numbering-checkbox')?.checked;
  const categoryTitle = includeRoman
    ? `${toRoman(category.id)}. ${category.name}`
    : category.name;

  // ⬅️ Fetch the actual songs here
  const songs = await fetchSongsByIds(orderedIds);

  renderA4Preview(songs, orderedIds, {
    debug: debugMode,
    category: categoryTitle
  });

  const container = document.getElementById('a4-preview-container');
  const exportBtn = document.getElementById('export-pdf-btn');
  const saveLayoutBtn = document.getElementById('save-layout-btn');

  setTimeout(() => {
    const hasPages = container.querySelectorAll('.a4-page').length > 0;
    exportBtn.disabled = !hasPages;
    saveLayoutBtn.disabled = !hasPages;
  }, 100);
}



async function handleLoadForCategory(category) {
  try {
    const songbookId = songbook.id;
    const categoryId = category.id;

    const response = await fetch(`php/load_layout.php?songbook_id=${songbookId}&category_id=${categoryId}`, {
      method: 'GET',
      credentials: 'same-origin'
    });

    const data = await response.json();

    if (data.success) {
      orderedIds = data.ordered_ids;
      currentCategory = category;

      // console.log(`Loaded layout for category ${categoryId}:`, orderedIds);

      // ⬇️ Only call with IDs now
      beginRender(orderedIds, category);
    } else {
      console.warn("Load layout failed:", data.message);
      alert("No saved layout found for this category.");
    }
  } catch (error) {
    console.error("Error loading layout:", error);
    alert("Error loading layout.");
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
