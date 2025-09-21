<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';

// Read the markdown file
$planFile = "plan.md";
$markdown = file_get_contents($planFile);

// Escape to safely pass to JS (json_encode handles quotes/newlines properly)
$escapedMarkdown = json_encode($markdown);

// Last modified time
$lastEdited = date("Y-m-d H:i:s", filemtime($planFile));
?>

  <div class="plan-container">
    <p class="last-edited">Last edited: <?php echo $lastEdited; ?></p>
    <div id="content">Loading...</div>
  </div>

  <!-- Load a Markdown parser from CDN -->
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script>
    // Pass PHP content to JS
    const markdown = <?php echo $escapedMarkdown; ?>;

    // Convert to HTML
    const html = marked.parse(markdown);

    // Insert into the page
    document.getElementById('content').innerHTML = html;
  </script>

  <style>
    .plan-container {
      max-width: 800px;
      margin: 40px auto;
      padding: 20px 30px;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .plan-container .last-edited {
      font-size: 0.85rem;
      color: #777;
      margin-bottom: 1em;
      text-align: right;
    }
    #content {
      font-family: system-ui, sans-serif;
      line-height: 1.6;
    }
    #content pre {
      background: #f4f4f4;
      padding: 10px;
      border-radius: 6px;
      overflow-x: auto;
    }
    #content code {
      background: #f4f4f4;
      padding: 2px 4px;
      border-radius: 4px;
    }
  </style>
</body>
</html>

<?php
include $phpBase . 'footer.php';
?>
