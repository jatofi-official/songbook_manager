<?php
session_start();
$title = "Import piesní";
$website = "import";
$phpBase = 'php/';
include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'auth.php';
load_user_settings_into_session($mysqli);

$loggedIn      = isset($_SESSION['user']);
$importEnabled = !empty($_SESSION['import_enabled']);

include $phpBase . 'header.php';
include $phpBase . 'navigation.php';

// mapa kategórií pre výsledky ([id => name])
$catMap = get_all_categories($mysqli);
?>

<main class="container py-4" style="max-width: 900px;">
  <h1 class="mb-3"><i class="bi bi-cloud-download me-2"></i>Import piesní</h1>

<?php if (!$loggedIn): ?>
  <div class="alert alert-warning">Pre import sa musíš <a href="<?= $projectUrl ?>/user_settings.php">prihlásiť</a>.</div>
<?php elseif (!$importEnabled): ?>
  <div class="alert alert-info">
    Import feature nie je zapnutý. Zapni ho v
    <a href="<?= $projectUrl ?>/user_settings.php">User settings → Appearance → Import feature</a>.
  </div>
<?php else: ?>

  <p class="text-muted">
    Importované piesne po uložení otvor a skontroluj v editore.
  </p>

  <div class="mb-3" style="max-width: 420px;">
    <label for="imp-visibility" class="form-label">Viditeľnosť importovaných piesní</label>
    <select id="imp-visibility" class="form-select">
      <option value="bez_kontroly" selected>Bez kontroly (viditeľné, ráta sa ako nedokončené)</option>
      <option value="private">Private (len ja)</option>
    </select>
    <div class="form-text">Public sa dá nastaviť len ručne v editore piesne — import nikdy nezverejní automaticky.</div>
  </div>

  <div class="mb-3" style="max-width: 420px;">
    <label for="imp-category" class="form-label">Kategória</label>
    <select id="imp-category" class="form-select">
      <option value="" selected>Auto-detekcia</option>
      <?php foreach ($catMap as $cid => $cname): ?>
        <option value="<?= (int)$cid ?>"><?= htmlspecialchars($cname) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="form-text">Kategória určuje aj slovník tagov — ak ju zvolíš, platí pre všetky piesne tohto importu.</div>
  </div>

  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="imp-enrich" checked>
    <label class="form-check-label" for="imp-enrich">Dohľadať chýbajúcich autorov z internetu</label>
    <div class="form-text">Robí web request na každú pieseň bez autora — pri veľkých PDF spevníkoch vypni.</div>
  </div>

  <ul class="nav nav-tabs" id="importTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-internet" type="button">🔍 Internet</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-link" type="button">🔗 Link</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-upload" type="button">📂 Upload</button></li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom p-3 mb-4">
    <!-- INTERNET -->
    <div class="tab-pane fade show active" id="tab-internet">
      <form class="imp-form" data-mode="internet">
        <div class="mb-2">
          <label class="form-label">Názov piesne *</label>
          <input type="text" name="title" class="form-control" required placeholder="napr. Sklíčka">
        </div>
        <div class="mb-2">
          <label class="form-label">Autor / interpret</label>
          <input type="text" name="artist" class="form-control" placeholder="napr. Modus">
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Nájsť a importovať</button>
      </form>
    </div>

    <!-- LINK -->
    <div class="tab-pane fade" id="tab-link">
      <form class="imp-form" data-mode="link">
        <div class="mb-2">
          <label class="form-label">URL piesne *</label>
          <input type="url" name="url" class="form-control" required placeholder="https://www.supermusic.cz/piesen.php?idpiesne=...">
          <div class="form-text">Podporované: supermusic, brnkni.cz, pisnicky-akordy.cz, 4334.sk, zpevnik.proscholy.cz, mladeznickyspevnik.sk (+ generic fallback).</div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-link-45deg me-1"></i>Importovať z linku</button>
      </form>
    </div>

    <!-- UPLOAD -->
    <div class="tab-pane fade" id="tab-upload">
      <form class="imp-form" data-mode="upload" enctype="multipart/form-data">
        <div class="mb-2">
          <label class="form-label">Súbor (.txt, .md, .pdf)</label>
          <input type="file" name="file" class="form-control" accept=".txt,.md,.pdf,text/plain,application/pdf">
          <div class="form-text">Text: viac piesní oddeľ riadkom <code>---</code>.
            PDF spevníky (notové aj textové) sa rozdelia na piesne automaticky.</div>
        </div>
        <div class="mb-2">
          <label class="form-label">... alebo vlož text</label>
          <textarea name="text" class="form-control" rows="6" placeholder="Title&#10;Artist&#10;&#10;C        G&#10;text piesne..."></textarea>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Importovať zo súboru</button>
      </form>
    </div>
  </div>

  <!-- PROGRESS -->
  <div id="imp-progress" class="d-none">
    <div class="d-flex align-items-center mb-2">
      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
      <strong id="imp-step-label">Spúšťam...</strong>
      <span id="imp-saved-count" class="badge bg-success ms-2 d-none"></span>
    </div>
    <div class="progress mb-3" style="height: 8px;">
      <div id="imp-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
    </div>
  </div>

  <!-- RESULTS -->
  <div id="imp-results"></div>

  <script>
  const CAT = <?= json_encode($catMap, JSON_UNESCAPED_UNICODE) ?>;
  const projectUrl = <?= json_encode($projectUrl) ?>;
  const progEl = document.getElementById('imp-progress');
  const labelEl = document.getElementById('imp-step-label');
  const barEl = document.getElementById('imp-bar');
  const resEl = document.getElementById('imp-results');
  let savedCount = 0;

  function esc(s){ return (s??'').toString().replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

  function addResult(d){
    const cat = CAT[d.category] || ('#' + d.category);
    const conf = d.confidence != null ? Math.round(d.confidence*100)+'%' : '';
    const yt = d.youtube ? `<a href="${esc(d.youtube)}" target="_blank" rel="noopener"><i class="bi bi-youtube text-danger"></i></a>` : '<span class="text-muted small">bez YT</span>';
    const tags = d.tags ? `<span class="badge bg-light text-dark border ms-1">${esc(d.tags)}</span>` : '';
    const aim = (d.ai_ms ? `<span class="text-muted small ms-2">AI ${d.ai_ms}ms · ${d.ai_tokens||0} tok</span>` : '');
    const card = document.createElement('div');
    card.className = 'alert alert-success d-flex justify-content-between align-items-center';
    card.innerHTML = `
      <div>
        <strong>${esc(d.title)}</strong> ${d.artist ? '– ' + esc(d.artist) : ''}
        <span class="badge bg-secondary ms-1">${esc(cat)}</span>
        <span class="badge bg-info text-dark ms-1">conf ${conf}</span>
        ${tags}
        <div class="small mt-1">${yt}${aim}</div>
      </div>
      <a class="btn btn-sm btn-outline-primary" href="${projectUrl}/song.php?id=${d.song_id}"
         target="_blank" rel="noopener" title="Otvorí sa v novom tabe — import beží ďalej">
        <i class="bi bi-pencil"></i> Skontrolovať draft
      </a>`;
    resEl.prepend(card);
  }

  function addDuplicate(d){
    const card = document.createElement('div');
    card.className = 'alert alert-warning d-flex justify-content-between align-items-center';
    card.innerHTML = `
      <div><i class="bi bi-exclamation-triangle me-1"></i>
        Už existuje: <strong>${esc(d.title||'')}</strong> — nebolo pridané znova.</div>
      <a class="btn btn-sm btn-outline-dark" href="${projectUrl}/song.php?id=${d.song_id}"
         target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right"></i> Otvoriť
      </a>`;
    resEl.prepend(card);
  }

  function addError(msg){
    const el = document.createElement('div');
    el.className = 'alert alert-danger';
    el.textContent = msg;
    resEl.prepend(el);
  }
  function addInfo(msg){
    const el = document.createElement('div');
    el.className = 'alert alert-secondary py-2';
    el.textContent = msg;
    resEl.prepend(el);
  }

  document.querySelectorAll('.imp-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const mode = form.dataset.mode;
      const fd = new FormData(form);
      fd.append('mode', mode);
      fd.append('visibility', document.getElementById('imp-visibility').value);
      fd.append('category', document.getElementById('imp-category').value);
      fd.append('enrich_authors', document.getElementById('imp-enrich').checked ? '1' : '');

      progEl.classList.remove('d-none');
      labelEl.textContent = 'Spúšťam...';
      barEl.style.width = '5%';
      savedCount = 0;
      const scEl = document.getElementById('imp-saved-count');
      scEl.classList.add('d-none'); scEl.textContent = '';
      form.querySelectorAll('button').forEach(b => b.disabled = true);

      try {
        const resp = await fetch('php/subpage/import_process.php', { method: 'POST', body: fd });
        const reader = resp.body.getReader();
        const dec = new TextDecoder();
        let buf = '';
        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          buf += dec.decode(value, { stream: true });
          let idx;
          while ((idx = buf.indexOf('\n\n')) >= 0) {
            const raw = buf.slice(0, idx); buf = buf.slice(idx + 2);
            const line = raw.split('\n').find(l => l.startsWith('data: '));
            if (!line) continue;
            let d; try { d = JSON.parse(line.slice(6)); } catch(_) { continue; }
            handleEvent(d);
          }
        }
      } catch (err) {
        addError('Chyba spojenia: ' + err.message);
      } finally {
        progEl.classList.add('d-none');
        form.querySelectorAll('button').forEach(b => b.disabled = false);
        barEl.style.width = '0%';
      }
    });
  });

  function handleEvent(d){
    if (d.status === 'progress') {
      labelEl.textContent = d.label || '...';
      if (d.step && d.total) barEl.style.width = Math.round(d.step/d.total*100) + '%';
    } else if (d.status === 'info') {
      addInfo(d.label);
    } else if (d.status === 'saved') {
      addResult(d);
      savedCount++;
      const scEl = document.getElementById('imp-saved-count');
      scEl.textContent = 'Uložené: ' + savedCount;
      scEl.classList.remove('d-none');
    } else if (d.status === 'duplicate') {
      addDuplicate(d);
    } else if (d.status === 'error') {
      addError(d.error || 'Neznáma chyba.');
    } else if (d.status === 'complete') {
      labelEl.textContent = 'Hotovo.';
      barEl.style.width = '100%';
    }
  }
  </script>

<?php endif; ?>
</main>

<?php include $phpBase . 'footer.php'; ?>
