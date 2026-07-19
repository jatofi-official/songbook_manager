<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once('../php/data.php');      
$website = "admin";
$title = "Admin dashboard";
include_once('../php/auth.php'); 
include_once('../php/db.php');          
include_once('../php/functions.php'); 
include_once("../php/header.php");
include_once('../php/navigation.php');

// Admin-only access
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != true) {
    ?>
    <div class="container mt-5 text-center">
        <p class="text-danger">Táto stránka je prístupná iba administrátorom.</p>
    </div>
<?php
    include '../php/footer.php';
    exit;
}

?>
   <!-- Dashboard Header -->
    <div class="dashboard-header">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-0">Admin dashboard</h1>
            <p class="text-muted mb-0">Important information about page activity</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center w-100 w-md-auto">

        </div>
    </div>
    </div>

    <!-- Main Part -->
    <div class="container">

    <?php
    $songsCounts = get_number_of_songs($mysqli);

    // Total songs
    $totalSongs = $songsCounts['total'];

    // Fixed songs
    $finishedSongs = $songsCounts['fixed'];
    ?>
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="../songs.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo $totalSongs ?></div>
                    <div class="label">Total Songs</div>
                </div>
            </a>
        </div>



        <div class="col-6 col-md-3 mb-3 mb-md-0">
            <a href="log.php" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_recent_activity_number($mysqli);?></div>
                    <div class="label">Recent activity</div>
                </div>
            </a>
        </div>

        <?php

        // Progress percentage
        $progressPercent = ($totalSongs > 0) ? ($finishedSongs / $totalSongs) * 100 : 0;

        // Example: Adjust the session variable name if different
        $isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] == true;
        $hrefLink = "../songs.php?finished=0";
        ?>

        <div class="col-6 col-md-3 mb-3 mb-md-0">
        <a href="<?= htmlspecialchars($hrefLink) ?>" class="stat-card-link" style="text-decoration: none;">
            <div class="stat-card p-3 d-flex flex-column justify-content-between" style="height: 100%;">
            <div class="d-flex justify-content-center align-items-baseline mb-2">
                <div class="text-muted small"><?= $finishedSongs ?> / <?= $totalSongs ?></div>
            </div>

            <div class="progress mb-2" style="height: 20px;">
                <div class="progress-bar bg-success" role="progressbar"
                style="width: <?= round($progressPercent) ?>%;"
                aria-valuenow="<?= round($progressPercent) ?>"
                aria-valuemin="0" aria-valuemax="100">
                <?= round($progressPercent) ?>%
                </div>
            </div>

            <div class="label text-center">Finished Songs</div>
            </div>
        </a>
        </div>

        <div class="col-6 col-md-3">
            <a href="../users.php?sort=new" class="stat-card-link">
                <div class="stat-card p-3">
                    <div class="number"><?php echo get_recent_activity_number($mysqli,"new_user"); ?></div>
                    <div class="label">New users</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Graphs -->
    <div class="row">
        <!-- Activity Last Week -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Activity past week</div>
                <div class="card-body">
                    <div class="chart-placeholder" style="height:250px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;">
                        Graph Placeholder
                    </div>
                </div>
            </div>
        </div>

        <!-- User Logins Last Week -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">Logins pasts week</div>
                <div class="card-body">
                    <div class="chart-placeholder" style="height:250px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;">
                        Graph Placeholder
                    </div>
                </div>
            </div>
        </div>
    </div>



