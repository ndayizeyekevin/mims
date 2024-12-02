<!-- Navbar for mobile view -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top d-lg-none">
    <div class="container-fluid">
        <a href="#">
            <img src="../images/logo-back.png" alt="Logo" class="logo" style="width: 130px; height: 40px"> <!-- Placeholder for logo -->
        </a>
        <a class="navbar-brand my-2 text-light" href="#">Admin Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavMobile" aria-controls="navbarNavMobile" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavMobile">
            <ul class="navbar-nav mx-3">
                    <li class="nav-item">
                        <a class="nav-link text-light" href="./index.php"><i class="fas fa-home"></i>  Home</a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="./clients.php" class="nav-link text-light me-2">
                        <i class="fas fa-user-tie"></i>  Clients </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="./users.php"><i class="fas fa-users"></i>  Manage Users</a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="./manage_claims.php" class="nav-link text-light">
                        <i class="fas fa-file-alt"></i>  View Claims</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="./profile.php"><i class="fas fa-user"></i>  Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="../logout.php"><i class="fas fa-sign-out-alt"></i>  Logout</a>
                    </li>
            </ul>
        </div>
    </div>
</nav>
