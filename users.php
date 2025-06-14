<?php
session_start();
include('php/db.php');
include('php/data.php');
$website = "users";
include('php/functions.php');
include("php/header.php");
include('php/navigation.php');

if(isset($_SESSION["admin_view"])&&$_SESSION["admin_view"]==true){
?>

<!-- Page Header -->
<div class="user-management-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-3">User Management</h1>
                <p class="lead mb-0">View and manage all users in the system</p>
            </div>
            <!-- <div class="col-md-4 text-end">
                <button class="btn btn-light me-2">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <button class="btn btn-outline-light">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </button>
            </div> -->
        </div>
    </div>
</div>

<div class="container">
    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search users by name, username or organization...">
                        <button class="btn btn-primary" type="button">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            <div class="col-md-4">
                <div class="d-flex">
                    <select class="form-select me-2">
                        <option>All Organizations</option>
                        <option>Melody Makers</option>
                        <option>Harmony Hub</option>
                        <option>Rhythm Collective</option>
                        <option>Sound Innovators</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- Users List -->
    <div class="mb-4">
        <h5 class="section-title">All Users</h5>
        
        <!-- User 1 -->
        <div class="user-card">
            <div class="row align-items-center">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="d-flex align-items-center">
                        <img src="https://ui-avatars.com/api/?name=Emma+Rodriguez&background=random" class="user-avatar me-3">
                        <div>
                            <div class="user-name">Emma Rodriguez</div>
                            <div class="username">@emmar</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div>
                        <div class="xp-value">5,570</div>
                        <div class="xp-label">Total XP</div>
                    </div>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <div class="level-badge">14</div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <span class="org-badge">Sound Innovators</span>
                </div>
                <div class="col-md-1 text-md-end">
                    <button class="btn btn-sm btn-outline-primary action-btn">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary action-btn">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- User 2 -->
        <div class="user-card">
            <div class="row align-items-center">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="d-flex align-items-center">
                        <img src="https://ui-avatars.com/api/?name=Olivia+Smith&background=random" class="user-avatar me-3">
                        <div>
                            <div class="user-name">Olivia Smith</div>
                            <div class="username">@olivias</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div>
                        <div class="xp-value">5,120</div>
                        <div class="xp-label">Total XP</div>
                    </div>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <div class="level-badge">13</div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <span class="org-badge">Melody Makers</span>
                </div>
                <div class="col-md-1 text-md-end">
                    <button class="btn btn-sm btn-outline-primary action-btn">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary action-btn">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>


</div>


<?php 
}
else{
    echo "<p>Oops... Nothing to see here</p>";
}
include("php/footer.php"); ?>
