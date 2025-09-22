<?php
// Filesystem path to project root
$projectRoot = dirname(__DIR__);

// Protocol and host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Base project URL
$projectUrl = $protocol . '://' . $host;

// Add /songbook_manager for local testing
if (strpos($host, 'localhost') !== false) {
    $projectUrl .= '/songbook_manager';
}

// Base paths
$stylesFs = $projectRoot . '/styles/';
$stylesUrl = rtrim($projectUrl, '/') . '/styles/';

// Helper function for versioned CSS
function cssWithVersion($fsPath, $urlPath) {
    return file_exists($fsPath) ? $urlPath . '?v=' . filemtime($fsPath) : $urlPath;
}

if (empty($title)) {
    $title = "Songbook manager";
}

?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- Custom styles -->
    <link href="<?= cssWithVersion($stylesFs . 'styles.css', $stylesUrl . 'styles.css') ?>" rel="stylesheet">
    <style>
    /* Remove padding from container-fluid to achieve true full width */
    .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
</style>

<?php 

if ($website == "songs") {
    echo '<link href="' . cssWithVersion($stylesFs . 'songs_styles.css', $stylesUrl . 'songs_styles.css') . '" rel="stylesheet">';
} elseif ($website == "user") {
    echo '<link href="' . cssWithVersion($stylesFs . 'user_styles.css', $stylesUrl . 'user_styles.css') . '" rel="stylesheet">';
} elseif ($website == "users") {
    echo '<link href="' . cssWithVersion($stylesFs . 'users_view.css', $stylesUrl . 'users_view.css') . '" rel="stylesheet">';
    echo '<link href="' . cssWithVersion($stylesFs . 'user_styles.css', $stylesUrl . 'user_styles.css') . '" rel="stylesheet">';
} elseif ($website == "songbooks") {
    echo '<link href="' . cssWithVersion($stylesFs . 'songbooks_view.css', $stylesUrl . 'songbooks_view.css') . '" rel="stylesheet">';
}

?>


<link href="https://fonts.googleapis.com/css2?family=Fira+Mono&display=swap" rel="stylesheet">


</head>

<body class="d-flex flex-column min-vh-100"> 
    <div class="container-fluid p-0"> 