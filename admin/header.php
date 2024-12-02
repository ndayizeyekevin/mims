<?php
if ($_SESSION['role'] == 'admin') {
    $role = 'Admin';
} 
?>


<!-- Add CSS for Profile Card -->
<style>
    .profile-card {
        background-color: #343a40; /* Dark background to match header */
        color: #ffffff; /* White text color */
        padding: 10px;
        border-radius: 5px;
    }
    .profile-card img {
        border: 3px solid #17a2b8; /* Blue border around the image */
    }
    
    .profile-container {
        cursor: pointer; /* Makes it clear that this section is clickable */
    }

    .profile-display {
        color: #ffffff; /* White text color */
        transition: color 0.3s; /* Smooth transition for color change */
    }

    .profile-container:hover .profile-display {
        color: #17a2b8; /* Change color on hover */
    }

    /* Dropdown menu hover effects */
    .dropdown-item {
        color: #343a40; /* Dark text color */
    }

    .dropdown-item:hover {
        color: #00f; /* White text color on hover */
    }

    .dropdown-item:focus {
        background-color: green;
        color: #00f; /* White text color on focus */
    }
</style>


<!-- Header with logo and title -->
<header class="bg-dark text-white py-5">
    <div class="container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <a href="./index.php">
                <img src="../images/logo-back.png" alt="Logo" class="logo" style="width: 130px; height: 40px;">
            </a>
            <h4 class="ms-3"><?php echo $role; ?></h4>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="dropdown">
            <div class="d-flex align-items-center profile-container" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="../images/user.png" alt="User" class="img-fluid rounded-circle" style="width: 40px; height: 40px;">
                <span class="ms-2 profile-display"><?php echo $_SESSION['first_name'];?> </span> <!-- Display "Profile" next to the image -->
            </div>
            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown" style="width: 300px;">
                <div class="dropdown-item text-center">
                    <div class="profile-card">
                        <img src="../images/user.png" alt="User" class="img-fluid rounded-circle" style="width: 80px; height: 80px;">
                        <h5 class="mt-2" style="text-transform: capitalize;"><?php echo $_SESSION['first_name'];?> </h5>
                        <p><?php echo $role;?></p>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="d-flex justify-content-between">
                        <a class="btn btn-outline-primary me-1" href="./profile.php">Security</a>
                        <a class="btn btn-outline-danger ms-1" href="../logout.php">Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

