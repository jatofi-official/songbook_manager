<?php
session_start();
$phpBase = dirname(__DIR__) . '/php/';

include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';

$projectRoot = dirname(__DIR__);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$projectFolder = basename($projectRoot);
$projectUrl = $protocol . "://" . $host . "/" . $projectFolder;

$pdfUrl = $projectUrl . "/pdf/skautskemesto.pdf";
?>

<div class="container my-4">
    <div id="pdf-pages"></div>
</div>

<!-- PDF.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.10.111/pdf.min.js"></script>
<script>
pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.10.111/pdf.worker.min.js';

const pdfUrl = "<?= $pdfUrl ?>";
const container = document.getElementById('pdf-pages');

pdfjsLib.getDocument(pdfUrl).promise.then(async function(pdf) {
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        // Create a placeholder div for loading
        const placeholder = document.createElement('div');
        placeholder.style.width = '100%';
        placeholder.style.height = '500px'; // initial height placeholder
        placeholder.style.display = 'flex';
        placeholder.style.justifyContent = 'center';
        placeholder.style.alignItems = 'center';
        placeholder.style.background = '#f0f0f0';
        placeholder.style.marginBottom = '2rem';
        placeholder.innerText = 'Loading page ' + pageNum + '...';
        container.appendChild(placeholder);

        // Render the page
        const page = await pdf.getPage(pageNum);
        const viewport = page.getViewport({ scale: 2 });
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        await page.render({ canvasContext: ctx, viewport: viewport }).promise;

        const img = document.createElement('img');
        img.src = canvas.toDataURL();
        img.style.width = '100%';
        img.style.display = 'block';
        img.style.marginBottom = '2rem';

        // Replace placeholder with the rendered image
        container.replaceChild(img, placeholder);
    }
});
</script>

<?php
include $phpBase . 'footer.php';
?>
