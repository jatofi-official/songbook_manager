<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "songbooks";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
else{
    exit;
}

// Get song details
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $songbook = get_songbook($mysqli, $id);

    // Songbook data
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


    // Group badge (only show if groupName is not empty)
    $groupHtml = $groupName !== '' ? "<span class=\"org-badge\">$groupName</span>" : '';


    // print_r($songbook);
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
            <h3 class="me-2 mb-0" id="songbook-name-text"><?php echo $name;?></h3>
          </div>
          <input type="text" class="form-control form-control-lg d-none" id="songbook-name-input" value="My Favorite Songbook">
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
          <p class="me-2 mb-0" id="description-text"><?php echo $description;?></p>
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
    <div class="mb-2">
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





            <!-- Add category/song buttons -->
            <div class="text-end">
              <button class="btn btn-outline-secondary me-2">➕ Add Song</button>
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



<script>
function toggleEdit(idPrefix) {
  const textEl = document.getElementById(idPrefix + '-text');
  const inputEl = document.getElementById(idPrefix + '-input');

  if (textEl.classList.contains('d-none')) {
    textEl.classList.remove('d-none');
    inputEl.classList.add('d-none');
  } else {
    textEl.classList.add('d-none');
    inputEl.classList.remove('d-none');
    inputEl.focus();
  }
}

function toggleCategoryEdit(index) {
  const textEl = document.getElementById(`category-name-${index}-text`);
  const inputEl = document.getElementById(`category-name-${index}-input`);

  if (textEl.classList.contains('d-none')) {
    textEl.classList.remove('d-none');
    inputEl.classList.add('d-none');
  } else {
    textEl.classList.add('d-none');
    inputEl.classList.remove('d-none');
    inputEl.focus();
  }
}

let categoryCounter = 2; // Adjust this if you have more preloaded categories

document.getElementById("add-category-btn").addEventListener("click", function () {
  categoryCounter++;
  const container = document.getElementById("category-container");

  const categoryHTML = `
    <div class="mb-5 category-block">
      <div class="mb-2 d-flex align-items-center justify-content-between">
        <div>
          <h5 class="mb-0 d-none" id="category-name-${categoryCounter}-text">New Category</h5>
          <input type="text" class="form-control mt-2" id="category-name-${categoryCounter}-input" placeholder="New Category">
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="toggleCategoryEdit(${categoryCounter})">✏️</button>
      </div>
      <ul class="list-group">
        <li class="list-group-item d-flex align-items-center">
          <input class="form-check-input me-2" type="checkbox">
          <input type="text" class="form-control" placeholder="New Song">
        </li>
      </ul>
    </div>
  `;

  container.insertAdjacentHTML("beforeend", categoryHTML);
});
</script>

<?php 

include("php/footer.php");
?>
