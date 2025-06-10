<?php
session_start();
include('php/db.php');          
include('php/data.php');    

$website = "songs";

include('php/functions.php'); 
include("php/header.php");
include('php/navigation.php');
include("php/categories.php");

?>
<div class="row h-100">
    <!-- Song List Column -->
    <div class="col-md-5 col-lg-4 p-0 song-list-container">
        <div class="search-box">
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Search songs...">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <div class="d-flex">
                <select class="form-select form-select-sm me-2">
                    <option>All Categories</option>
                    <option>Rock</option>
                    <option>Pop</option>
                    <option>Jazz</option>
                    <option>Classical</option>
                </select>
                <select class="form-select form-select-sm">
                    <option>All Instruments</option>
                    <option>Guitar</option>
                    <option>Piano</option>
                    <option>Bass</option>
                    <option>Drums</option>
                </select>
            </div>
        </div>
        
        <div class="song-list">
            <div class="song-list-item active">
                <div class="song-title">Shape of You</div>
                <div class="song-artist">Ed Sheeran</div>
                <div class="mt-2">
                    <span class="tag">pop</span>
                    <span class="tag">modern</span>
                    <span class="badge instrument-badge">Guitar</span>
                    <?php if(isset($_SESSION["admin_view"]) && $_SESSION["admin_view"]==true) {?>
                        <span class="badge bg-success visibility-badge">Public</span>
                    <?php }?>
                </div>
            </div>
            
            
        </div>
    </div>
    
    <!-- Song Detail Column -->
    <div class="col-md-7 col-lg-8 p-0 song-detail-container">
        <div class="song-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2>Shape of You</h2>
                    <h4 class="text-muted">Ed Sheeran</h4>
                </div>
                <div>
                    <button class="btn btn-outline-secondary me-2">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            
            <div class="song-meta">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Category:</strong> Pop
                        </div>
                        <div class="mb-2">
                            <strong>Capo:</strong>
                            <span class="capo-display">Original: 2nd fret</span>
                            <span class="capo-display">Alt: 7th fret</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Instruments:</strong>
                            <span class="badge instrument-badge">Guitar</span>
                        </div>
                        <div class="mb-2">
                            <strong>Tags:</strong>
                            <span class="tag">pop</span>
                            <span class="tag">modern</span>
                            <span class="tag">dance</span>
                        </div>
                        <div class="mb-2">
                            <strong>Visibility:</strong>
                            <span class="badge bg-success">Public</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="song-content">
            <p>test</p>
        </div>
    </div>
<?php

include("php/footer.php");
?>