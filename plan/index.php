<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';

$planFile = "plan.md";

// HANDLE SAVE FIRST — BEFORE ANY HTML OUTPUT
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION["admin"]) && $_SESSION["admin"]==1){
    $newContent = $_POST['plan_content'] ?? '';
    file_put_contents($planFile, $newContent);
    header("Location: ".$_SERVER['PHP_SELF']); // reload page after save
    exit; // important to stop further rendering
}

// Read the markdown file
$markdown = file_get_contents($planFile);

// Escape to safely pass to JS
$escapedMarkdown = json_encode($markdown);

// Last modified time
$lastEdited = date("d-m-Y H:i", filemtime($planFile));
?>


  <div class="plan-container">
    <p class="last-edited">Upravené: <?php echo $lastEdited; ?></p>
    <div id="content">Loading...</div>


  </div>
    <?php 
    if(isset($_SESSION["admin"])&&$_SESSION["admin"]==1){
    ?>
    <div class="markdown-help">
    <button class="toggle-btn" onclick="toggleHelp()">Markdown Cheatsheet</button>
    <div class= "plan-container" id="cheatsheet" style="display:none;">
        <pre>
# Markdown Cheatsheet

**Headings**
# H1
## H2
### H3

**bold**
*italic*
~strikethrough~

**Lists**
- Item 1
- Item 2
- Nested item
1. Numbered
2. List

**Links & Images**
[OpenAI](https://openai.com)
![Alt text](image.png)

**Code**
Inline `code`

**Quotes**
> This is a blockquote
        </pre>
    </div>
    </div>

    <script>
    function toggleHelp() {
        const el = document.getElementById("cheatsheet");
        el.style.display = el.style.display === "none" ? "block" : "none";
    }
    </script>

    <style>
    .markdown-help {
        max-width: 800px;
        margin: 20px auto;
        text-align: left;
    }
    .toggle-btn {
        background: #eee;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 6px 12px;
        cursor: pointer;
    }
    .markdown-help pre {
        padding: 12px;
        border-radius: 6px;
        margin-top: 10px;
        overflow-x: auto;
        font-size: 0.9em;
    }
    </style>


<!-- Admin Edit Button -->
<button id="editBtn" style="margin-top: 10px;">✏️ Editovať plán</button>
<form id="editForm" method="POST" style="display:none; margin-top:10px;">
    <textarea name="plan_content" id="planTextarea" style="width:100%; height:400px;"><?php echo htmlspecialchars($markdown); ?></textarea>
    <br>
    <button type="submit">Uložiť</button>
    <button type="button" id="cancelBtn">Zrušiť</button>
</form>

<?php
// Handle saving the file
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION["admin"]) && $_SESSION["admin"]==1){
    $newContent = $_POST['plan_content'] ?? '';
    file_put_contents($planFile, $newContent);
    header("Location: ".$_SERVER['PHP_SELF']); // reload page after save
    exit;
}
?>

<script>
const editBtn = document.getElementById('editBtn');
const editForm = document.getElementById('editForm');
const cancelBtn = document.getElementById('cancelBtn');
const contentDiv = document.getElementById('content');

editBtn.addEventListener('click', () => {
    editForm.style.display = 'block';
    contentDiv.style.display = 'none';
    editBtn.style.display = 'none';
});

cancelBtn.addEventListener('click', () => {
    editForm.style.display = 'none';
    contentDiv.style.display = 'block';
    editBtn.style.display = 'inline-block';
});
</script>

    <?php
        }
?>


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
      text-align: left;
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
