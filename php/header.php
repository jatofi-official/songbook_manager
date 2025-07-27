<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Songbook Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- Custom styles -->
    <link href="styles/styles.css" rel="stylesheet">	
    <style>
    /* Remove padding from container-fluid to achieve true full width */
    .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
</style>

<?php 
function cssWithVersion($filepath) {
    if (file_exists($filepath)) {
        return $filepath . '?v=' . filemtime($filepath);
    }
    return $filepath;
}

if($website == "songs"){
    echo '<link href="styles/songs_styles.css?v=' . filemtime('styles/songs_styles.css') . '" rel="stylesheet">';
}
else if($website == "user"){
    echo '<link href="styles/user_styles.css?v=' . filemtime('styles/user_styles.css') . '" rel="stylesheet">';
}
else if($website == "users"){
    echo '<link href="styles/users_view.css?v=' . filemtime('styles/users_view.css') . '" rel="stylesheet">';
}
else if($website == "songbooks"){
    echo '<link href="styles/songbooks_view.css?v=' . filemtime('styles/songbooks_view.css') . '" rel="stylesheet">';
}
?>


<link href="https://fonts.googleapis.com/css2?family=Fira+Mono&display=swap" rel="stylesheet">


</head>

<body class="d-flex flex-column min-vh-100"> 
    <div class="container-fluid p-0"> 