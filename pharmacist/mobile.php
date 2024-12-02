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
            <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link text-light" href="./index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="record_transactions.php" class="nav-link text-light me-2">
                        <i class="fas fa-exchange-alt"></i> Record Transactions </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="view_reports.php" class="nav-link text-light me-2">
                        <i class="fas fa-chart-line"></i> View Reports </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="file_claims.php" class="nav-link text-light">
                        <i class="fas fa-file-alt"></i> File Claims</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="./profile.php"><i class="fas fa-user"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
            </ul>
        </div>
    </div>
</nav>
