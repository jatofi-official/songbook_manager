<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Songs</title>
  <style>
    body {
      font-family: sans-serif;
      margin: 0;
      padding: 0;
      background: #f7f7f7;
    }

    .container {
      display: flex;
      flex-direction: row;
      gap: 1rem;
      padding: 1rem;
      flex-wrap: wrap;
    }

    .song-list {
      flex: 1 1 300px;
      min-width: 280px;
      background: #fff;
      border-radius: 8px;
      padding: 1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .song-card {
      border-bottom: 1px solid #ccc;
      padding: 0.5rem 0;
    }

    .song-card:last-child {
      border-bottom: none;
    }

    .filters {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .filters select, .filters input {
      padding: 0.5rem;
      font-size: 1rem;
    }

    @media (max-width: 600px) {
      .container {
        flex-direction: column;
        padding: 0.5rem;
      }

      .song-list {
        width: 100%;
        padding: 0.75rem;
      }

      .filters {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="song-list">
    <div class="filters">
      <!-- Search -->
      <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

      <!-- Category -->
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach ($categories as $catId => $catName): ?>
          <option value="<?= $catId ?>" <?= ($category == $catId) ? 'selected' : '' ?>><?= htmlspecialchars($catName) ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Instruments -->
      <select name="instruments">
        <option value="">All Instruments</option>
        <?php foreach ($instrumentsList as $instr): ?>
          <option value="<?= $instr ?>" <?= in_array($instr, $instruments) ? 'selected' : '' ?>><?= htmlspecialchars($instr) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Songs -->
    <?php foreach ($songs as $song): ?>
      <div class="song-card">
        <strong><?= htmlspecialchars($song['title']) ?></strong><br>
        <small><?= htmlspecialchars($song['artist']) ?> | <?= htmlspecialchars($song['category']) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</div>

</body>
</html>