<!-- Import: OpenRouter AI nastavenia -->
<?php
$orModel = get_app_setting($mysqli, 'openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free');
$orKeySet = trim(get_app_setting($mysqli, 'openrouter_api_key', '')) !== '';
?>
<div class="row">
  <div class="col-12 mb-4">
    <div class="card">
      <div class="card-header">Import — AI tagy (OpenRouter)</div>
      <div class="card-body">
        <div id="orMsg"></div>
        <div class="mb-2">
          <label class="form-label">OpenRouter API kľúč
            <span class="badge <?= $orKeySet ? 'bg-success' : 'bg-secondary' ?>"><?= $orKeySet ? 'nastavený' : 'nenastavený' ?></span>
          </label>
          <input type="password" id="orKey" class="form-control" autocomplete="off"
                 placeholder="<?= $orKeySet ? '•••••••• (ponechaj prázdne = nemeniť)' : 'sk-or-...' ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">Model (voľný text)</label>
          <input type="text" id="orModel" class="form-control" value="<?= htmlspecialchars($orModel) ?>"
                 placeholder="napr. meta-llama/llama-3.3-70b-instruct:free">
          <div class="form-text">
            Musí byť <strong>CHAT</strong> model (nie embedding!). Free príklady:
            <code>meta-llama/llama-3.3-70b-instruct:free</code>, <code>google/gemini-2.0-flash-exp:free</code>.
            Free modely končia na <code>:free</code>.
          </div>
        </div>
        <button type="button" id="orSave" class="btn btn-primary btn-sm">Uložiť</button>
      </div>
    </div>
  </div>
</div>

<!-- Import: posledné importy + AI metriky -->
<div class="row">
  <div class="col-12 mb-4">
    <div class="card">
      <div class="card-header">Import — posledné (AI metriky)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Kedy</th><th>Pieseň</th><th>Zdroj</th><th>Model</th><th>ms</th><th>tokeny (in/out)</th><th>AI chyba</th></tr></thead>
          <tbody>
          <?php
          $ilRes = $mysqli->query(
            "SELECT il.created_at, il.source_type, il.ai_model, il.ai_ms,
                    il.ai_prompt_tokens, il.ai_completion_tokens, il.ai_error, s.title
             FROM import_log il LEFT JOIN songs s ON s.id = il.song_id
             ORDER BY il.id DESC LIMIT 20");
          if ($ilRes && $ilRes->num_rows) {
            while ($r = $ilRes->fetch_assoc()) {
              $err = trim((string)($r['ai_error'] ?? ''));
              echo '<tr><td class="small">' . htmlspecialchars($r['created_at']) . '</td>'
                 . '<td>' . htmlspecialchars($r['title'] ?? '—') . '</td>'
                 . '<td class="small">' . htmlspecialchars($r['source_type']) . '</td>'
                 . '<td class="small">' . htmlspecialchars($r['ai_model'] ?: '—') . '</td>'
                 . '<td>' . ((int)$r['ai_ms'] ?: '—') . '</td>'
                 . '<td class="small">' . (int)$r['ai_prompt_tokens'] . ' / ' . (int)$r['ai_completion_tokens'] . '</td>'
                 . '<td class="small ' . ($err !== '' ? 'text-danger' : 'text-muted') . '">'
                 . htmlspecialchars($err !== '' ? $err : '—') . '</td></tr>';
            }
          } else {
            echo '<tr><td colspan="7" class="text-muted text-center">Žiadne importy zatiaľ.</td></tr>';
          }
          ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('orSave').addEventListener('click', function () {
  const fd = new FormData();
  fd.append('openrouter_api_key', document.getElementById('orKey').value);
  fd.append('openrouter_model', document.getElementById('orModel').value);
  fetch('../php/subpage/save_app_settings.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      const m = document.getElementById('orMsg');
      if (d.success) {
        m.innerHTML = '<div class="alert alert-success py-2">Uložené. Obnovujem...</div>';
        setTimeout(() => location.reload(), 500); // reload -> badge "nastavený" + metriky
      } else {
        m.innerHTML = '<div class="alert alert-danger py-2">' + (d.error || 'Chyba') + '</div>';
      }
    })
    .catch(e => { document.getElementById('orMsg').innerHTML = '<div class="alert alert-danger py-2">' + e.message + '</div>'; });
});
</script>

<style>
.hover-scale:hover {
    transform: scale(1.03);
}
</style>


<?php
include('../php/footer.php');
?>