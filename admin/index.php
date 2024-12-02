<?php
session_start(); // Start the session

// Check if the user_id is set in the session
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page or show an error message
    header("Location: ../login.php"); // Change 'login.php' to your actual login page
    exit();
}



$user_id = $_SESSION['user_id'];

include '../include/connection.php';

try {
    // Fetch pharmacies and insurance companies from the database
    $stmt_pharmacies = $connection->prepare("SELECT pharmacy_id, pharmacy_name FROM pharmacies ORDER BY pharmacy_name ASC");
    $stmt_pharmacies->execute();
    $pharmacies = $stmt_pharmacies->fetchAll(PDO::FETCH_ASSOC);

    $stmt_insurance = $connection->prepare("SELECT insurance_id, insurance_name FROM insurance_companies ORDER BY insurance_name ASC");
    $stmt_insurance->execute();
    $insurances = $stmt_insurance->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error and display a generic message
    error_log("Database Error: " . $e->getMessage());
    echo "An error occurred while fetching data. Please try again later.";
    exit();
}

// Function to fetch users (assuming a function in app.php)
require_once './app.php';
$users = getUsers(); // Ensure this function is properly defined in app.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/style.css">
    <!-- Icon -->
    <link rel="icon" type="image/x-icon" href="../images/logo-removebg-preview.png">

</head>
<body>

<!-- Header with logo and title -->
<?php include './header.php'; ?>

<!-- Navigation -->
<?php include './mobile.php'; ?>

<!-- Main Content -->
<div class="container-fluid">

    <div class="row">
        <!-- Sidebar is assumed to be part of nav.php -->
        <?php include './nav.php'; ?>
        <main class="col-lg-10 ms-auto main-content p-4">
        <div class="dashboard-header">
            <h2>Admin Dashboard</h2>
        </div>

        <div class="container mt-4">
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users"></i> Users</h5>
                            <p class="card-text">Manage all users in the system.</p>
                            <a href="./users.php" class="btn btn-dark">Go to Users</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-box"></i> Insurance Companies</h5>
                            <p class="card-text">View and manage insurance companies.</p>
                            <a href="./insurance_companies.php" class="btn btn-dark">Go to Insurance Companies</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-hospital"></i> Pharmacies</h5>
                            <p class="card-text">Manage pharmacy details.</p>
                            <a href="./pharmacies.php" class="btn btn-dark">Go to Pharmacies</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                
                
                <div class="col-md-4">
                    <div class="card text-white bg-secondary mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-circle"></i> Profile</h5>
                            <p class="card-text">View and edit your profile.</p>
                            <a href="#" class="btn btn-dark">Go to Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            
            
    </div>
</div>

<!-- Footer -->
<?php include '../include/footer.php'; ?>
<!-- Bootstrap 5 JS Bundle (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>
                                                                   